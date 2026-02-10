<?php

/**
 * Privacy / GDPR handling for WE Spamfighter.
 *
 * When OpenAI is active, personal data is sent to OpenAI (USA).
 * This class provides privacy policy content and form notices.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Core;

/**
 * Privacy class.
 */
class Privacy
{

    /**
     * Instance.
     *
     * @var Privacy
     */
    private static $instance = null;

    /**
     * Get instance.
     *
     * @return Privacy
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
        add_action('admin_init', array($this, 'add_privacy_policy_content'));
        add_shortcode('we_spamfighter_privacy', array($this, 'render_privacy_shortcode'));
        add_filter('the_content', array($this, 'maybe_append_privacy_passage'), 15, 1);
        add_action('comment_form_after', array($this, 'maybe_output_comment_form_notice'));
        add_filter('wpcf7_form_elements', array($this, 'maybe_append_cf7_form_notice'), 20, 1);
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_form_notice_style'), 20);
    }

    /**
     * Check if OpenAI is active (enabled and API key configured).
     *
     * @return bool
     */
    private function is_openai_active()
    {
        $settings = get_option('we_spamfighter_settings', array());
        return !empty($settings['openai_enabled']) && !empty($settings['openai_api_key']);
    }

    /**
     * Get the privacy passage text for the privacy policy page.
     * Only returns content when OpenAI is active.
     *
     * @return string
     */
    public function get_privacy_passage_text()
    {
        if (!$this->is_openai_active()) {
            return '';
        }

        $openai_url = 'https://openai.com/privacy';

        $text = sprintf(
            /* translators: %s: URL to OpenAI privacy policy */
            __('We use the plugin WE Spamfighter to detect spam in contact forms and comments. When the AI check (OpenAI) is enabled, your entered data (name, email address, subject and message content) is transmitted to OpenAI (USA) for spam checking. The processing is based on our legitimate interest in effective spam protection (Art. 6(1)(f) GDPR). The transmission to OpenAI is based on Standard Contractual Clauses (Art. 46(2)(c) GDPR). The data is transmitted exclusively for spam checking and is not used by OpenAI for model training or advertising purposes. For details on processing and storage at OpenAI, see: %s', 'we-spamfighter'),
            $openai_url
        );

        return wp_kses_post(wpautop($text));
    }

    /**
     * Get the short form notice for display near forms.
     * Only returns content when OpenAI is active.
     *
     * @return string
     */
    public function get_form_notice_text()
    {
        if (!$this->is_openai_active()) {
            return '';
        }

        $privacy_url = get_privacy_policy_url();
        $link_text   = __('privacy policy', 'we-spamfighter');
        $link        = $privacy_url
            ? '<a href="' . esc_url($privacy_url) . '">' . esc_html($link_text) . '</a>'
            : esc_html($link_text);

        return sprintf(
            /* translators: %s: link to privacy policy or plain text "privacy policy" */
            __('Your message may be transmitted to OpenAI (USA) for spam checking. For details, see our %s.', 'we-spamfighter'),
            $link
        );
    }

    /**
     * Add suggested privacy policy content (WordPress Privacy Policy Guide).
     */
    public function add_privacy_policy_content()
    {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        if (!$this->is_openai_active()) {
            return;
        }

        $content = '<h2>' . esc_html__('Spam protection', 'we-spamfighter') . '</h2>';
        $content .= $this->get_privacy_passage_text();

        wp_add_privacy_policy_content('WE Spamfighter', $content);
    }

    /**
     * Render the privacy shortcode.
     *
     * @return string
     */
    public function render_privacy_shortcode()
    {
        return $this->get_privacy_passage_text();
    }

    /**
     * Append privacy passage to the_content when viewing the privacy policy page.
     * Only if the passage is not already present (e.g. via shortcode).
     * Respects privacy_page_mode setting: filter = append, manual/none = do not append.
     *
     * @param string $content Post content.
     * @return string
     */
    public function maybe_append_privacy_passage($content)
    {
        if (!$this->is_openai_active()) {
            return $content;
        }

        $settings = get_option('we_spamfighter_settings', array());
        $mode = $settings['privacy_page_mode'] ?? 'filter';
        if ('filter' !== $mode) {
            return $content;
        }

        $privacy_page_id = (int) get_option('wp_page_for_privacy_policy', 0);
        if (!$privacy_page_id || !is_singular('page')) {
            return $content;
        }

        if (get_the_ID() !== $privacy_page_id) {
            return $content;
        }

        // Avoid duplicate: if shortcode is present, don't append.
        if (has_shortcode($content, 'we_spamfighter_privacy')) {
            return $content;
        }

        $passage = $this->get_privacy_passage_text();
        if (empty($passage)) {
            return $content;
        }

        $heading = '<h2 class="we-spamfighter-privacy-heading">' . esc_html__('Spam protection', 'we-spamfighter') . '</h2>';
        return $content . "\n\n" . $heading . $passage;
    }

    /**
     * Output form notice after comment form when comments are protected and OpenAI is active.
     */
    public function maybe_output_comment_form_notice()
    {
        $settings = get_option('we_spamfighter_settings', array());
        if (empty($settings['comments_enabled'])) {
            return;
        }
        // Only show when form_notice_enabled is true (default for existing installs).
        if (isset($settings['form_notice_enabled']) && !$settings['form_notice_enabled']) {
            return;
        }

        $notice = $this->get_form_notice_text();
        if (empty($notice)) {
            return;
        }

        echo '<p class="we-spamfighter-form-notice">' . wp_kses_post($notice) . '</p>';
    }

    /**
     * Enqueue minimal style for form notice when it may be shown.
     */
    public function maybe_enqueue_form_notice_style()
    {
        if (is_admin()) {
            return;
        }

        $settings = get_option('we_spamfighter_settings', array());
        if (empty($settings['comments_enabled']) && empty($settings['cf7_enabled'])) {
            return;
        }

        if (!$this->get_form_notice_text()) {
            return;
        }

        wp_register_style('we-spamfighter-form-notice', false);
        wp_enqueue_style('we-spamfighter-form-notice');
        wp_add_inline_style('we-spamfighter-form-notice', '.we-spamfighter-form-notice{font-size:.85em;color:#666;margin-top:1em;margin-bottom:0}');
    }

    /**
     * Append form notice to CF7 form elements when CF7 is protected and OpenAI is active.
     *
     * @param string $elements Form elements HTML.
     * @return string
     */
    public function maybe_append_cf7_form_notice($elements)
    {
        $settings = get_option('we_spamfighter_settings', array());
        if (empty($settings['cf7_enabled'])) {
            return $elements;
        }
        if (isset($settings['form_notice_enabled']) && !$settings['form_notice_enabled']) {
            return $elements;
        }

        $notice = $this->get_form_notice_text();
        if (empty($notice)) {
            return $elements;
        }

        return $elements . '<p class="we-spamfighter-form-notice">' . wp_kses_post($notice) . '</p>';
    }
}
