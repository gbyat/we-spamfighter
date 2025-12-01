<?php

/**
 * Comments integration.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Integration;

use WeSpamfighter\Core\Database;
use WeSpamfighter\Detection\OpenAI;
use WeSpamfighter\Detection\LanguageDetector;
use WeSpamfighter\Detection\HeuristicDetector;

/**
 * Comments integration class.
 */
class Comments
{

    /**
     * Instance.
     *
     * @var Comments
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
     * @return Comments
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

        // Hook into comment submission.
        add_filter('pre_comment_approved', array($this, 'check_comment'), 10, 2);
        add_action('comment_post', array($this, 'save_comment_submission'), 10, 3);
    }

    /**
     * Check comment before approval.
     *
     * Comments are NOT saved to our database. They are handled by WordPress.
     * We only check for spam and mark them accordingly.
     *
     * @param int|string $approved Approval status.
     * @param array      $commentdata Comment data.
     * @return int|string
     */
    public function check_comment($approved, $commentdata)
    {
        if (! $this->is_enabled()) {
            return $approved;
        }

        // Check if pingbacks/trackbacks should be automatically marked as spam.
        if (! empty($this->settings['auto_mark_pingbacks_spam'])) {
            $comment_type = isset($commentdata['comment_type']) ? $commentdata['comment_type'] : '';

            // Check if this is a pingback or trackback.
            if ('pingback' === $comment_type || 'trackback' === $comment_type) {
                // Automatically mark as spam without OpenAI check.
                $approved = 'spam';

                // Send immediate notification if enabled.
                if (! empty($this->settings['notification_type']) && 'immediate' === $this->settings['notification_type']) {
                    $this->send_comment_spam_notification($commentdata, 1.0, array('reason' => 'Automatically marked as spam (pingback/trackback)'));
                }

                return $approved;
            }
        }

        // If OpenAI is not enabled, return approved status as-is.
        if (! $this->is_openai_enabled()) {
            return $approved;
        }

        // Get entry data for analysis.
        $entry_data = $this->get_entry_data($commentdata);

        $is_spam = false;
        $score = 0.0;
        $openai_result = null;
        $threshold = (float) ($this->settings['ai_threshold'] ?? 0.7);

        // STEP 1: Check with heuristic detector FIRST (local, fast, free).
        if (! empty($this->settings['heuristic_enabled']) && ! empty($entry_data)) {
            $heuristic_result = HeuristicDetector::analyze($entry_data, $this->settings);
            $heuristic_score = isset($heuristic_result['score']) ? (float) $heuristic_result['score'] : 0.0;

            if ($heuristic_score > 0) {
                $score = min(1.0, $score + $heuristic_score);

                // Check if already spam.
                if ($score >= $threshold || ! empty($heuristic_result['is_spam'])) {
                    $is_spam = true;
                }
            }
        }

        // STEP 2: Check language mismatch if enabled (local, fast, free).
        if (! empty($this->settings['mark_different_language_spam']) && ! empty($entry_data)) {
            // Use simple language detection (no OpenAI needed).
            $detected_lang = OpenAI::normalize_language_code(LanguageDetector::detect_language($entry_data));

            // Get expected language from WordPress locale.
            $locale = get_locale();
            $expected_lang = OpenAI::normalize_language_code(substr($locale, 0, 2));

            // If languages don't match and both are valid.
            if (! empty($expected_lang) && ! empty($detected_lang) && $expected_lang !== $detected_lang) {
                $score_boost = (float) ($this->settings['language_spam_score_boost'] ?? 0.3);
                $score = min(1.0, $score + $score_boost);

                // Check if already spam.
                if (! $is_spam && $score >= $threshold) {
                    $is_spam = true;
                }
            }
        }

        // STEP 3: Only check with OpenAI if score is still below threshold (saves API costs).
        if ($this->is_openai_enabled() && ! empty($entry_data) && ! $is_spam && $score < $threshold) {
            $openai_result = $this->check_with_openai($entry_data, 0);
            $openai_score = isset($openai_result['score']) ? (float) $openai_result['score'] : 0.0;

            if ($openai_score > 0) {
                // Add OpenAI score to existing score.
                $score = min(1.0, $score + $openai_score);

                // Check spam status.
                $openai_is_spam = ! empty($openai_result['is_spam']);
                if (! $is_spam && ($openai_is_spam || $score >= $threshold)) {
                    $is_spam = true;
                }
            }
        }

        // If spam, mark as spam (WordPress will handle it).
        if ($is_spam) {
            $approved = 'spam';

            // Send immediate notification if spam detected.
            if (! empty($this->settings['notification_type']) && 'immediate' === $this->settings['notification_type']) {
                $this->send_comment_spam_notification($commentdata, $score, $openai_result);
            }
        }

        return $approved;
    }

    /**
     * Save comment submission after comment is posted.
     * 
     * This hook is no longer used since we don't save comments anymore.
     * Kept for backward compatibility.
     *
     * @param int        $comment_id Comment ID.
     * @param int|string $approved Approval status.
     * @param array      $commentdata Comment data.
     * @return void
     */
    public function save_comment_submission($comment_id, $approved, $commentdata)
    {
        // Comments are no longer saved to our database.
        // They are handled by WordPress comments system.
    }

    /**
     * Get entry data from comment data.
     *
     * @param array $commentdata Comment data.
     * @return array
     */
    private function get_entry_data($commentdata)
    {
        $entry = array();

        if (! empty($commentdata['comment_author'])) {
            $entry['author'] = $commentdata['comment_author'];
        }

        if (! empty($commentdata['comment_author_email'])) {
            $entry['email'] = $commentdata['comment_author_email'];
        }

        if (! empty($commentdata['comment_author_url'])) {
            $entry['url'] = $commentdata['comment_author_url'];
        }

        if (! empty($commentdata['comment_content'])) {
            $entry['comment'] = $commentdata['comment_content'];
        }

        return $entry;
    }

    /**
     * Check with OpenAI.
     *
     * @param array $entry Entry data.
     * @param int   $post_id Post ID.
     * @return array
     */
    private function check_with_openai($entry, $post_id)
    {
        $api_key = $this->settings['openai_api_key'] ?? '';
        $model = $this->settings['openai_model'] ?? 'gpt-4o-mini';

        if (empty($api_key)) {
            return array(
                'score'   => 0,
                'is_spam' => false,
                'reason'  => 'API key not configured',
            );
        }

        $detector = new OpenAI($api_key, $model);
        $locale = get_locale();
        $lang = substr($locale, 0, 2);

        return $detector->analyze($entry, $lang);
    }

    /**
     * Send notification for spam comment.
     *
     * @param array $commentdata Comment data.
     * @param float $spam_score Spam score.
     * @param array $detection_details Detection details.
     * @return void
     */
    private function send_comment_spam_notification($commentdata, $spam_score, $detection_details)
    {
        $settings = $this->settings;
        $notification_type = $settings['notification_type'] ?? 'none';

        if ('immediate' !== $notification_type) {
            return;
        }

        $to = $settings['notification_email'] ?? get_option('admin_email');
        if (empty($to)) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: Site name */
            __('[%s] Spam Comment Detected', 'we-spamfighter'),
            get_bloginfo('name')
        );

        $body = $this->build_comment_spam_email_body($commentdata, $spam_score, $detection_details);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Build email body for spam comment notification.
     *
     * @param array $commentdata Comment data.
     * @param float $spam_score Spam score.
     * @param array $detection_details Detection details.
     * @return string
     */
    private function build_comment_spam_email_body($commentdata, $spam_score, $detection_details)
    {
        ob_start();
?>
        <p><?php printf(esc_html__('A spam comment has been detected by %s and moved to the spam folder.', 'we-spamfighter'), 'WE Spamfighter'); ?></p>
        <p><strong><?php esc_html_e('Author:', 'we-spamfighter'); ?></strong> <?php echo esc_html($commentdata['comment_author'] ?? '-'); ?></p>
        <p><strong><?php esc_html_e('Email:', 'we-spamfighter'); ?></strong> <?php echo esc_html($commentdata['comment_author_email'] ?? '-'); ?></p>
        <p><strong><?php esc_html_e('Post ID:', 'we-spamfighter'); ?></strong> <?php echo esc_html($commentdata['comment_post_ID'] ?? '-'); ?></p>
        <p><strong><?php esc_html_e('Spam Score:', 'we-spamfighter'); ?></strong> <?php echo esc_html(number_format($spam_score, 2)); ?></p>
        <p><strong><?php esc_html_e('Comment Content:', 'we-spamfighter'); ?></strong></p>
        <pre><?php echo esc_html($commentdata['comment_content'] ?? ''); ?></pre>
        <p><strong><?php esc_html_e('Detection Details:', 'we-spamfighter'); ?></strong></p>
        <pre><?php echo esc_html(wp_json_encode($detection_details, JSON_PRETTY_PRINT)); ?></pre>
        <p><a href="<?php echo esc_url(admin_url('edit-comments.php?comment_status=spam')); ?>"><?php esc_html_e('View Spam Comments', 'we-spamfighter'); ?></a></p>
<?php
        return ob_get_clean();
    }

    /**
     * Check if comments protection is enabled.
     *
     * @return bool
     */
    private function is_enabled()
    {
        return ! empty($this->settings['comments_enabled']);
    }

    /**
     * Check if OpenAI is enabled.
     *
     * @return bool
     */
    private function is_openai_enabled()
    {
        return ! empty($this->settings['openai_enabled']) && ! empty($this->settings['openai_api_key']);
    }
}
