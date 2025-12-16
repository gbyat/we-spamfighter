<?php

/**
 * Activity Log class.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Core;

/**
 * Activity Log class for tracking important plugin events.
 */
class ActivityLog
{

    /**
     * Instance.
     *
     * @var ActivityLog
     */
    private static $instance = null;

    /**
     * Option name for activity log.
     *
     * @var string
     */
    private $option_name = 'we_spamfighter_activity_log';

    /**
     * Maximum number of log entries to keep.
     *
     * @var int
     */
    private $max_entries = 100;

    /**
     * Get instance.
     *
     * @return ActivityLog
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
        // Activity log is optional and only used if enabled in settings.
    }

    /**
     * Check if activity logging is enabled.
     *
     * @return bool
     */
    private function is_enabled()
    {
        $settings = get_option('we_spamfighter_settings', array());
        return isset($settings['activity_log_enabled']) && $settings['activity_log_enabled'];
    }

    /**
     * Log an activity event.
     *
     * @param string $event_type Event type (e.g., 'weekly_summary_sent', 'table_maintenance').
     * @param string $message Human-readable message.
     * @param array  $context Additional context data.
     * @return void
     */
    public function log($event_type, $message, $context = array())
    {
        // Only log if enabled.
        if (! $this->is_enabled()) {
            return;
        }

        $log_entry = array(
            'timestamp'  => current_time('mysql'),
            'event_type' => sanitize_text_field($event_type),
            'message'    => sanitize_text_field($message),
            'context'    => $context,
        );

        // Get existing log entries.
        $log = get_option($this->option_name, array());

        // Add new entry at the beginning.
        array_unshift($log, $log_entry);

        // Limit to max_entries.
        if (count($log) > $this->max_entries) {
            $log = array_slice($log, 0, $this->max_entries);
        }

        // Save log.
        update_option($this->option_name, $log, false);
    }

    /**
     * Get activity log entries.
     *
     * @param int $limit Maximum number of entries to return.
     * @return array Log entries.
     */
    public function get_entries($limit = 50)
    {
        $log = get_option($this->option_name, array());
        return array_slice($log, 0, absint($limit));
    }

    /**
     * Clear activity log (all entries).
     *
     * @return bool
     */
    public function clear()
    {
        return delete_option($this->option_name);
    }

    /**
     * Clean old activity log entries based on retention days.
     *
     * @param int $days Number of days to keep (default: uses log_retention_days setting).
     * @return int Number of deleted entries.
     */
    public function clean_old_entries($days = null)
    {
        // Use retention days from settings if not specified.
        if (null === $days) {
            $settings = get_option('we_spamfighter_settings', array());
            $days = isset($settings['log_retention_days']) ? absint($settings['log_retention_days']) : 30;
        }

        $days = absint($days);
        if ($days < 1) {
            $days = 30;
        }

        $log = get_option($this->option_name, array());
        if (empty($log)) {
            return 0;
        }

        $cutoff_date = strtotime("-{$days} days");
        $original_count = count($log);
        $cleaned_log = array();

        // Keep only entries newer than cutoff date.
        foreach ($log as $entry) {
            $entry_timestamp = strtotime($entry['timestamp']);
            if ($entry_timestamp >= $cutoff_date) {
                $cleaned_log[] = $entry;
            }
        }

        // Also enforce max_entries limit.
        if (count($cleaned_log) > $this->max_entries) {
            $cleaned_log = array_slice($cleaned_log, 0, $this->max_entries);
        }

        // Update option.
        if (empty($cleaned_log)) {
            delete_option($this->option_name);
        } else {
            update_option($this->option_name, $cleaned_log, false);
        }

        return $original_count - count($cleaned_log);
    }

    /**
     * Get activity log count.
     *
     * @return int
     */
    public function get_count()
    {
        $log = get_option($this->option_name, array());
        return count($log);
    }
}
