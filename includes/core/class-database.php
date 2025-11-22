<?php

/**
 * Database handler.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Core;

/**
 * Database class.
 */
class Database
{

    /**
     * Instance.
     *
     * @var Database
     */
    private static $instance = null;

    /**
     * Table name for submissions.
     *
     * @var string
     */
    private $table_name;

    /**
     * Get instance.
     *
     * @return Database
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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'we_spamfighter_submissions';
    }

    /**
     * Create database tables.
     */
    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			submission_type varchar(50) NOT NULL DEFAULT 'cf7',
			form_id bigint(20) UNSIGNED,
			submission_data longtext NOT NULL,
			is_spam tinyint(1) NOT NULL DEFAULT 0,
			spam_score float NOT NULL DEFAULT 0,
			detection_method varchar(255),
			detection_details longtext,
			user_ip varchar(100),
			user_agent text,
			site_id bigint(20) UNSIGNED,
			email_sent tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY created_at (created_at),
			KEY is_spam (is_spam),
			KEY submission_type (submission_type),
			KEY site_id (site_id)
		) $charset_collate;";

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core constant in global namespace.
        require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Save submission.
     *
     * @param array $data Submission data.
     * @return int|false Insert ID or false on failure.
     */
    public function save_submission($data)
    {
        global $wpdb;

        $defaults = array(
            'submission_type'  => 'cf7',
            'form_id'          => 0,
            'submission_data'  => '',
            'is_spam'          => 0,
            'spam_score'       => 0,
            'detection_method' => '',
            'detection_details' => '',
            'user_ip'          => $this->get_user_ip(),
            'user_agent'       => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'site_id'          => get_current_blog_id(),
            'email_sent'       => 0,
        );

        $data = wp_parse_args($data, $defaults);

        // Serialize complex data.
        if (is_array($data['submission_data'])) {
            $data['submission_data'] = wp_json_encode($data['submission_data']);
        }
        if (is_array($data['detection_details'])) {
            $data['detection_details'] = wp_json_encode($data['detection_details']);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array(
                '%s', // submission_type
                '%d', // form_id
                '%s', // submission_data
                '%d', // is_spam
                '%f', // spam_score
                '%s', // detection_method
                '%s', // detection_details
                '%s', // user_ip
                '%s', // user_agent
                '%d', // site_id
                '%d', // email_sent
            )
        );

        // Clear cache after insert.
        if ($result) {
            // Clear count cache.
            wp_cache_delete('we_spamfighter_count_all', 'we_spamfighter');
            wp_cache_delete('we_spamfighter_count_normal', 'we_spamfighter');
            wp_cache_delete('we_spamfighter_count_spam', 'we_spamfighter');

            // Note: We don't clear the submissions list cache here because it would require
            // clearing all possible cache keys. The cache TTL is short (5 minutes) anyway.
        }

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get submissions.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_submissions($args = array())
    {
        global $wpdb;

        $defaults = array(
            'limit'           => 50,
            'offset'          => 0,
            'order_by'        => 'created_at',
            'order'           => 'DESC',
            'is_spam'         => null, // null = all, 0 = normal, 1 = spam
            'submission_type' => null,
            'form_id'         => null,
            'site_id'         => null,
            'date_from'       => null,
            'date_to'         => null,
        );

        $args = wp_parse_args($args, $defaults);

        // Whitelist for order_by to prevent SQL injection.
        $allowed_order_by = array('id', 'created_at', 'form_id', 'spam_score', 'site_id');
        if (! in_array($args['order_by'], $allowed_order_by, true)) {
            $args['order_by'] = 'created_at';
        }

        // Whitelist for order direction.
        $args['order'] = strtoupper($args['order']);
        if (! in_array($args['order'], array('ASC', 'DESC'), true)) {
            $args['order'] = 'DESC';
        }

        // Validate and sanitize limit and offset.
        $args['limit']  = absint($args['limit']);
        $args['offset'] = absint($args['offset']);

        $where = array('1=1');

        if (! is_null($args['is_spam'])) {
            $where[] = $wpdb->prepare('is_spam = %d', absint($args['is_spam']));
        }

        if (! is_null($args['submission_type'])) {
            $where[] = $wpdb->prepare('submission_type = %s', sanitize_text_field($args['submission_type']));
        }

        if (! is_null($args['form_id'])) {
            $where[] = $wpdb->prepare('form_id = %d', absint($args['form_id']));
        }

        if (! is_null($args['site_id'])) {
            $where[] = $wpdb->prepare('site_id = %d', absint($args['site_id']));
        }

        if (! is_null($args['date_from'])) {
            $where[] = $wpdb->prepare('created_at >= %s', sanitize_text_field($args['date_from']));
        }

        if (! is_null($args['date_to'])) {
            $where[] = $wpdb->prepare('created_at <= %s', sanitize_text_field($args['date_to']));
        }

        $where_clause = implode(' AND ', $where);

        // Sanitize order_by and order - already whitelisted above, but use esc_sql for extra safety.
        $order_by = esc_sql($args['order_by']);
        $order = esc_sql($args['order']);

        // Build cache key based on query parameters.
        $cache_key = 'we_spamfighter_submissions_' . md5(serialize($args));

        // Try to get from cache first.
        $results = wp_cache_get($cache_key, 'we_spamfighter');
        if (false !== $results) {
            return $results;
        }

        // Build query - order_by and order are whitelisted and escaped.
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- order_by and order are whitelisted and escaped.
            $args['limit'],
            $args['offset']
        );

        // Custom table query. No WordPress API available for custom tables. Query is prepared above. ARRAY_A is WordPress core constant.
        $results = $wpdb->get_results($query, \ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound

        // Cache the results for 5 minutes.
        wp_cache_set($cache_key, $results, 'we_spamfighter', 5 * \MINUTE_IN_SECONDS);

        return $results;
    }

    /**
     * Get submission by ID.
     *
     * @param int $id Submission ID.
     * @return array|null
     */
    public function get_submission($id)
    {
        global $wpdb;

        $id = absint($id);
        $cache_key = 'we_spamfighter_submission_' . $id;

        // Try to get from cache first.
        $result = wp_cache_get($cache_key, 'we_spamfighter');
        if (false !== $result) {
            return $result;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Custom table query with caching. ARRAY_A is WordPress core constant.
        $result = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
            \ARRAY_A
        );

        // Cache the result for 1 hour.
        wp_cache_set($cache_key, $result, 'we_spamfighter', \HOUR_IN_SECONDS);

        return $result;
    }

    /**
     * Update submission.
     *
     * @param int   $id Submission ID.
     * @param array $data Update data.
     * @return bool
     */
    public function update_submission($id, $data)
    {
        global $wpdb;

        // Serialize complex data if needed.
        if (isset($data['submission_data']) && is_array($data['submission_data'])) {
            $data['submission_data'] = wp_json_encode($data['submission_data']);
        }
        if (isset($data['detection_details']) && is_array($data['detection_details'])) {
            $data['detection_details'] = wp_json_encode($data['detection_details']);
        }

        $id = absint($id);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table update.
        $result = (bool) $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );

        // Clear cache after update.
        if ($result) {
            // Clear single submission cache.
            $cache_key = 'we_spamfighter_submission_' . $id;
            wp_cache_delete($cache_key, 'we_spamfighter');

            // Clear count cache.
            wp_cache_delete('we_spamfighter_count_all', 'we_spamfighter');
            wp_cache_delete('we_spamfighter_count_normal', 'we_spamfighter');
            wp_cache_delete('we_spamfighter_count_spam', 'we_spamfighter');

            // Note: We don't clear the submissions list cache here because it would require
            // clearing all possible cache keys. The cache TTL is short (5 minutes) anyway.
        }

        return $result;
    }

    /**
     * Move submission from spam to normal.
     *
     * @param int $id Submission ID.
     * @return bool
     */
    public function move_to_normal($id)
    {
        return $this->update_submission(
            $id,
            array(
                'is_spam' => 0,
            )
        );
    }

    /**
     * Move submission from normal to spam.
     *
     * @param int $id Submission ID.
     * @return bool
     */
    public function move_to_spam($id)
    {
        return $this->update_submission(
            $id,
            array(
                'is_spam' => 1,
            )
        );
    }

    /**
     * Delete a single submission.
     *
     * @param int $id Submission ID.
     * @return bool
     */
    public function delete_submission($id)
    {
        global $wpdb;

        $id = absint($id);
        if (! $id) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Delete operation.
        $result = (bool) $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );

        // Clear cache after delete.
        if ($result) {
            // Clear single submission cache.
            $cache_key = 'we_spamfighter_submission_' . $id;
            wp_cache_delete($cache_key, 'we_spamfighter');

            // Clear count cache.
            wp_cache_delete('we_spamfighter_count_all', 'we_spamfighter');
            wp_cache_delete('we_spamfighter_count_normal', 'we_spamfighter');
            wp_cache_delete('we_spamfighter_count_spam', 'we_spamfighter');

            // Clear submissions list cache (all possible keys).
            wp_cache_flush_group('we_spamfighter');
        }

        return $result;
    }

    /**
     * Bulk delete submissions.
     *
     * @param array $ids Array of submission IDs.
     * @return int Number of deleted rows.
     */
    public function bulk_delete_submissions($ids)
    {
        global $wpdb;

        if (empty($ids) || ! is_array($ids)) {
            return 0;
        }

        // Sanitize IDs.
        $ids = array_map('absint', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return 0;
        }

        // Build placeholders for IN clause.
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete operation.
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are prepared.
                ...$ids
            )
        );

        // Clear cache after bulk delete.
        if ($result) {
            // Clear count cache.
            wp_cache_delete('we_spamfighter_count_all', 'we_spamfighter');
            wp_cache_delete('we_spamfighter_count_normal', 'we_spamfighter');
            wp_cache_delete('we_spamfighter_count_spam', 'we_spamfighter');

            // Clear submissions list cache (all possible keys).
            wp_cache_flush_group('we_spamfighter');
        }

        return (int) $result;
    }

    /**
     * Mark email as sent.
     *
     * @param int $id Submission ID.
     * @return bool
     */
    public function mark_email_sent($id)
    {
        return $this->update_submission(
            $id,
            array(
                'email_sent' => 1,
            )
        );
    }

    /**
     * Get statistics.
     *
     * @param int|null $days Number of days to look back. If null or 0, returns all-time statistics.
     * @return array
     */
    public function get_statistics($days = null)
    {
        global $wpdb;

        $stats = array();
        $where_clause = '1=1';

        // If days is specified, filter by date.
        if (null !== $days && $days > 0) {
            $days = absint($days);
            if ($days > 0 && $days <= 365) {
                $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
                $where_clause = $wpdb->prepare('created_at >= %s', $date_from);
            }
        }
        // If days is null or 0, show all-time statistics (no date filter).

        // Only count CF7 submissions (comments are handled by WordPress).
        $cf7_where_clause = $where_clause . " AND submission_type = 'cf7'";

        // Total submissions (CF7 only).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Statistics query. where_clause is prepared with wpdb->prepare() or safe literal.
        $stats['total'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE {$cf7_where_clause}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- where_clause is already prepared or safe.
        );

        // Normal submissions (CF7 only).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Statistics query. where_clause is prepared with wpdb->prepare() or safe literal.
        $stats['normal'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE {$cf7_where_clause} AND is_spam = 0" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- where_clause is already prepared or safe.
        );

        // Spam submissions (CF7 only).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Statistics query. where_clause is prepared with wpdb->prepare() or safe literal.
        $stats['spam'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE {$cf7_where_clause} AND is_spam = 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- where_clause is already prepared or safe.
        );

        // By submission type (CF7 only, since comments are handled by WordPress).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Statistics query. where_clause is prepared with wpdb->prepare() or safe literal. ARRAY_A is WordPress core constant.
        $stats['by_type'] = $wpdb->get_results(
            "SELECT submission_type, COUNT(*) as count 
			 FROM {$this->table_name} 
			 WHERE {$cf7_where_clause} 
			 GROUP BY submission_type 
			 ORDER BY count DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- where_clause is already prepared or safe.
            \ARRAY_A
        );

        // Daily trend (CF7 only).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Statistics query. where_clause is prepared with wpdb->prepare() or safe literal. ARRAY_A is WordPress core constant.
        $stats['daily_trend'] = $wpdb->get_results(
            "SELECT DATE(created_at) as date, 
			 COUNT(*) as total,
			 SUM(CASE WHEN is_spam = 0 THEN 1 ELSE 0 END) as normal,
			 SUM(CASE WHEN is_spam = 1 THEN 1 ELSE 0 END) as spam
			 FROM {$this->table_name} 
			 WHERE {$cf7_where_clause} 
			 GROUP BY DATE(created_at) 
			 ORDER BY date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- where_clause is already prepared or safe.
            \ARRAY_A
        );

        return $stats;
    }

    /**
     * Get total count of submissions (all time, not limited by days).
     *
     * @param int|null $is_spam 0 for normal, 1 for spam, null for all.
     * @return int Total count.
     */
    public function get_total_count($is_spam = null)
    {
        global $wpdb;

        // Build cache key based on spam status.
        $cache_key = 'we_spamfighter_count_' . (null === $is_spam ? 'all' : ($is_spam ? 'spam' : 'normal'));

        // Try to get from cache first.
        $count = wp_cache_get($cache_key, 'we_spamfighter');
        if (false !== $count) {
            return (int) $count;
        }

        $query = "SELECT COUNT(*) FROM {$this->table_name}";
        $where = array();

        // Only count CF7 submissions (comments are handled by WordPress).
        $where[] = "submission_type = 'cf7'";

        if (null !== $is_spam) {
            $where[] = $wpdb->prepare('is_spam = %d', $is_spam);
        }

        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Count query with caching.
        $count = (int) $wpdb->get_var($query);

        // Cache the count for 5 minutes.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core constant in global namespace.
        wp_cache_set($cache_key, $count, 'we_spamfighter', 5 * \MINUTE_IN_SECONDS);

        return $count;
    }

    /**
     * Clean old logs.
     *
     * @param int $days Number of days to keep.
     * @return int Number of deleted rows.
     */
    public function clean_old_logs($days = 30)
    {
        global $wpdb;

        // Sanitize days parameter.
        $days = absint($days);
        if ($days < 1) {
            $days = 30;
        }

        $date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup operation.
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $date
            )
        );
    }

    /**
     * Get user IP address.
     *
     * @return string
     */
    private function get_user_ip()
    {
        $ip = '';

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return $ip;
    }
}
