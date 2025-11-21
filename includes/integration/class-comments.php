<?php

/**
 * Comments integration.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Integration;

use WeSpamfighter\Core\Database;
use WeSpamfighter\Detection\OpenAI;

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
     * @param int|string $approved Approval status.
     * @param array      $commentdata Comment data.
     * @return int|string
     */
    public function check_comment($approved, $commentdata)
    {
        if (! $this->is_enabled() || ! $this->is_openai_enabled()) {
            // Still save submission even if checks are disabled.
            $this->save_submission($commentdata, false, 0, '');
            return $approved;
        }

        // Get entry data for analysis.
        $entry_data = $this->get_entry_data($commentdata);

        // Check with OpenAI.
        $openai_result = $this->check_with_openai($entry_data, 0);
        $score = isset($openai_result['score']) ? (float) $openai_result['score'] : 0.0;
        $threshold = (float) ($this->settings['ai_threshold'] ?? 0.7);

        $is_spam = ! empty($openai_result['is_spam']);
        if (! $is_spam && $score >= $threshold) {
            $is_spam = true;
        }

        // Store result for later use in save_comment_submission.
        $commentdata['we_spamfighter_is_spam'] = $is_spam;
        $commentdata['we_spamfighter_score'] = $score;
        $commentdata['we_spamfighter_result'] = $openai_result;

        // If spam, mark as spam.
        if ($is_spam) {
            $approved = 'spam';
        }

        // Save submission to database.
        $detection_method = $is_spam ? 'openai' : '';
        $detection_details = $is_spam ? array('openai' => $openai_result) : array();
        $submission_id = $this->save_submission($commentdata, $is_spam, $score, $detection_method, $detection_details);

        // Send immediate notification if spam detected.
        if ($is_spam && $submission_id) {
            $submission_data = Database::get_instance()->get_submission($submission_id);
            if ($submission_data) {
                \WeSpamfighter\Core\Notifications::get_instance()->send_immediate_notification($submission_id, $submission_data);
            }
        }

        return $approved;
    }

    /**
     * Save comment submission after comment is posted.
     *
     * @param int        $comment_id Comment ID.
     * @param int|string $approved Approval status.
     * @param array      $commentdata Comment data.
     * @return void
     */
    public function save_comment_submission($comment_id, $approved, $commentdata)
    {
        // This is handled in check_comment, but we keep this hook for consistency.
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
     * Save submission to database.
     *
     * @param array  $commentdata Comment data.
     * @param bool   $is_spam Whether it's spam.
     * @param float  $spam_score Spam score.
     * @param string $detection_method Detection method.
     * @param array  $detection_details Detection details.
     * @return int|false
     */
    private function save_submission($commentdata, $is_spam, $spam_score, $detection_method = '', $detection_details = array())
    {
        $db = Database::get_instance();

        return $db->save_submission(array(
            'submission_type'  => 'comment',
            'form_id'          => isset($commentdata['comment_post_ID']) ? (int) $commentdata['comment_post_ID'] : 0,
            'submission_data'  => $commentdata,
            'is_spam'          => $is_spam ? 1 : 0,
            'spam_score'       => $spam_score,
            'detection_method' => $detection_method,
            'detection_details' => $detection_details,
            'email_sent'       => 0, // Comments don't send emails through this system.
        ));
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
