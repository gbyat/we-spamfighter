<?php

/**
 * Plugin Name: WE Spamfighter
 * Plugin URI: https://github.com/gbyat/we-spamfighter
 * Description: Advanced spam protection for Contact Form 7 and Comments using AI-powered and heuristic detection. Works with or without OpenAI - includes local spam detection for cost-effective filtering.
 * Version: 1.1.2
 * Author: webentwicklerin, Gabriele Laesser
 * Author URI: https://webentwicklerin.at
 * Text Domain: we-spamfighter
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8.3
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('WE_SPAMFIGHTER_VERSION', '1.1.2');
define('WE_SPAMFIGHTER_PLUGIN_FILE', __FILE__);
define('WE_SPAMFIGHTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WE_SPAMFIGHTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WE_SPAMFIGHTER_GITHUB_REPO', 'gbyat/we-spamfighter');

// Autoloader.
spl_autoload_register(
    function ($class) {
        $prefix   = 'WeSpamfighter\\';
        $base_dir = __DIR__ . '/includes/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);

        // Convert namespace separators to directory separators.
        $relative_class = str_replace('\\', '/', $relative_class);

        // Convert class name from PascalCase to kebab-case for WordPress standards.
        // Extract directory and class name.
        $parts      = explode('/', $relative_class);
        $class_name = array_pop($parts);

        // Convert directories to lowercase (WordPress standard).
        $parts = array_map('strtolower', $parts);

        // Convert PascalCase to kebab-case.
        $class_name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name));

        // Build file path with 'class-' prefix.
        $file = $base_dir . (!empty($parts) ? implode('/', $parts) . '/' : '') . 'class-' . $class_name . '.php';

        if (file_exists($file)) {
            require $file;
        }
    },
    true, // Throw exceptions
    true  // Prepend to autoloader stack
);

/**
 * Main plugin class.
 */
class Plugin
{

    /**
     * Plugin instance.
     *
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Get plugin instance.
     *
     * @return Plugin
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks()
    {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('plugins_loaded', array($this, 'init_github_updater'), 5); // Early, before other plugins_loaded hooks
        add_action('plugins_loaded', array($this, 'load_integrations'), 20); // Load after CF7 loads
        add_action('init', array($this, 'init'));

        // Admin hooks.
        if (is_admin()) {
            Admin\Dashboard::get_instance();
            Admin\Settings::get_instance();
        }

        // Cron: daily cleanup of old logs.
        add_action('we_spamfighter_clean_logs', array($this, 'clean_logs_cron'));

        // Cron: daily and weekly spam summaries.
        add_action('we_spamfighter_daily_summary', array($this, 'send_daily_summary_cron'));
        add_action('we_spamfighter_weekly_summary', array($this, 'send_weekly_summary_cron'));

        // Activation/Deactivation/Uninstall.
        register_activation_hook(WE_SPAMFIGHTER_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(WE_SPAMFIGHTER_PLUGIN_FILE, array($this, 'deactivate'));
        register_uninstall_hook(WE_SPAMFIGHTER_PLUGIN_FILE, array(__CLASS__, 'uninstall'));

        // Whitelist OpenAI API requests to bypass theme/plugin HTTP blocking.
        // Priority 15 runs AFTER the theme's blocking filter (priority 10) to override it.
        add_filter('pre_http_request', array($this, 'whitelist_openai_requests'), 15, 3);

        // Alternative: Remove the theme's blocking filter for OpenAI API requests.
        // This is a more aggressive approach if the priority method doesn't work.
        add_action('init', array($this, 'remove_theme_http_blocking'), 999);
    }

    /**
     * Load text domain for translations.
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('we-spamfighter', false, dirname(plugin_basename(WE_SPAMFIGHTER_PLUGIN_FILE)) . '/languages');
    }

    /**
     * Initialize GitHub Updater for automatic updates.
     */
    public function init_github_updater()
    {
        if (! (is_admin() || wp_doing_cron())) {
            return;
        }

        if (class_exists('WeSpamfighter\\Core\\Updater')) {
            new Core\Updater(WE_SPAMFIGHTER_PLUGIN_FILE);
        }
    }

    /**
     * Initialize plugin.
     */
    public function init()
    {
        // Initialize core components.
        Core\Logger::get_instance();
        Core\Database::get_instance();
    }

    /**
     * Load integrations for CF7 and Comments.
     */
    public function load_integrations()
    {
        $settings = get_option('we_spamfighter_settings', array());

        // Load Contact Form 7 integration.
        // Always load if CF7 is installed, so script hook is registered.
        // Spam checking is still conditional on cf7_enabled setting.
        if (class_exists('WPCF7_ContactForm')) {
            // Manually require the file first, then check if class exists.
            $cf7_class_file = __DIR__ . '/includes/integration/class-contact-form-7.php';
            if (file_exists($cf7_class_file)) {
                require_once $cf7_class_file;
            }

            if (class_exists('\WeSpamfighter\Integration\ContactForm7')) {
                try {
                    Integration\ContactForm7::get_instance();
                } catch (\Exception $e) {
                    // Silent fail - integration will not be loaded.
                }
            }
        }

        // Load Comments integration.
        if (! empty($settings['comments_enabled'])) {
            if (class_exists('\WeSpamfighter\Integration\Comments')) {
                try {
                    Integration\Comments::get_instance();
                } catch (\Exception $e) {
                    // Silent fail - integration will not be loaded.
                }
            }
        }
    }

    /**
     * Plugin activation.
     */
    public function activate()
    {
        Core\Database::get_instance()->create_tables();

        // Set default options.
        $defaults = array(
            'cf7_enabled'                  => false,
            'comments_enabled'             => false,
            'openai_enabled'               => false,
            'openai_api_key'               => '',
            'openai_model'                 => 'gpt-4o-mini',
            'ai_threshold'                 => 0.7,
            'auto_mark_pingbacks_spam'     => false,
            'mark_different_language_spam' => false,
            'language_spam_score_boost'    => 0.3,
            'heuristic_enabled'            => false,
            'heuristic_threshold'          => 0.6,
            'disable_link_check'           => false,
            'disable_character_check'      => false,
            'disable_phrase_check'         => false,
            'disable_email_check'          => false,
            'log_retention_days'           => 30,
            'notification_email'           => get_option('admin_email'),
            'notification_type'            => 'none',
        );

        add_option('we_spamfighter_settings', $defaults);

        flush_rewrite_rules();

        // Schedule daily cleanup if not already scheduled.
        if (! wp_next_scheduled('we_spamfighter_clean_logs')) {
            $hour_in_seconds = defined('HOUR_IN_SECONDS') ? constant('HOUR_IN_SECONDS') : 3600;
            wp_schedule_event(time() + $hour_in_seconds, 'daily', 'we_spamfighter_clean_logs');
        }

        // Schedule daily summary (runs at 8 AM).
        if (! wp_next_scheduled('we_spamfighter_daily_summary')) {
            $next_daily = strtotime('tomorrow 8:00');
            wp_schedule_event($next_daily, 'daily', 'we_spamfighter_daily_summary');
        }

        // Schedule weekly summary (runs on Monday at 8 AM).
        if (! wp_next_scheduled('we_spamfighter_weekly_summary')) {
            $next_monday = strtotime('next Monday 8:00');
            // WordPress doesn't have a built-in 'weekly' schedule, so we schedule it as a one-time event and reschedule it.
            wp_schedule_single_event($next_monday, 'we_spamfighter_weekly_summary');
        }
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate()
    {
        flush_rewrite_rules();
        // Clear scheduled cleanup and summaries.
        wp_clear_scheduled_hook('we_spamfighter_clean_logs');
        wp_clear_scheduled_hook('we_spamfighter_daily_summary');
        wp_clear_scheduled_hook('we_spamfighter_weekly_summary');
    }

    /**
     * Plugin uninstall.
     *
     * Note: This method is called by register_uninstall_hook, but the actual
     * uninstall logic is in uninstall.php. This is kept for compatibility.
     * The uninstall.php file will be executed when the plugin is deleted.
     */
    public static function uninstall()
    {
        // The actual uninstall logic is in uninstall.php.
        // This method is kept for compatibility but is not used.
    }

    /**
     * Cron callback: clean old logs based on retention settings.
     */
    public function clean_logs_cron()
    {
        $settings = get_option('we_spamfighter_settings', array());
        $days     = isset($settings['log_retention_days']) ? absint($settings['log_retention_days']) : 30;
        if ($days < 7) {
            $days = 7; // enforce a sane minimum
        }

        $deleted = Core\Database::get_instance()->clean_old_logs($days);

        // Optional: debug log
        if (defined('WP_DEBUG') && constant('WP_DEBUG')) {
            Core\Logger::get_instance()->info('Cron: cleaned old logs', array('retention_days' => $days, 'deleted_rows' => (int) $deleted));
        }
    }

    /**
     * Cron callback: send daily spam summary.
     */
    public function send_daily_summary_cron()
    {
        Core\Notifications::get_instance()->send_daily_summary();
    }

    /**
     * Cron callback: send weekly spam summary.
     */
    public function send_weekly_summary_cron()
    {
        Core\Notifications::get_instance()->send_weekly_summary();

        // Reschedule for next week (Monday at 8 AM).
        $next_monday = strtotime('next Monday 8:00');
        wp_schedule_single_event($next_monday, 'we_spamfighter_weekly_summary');
    }

    /**
     * Whitelist OpenAI API requests to bypass theme/plugin HTTP blocking.
     *
     * This filter runs with priority 15 (AFTER the theme's priority 10 filter) to override
     * any blocking attempts for OpenAI API requests.
     *
     * @param false|array|\WP_Error $preempt Whether to preempt an HTTP request's return value. Default false.
     * @param array                 $parsed_args HTTP request arguments.
     * @param string                $url The request URL.
     * @return false|array|\WP_Error
     */
    public function whitelist_openai_requests($preempt, $parsed_args, $url)
    {
        $openai_host = parse_url('https://api.openai.com/v1/chat/completions', PHP_URL_HOST);
        $url_host = parse_url($url, PHP_URL_HOST);

        // If this is an OpenAI API request, allow it by returning false (don't preempt).
        // We run AFTER the theme's blocking filter (priority 10) to override it.
        if ($url_host === $openai_host) {
            return false; // Don't preempt - allow the request to proceed, overriding any previous blocking
        }

        // For other URLs, return the preempt value unchanged (let other filters decide).
        return $preempt;
    }

    /**
     * Remove theme's HTTP blocking filter for OpenAI API requests.
     *
     * This is a more aggressive approach that directly removes the blocking filter
     * if it interferes with OpenAI API requests.
     */
    public function remove_theme_http_blocking()
    {
        global $wp_filter;

        // Check if the blocking filter exists.
        if (isset($wp_filter['pre_http_request']) && isset($wp_filter['pre_http_request']->callbacks[10])) {
            $callbacks = $wp_filter['pre_http_request']->callbacks[10];

            // Look for the selective_http_block function.
            foreach ($callbacks as $key => $callback) {
                if (isset($callback['function']) && $callback['function'] === 'selective_http_block') {
                    // Remove the blocking filter.
                    remove_filter('pre_http_request', 'selective_http_block', 10);

                    // Also add a replacement filter that allows OpenAI API.
                    add_filter('pre_http_request', function ($preempt, $parsed_args, $url) {
                        $openai_host = parse_url('https://api.openai.com/v1/chat/completions', PHP_URL_HOST);
                        $url_host = parse_url($url, PHP_URL_HOST);

                        // Always allow OpenAI API requests.
                        if ($url_host === $openai_host) {
                            return false; // Don't preempt
                        }

                        // For other URLs, run the original selective_http_block function if it exists.
                        if (function_exists('selective_http_block')) {
                            return selective_http_block($preempt, $parsed_args, $url);
                        }

                        return $preempt;
                    }, 10, 3);

                    break;
                }
            }
        }
    }
}

// Initialize plugin.
Plugin::get_instance();
