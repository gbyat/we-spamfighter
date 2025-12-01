<?php

/**
 * Contact Form 7 integration.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Integration;

use WeSpamfighter\Core\Database;
use WeSpamfighter\Detection\OpenAI;
use WeSpamfighter\Detection\LanguageDetector;
use WeSpamfighter\Detection\HeuristicDetector;

/**
 * Contact Form 7 integration class.
 */
class ContactForm7
{

    /**
     * Instance.
     *
     * @var ContactForm7
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
     * @return ContactForm7
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Spam status storage.
     *
     * @var array
     */
    private $spam_status = array();

    /**
     * Constructor.
     */
    private function __construct()
    {
        $this->settings = get_option('we_spamfighter_settings', array());

        // Hook into CF7 form submission.
        // wpcf7_before_send_mail is an ACTION hook (not filter!), and $abort is passed by reference!
        add_action('wpcf7_before_send_mail', array($this, 'check_submission_before_send'), 10, 3);
        // Use wpcf7_skip_mail filter as alternative way to prevent email.
        add_filter('wpcf7_skip_mail', array($this, 'skip_mail_if_spam'), 10, 2);
        add_filter('wpcf7_mail_components', array($this, 'prevent_spam_email'), 10, 3);

        // Override CF7 abort message with custom message.
        add_filter('wpcf7_display_message', array($this, 'override_abort_message'), 10, 2);

        // Register and enqueue frontend scripts using WordPress standard method.
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 20);
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_scripts()
    {
        // Only on frontend.
        if (is_admin()) {
            return;
        }

        // Check if we're in debug mode - load unminified files if WP_DEBUG is enabled.
        $suffix = (defined('WP_DEBUG') && constant('WP_DEBUG')) ? '' : '.min';

        // Build script URL and path.
        $script_url  = WE_SPAMFIGHTER_PLUGIN_URL . 'assets/js/frontend' . $suffix . '.js';
        $script_path = WE_SPAMFIGHTER_PLUGIN_DIR . 'assets/js/frontend' . $suffix . '.js';

        // Fallback to non-minified if minified doesn't exist.
        if (!file_exists($script_path) && $suffix === '.min') {
            $script_url  = WE_SPAMFIGHTER_PLUGIN_URL . 'assets/js/frontend.js';
            $script_path = WE_SPAMFIGHTER_PLUGIN_DIR . 'assets/js/frontend.js';
        }

        // Verify script file exists.
        if (!file_exists($script_path)) {
            return;
        }

        // Load CSS content for inline injection.
        $css_path = WE_SPAMFIGHTER_PLUGIN_DIR . 'assets/css/frontend' . $suffix . '.css';
        if (!file_exists($css_path) && $suffix === '.min') {
            $css_path = WE_SPAMFIGHTER_PLUGIN_DIR . 'assets/css/frontend.css';
        }

        // Register script.
        wp_register_script(
            'we-spamfighter-frontend',
            $script_url,
            array(), // No dependencies - uses vanilla JavaScript
            WE_SPAMFIGHTER_VERSION,
            true // Load in footer
        );

        // Enqueue script.
        wp_enqueue_script('we-spamfighter-frontend');

        // Inject CSS inline in head (better performance - reduces HTTP requests).
        if (file_exists($css_path)) {
            $css_content = file_get_contents($css_path);
            if ($css_content) {
                // Minify CSS if not already minified (for performance).
                if ($suffix === '' && function_exists('preg_replace')) {
                    // Remove comments.
                    $css_content = preg_replace('/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', '', $css_content);
                    // Minify whitespace.
                    $css_content = preg_replace('/\s+/', ' ', $css_content);
                    // Remove spaces around selectors and properties.
                    $css_content = preg_replace('/\s*([{}:;])\s*/', '$1', $css_content);
                    // Remove trailing semicolons.
                    $css_content = preg_replace('/;}/', '}', $css_content);
                    $css_content = trim($css_content);
                }

                // Add inline CSS to head.
                add_action('wp_head', function () use ($css_content) {
                    echo '<style id="we-spamfighter-frontend-inline-css">' . esc_html($css_content) . '</style>' . "\n";
                }, 99);
            }
        }
    }

    /**
     * Check submission before sending mail.
     * NOTE: This is an ACTION hook, not a filter!
     * $abort is passed by reference using do_action_ref_array!
     *
     * @param WPCF7_ContactForm $contact_form Contact form object.
     * @param bool              &$abort Whether to abort (passed by reference!).
     * @param WPCF7_Submission  $submission Submission object.
     * @return void
     */
    public function check_submission_before_send($contact_form, &$abort, $submission)
    {
        // Safety checks.
        if (! $contact_form || ! $submission) {
            return;
        }

        try {
            // Reload settings in case they changed.
            $this->settings = get_option('we_spamfighter_settings', array());

            if (! $this->is_enabled()) {
                // Still save submission even if checks are disabled.
                $this->save_submission($contact_form, $submission, false, 0, '');
                return; // Don't modify $abort
            }

            $form_id = $contact_form->id();
            $posted_data = $submission->get_posted_data();

            // Get entry data for analysis.
            $entry_data = $this->get_entry_data($posted_data);

            $is_spam = false;
            $score = 0.0;
            $detection_method = '';
            $detection_details = array();
            $threshold = (float) ($this->settings['ai_threshold'] ?? 0.7);
            $openai_result = null;

            // STEP 1: Check with heuristic detector FIRST (local, fast, free).
            if (! empty($this->settings['heuristic_enabled']) && ! empty($entry_data)) {
                $heuristic_result = HeuristicDetector::analyze($entry_data, $this->settings);
                $heuristic_score = isset($heuristic_result['score']) ? (float) $heuristic_result['score'] : 0.0;

                if ($heuristic_score > 0) {
                    $score = min(1.0, $score + $heuristic_score);
                    $detection_details['heuristic'] = $heuristic_result;

                    // Check if already spam.
                    if ($score >= $threshold || ! empty($heuristic_result['is_spam'])) {
                        $is_spam = true;
                        $detection_method = 'heuristic';
                    } else {
                        $detection_method = 'heuristic';
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

                    // Update detection details.
                    if (empty($detection_details)) {
                        $detection_details = array();
                    }
                    $detection_details['language_mismatch'] = array(
                        'expected' => $expected_lang,
                        'detected' => $detected_lang,
                        'score_boost' => $score_boost,
                        'detection_method' => 'heuristic',
                    );

                    // Check if already spam.
                    if (! $is_spam && $score >= $threshold) {
                        $is_spam = true;
                        $detection_method = empty($detection_method) ? 'language' : $detection_method . '+language';
                    } else {
                        $detection_method = empty($detection_method) ? 'language' : $detection_method . '+language';
                    }
                }
            }

            // STEP 3: Only check with OpenAI if score is still below threshold (saves API costs).
            $openai_enabled = $this->is_openai_enabled();
            if ($openai_enabled && ! empty($entry_data) && ! $is_spam && $score < $threshold) {
                $openai_result = $this->check_with_openai($entry_data, $form_id);
                $openai_score = isset($openai_result['score']) ? (float) $openai_result['score'] : 0.0;

                if ($openai_score > 0) {
                    // Add OpenAI score to existing score.
                    $score = min(1.0, $score + $openai_score);
                    $detection_details['openai'] = $openai_result;

                    // Check spam status.
                    $openai_is_spam = ! empty($openai_result['is_spam']);
                    if (! $is_spam && ($openai_is_spam || $score >= $threshold)) {
                        $is_spam = true;
                    }

                    // Update detection method.
                    if (empty($detection_method)) {
                        $detection_method = 'openai';
                    } else {
                        $detection_method .= '+openai';
                    }
                }
            }

            // Save submission to database (always save, even if not spam).
            $submission_id = $this->save_submission($contact_form, $submission, $is_spam, $score, $detection_method, $detection_details);

            // Store spam status for this submission.
            if ($submission_id) {
                $this->spam_status[$submission_id] = array(
                    'is_spam' => $is_spam,
                    'score' => $score,
                );

                // Store in submission meta for retrieval in prevent_spam_email.
                // Note: WPCF7_Submission doesn't have add_meta(), so we store in our internal array.
                // The submission object can be retrieved via WPCF7_Submission::get_instance() later.

                // Send immediate notification if spam detected.
                if ($is_spam) {
                    $submission_data = Database::get_instance()->get_submission($submission_id);
                    if ($submission_data) {
                        \WeSpamfighter\Core\Notifications::get_instance()->send_immediate_notification($submission_id, $submission_data);
                    }
                }
            }

            // If spam, abort sending by setting $abort to true (passed by reference).
            if ($is_spam) {
                $abort = true; // Modify by reference to prevent email
            }
        } catch (\Exception $e) {
            // Don't abort on error, let the form submit normally.
        } catch (\Error $e) {
            // Catch fatal errors too - don't abort on error, let the form submit normally.
        }
    }

    /**
     * Skip mail if submission is spam (alternative approach).
     *
     * @param bool              $skip Whether to skip mail.
     * @param WPCF7_ContactForm $contact_form Contact form object.
     * @return bool
     */
    public function skip_mail_if_spam($skip, $contact_form)
    {
        // Get the current submission instance.
        $submission = \WPCF7_Submission::get_instance();

        if (! $submission) {
            return $skip;
        }

        // Check our internal storage first (we store spam status there).
        $form_id = $contact_form->id();
        $is_spam = false;
        $submission_id = null;

        // Get most recent submission for this form from our database.
        $db = \WeSpamfighter\Core\Database::get_instance();
        $recent_submissions = $db->get_submissions(array(
            'form_id'  => $form_id,
            'limit'    => 1,
            'order_by' => 'created_at',
            'order'    => 'DESC',
        ));

        if (!empty($recent_submissions)) {
            $latest = $recent_submissions[0];
            $created_time = strtotime($latest['created_at']);
            // Only consider if created within last minute.
            if (time() - $created_time < 60) {
                $submission_id = $latest['id'];
                $is_spam = (bool) $latest['is_spam'];
            }
        }

        // Also check our internal storage if we have the ID.
        if ($submission_id && isset($this->spam_status[$submission_id])) {
            $is_spam = $this->spam_status[$submission_id]['is_spam'];
        }

        if ($is_spam) {
            return true; // Skip mail
        }

        return $skip;
    }

    /**
     * Prevent email from being sent if submission is spam (fallback).
     * NOTE: Third parameter is WPCF7_Mail object, not WPCF7_Submission!
     * This is a fallback - wpcf7_before_send_mail should handle the abort.
     *
     * @param array             $components Mail components.
     * @param WPCF7_ContactForm $contact_form Contact form object.
     * @param WPCF7_Mail        $mail Mail object.
     * @return array
     */
    public function prevent_spam_email($components, $contact_form, $mail)
    {
        // Try to get the current submission instance (like Flamingo does).
        $submission = \WPCF7_Submission::get_instance();

        if (! $submission) {
            // If we can't get submission, just return components as-is.
            return $components;
        }

        // Check our internal storage first (we store spam status there).
        $form_id = $contact_form->id();
        $is_spam = false;
        $submission_id = null;

        // Get most recent submission for this form from our database.
        $db = \WeSpamfighter\Core\Database::get_instance();
        $recent_submissions = $db->get_submissions(array(
            'form_id'  => $form_id,
            'limit'    => 1,
            'order_by' => 'created_at',
            'order'    => 'DESC',
        ));

        if (!empty($recent_submissions)) {
            $latest = $recent_submissions[0];
            $created_time = strtotime($latest['created_at']);
            // Only consider if created within last minute.
            if (time() - $created_time < 60) {
                $submission_id = $latest['id'];
                $is_spam = (bool) $latest['is_spam'];
            }
        }

        // Also check our internal storage if we have the ID.
        if ($submission_id && isset($this->spam_status[$submission_id])) {
            $is_spam = $this->spam_status[$submission_id]['is_spam'];
        }

        // If spam, return empty components to prevent email (fallback if abort didn't work).
        if ($is_spam) {
            // Return empty components to prevent email from being sent.
            return array(
                'subject' => '',
                'sender' => '',
                'body' => '',
                'recipient' => '',
                'additional_headers' => '',
                'attachments' => array(),
            );
        }

        // Mark email as sent in database if not spam.
        if ($submission_id) {
            $this->mark_email_sent($submission_id);
        } else {
            $this->mark_email_sent_by_form($contact_form->id(), $submission);
        }

        return $components;
    }

    /**
     * Override CF7 abort message with custom thank you message.
     *
     * @param string $message The message to display.
     * @param string $status The status (mail_sent, mail_failed, validation_failed, spam, aborted).
     * @return string
     */
    public function override_abort_message($message, $status)
    {
        // Check if email sending was aborted (either by status or by checking the message content).
        // CF7 shows "Der E-Mail-Versand wurde abgebrochen." (or translated version) when abort is set to true.
        $is_aborted = false;

        // Check status.
        if ('aborted' === $status) {
            $is_aborted = true;
        }

        // Also check if the message contains abort-related text (in case status is different).
        // This covers both German and English versions.
        $abort_messages = array(
            'abgebrochen',
            'abort',
            'mail.*send.*abort',
        );
        foreach ($abort_messages as $abort_msg) {
            if (preg_match('/' . $abort_msg . '/i', $message)) {
                $is_aborted = true;
                break;
            }
        }

        if ($is_aborted) {
            // Check if this was a spam submission by checking the most recent submission.
            $submission = \WPCF7_Submission::get_instance();
            if ($submission) {
                $contact_form = $submission->get_contact_form();
                $form_id = $contact_form ? $contact_form->id() : 0;
                if ($form_id) {
                    $db = \WeSpamfighter\Core\Database::get_instance();
                    $recent_submissions = $db->get_submissions(array(
                        'form_id'  => $form_id,
                        'limit'    => 1,
                        'order_by' => 'created_at',
                        'order'    => 'DESC',
                    ));

                    if (!empty($recent_submissions)) {
                        $latest = $recent_submissions[0];
                        $created_time = strtotime($latest['created_at']);
                        // Only override if submission was created within last minute and is spam.
                        if (time() - $created_time < 60 && !empty($latest['is_spam'])) {
                            // Return custom thank you message (translatable with .pot file).
                            return __('Thank you for your message.', 'we-spamfighter');
                        }
                    }
                }
            }
        }

        return $message;
    }

    /**
     * Get entry data from posted data.
     *
     * @param array $posted_data Posted data.
     * @return array
     */
    private function get_entry_data($posted_data)
    {
        $entry = array();

        // List of CF7 special fields to skip.
        $cf7_special_fields = array(
            '_wpcf7',
            '_wpcf7_version',
            '_wpcf7_locale',
            '_wpcf7_unit_tag',
            '_wpcf7_container_post',
            '_wpcf7_posted_data_hash',
        );

        foreach ($posted_data as $key => $value) {
            // Skip special CF7 fields.
            if (in_array($key, $cf7_special_fields, true)) {
                continue;
            }

            // Skip fields that start with underscore (CF7 internal fields).
            if (strpos($key, '_') === 0) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', array_filter($value));
            }

            if (empty($value)) {
                continue;
            }

            $entry[$key] = sanitize_text_field($value);
        }

        return $entry;
    }

    /**
     * Check with OpenAI.
     *
     * @param array $entry Entry data.
     * @param int   $form_id Form ID.
     * @return array
     */
    private function check_with_openai($entry, $form_id)
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
     * @param WPCF7_ContactForm $contact_form Contact form object.
     * @param WPCF7_Submission  $submission Submission object.
     * @param bool              $is_spam Whether it's spam.
     * @param float             $spam_score Spam score.
     * @param string            $detection_method Detection method.
     * @param array             $detection_details Detection details.
     * @return int|false
     */
    private function save_submission($contact_form, $submission, $is_spam, $spam_score, $detection_method = '', $detection_details = array())
    {
        try {
            $db = Database::get_instance();
            $posted_data = $submission->get_posted_data();

            $result = $db->save_submission(array(
                'submission_type'  => 'cf7',
                'form_id'          => $contact_form->id(),
                'submission_data'  => $posted_data,
                'is_spam'          => $is_spam ? 1 : 0,
                'spam_score'       => $spam_score,
                'detection_method' => $detection_method,
                'detection_details' => $detection_details,
                'email_sent'       => 0, // Will be updated when email is actually sent.
            ));

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Mark email as sent in database by submission ID.
     *
     * @param int $submission_id Submission ID.
     * @return void
     */
    private function mark_email_sent($submission_id)
    {
        $db = Database::get_instance();
        $db->mark_email_sent($submission_id);
    }

    /**
     * Mark email as sent in database by form ID (fallback).
     *
     * @param int              $form_id Form ID.
     * @param WPCF7_Submission $submission Submission object.
     * @return void
     */
    private function mark_email_sent_by_form($form_id, $submission)
    {
        // Find the submission in database by form_id and recent timestamp.
        $db = Database::get_instance();
        $submissions = $db->get_submissions(array(
            'form_id'  => $form_id,
            'is_spam'  => 0,
            'limit'    => 1,
            'order_by' => 'created_at',
            'order'    => 'DESC',
        ));

        if (! empty($submissions)) {
            $latest = $submissions[0];
            // Only mark as sent if it was created within the last minute.
            $created_time = strtotime($latest['created_at']);
            if (time() - $created_time < 60) {
                $db->mark_email_sent($latest['id']);
            }
        }
    }

    /**
     * Check if CF7 protection is enabled.
     *
     * @return bool
     */
    private function is_enabled()
    {
        return ! empty($this->settings['cf7_enabled']);
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
