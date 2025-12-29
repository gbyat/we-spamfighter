<?php

/**
 * Email notifications handler.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Core;

use WeSpamfighter\Core\Database;

/**
 * Notifications class.
 */
class Notifications
{

    /**
     * Instance.
     *
     * @var Notifications
     */
    private static $instance = null;

    /**
     * Settings.
     *
     * @var array
     */
    private $settings = array();

    /**
     * Get instance.
     *
     * @return Notifications
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
        $this->settings = get_option('we_spamfighter_settings', array());
    }

    /**
     * Send immediate spam notification.
     *
     * @param int   $submission_id Submission ID.
     * @param array $submission_data Submission data.
     * @return bool
     */
    public function send_immediate_notification($submission_id, $submission_data = array())
    {
        $notification_type = $this->settings['notification_type'] ?? 'none';

        // Only send if immediate notifications are enabled.
        if ('immediate' !== $notification_type) {
            return false;
        }

        $email = $this->get_notification_email();
        if (empty($email)) {
            return false;
        }

        // Get submission details if not provided.
        if (empty($submission_data)) {
            $db = Database::get_instance();
            $submission_data = $db->get_submission($submission_id);
            if (! $submission_data) {
                return false;
            }
        }

        $subject = sprintf(
            /* translators: %s: Site name */
            __('[%s] Spam Detected', 'we-spamfighter'),
            get_bloginfo('name')
        );

        $message = $this->build_immediate_notification_message($submission_data);

        return wp_mail($email, $subject, $message, $this->get_email_headers());
    }

    /**
     * Send daily summary.
     *
     * @return bool
     */
    public function send_daily_summary()
    {
        // Reload settings to get current values.
        $this->settings = get_option('we_spamfighter_settings', array());
        $notification_type = $this->settings['notification_type'] ?? 'none';

        // Only send if daily notifications are enabled.
        if ('daily' !== $notification_type) {
            // Don't log anything if daily notifications are not enabled to avoid confusion.
            return false;
        }

        $email = $this->get_notification_email();
        if (empty($email)) {
            // Log missing email.
            if (class_exists('\WeSpamfighter\Core\ActivityLog')) {
                $settings = get_option('we_spamfighter_settings', array());
                $activity_log_enabled = isset($settings['activity_log_enabled']) && $settings['activity_log_enabled'];
                if ($activity_log_enabled) {
                    $activity_log = \WeSpamfighter\Core\ActivityLog::get_instance();
                    $activity_log->log(
                        'daily_summary_error',
                        __('Daily summary failed: No email address configured', 'we-spamfighter'),
                        array('email' => $email)
                    );
                }
            }
            return false;
        }

        $db = Database::get_instance();

        // Use WordPress timezone to calculate dates correctly.
        // current_time('timestamp') returns Unix timestamp in WordPress timezone.
        $now_timestamp = current_time('timestamp');
        $yesterday_start_timestamp = strtotime('yesterday midnight', $now_timestamp);
        $today_start_timestamp = strtotime('today midnight', $now_timestamp);

        // Convert to MySQL datetime format using date() with WordPress timezone timestamp.
        $yesterday_start = date('Y-m-d H:i:s', $yesterday_start_timestamp);
        $today_start = date('Y-m-d H:i:s', $today_start_timestamp);

        // Get spam submissions from yesterday.
        $spam_submissions = $db->get_submissions(array(
            'is_spam'   => 1,
            'date_from' => $yesterday_start,
            'date_to'   => $today_start,
            'limit'     => 1000, // Get all from yesterday.
        ));

        // Send email even if no spam (to confirm the system is working).
        $subject = sprintf(
            /* translators: %s: Site name */
            __('[%s] Daily Spam Summary', 'we-spamfighter'),
            get_bloginfo('name')
        );

        if (empty($spam_submissions)) {
            $message = sprintf(
                /* translators: %s: Site name */
                __('No spam submissions were detected on %s yesterday.', 'we-spamfighter'),
                get_bloginfo('name')
            ) . "\n\n";
            $message .= __('Great job keeping your site clean!', 'we-spamfighter') . "\n\n";
            $admin_url = admin_url('admin.php?page=we-spamfighter');
            $message .= __('View Dashboard:', 'we-spamfighter') . "\n";
            $message .= $admin_url . "\n";
        } else {
            $message = $this->build_summary_message($spam_submissions, 'daily');
        }

        $result = wp_mail($email, $subject, $message, $this->get_email_headers());

        // Log result for debugging.
        if (class_exists('\WeSpamfighter\Core\ActivityLog')) {
            $settings = get_option('we_spamfighter_settings', array());
            $activity_log_enabled = isset($settings['activity_log_enabled']) && $settings['activity_log_enabled'];
            if ($activity_log_enabled) {
                $activity_log = \WeSpamfighter\Core\ActivityLog::get_instance();
                $activity_log->log(
                    'daily_summary_email',
                    $result ? __('Daily summary email sent successfully', 'we-spamfighter') : __('Daily summary email failed to send', 'we-spamfighter'),
                    array(
                        'email' => $email,
                        'success' => $result,
                        'spam_count' => count($spam_submissions),
                        'date_range' => array(
                            'from' => $yesterday_start,
                            'to' => $today_start,
                        ),
                        'wp_mail_result' => $result,
                    )
                );
            }
        }

        // Also log to error log if wp_mail failed (if WP_DEBUG_LOG is enabled).
        // Note: WP_DEBUG_LOG is a WordPress constant that may be set in wp-config.php.
        if (!$result) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            @error_log('WE Spamfighter: Daily summary email failed. Email: ' . $email . ', Spam count: ' . count($spam_submissions));
        }

        return $result;
    }

    /**
     * Send weekly summary.
     *
     * @return bool
     */
    public function send_weekly_summary()
    {
        // Reload settings to get current values.
        $this->settings = get_option('we_spamfighter_settings', array());
        $notification_type = $this->settings['notification_type'] ?? 'none';

        // Only send if weekly notifications are enabled.
        if ('weekly' !== $notification_type) {
            // Don't log anything if weekly notifications are not enabled to avoid confusion.
            return false;
        }

        $email = $this->get_notification_email();
        if (empty($email)) {
            // Log missing email.
            if (class_exists('\WeSpamfighter\Core\ActivityLog')) {
                $settings = get_option('we_spamfighter_settings', array());
                $activity_log_enabled = isset($settings['activity_log_enabled']) && $settings['activity_log_enabled'];
                if ($activity_log_enabled) {
                    $activity_log = \WeSpamfighter\Core\ActivityLog::get_instance();
                    $activity_log->log(
                        'weekly_summary_error',
                        __('Weekly summary failed: No email address configured', 'we-spamfighter'),
                        array('email' => $email)
                    );
                }
            }
            return false;
        }

        $db = Database::get_instance();

        // Use WordPress timezone to calculate dates correctly.
        // current_time('timestamp') returns Unix timestamp in WordPress timezone.
        $now_timestamp = current_time('timestamp');
        $week_ago_start_timestamp = strtotime('-7 days midnight', $now_timestamp);
        $today_start_timestamp = strtotime('today midnight', $now_timestamp);

        // Convert to MySQL datetime format using date() with WordPress timezone timestamp.
        $week_ago_start = date('Y-m-d H:i:s', $week_ago_start_timestamp);
        $today_start = date('Y-m-d H:i:s', $today_start_timestamp);

        // Get spam submissions from last week.
        $spam_submissions = $db->get_submissions(array(
            'is_spam'   => 1,
            'date_from' => $week_ago_start,
            'date_to'   => $today_start,
            'limit'     => 1000, // Get all from last week.
        ));

        $subject = sprintf(
            /* translators: %s: Site name */
            __('[%s] Weekly Spam Summary', 'we-spamfighter'),
            get_bloginfo('name')
        );

        // Send email even if no spam (to confirm the system is working).
        if (empty($spam_submissions)) {
            $message = sprintf(
                /* translators: %s: Site name */
                __('No spam submissions were detected on %s in the last week.', 'we-spamfighter'),
                get_bloginfo('name')
            ) . "\n\n";
            $message .= __('Great job keeping your site clean!', 'we-spamfighter') . "\n\n";
            $admin_url = admin_url('admin.php?page=we-spamfighter');
            $message .= __('View Dashboard:', 'we-spamfighter') . "\n";
            $message .= $admin_url . "\n";
        } else {
            $message = $this->build_summary_message($spam_submissions, 'weekly');
        }

        $result = wp_mail($email, $subject, $message, $this->get_email_headers());

        // Log result for debugging.
        if (class_exists('\WeSpamfighter\Core\ActivityLog')) {
            $settings = get_option('we_spamfighter_settings', array());
            $activity_log_enabled = isset($settings['activity_log_enabled']) && $settings['activity_log_enabled'];
            if ($activity_log_enabled) {
                $activity_log = \WeSpamfighter\Core\ActivityLog::get_instance();
                $activity_log->log(
                    'weekly_summary_email',
                    $result ? __('Weekly summary email sent successfully', 'we-spamfighter') : __('Weekly summary email failed to send', 'we-spamfighter'),
                    array(
                        'email' => $email,
                        'success' => $result,
                        'spam_count' => count($spam_submissions),
                        'date_range' => array(
                            'from' => $week_ago_start,
                            'to' => $today_start,
                        ),
                        'wp_mail_result' => $result,
                    )
                );
            }
        }

        // Also log to error log if wp_mail failed (if WP_DEBUG_LOG is enabled).
        // Note: WP_DEBUG_LOG is a WordPress constant that may be set in wp-config.php.
        if (!$result) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            @error_log('WE Spamfighter: Weekly summary email failed. Email: ' . $email . ', Spam count: ' . count($spam_submissions));
        }

        return $result;
    }

    /**
     * Build immediate notification message.
     *
     * @param array $submission_data Submission data.
     * @return string
     */
    private function build_immediate_notification_message($submission_data)
    {
        $submission_data_decoded = json_decode($submission_data['submission_data'] ?? '{}', true);
        $detection_details = json_decode($submission_data['detection_details'] ?? '{}', true);

        $message = sprintf(
            /* translators: %s: Site name */
            __('A spam submission was detected on %s.', 'we-spamfighter'),
            get_bloginfo('name')
        ) . "\n\n";

        $message .= __('Submission Details:', 'we-spamfighter') . "\n";
        $message .= __('ID:', 'we-spamfighter') . ' ' . ($submission_data['id'] ?? 'N/A') . "\n";
        $message .= __('Type:', 'we-spamfighter') . ' ' . ucfirst($submission_data['submission_type'] ?? 'N/A') . "\n";
        $message .= __('Form/Post ID:', 'we-spamfighter') . ' ' . ($submission_data['form_id'] ?? 'N/A') . "\n";
        $message .= __('Spam Score:', 'we-spamfighter') . ' ' . number_format($submission_data['spam_score'] ?? 0, 2) . "\n";
        $message .= __('Date:', 'we-spamfighter') . ' ' . ($submission_data['created_at'] ?? 'N/A') . "\n\n";

        if (! empty($submission_data_decoded)) {
            $message .= __('Submission Data:', 'we-spamfighter') . "\n";
            foreach ($submission_data_decoded as $key => $value) {
                if (strpos($key, '_') === 0) {
                    continue; // Skip technical fields.
                }
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $message .= '  ' . esc_html($key) . ': ' . esc_html($value) . "\n";
            }
            $message .= "\n";
        }

        if (! empty($detection_details)) {
            $message .= __('Detection Details:', 'we-spamfighter') . "\n";
            if (isset($detection_details['openai']['reasoning'])) {
                $message .= '  ' . esc_html($detection_details['openai']['reasoning']) . "\n";
            }
            $message .= "\n";
        }

        $admin_url = admin_url('admin.php?page=we-spamfighter&tab=spam');
        $message .= __('View in Dashboard:', 'we-spamfighter') . "\n";
        $message .= $admin_url . "\n";

        return $message;
    }

    /**
     * Build summary message.
     *
     * @param array  $submissions Spam submissions.
     * @param string $period Period (daily or weekly).
     * @return string
     */
    private function build_summary_message($submissions, $period = 'daily')
    {
        $period_label = ('weekly' === $period) ? __('last week', 'we-spamfighter') : __('yesterday', 'we-spamfighter');
        $count = count($submissions);

        $message = sprintf(
            /* translators: %1$d: Count, %2$s: Period, %3$s: Site name */
            __('%1$d spam submission(s) were detected on %3$s %2$s.', 'we-spamfighter'),
            $count,
            $period_label,
            get_bloginfo('name')
        ) . "\n\n";

        $message .= __('Summary:', 'we-spamfighter') . "\n";
        $message .= __('Total Spam:', 'we-spamfighter') . ' ' . $count . "\n\n";

        // Group by form/type.
        $by_type = array();
        foreach ($submissions as $submission) {
            $type = $submission['submission_type'] ?? 'unknown';
            $form_id = $submission['form_id'] ?? 0;
            $key = $type . '-' . $form_id;
            if (! isset($by_type[$key])) {
                $by_type[$key] = array(
                    'type'   => $type,
                    'form_id' => $form_id,
                    'count'  => 0,
                );
            }
            $by_type[$key]['count']++;
        }

        if (! empty($by_type)) {
            $message .= __('By Form/Type:', 'we-spamfighter') . "\n";
            foreach ($by_type as $item) {
                $message .= '  ' . ucfirst($item['type']) . ' (ID: ' . $item['form_id'] . '): ' . $item['count'] . "\n";
            }
            $message .= "\n";
        }

        // Show top 5 submissions (highest spam scores).
        $top_submissions = array_slice($submissions, 0, 5);
        if (! empty($top_submissions)) {
            $message .= __('Top Spam Submissions:', 'we-spamfighter') . "\n";
            foreach ($top_submissions as $submission) {
                $submission_data = json_decode($submission['submission_data'] ?? '{}', true);
                $preview = '';
                if (! empty($submission_data)) {
                    foreach ($submission_data as $key => $value) {
                        if (strpos($key, '_') === 0) {
                            continue;
                        }
                        if (is_array($value)) {
                            $value = implode(', ', $value);
                        }
                        if (strlen($value) > 50) {
                            $value = substr($value, 0, 50) . '...';
                        }
                        $preview .= esc_html($key) . ': ' . esc_html($value) . ' | ';
                    }
                    $preview = rtrim($preview, ' | ');
                }
                $message .= '  ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission['created_at'])) . ' - Score: ' . number_format($submission['spam_score'], 2);
                if ($preview) {
                    $message .= ' - ' . $preview;
                }
                $message .= "\n";
            }
            $message .= "\n";
        }

        $admin_url = admin_url('admin.php?page=we-spamfighter&tab=spam');
        $message .= __('View All in Dashboard:', 'we-spamfighter') . "\n";
        $message .= $admin_url . "\n";

        return $message;
    }

    /**
     * Get notification email address.
     *
     * @return string
     */
    private function get_notification_email()
    {
        $email = $this->settings['notification_email'] ?? '';
        if (empty($email)) {
            $email = get_option('admin_email');
        }
        return sanitize_email($email);
    }

    /**
     * Get email headers.
     *
     * @return array
     */
    private function get_email_headers()
    {
        $headers = array();
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>';

        return $headers;
    }
}
