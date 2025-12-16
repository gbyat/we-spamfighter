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
     * Clear activity log.
     *
     * @return bool
     */
    public function clear()
    {
        return delete_option($this->option_name);
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
