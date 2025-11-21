<?php

/**
 * Uninstall script for WE Spamfighter.
 *
 * This file is executed when the plugin is deleted from WordPress.
 * It removes all plugin data (options, database tables, cache, etc.)
 * unless the user has enabled "Keep Data on Uninstall" in settings.
 *
 * @package WeSpamfighter
 */

// If uninstall not called from WordPress, then exit.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if we should keep data.
$settings = get_option('we_spamfighter_settings', array());
$keep_data = isset($settings['keep_data_on_uninstall']) ? (bool) $settings['keep_data_on_uninstall'] : false;

// If user wants to keep data, don't delete anything.
if ($keep_data) {
    return;
}

// Load WordPress database.
global $wpdb;

// Delete options.
delete_option('we_spamfighter_settings');

// Delete database table.
$table_name = $wpdb->prefix . 'we_spamfighter_submissions';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall operation. Table name is safe (constructed from $wpdb->prefix).
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Delete all transients and cache entries with our prefix.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall operation. No caching needed during uninstall.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_we_spamfighter_') . '%',
        $wpdb->esc_like('_transient_timeout_we_spamfighter_') . '%'
    )
);

// Delete cache entries.
if (function_exists('wp_cache_flush_group')) {
    wp_cache_flush_group('we_spamfighter');
}

// Clear scheduled cron events.
wp_clear_scheduled_hook('we_spamfighter_clean_logs');
wp_clear_scheduled_hook('we_spamfighter_daily_summary');
wp_clear_scheduled_hook('we_spamfighter_weekly_summary');

// Delete log files (if they exist).
$upload_dir = wp_upload_dir();
$log_dir = $upload_dir['basedir'] . '/we-spamfighter-logs';

if (file_exists($log_dir) && is_dir($log_dir)) {
    // Initialize WP_Filesystem.
    if (! function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    WP_Filesystem();

    global $wp_filesystem;

    if ($wp_filesystem) {
        // Delete all files in the log directory.
        $files = $wp_filesystem->dirlist($log_dir);
        if ($files) {
            foreach ($files as $file) {
                if (isset($file['name'])) {
                    $file_path = trailingslashit($log_dir) . $file['name'];
                    if ($wp_filesystem->is_file($file_path)) {
                        $wp_filesystem->delete($file_path);
                    }
                }
            }
        }

        // Delete the log directory itself.
        $wp_filesystem->rmdir($log_dir);
    } else {
        // Fallback: Use PHP functions if WP_Filesystem is not available.
        $files = glob(trailingslashit($log_dir) . '*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        if (is_dir($log_dir)) {
            rmdir($log_dir);
        }
    }
}

// Multisite: Delete data for all sites.
if (is_multisite()) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall operation. No caching needed during uninstall.
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);

        // Delete site-specific options.
        delete_option('we_spamfighter_settings');

        // Delete site-specific database table.
        $table_name = $wpdb->prefix . 'we_spamfighter_submissions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall operation. Table name is safe (constructed from $wpdb->prefix).
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

        // Delete site-specific cache.
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('we_spamfighter');
        }

        restore_current_blog();
    }
}
