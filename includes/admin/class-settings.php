<?php

/**
 * Admin settings page.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Admin;

use WeSpamfighter\Detection\AiSpamDetector;

/**
 * Settings class.
 */
class Settings
{

    /**
     * Instance.
     *
     * @var Settings
     */
    private static $instance = null;

    /**
     * Settings option name.
     *
     * @var string
     */
    private $option_name = 'we_spamfighter_settings';

    /**
     * Get instance.
     *
     * @return Settings
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
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_we_spamfighter_test_api', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_we_spamfighter_clear_activity_log', array($this, 'ajax_clear_activity_log'));
    }

    /**
     * Add admin menu.
     */
    public function add_menu()
    {
        add_submenu_page(
            'we-spamfighter',
            __('Settings', 'we-spamfighter'),
            __('Settings', 'we-spamfighter'),
            'manage_options',
            'we-spamfighter-settings',
            array($this, 'render_settings_page')
        );

        // Add Activity Log submenu only if enabled.
        $settings = get_option('we_spamfighter_settings', array());
        $activity_log_enabled = isset($settings['activity_log_enabled']) && $settings['activity_log_enabled'];

        if ($activity_log_enabled && class_exists('\WeSpamfighter\Core\ActivityLog')) {
            add_submenu_page(
                'we-spamfighter',
                __('Activity Log', 'we-spamfighter'),
                __('Activity Log', 'we-spamfighter'),
                'manage_options',
                'we-spamfighter-activity-log',
                array($this, 'render_activity_log_page')
            );
        }
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook Page hook.
     */
    public function enqueue_scripts($hook)
    {
        // Check if we're on the settings page or activity log page.
        if (strpos($hook, 'we-spamfighter-settings') === false && strpos($hook, 'we-spamfighter-activity-log') === false) {
            return;
        }

        // Check if we're in debug mode - load unminified files if WP_DEBUG is enabled.
        $suffix = (defined('WP_DEBUG') && constant('WP_DEBUG')) ? '' : '.min';

        // Build CSS URL and path.
        $css_url  = WE_SPAMFIGHTER_PLUGIN_URL . 'assets/css/admin' . $suffix . '.css';
        $css_path = WE_SPAMFIGHTER_PLUGIN_DIR . 'assets/css/admin' . $suffix . '.css';

        // Fallback to non-minified if minified doesn't exist.
        if (!file_exists($css_path) && $suffix === '.min') {
            $css_url  = WE_SPAMFIGHTER_PLUGIN_URL . 'assets/css/admin.css';
            $css_path = WE_SPAMFIGHTER_PLUGIN_DIR . 'assets/css/admin.css';
        }

        // Build JS URL and path.
        $js_url  = WE_SPAMFIGHTER_PLUGIN_URL . 'assets/js/admin' . $suffix . '.js';
        $js_path = WE_SPAMFIGHTER_PLUGIN_DIR . 'assets/js/admin' . $suffix . '.js';

        // Fallback to non-minified if minified doesn't exist.
        if (!file_exists($js_path) && $suffix === '.min') {
            $js_url  = WE_SPAMFIGHTER_PLUGIN_URL . 'assets/js/admin.js';
            $js_path = WE_SPAMFIGHTER_PLUGIN_DIR . 'assets/js/admin.js';
        }

        // Only enqueue if files exist.
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'we-spamfighter-admin',
                $css_url,
                array(),
                WE_SPAMFIGHTER_VERSION
            );
        }

        if (file_exists($js_path)) {
            wp_enqueue_script(
                'we-spamfighter-admin',
                $js_url,
                array('jquery'),
                WE_SPAMFIGHTER_VERSION,
                true
            );
        }

        wp_localize_script(
            'we-spamfighter-admin',
            'weSpamfighterAdmin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('we_spamfighter_nonce'),
            )
        );
    }

    /**
     * Register settings.
     */
    public function register_settings()
    {
        register_setting(
            'we_spamfighter_settings_group',
            $this->option_name,
            array($this, 'sanitize_settings')
        );

        // General section.
        add_settings_section(
            'we_spamfighter_general',
            __('General Settings', 'we-spamfighter'),
            array($this, 'render_general_section'),
            'we-spamfighter'
        );

        add_settings_field(
            'cf7_enabled',
            __('Enable Contact Form 7 Protection', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_general',
            array(
                'field_id'    => 'cf7_enabled',
                'description' => __('Enable spam protection for Contact Form 7 submissions', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'comments_enabled',
            __('Enable Comments Protection', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_general',
            array(
                'field_id'    => 'comments_enabled',
                'description' => __('Enable spam protection for comments', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'auto_mark_pingbacks_spam',
            __('Automatically Mark Pingbacks/Trackbacks as Spam', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_general',
            array(
                'field_id'    => 'auto_mark_pingbacks_spam',
                'description' => __('Automatically mark pingbacks and trackbacks as spam without OpenAI check', 'we-spamfighter'),
                'class'       => 'we-spamfighter-pingback-option',
            )
        );

        add_settings_field(
            'mark_different_language_spam',
            __('Mark Different Language as Spam', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_general',
            array(
                'field_id'    => 'mark_different_language_spam',
                'description' => __('Automatically mark submissions as spam if free-text content appears to be in a different language than your website. Name, email, phone, salutation, birth date, and similar fields are ignored so foreign names alone do not trigger this.', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'language_spam_score_boost',
            __('Language Mismatch Score Boost', 'we-spamfighter'),
            array($this, 'render_number_field'),
            'we-spamfighter',
            'we_spamfighter_general',
            array(
                'field_id'    => 'language_spam_score_boost',
                'min'         => 0.1,
                'max'         => 1.0,
                'step'        => 0.1,
                'description' => __('Amount to increase spam score when language doesn\'t match (0.1 - 1.0). Default: 0.3', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'spam_blocked_message',
            __('Spam Blocked Message', 'we-spamfighter'),
            array($this, 'render_textarea_field'),
            'we-spamfighter',
            'we_spamfighter_general',
            array(
                'field_id'    => 'spam_blocked_message',
                'rows'        => 3,
                'description' => __('Message displayed to users when spam is detected and submission is blocked. Default: "Thank you for your message."', 'we-spamfighter'),
            )
        );

        // AI detection section.
        add_settings_section(
            'we_spamfighter_openai',
            __('AI Detection', 'we-spamfighter'),
            array($this, 'render_openai_section'),
            'we-spamfighter'
        );

        add_settings_field(
            'openai_enabled',
            __('Enable AI Detection', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_openai',
            array(
                'field_id'    => 'openai_enabled',
                'description' => __('Use AI to detect spam (via WordPress Connectors or direct OpenAI API)', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'ai_backend',
            __('AI Connection', 'we-spamfighter'),
            array($this, 'render_ai_backend_field'),
            'we-spamfighter',
            'we_spamfighter_openai',
            array(
                'field_id'    => 'ai_backend',
                'description' => __('Use credentials from Settings → Connectors (WordPress 7.0+) or the plugin’s own OpenAI API key.', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'ai_provider',
            __('AI Provider (Connector)', 'we-spamfighter'),
            array($this, 'render_ai_provider_field'),
            'we-spamfighter',
            'we_spamfighter_openai',
            array(
                'field_id'    => 'ai_provider',
                'description' => __('Choose which configured WordPress AI connector to use (e.g. Mistral, OpenAI, Anthropic).', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'ai_model_preference',
            __('Preferred Model(s)', 'we-spamfighter'),
            array($this, 'render_text_field'),
            'we-spamfighter',
            'we_spamfighter_openai',
            array(
                'field_id'    => 'ai_model_preference',
                'description' => __('Optional. Comma-separated model IDs for the selected provider (first available match is used). Leave empty to use the provider default.', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'openai_api_key',
            __('OpenAI API Key', 'we-spamfighter'),
            array($this, 'render_text_field'),
            'we-spamfighter',
            'we_spamfighter_openai',
            array(
                'field_id'    => 'openai_api_key',
                'type'        => 'password',
                'description' => sprintf(
                    /* translators: %s: OpenAI API URL */
                    __('Only for “Direct OpenAI API”. Get your API key from <a href="%s" target="_blank">OpenAI Platform</a>. You can also define WE_SPAMFIGHTER_OPENAI_KEY in wp-config.php.', 'we-spamfighter'),
                    'https://platform.openai.com/api-keys'
                ),
            )
        );

        add_settings_field(
            'openai_model',
            __('OpenAI Model', 'we-spamfighter'),
            array($this, 'render_select_field'),
            'we-spamfighter',
            'we_spamfighter_openai',
            array(
                'field_id'    => 'openai_model',
                'options'     => array(
                    'gpt-4o-mini'   => 'GPT-4o Mini (recommended)',
                    'gpt-5-mini'    => 'GPT-5 mini',
                    'gpt-4o'        => 'GPT-4o',
                    'gpt-5'         => 'GPT-5',
                    'gpt-4-turbo'   => 'GPT-4 Turbo',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                ),
                'description' => __('Only for “Direct OpenAI API”. Which model to use for spam detection.', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'ai_threshold',
            __('AI Spam Threshold', 'we-spamfighter'),
            array($this, 'render_number_field'),
            'we-spamfighter',
            'we_spamfighter_openai',
            array(
                'field_id'    => 'ai_threshold',
                'min'         => 0,
                'max'         => 1,
                'step'        => 0.1,
                'description' => __('Spam score threshold (0.0 - 1.0). Higher values mean fewer submissions are flagged as spam (less strict). Lower values mean more submissions are flagged as spam (more strict). Default: 0.7', 'we-spamfighter'),
            )
        );

        // Heuristic Detection section.
        add_settings_section(
            'we_spamfighter_heuristic',
            __('Heuristic Detection', 'we-spamfighter'),
            array($this, 'render_heuristic_section'),
            'we-spamfighter'
        );

        add_settings_field(
            'heuristic_enabled',
            __('Enable Heuristic Detection', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'heuristic_enabled',
                'description' => __('Use local heuristics to detect spam patterns (works without OpenAI)', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'heuristic_threshold',
            __('Heuristic Spam Threshold', 'we-spamfighter'),
            array($this, 'render_number_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'heuristic_threshold',
                'min'         => 0,
                'max'         => 1,
                'step'        => 0.1,
                'description' => __('Spam score threshold for heuristic detection (0.0 - 1.0). Default: 0.6', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'enable_link_check',
            __('Enable Link Check', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'enable_link_check',
                'description' => __('Enable suspicious link detection', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'enable_character_check',
            __('Enable Character Pattern Check', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'enable_character_check',
                'description' => __('Enable suspicious character pattern detection (e.g., ALL CAPS, repeated characters)', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'enable_phrase_check',
            __('Enable Spam Phrase Check', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'enable_phrase_check',
                'description' => __('Enable known spam phrase detection', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'enable_email_check',
            __('Enable Email Pattern Check', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'enable_email_check',
                'description' => __('Enable suspicious email pattern detection', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'enable_referrer_check',
            __('Enable Referrer Check', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'enable_referrer_check',
                'description' => __('Enable referrer analysis (missing or suspicious referrer detection)', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'enable_user_agent_check',
            __('Enable User Agent Check', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'enable_user_agent_check',
                'description' => __('Enable user agent analysis (bot and suspicious user agent detection)', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'enable_content_length_check',
            __('Enable Content Length Check', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'enable_content_length_check',
                'description' => __('Enable content length analysis (very short or extremely long content detection)', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'enable_cf7_fieldtype_check',
            __('Enable CF7 field-type heuristics', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'enable_cf7_fieldtype_check',
                'description' => __('For Contact Form 7: score suspicious content in [text] fields (URLs, emails, very long lines) and odd [url] query patterns. Does not replace CF7 validation.', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'cf7_text_line_max_length',
            __('CF7 single-line text max length', 'we-spamfighter'),
            array($this, 'render_number_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'cf7_text_line_max_length',
                'min'         => 80,
                'max'         => 2000,
                'step'        => 10,
                'description' => __('Characters: above this length in a CF7 [text] field adds a spam score. Default: 400.', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'enable_mixed_script_check',
            __('Enable Mixed Script Check', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'enable_mixed_script_check',
                'description' => __('Enable mixed script detection (different character sets like Cyrillic + Latin)', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'enable_unicode_check',
            __('Enable Unicode Anomalies Check', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'enable_unicode_check',
                'description' => __('Enable Unicode anomalies detection (zero-width characters, control characters, homoglyphs)', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'enable_numbers_letters_only_check',
            __('Enable Numbers/Letters Only Check', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'enable_numbers_letters_only_check',
                'description' => __('Enable detection of content containing only numbers or only letters', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'enable_ip_in_content_check',
            __('Enable IP Address in Content Check', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'enable_ip_in_content_check',
                'description' => __('Enable detection of IP addresses in content (not in URLs)', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'enable_repeated_multilingual_check',
            __('Enable Repeated Multilingual Content Check', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_heuristic',
            array(
                'field_id'    => 'enable_repeated_multilingual_check',
                'description' => __('Enable detection of repeated multilingual content (same message in multiple languages separated by markers like ***)', 'we-spamfighter'),
            )
        );

        // Notifications section.
        add_settings_section(
            'we_spamfighter_notifications',
            __('Email Notifications', 'we-spamfighter'),
            array($this, 'render_notifications_section'),
            'we-spamfighter'
        );

        add_settings_field(
            'notification_email',
            __('Notification Email', 'we-spamfighter'),
            array($this, 'render_text_field'),
            'we-spamfighter',
            'we_spamfighter_notifications',
            array(
                'field_id'    => 'notification_email',
                'type'        => 'email',
                'description' => __('Email address to receive spam notifications. If empty, admin email will be used.', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'notification_type',
            __('Notification Type', 'we-spamfighter'),
            array($this, 'render_select_field'),
            'we-spamfighter',
            'we_spamfighter_notifications',
            array(
                'field_id'    => 'notification_type',
                'options'     => array(
                    'none'     => __('No notifications', 'we-spamfighter'),
                    'immediate' => __('Immediate (on each spam detection)', 'we-spamfighter'),
                    'daily'    => __('Daily summary', 'we-spamfighter'),
                    'weekly'   => __('Weekly summary', 'we-spamfighter'),
                ),
                'description' => __('How often to receive spam notifications.', 'we-spamfighter'),
            )
        );

        // Maintenance section.
        add_settings_section(
            'we_spamfighter_maintenance',
            __('Maintenance', 'we-spamfighter'),
            array($this, 'render_maintenance_section'),
            'we-spamfighter'
        );

        add_settings_field(
            'log_retention_days',
            __('Log Retention', 'we-spamfighter'),
            array($this, 'render_number_field'),
            'we-spamfighter',
            'we_spamfighter_maintenance',
            array(
                'field_id'    => 'log_retention_days',
                'min'         => 7,
                'max'         => 365,
                'step'        => 1,
                'description' => __('Days to keep logs (default: 30)', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'keep_data_on_uninstall',
            __('Keep Data on Uninstall', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_maintenance',
            array(
                'field_id'    => 'keep_data_on_uninstall',
                'description' => __('If enabled, all plugin data (options, submissions, statistics) will be kept when the plugin is deleted. If disabled, all data will be permanently deleted.', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'github_updates_enabled',
            __('Enable GitHub Updates', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_maintenance',
            array(
                'field_id'    => 'github_updates_enabled',
                'description' => __('Enable automatic updates from GitHub releases. <strong>Activate at your own risk.</strong> Updates will be installed automatically without additional confirmation.', 'we-spamfighter'),
            )
        );

        add_settings_field(
            'activity_log_enabled',
            __('Enable Activity Log', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_maintenance',
            array(
                'field_id'    => 'activity_log_enabled',
                'description' => __('Enable activity logging to track important plugin events (e.g., weekly summaries sent, table maintenance).', 'we-spamfighter'),
            )
        );

        // Privacy section (DSGVO).
        add_settings_section(
            'we_spamfighter_privacy',
            __('Privacy / GDPR', 'we-spamfighter'),
            array($this, 'render_privacy_section'),
            'we-spamfighter'
        );

        add_settings_field(
            'privacy_page_mode',
            __('Privacy passage on privacy policy page', 'we-spamfighter'),
            array($this, 'render_select_field'),
            'we-spamfighter',
            'we_spamfighter_privacy',
            array(
                'field_id'    => 'privacy_page_mode',
                'options'     => array(
                    'filter'  => __('Filter: Append automatically to privacy page', 'we-spamfighter'),
                    'manual'  => __('Manual: Use shortcode [we_spamfighter_privacy] or block', 'we-spamfighter'),
                    'none'    => __('None: Do not add (at your own risk)', 'we-spamfighter'),
                ),
                'description' => __('How to include the privacy passage. Only active when OpenAI is enabled.', 'we-spamfighter'),
                'default'     => 'filter',
            )
        );

        add_settings_field(
            'form_notice_enabled',
            __('Form notice', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_privacy',
            array(
                'field_id'    => 'form_notice_enabled',
                'description' => __('Show notice at comment and CF7 forms when OpenAI is active.', 'we-spamfighter'),
                'default'     => true,
            )
        );
    }

    /**
     * Render general section.
     */
    public function render_general_section()
    {
        echo esc_html__('Configure general spam protection settings.', 'we-spamfighter');
    }

    /**
     * Render privacy section.
     */
    public function render_privacy_section()
    {
        echo '<p>' . esc_html__('Privacy-related options for OpenAI usage. These settings only take effect when OpenAI is enabled and an API key is configured.', 'we-spamfighter') . '</p>';
    }

    /**
     * Render OpenAI section.
     */
    public function render_openai_section()
    {
        if (AiSpamDetector::is_wp_client_available()) {
            echo esc_html__('Configure AI spam detection. You can use WordPress Connectors (recommended on WordPress 7.0+) or a direct OpenAI API key in this plugin.', 'we-spamfighter');
            return;
        }

        echo esc_html__('Configure AI spam detection using a direct OpenAI API key. WordPress Connectors require WordPress 7.0 or newer.', 'we-spamfighter');
    }

    /**
     * Render AI backend select field.
     *
     * @param array $args Field arguments.
     */
    public function render_ai_backend_field($args)
    {
        $settings = get_option($this->option_name, array());
        $value    = isset($settings['ai_backend']) ? (string) $settings['ai_backend'] : 'direct';

        $options = array(
            'direct' => __('Direct OpenAI API (plugin API key)', 'we-spamfighter'),
        );

        if (AiSpamDetector::is_wp_client_available()) {
            $options['wp_connectors'] = __('WordPress Connectors (Settings → Connectors)', 'we-spamfighter');
        }

        if (! isset($options[$value])) {
            $value = 'direct';
        }

        printf(
            '<select name="%s[%s]" id="%s">',
            esc_attr($this->option_name),
            esc_attr($args['field_id']),
            esc_attr($args['field_id'])
        );

        foreach ($options as $option_value => $option_label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                selected($value, $option_value, false),
                esc_html($option_label)
            );
        }

        echo '</select>';

        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Render AI provider (connector) select field.
     *
     * @param array $args Field arguments.
     */
    public function render_ai_provider_field($args)
    {
        $settings  = get_option($this->option_name, array());
        $value     = isset($settings['ai_provider']) ? (string) $settings['ai_provider'] : '';
        $providers = AiSpamDetector::get_available_ai_providers();

        printf(
            '<select name="%s[%s]" id="%s">',
            esc_attr($this->option_name),
            esc_attr($args['field_id']),
            esc_attr($args['field_id'])
        );

        echo '<option value="">' . esc_html__('— Select provider —', 'we-spamfighter') . '</option>';

        foreach ($providers as $provider_id => $provider_name) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($provider_id),
                selected($value, $provider_id, false),
                esc_html($provider_name)
            );
        }

        echo '</select>';

        if ($value !== '' && ! isset($providers[$value])) {
            echo '<p class="description">' . esc_html__('The previously selected provider is no longer connected. Choose a connected provider or configure one under Settings → Connectors.', 'we-spamfighter') . '</p>';
        }

        if (empty($providers)) {
            echo '<p class="description">' . esc_html__('No connected AI connectors found. Configure an AI provider under Settings → Connectors.', 'we-spamfighter') . '</p>';
        } elseif (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }

        $connectors_url = admin_url('options-connectors.php');
        printf(
            '<p class="description"><a href="%s">%s</a></p>',
            esc_url($connectors_url),
            esc_html__('Manage connectors in WordPress', 'we-spamfighter')
        );
    }

    /**
     * Render heuristic section.
     */
    public function render_heuristic_section()
    {
        echo esc_html__('Configure local heuristic spam detection (works without OpenAI).', 'we-spamfighter');
?>
        <div style="margin-top: 15px;">
            <button type="button" id="we-spamfighter-enable-all-checks" class="button button-secondary" style="margin-right: 10px;">
                <?php esc_html_e('Enable All Checks', 'we-spamfighter'); ?>
            </button>
            <button type="button" id="we-spamfighter-disable-all-checks" class="button button-secondary">
                <?php esc_html_e('Disable All Checks', 'we-spamfighter'); ?>
            </button>
        </div>
        <script>
            (function() {
                var enableAllBtn = document.getElementById('we-spamfighter-enable-all-checks');
                var disableAllBtn = document.getElementById('we-spamfighter-disable-all-checks');

                if (enableAllBtn && disableAllBtn) {
                    // Find all heuristic check checkboxes by their name attribute.
                    var getHeuristicCheckboxes = function() {
                        var checkboxes = document.querySelectorAll('input[type="checkbox"][name^="we_spamfighter_settings[enable_"][name$="_check]"]');
                        return Array.prototype.slice.call(checkboxes);
                    };

                    enableAllBtn.addEventListener('click', function() {
                        var checkboxes = getHeuristicCheckboxes();
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = true;
                        });
                    });

                    disableAllBtn.addEventListener('click', function() {
                        var checkboxes = getHeuristicCheckboxes();
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = false;
                        });
                    });
                }
            })();
        </script>
    <?php
    }

    /**
     * Render notifications section.
     */
    public function render_notifications_section()
    {
        echo esc_html__('Configure email notifications for spam detections.', 'we-spamfighter');
    }

    /**
     * Render maintenance section.
     */
    public function render_maintenance_section()
    {
        echo esc_html__('Configure maintenance and cleanup settings.', 'we-spamfighter');

        // Show cron job status for debugging email notifications.
        $daily_next = wp_next_scheduled('we_spamfighter_daily_summary');
        $weekly_next = wp_next_scheduled('we_spamfighter_weekly_summary');
        $settings = get_option('we_spamfighter_settings', array());
        $notification_type = $settings['notification_type'] ?? 'none';

        if ('daily' === $notification_type || 'weekly' === $notification_type) {
            echo '<div class="notice notice-info inline" style="margin-top: 15px; padding: 12px;">';
            echo '<p><strong>' . esc_html__('Email Notification Status:', 'we-spamfighter') . '</strong></p>';

            if ('daily' === $notification_type) {
                if ($daily_next) {
                    $timezone = wp_timezone();
                    $next_run = new \DateTime('@' . $daily_next);
                    $next_run->setTimezone($timezone);
                    echo '<p>' . sprintf(
                        /* translators: %s: Next scheduled time */
                        esc_html__('Daily summary: Next scheduled for %s', 'we-spamfighter'),
                        '<strong>' . esc_html($next_run->format(get_option('date_format') . ' ' . get_option('time_format'))) . '</strong>'
                    ) . '</p>';
                } else {
                    echo '<p style="color: #d63638;"><strong>' . esc_html__('Daily summary: NOT SCHEDULED - Please save settings to schedule.', 'we-spamfighter') . '</strong></p>';
                }
            }

            if ('weekly' === $notification_type) {
                if ($weekly_next) {
                    $timezone = wp_timezone();
                    $next_run = new \DateTime('@' . $weekly_next);
                    $next_run->setTimezone($timezone);
                    echo '<p>' . sprintf(
                        /* translators: %s: Next scheduled time */
                        esc_html__('Weekly summary: Next scheduled for %s', 'we-spamfighter'),
                        '<strong>' . esc_html($next_run->format(get_option('date_format') . ' ' . get_option('time_format'))) . '</strong>'
                    ) . '</p>';
                } else {
                    echo '<p style="color: #d63638;"><strong>' . esc_html__('Weekly summary: NOT SCHEDULED - Please save settings to schedule.', 'we-spamfighter') . '</strong></p>';
                }
            }

            // Check if WordPress cron is disabled.
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- DISABLE_WP_CRON is a WordPress core constant.
            $wp_cron_disabled = defined('DISABLE_WP_CRON') && constant('DISABLE_WP_CRON');
            if ($wp_cron_disabled) {
                echo '<p style="color: #d63638;"><strong>' . esc_html__('WARNING: WordPress cron is disabled. Email notifications will not work unless you use a real cron job.', 'we-spamfighter') . '</strong></p>';
            }

            // Show notification email.
            $notification_email = $settings['notification_email'] ?? get_option('admin_email');
            echo '<p>' . sprintf(
                /* translators: %s: Email address */
                esc_html__('Notification email: %s', 'we-spamfighter'),
                '<strong>' . esc_html($notification_email) . '</strong>'
            ) . '</p>';

            // Suggest checking Activity Log if enabled.
            if (!empty($settings['activity_log_enabled'])) {
                echo '<p>' . sprintf(
                    /* translators: %s: Link to Activity Log */
                    esc_html__('Check the %s for email delivery logs and debug information.', 'we-spamfighter'),
                    '<a href="' . esc_url(admin_url('admin.php?page=we-spamfighter-activity-log')) . '">' . esc_html__('Activity Log', 'we-spamfighter') . '</a>'
                ) . '</p>';
            }

            echo '</div>';
        }
    }

    /**
     * Render checkbox field with toggle switch.
     *
     * @param array $args Field arguments.
     */
    public function render_checkbox_field($args)
    {
        $settings = get_option($this->option_name, array());
        $default  = isset($args['default']) ? (bool) $args['default'] : false;
        $value    = isset($settings[$args['field_id']]) ? $settings[$args['field_id']] : $default;
        $class    = isset($args['class']) ? esc_attr($args['class']) : '';
        $field_id = esc_attr($args['field_id']);
        $field_name = esc_attr($this->option_name . '[' . $field_id . ']');
        $checked = checked($value, true, false);
        $label_text = isset($args['description']) ? wp_kses_post($args['description']) : '';

    ?>
        <div class="we-toggle-wrapper <?php echo $class; ?>">
            <label class="we-toggle-switch">
                <input type="checkbox" name="<?php echo $field_name; ?>" value="1" <?php echo $checked; ?> />
                <span class="we-toggle-slider"></span>
            </label>
            <?php if ($label_text) : ?>
                <span class="we-toggle-label"><?php echo $label_text; ?></span>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * Render text field.
     *
     * @param array $args Field arguments.
     */
    public function render_text_field($args)
    {
        $settings = get_option($this->option_name, array());
        $value    = isset($settings[$args['field_id']]) ? $settings[$args['field_id']] : '';
        $type     = isset($args['type']) ? $args['type'] : 'text';

        printf(
            '<input type="%s" name="%s[%s]" value="%s" class="regular-text" />',
            esc_attr($type),
            esc_attr($this->option_name),
            esc_attr($args['field_id']),
            esc_attr($value)
        );

        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', wp_kses_post($args['description']));
        }
    }

    /**
     * Render number field.
     *
     * @param array $args Field arguments.
     */
    public function render_number_field($args)
    {
        $settings = get_option($this->option_name, array());
        $value    = isset($settings[$args['field_id']]) ? $settings[$args['field_id']] : '';

        printf(
            '<input type="number" name="%s[%s]" value="%s" min="%s" max="%s" step="%s" />',
            esc_attr($this->option_name),
            esc_attr($args['field_id']),
            esc_attr($value),
            esc_attr($args['min']),
            esc_attr($args['max']),
            esc_attr($args['step'])
        );

        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Render textarea field.
     *
     * @param array $args Field arguments.
     */
    public function render_textarea_field($args)
    {
        $settings = get_option($this->option_name, array());
        $value    = isset($settings[$args['field_id']]) ? $settings[$args['field_id']] : '';
        $rows     = isset($args['rows']) ? absint($args['rows']) : 3;
        $cols     = isset($args['cols']) ? absint($args['cols']) : 50;

        printf(
            '<textarea name="%s[%s]" rows="%d" cols="%d" class="large-text">%s</textarea>',
            esc_attr($this->option_name),
            esc_attr($args['field_id']),
            esc_attr($rows),
            esc_attr($cols),
            esc_textarea($value)
        );

        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', wp_kses_post($args['description']));
        }
    }

    /**
     * Render select field.
     *
     * @param array $args Field arguments.
     */
    public function render_select_field($args)
    {
        $settings = get_option($this->option_name, array());
        $default  = isset($args['default']) ? $args['default'] : '';
        $value    = isset($settings[$args['field_id']]) ? $settings[$args['field_id']] : $default;

        printf(
            '<select name="%s[%s]">',
            esc_attr($this->option_name),
            esc_attr($args['field_id'])
        );

        foreach ($args['options'] as $option_value => $option_label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                selected($value, $option_value, false),
                esc_html($option_label)
            );
        }

        echo '</select>';

        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Render settings page with tabs.
     */
    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Define tabs.
        $tabs = array(
            'general' => array(
                'title' => __('General', 'we-spamfighter'),
                'sections' => array('we_spamfighter_general'),
            ),
            'heuristic' => array(
                'title' => __('Heuristic Detection', 'we-spamfighter'),
                'sections' => array('we_spamfighter_heuristic'),
            ),
            'openai' => array(
                'title' => __('AI Detection', 'we-spamfighter'),
                'sections' => array('we_spamfighter_openai'),
            ),
            'notifications' => array(
                'title' => __('Notifications', 'we-spamfighter'),
                'sections' => array('we_spamfighter_notifications'),
            ),
            'maintenance' => array(
                'title' => __('Maintenance', 'we-spamfighter'),
                'sections' => array('we_spamfighter_maintenance'),
            ),
            'privacy' => array(
                'title' => __('Privacy', 'we-spamfighter'),
                'sections' => array('we_spamfighter_privacy'),
            ),
        );

        // Get active tab from URL or default to first.
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';
        if (! isset($tabs[$active_tab])) {
            $active_tab = 'general';
        }

    ?>
        <div class="wrap we-spamfighter-settings-wrap">
            <h1><?php esc_html_e('WE Spamfighterin Settings', 'we-spamfighter'); ?></h1>

            <?php settings_errors(); ?>

            <nav class="we-settings-nav-tabs">
                <?php foreach ($tabs as $tab_id => $tab) : ?>
                    <a href="?page=we-spamfighter-settings&tab=<?php echo esc_attr($tab_id); ?>"
                        class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab['title']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="options.php">
                <?php
                settings_fields('we_spamfighter_settings_group');
                ?>

                <?php foreach ($tabs as $tab_id => $tab) : ?>
                    <?php
                    // Skip activity-log tab in form - it's rendered separately below.
                    if ($tab_id === 'activity-log') {
                        continue;
                    }
                    ?>
                    <div class="we-settings-tab-content <?php echo $active_tab === $tab_id ? 'active' : ''; ?>" id="tab-<?php echo esc_attr($tab_id); ?>">
                        <?php
                        global $wp_settings_sections, $wp_settings_fields;

                        foreach ($tab['sections'] as $section_id) {
                            // Render section header.
                            if (isset($wp_settings_sections['we-spamfighter'][$section_id])) {
                                $section = $wp_settings_sections['we-spamfighter'][$section_id];
                                if (! empty($section['title'])) {
                                    echo '<h2 class="we-settings-section-title">' . esc_html($section['title']) . '</h2>';
                                }
                                if (! empty($section['callback']) && is_callable($section['callback'])) {
                                    echo '<div class="we-settings-section-description">';
                                    call_user_func($section['callback'], $section);
                                    echo '</div>';
                                }
                            }

                            // Render section fields.
                            if (isset($wp_settings_fields['we-spamfighter'][$section_id])) {
                                echo '<table class="form-table" role="presentation">';
                                foreach ($wp_settings_fields['we-spamfighter'][$section_id] as $field_id => $field) {
                                    echo '<tr>';
                                    if (! empty($field['args']['label_for'])) {
                                        echo '<th scope="row"><label for="' . esc_attr($field['args']['label_for']) . '">' . esc_html($field['title']) . '</label></th>';
                                    } else {
                                        echo '<th scope="row">' . esc_html($field['title']) . '</th>';
                                    }
                                    echo '<td>';
                                    call_user_func($field['callback'], $field['args']);
                                    echo '</td>';
                                    echo '</tr>';
                                }
                                echo '</table>';
                            }
                        }
                        ?>
                    </div>
                <?php endforeach; ?>

                <div class="we-settings-submit-wrap">
                    <?php submit_button(); ?>
                </div>
            </form>

            <?php if ($active_tab === 'openai') : ?>
                <div class="we-test-api-section">
                    <h2><?php esc_html_e('Test AI Connection', 'we-spamfighter'); ?></h2>
                    <p>
                        <?php esc_html_e('Test your configured AI connection (WordPress Connectors or direct OpenAI API).', 'we-spamfighter'); ?>
                    </p>
                    <button type="button" id="we-test-api-btn" class="button button-secondary">
                        <?php esc_html_e('Test Connection', 'we-spamfighter'); ?>
                    </button>
                    <span id="we-test-api-result" style="margin-left:15px;"></span>
                </div>
            <?php endif; ?>

            <?php
            // Show Clear Activity Log button in Maintenance tab if activity log is enabled.
            $settings = get_option('we_spamfighter_settings', array());
            $activity_log_enabled = isset($settings['activity_log_enabled']) && $settings['activity_log_enabled'];
            if ($active_tab === 'maintenance' && $activity_log_enabled && class_exists('\WeSpamfighter\Core\ActivityLog')) :
                $activity_log = \WeSpamfighter\Core\ActivityLog::get_instance();
                $log_count = $activity_log->get_count();

                // Handle clear action if requested.
                if (isset($_GET['clear_activity_log']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'clear_activity_log')) {
                    $activity_log->clear();
                    wp_redirect(add_query_arg(array('page' => 'we-spamfighter-settings', 'tab' => 'maintenance', 'activity_log_cleared' => '1'), admin_url('admin.php')));
                    exit;
                }
            ?>
                <div class="we-clear-activity-log-section" style="margin-top: 30px;">
                    <h2><?php esc_html_e('Activity Log', 'we-spamfighter'); ?></h2>
                    <?php if (isset($_GET['activity_log_cleared'])) : ?>
                        <div class="notice notice-success inline is-dismissible">
                            <p><?php esc_html_e('Activity log cleared successfully.', 'we-spamfighter'); ?></p>
                        </div>
                    <?php endif; ?>
                    <p>
                        <?php
                        printf(
                            /* translators: %d: Number of log entries */
                            esc_html__('Currently %d entries in the activity log.', 'we-spamfighter'),
                            $log_count
                        );
                        ?>
                    </p>
                    <?php if ($log_count > 0) : ?>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => 'we-spamfighter-settings', 'tab' => 'maintenance', 'clear_activity_log' => '1'), admin_url('admin.php')), 'clear_activity_log')); ?>"
                            class="button button-secondary"
                            onclick="return confirm('<?php echo esc_js(__('Are you sure you want to clear the activity log? This action cannot be undone.', 'we-spamfighter')); ?>');">
                            <?php esc_html_e('Clear Activity Log', 'we-spamfighter'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Activity Log page.
     */
    public function render_activity_log_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = get_option('we_spamfighter_settings', array());
        $activity_log_enabled = isset($settings['activity_log_enabled']) && $settings['activity_log_enabled'];

        // If activity log is disabled, show message.
        if (! $activity_log_enabled || ! class_exists('\WeSpamfighter\Core\ActivityLog')) {
        ?>
            <div class="wrap">
                <h1><?php esc_html_e('Activity Log', 'we-spamfighter'); ?></h1>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e('Activity Log is not enabled. Please enable it in Settings → Maintenance tab.', 'we-spamfighter'); ?>
                    </p>
                </div>
            </div>
        <?php
            return;
        }

        $activity_log = \WeSpamfighter\Core\ActivityLog::get_instance();

        // Handle clear action if requested.
        if (isset($_GET['clear_activity_log']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'clear_activity_log')) {
            $activity_log->clear();
            wp_redirect(add_query_arg(array('page' => 'we-spamfighter-activity-log', 'activity_log_cleared' => '1'), admin_url('admin.php')));
            exit;
        }

        $log_entries = $activity_log->get_entries(50);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Activity Log', 'we-spamfighter'); ?></h1>

            <?php if (isset($_GET['activity_log_cleared'])) : ?>
                <div class="notice notice-success inline is-dismissible">
                    <p><?php esc_html_e('Activity log cleared successfully.', 'we-spamfighter'); ?></p>
                </div>
            <?php endif; ?>

            <div class="we-activity-log-section" style="margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <p class="description">
                            <?php esc_html_e('Recent plugin activities and events.', 'we-spamfighter'); ?>
                        </p>
                    </div>
                    <?php if (! empty($log_entries)) : ?>
                        <div>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => 'we-spamfighter-activity-log', 'clear_activity_log' => '1'), admin_url('admin.php')), 'clear_activity_log')); ?>"
                                class="button button-secondary"
                                onclick="return confirm('<?php echo esc_js(__('Are you sure you want to clear the activity log? This action cannot be undone.', 'we-spamfighter')); ?>');">
                                <?php esc_html_e('Clear Activity Log', 'we-spamfighter'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (! empty($log_entries)) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 160px;"><?php esc_html_e('Date/Time', 'we-spamfighter'); ?></th>
                                <th style="width: 200px;"><?php esc_html_e('Event', 'we-spamfighter'); ?></th>
                                <th><?php esc_html_e('Message', 'we-spamfighter'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($log_entries as $entry) : ?>
                                <tr>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry['timestamp']))); ?></td>
                                    <td><code><?php echo esc_html($entry['event_type']); ?></code></td>
                                    <td><?php echo esc_html($entry['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <div class="notice notice-info inline">
                        <p>
                            <strong><?php esc_html_e('No activity log entries yet.', 'we-spamfighter'); ?></strong>
                        </p>
                        <p>
                            <?php esc_html_e('Activity log entries will appear here once plugin events occur, such as:', 'we-spamfighter'); ?>
                        </p>
                        <ul style="list-style-type: disc; margin-left: 20px;">
                            <li><?php esc_html_e('Weekly spam summary emails sent', 'we-spamfighter'); ?></li>
                            <li><?php esc_html_e('Daily spam summary emails sent', 'we-spamfighter'); ?></li>
                            <li><?php esc_html_e('Table maintenance completed', 'we-spamfighter'); ?></li>
                            <li><?php esc_html_e('Old logs cleaned', 'we-spamfighter'); ?></li>
                        </ul>
                        <p>
                            <?php esc_html_e('The log will start recording events after the next scheduled task runs.', 'we-spamfighter'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
<?php
    }

    /**
     * AJAX handler for clearing activity log.
     */
    public function ajax_clear_activity_log()
    {
        check_ajax_referer('we_spamfighter_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'we-spamfighter')));
        }

        if (! class_exists('\WeSpamfighter\Core\ActivityLog')) {
            wp_send_json_error(array('message' => __('Activity Log class not found', 'we-spamfighter')));
        }

        $activity_log = \WeSpamfighter\Core\ActivityLog::get_instance();
        $count_before = $activity_log->get_count();
        $result = $activity_log->clear();

        if ($result) {
            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: %d: Number of entries deleted */
                    __('Activity log cleared successfully. %d entries deleted.', 'we-spamfighter'),
                    $count_before
                ),
                'deleted_count' => $count_before,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to clear activity log', 'we-spamfighter')));
        }
    }

    /**
     * AJAX handler for testing OpenAI connection.
     */
    public function ajax_test_connection()
    {
        check_ajax_referer('we_spamfighter_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $settings = get_option($this->option_name, array());

        if (! AiSpamDetector::is_enabled($settings)) {
            if (AiSpamDetector::uses_wp_connectors($settings)) {
                wp_send_json_error(array('message' => __('Please enable AI detection and select a configured WordPress connector.', 'we-spamfighter')));
            }
            wp_send_json_error(array('message' => __('Please enable AI detection and configure your OpenAI API key first.', 'we-spamfighter')));
        }

        try {
            $result = AiSpamDetector::analyze(
                array(
                    'message' => 'This is a test message to verify the API connection.',
                ),
                $settings,
                'en'
            );

            if (! empty($result['error'])) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        /* translators: %s: error reason */
                        __('AI connection test failed: %s', 'we-spamfighter'),
                        $result['reason'] ?? __('Unknown error', 'we-spamfighter')
                    ),
                ));
            }

            $backend_label = AiSpamDetector::uses_wp_connectors($settings)
                ? sprintf(
                    /* translators: %s: connector ID */
                    __('WordPress Connectors (%s)', 'we-spamfighter'),
                    $settings['ai_provider'] ?? ''
                )
                : __('Direct OpenAI API', 'we-spamfighter');

            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: 1: backend label, 2: spam score */
                    __('%1$s connection successful! Test spam score: %2$.2f', 'we-spamfighter'),
                    $backend_label,
                    (float) ($result['score'] ?? 0)
                ),
                'score'   => $result['score'] ?? 0,
                'details' => $result,
            ));
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => 'Exception: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Sanitize settings.
     *
     * @param array $input Input settings.
     * @return array Sanitized settings.
     */
    public function sanitize_settings($input)
    {
        // Get existing settings to preserve values that aren't being updated.
        $existing = get_option($this->option_name, array());
        $sanitized = array();

        // Reload integrations after saving (using action hook).
        add_action('update_option_' . $this->option_name, array($this, 'reload_integrations'), 10, 2);

        // Boolean fields.
        $boolean_fields = array(
            'cf7_enabled',
            'comments_enabled',
            'openai_enabled',
            'auto_mark_pingbacks_spam',
            'mark_different_language_spam',
            'heuristic_enabled',
            'enable_link_check',
            'enable_character_check',
            'enable_phrase_check',
            'enable_email_check',
            'enable_referrer_check',
            'enable_user_agent_check',
            'enable_content_length_check',
            'enable_mixed_script_check',
            'enable_unicode_check',
            'enable_numbers_letters_only_check',
            'enable_ip_in_content_check',
            'enable_repeated_multilingual_check',
            'enable_cf7_fieldtype_check',
            'keep_data_on_uninstall',
            'github_updates_enabled',
            'activity_log_enabled',
        );

        // Check if heuristic is being enabled (was disabled, now enabled).
        $heuristic_was_disabled = empty($existing['heuristic_enabled']);
        $heuristic_now_enabled = isset($input['heuristic_enabled']) && !empty($input['heuristic_enabled']);

        // Define heuristic check fields.
        $heuristic_check_fields = array(
            'enable_link_check',
            'enable_character_check',
            'enable_phrase_check',
            'enable_email_check',
            'enable_referrer_check',
            'enable_user_agent_check',
            'enable_content_length_check',
            'enable_mixed_script_check',
            'enable_unicode_check',
            'enable_numbers_letters_only_check',
            'enable_ip_in_content_check',
        );

        // First, process heuristic_enabled to know its final state.
        $heuristic_final_state = isset($input['heuristic_enabled']) ? (bool) $input['heuristic_enabled'] : false;

        foreach ($boolean_fields as $field) {
            if ('enable_cf7_fieldtype_check' === $field) {
                $sanitized[ $field ] = isset($input[ $field ])
                    ? (bool) $input[ $field ]
                    : ( isset($existing[ $field ]) ? (bool) $existing[ $field ] : true );
                continue;
            }

            if ($field === 'heuristic_enabled') {
                $sanitized[$field] = $heuristic_final_state;
                continue;
            }

            // If this is a heuristic check field (enable_*_check).
            if (in_array($field, $heuristic_check_fields, true)) {
                // If heuristic is being enabled for the first time, activate all checks by default.
                if ($heuristic_was_disabled && $heuristic_now_enabled) {
                    // Only set to true if not explicitly provided in input (first activation).
                    // If provided in input, use that value (allows user to customize on first activation).
                    if (isset($input[$field])) {
                        $sanitized[$field] = (bool) $input[$field];
                    } else {
                        // First activation: default to true.
                        $sanitized[$field] = true;
                    }
                } else {
                    // Heuristic was already enabled or is being disabled.
                    // Use the input value if provided, otherwise unchecked = false (if heuristic enabled).
                    if (isset($input[$field])) {
                        $sanitized[$field] = (bool) $input[$field];
                    } else {
                        // Checkbox not in form = unchecked = false (only if heuristic is enabled).
                        // If heuristic is disabled, this will be handled below.
                        if ($heuristic_final_state) {
                            // Heuristic is enabled: unchecked checkbox means false.
                            $sanitized[$field] = false;
                        } else {
                            // Heuristic is disabled: preserve existing (will be set to false below anyway).
                            $sanitized[$field] = isset($existing[$field]) ? (bool) $existing[$field] : false;
                        }
                    }
                }
            } else {
                // For all other boolean fields, use standard logic.
                $sanitized[$field] = isset($input[$field]) ? (bool) $input[$field] : false;
            }
        }

        // If heuristic is disabled, disable all heuristic checks as well.
        if (empty($sanitized['heuristic_enabled'])) {
            foreach ($heuristic_check_fields as $check_field) {
                $sanitized[$check_field] = false;
            }
        }

        // Text fields - preserve existing value if not provided.
        if (isset($input['openai_api_key'])) {
            $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key']);
        } else {
            $sanitized['openai_api_key'] = isset($existing['openai_api_key']) ? $existing['openai_api_key'] : '';
        }

        $allowed_backends = array('direct');
        if (AiSpamDetector::is_wp_client_available()) {
            $allowed_backends[] = 'wp_connectors';
        }

        if (isset($input['ai_backend'])) {
            $backend = sanitize_text_field($input['ai_backend']);
            $sanitized['ai_backend'] = in_array($backend, $allowed_backends, true) ? $backend : 'direct';
        } else {
            $sanitized['ai_backend'] = isset($existing['ai_backend'])
                ? (string) $existing['ai_backend']
                : (AiSpamDetector::is_wp_client_available() ? 'wp_connectors' : 'direct');
        }

        if (! in_array($sanitized['ai_backend'], $allowed_backends, true)) {
            $sanitized['ai_backend'] = 'direct';
        }

        if (isset($input['ai_provider'])) {
            $provider  = sanitize_key($input['ai_provider']);
            $providers = AiSpamDetector::get_available_ai_providers();
            $sanitized['ai_provider'] = ($provider !== '' && isset($providers[$provider])) ? $provider : '';
        } else {
            $sanitized['ai_provider'] = isset($existing['ai_provider']) ? sanitize_key((string) $existing['ai_provider']) : '';
            $providers = AiSpamDetector::get_available_ai_providers();
            if ($sanitized['ai_provider'] !== '' && ! isset($providers[$sanitized['ai_provider']])) {
                $sanitized['ai_provider'] = '';
            }
        }

        if (isset($input['ai_model_preference'])) {
            $sanitized['ai_model_preference'] = sanitize_text_field($input['ai_model_preference']);
        } else {
            $sanitized['ai_model_preference'] = isset($existing['ai_model_preference']) ? (string) $existing['ai_model_preference'] : '';
        }

        // Whitelist for OpenAI model - preserve existing if not provided.
        if (isset($input['openai_model'])) {
            $allowed_models = array('gpt-4o-mini', 'gpt-5-mini', 'gpt-4o', 'gpt-5', 'gpt-4-turbo', 'gpt-3.5-turbo');
            $model          = sanitize_text_field($input['openai_model']);
            $sanitized['openai_model'] = in_array($model, $allowed_models, true) ? $model : 'gpt-4o-mini';
        } else {
            $sanitized['openai_model'] = isset($existing['openai_model']) ? $existing['openai_model'] : 'gpt-4o-mini';
        }

        // Numeric fields - preserve existing if not provided.
        if (isset($input['ai_threshold'])) {
            $sanitized['ai_threshold'] = min(max(floatval($input['ai_threshold']), 0), 1);
        } else {
            $sanitized['ai_threshold'] = isset($existing['ai_threshold']) ? floatval($existing['ai_threshold']) : 0.7;
        }

        if (isset($input['language_spam_score_boost'])) {
            $sanitized['language_spam_score_boost'] = min(max(floatval($input['language_spam_score_boost']), 0.1), 1.0);
        } else {
            $sanitized['language_spam_score_boost'] = isset($existing['language_spam_score_boost']) ? floatval($existing['language_spam_score_boost']) : 0.3;
        }

        if (isset($input['heuristic_threshold'])) {
            $sanitized['heuristic_threshold'] = min(max(floatval($input['heuristic_threshold']), 0), 1);
        } else {
            $sanitized['heuristic_threshold'] = isset($existing['heuristic_threshold']) ? floatval($existing['heuristic_threshold']) : 0.6;
        }

        if (isset($input['cf7_text_line_max_length'])) {
            $sanitized['cf7_text_line_max_length'] = min(2000, max(80, intval($input['cf7_text_line_max_length'])));
        } else {
            $sanitized['cf7_text_line_max_length'] = isset($existing['cf7_text_line_max_length']) ? intval($existing['cf7_text_line_max_length']) : 400;
        }

        if (isset($input['log_retention_days'])) {
            $sanitized['log_retention_days'] = max(intval($input['log_retention_days']), 7);
        } else {
            $sanitized['log_retention_days'] = isset($existing['log_retention_days']) ? intval($existing['log_retention_days']) : 30;
        }


        // Notification email.
        if (isset($input['notification_email'])) {
            $email = sanitize_email($input['notification_email']);
            $sanitized['notification_email'] = ! empty($email) ? $email : get_option('admin_email');
        } else {
            $sanitized['notification_email'] = isset($existing['notification_email']) ? $existing['notification_email'] : get_option('admin_email');
        }

        // Spam blocked message.
        if (isset($input['spam_blocked_message'])) {
            $sanitized['spam_blocked_message'] = sanitize_textarea_field($input['spam_blocked_message']);
        } else {
            $sanitized['spam_blocked_message'] = isset($existing['spam_blocked_message']) ? $existing['spam_blocked_message'] : __('Thank you for your message.', 'we-spamfighter');
        }

        // Notification type.
        if (isset($input['notification_type'])) {
            $allowed_types = array('none', 'immediate', 'daily', 'weekly');
            $sanitized['notification_type'] = in_array($input['notification_type'], $allowed_types, true) ? $input['notification_type'] : 'none';
        } else {
            $sanitized['notification_type'] = isset($existing['notification_type']) ? $existing['notification_type'] : 'none';
        }

        // Privacy page mode.
        if (isset($input['privacy_page_mode'])) {
            $allowed_modes = array('filter', 'manual', 'none');
            $sanitized['privacy_page_mode'] = in_array($input['privacy_page_mode'], $allowed_modes, true) ? $input['privacy_page_mode'] : 'filter';
        } else {
            $sanitized['privacy_page_mode'] = isset($existing['privacy_page_mode']) ? $existing['privacy_page_mode'] : 'filter';
        }

        // Form notice enabled (default true for existing installs).
        if (isset($input['form_notice_enabled'])) {
            $sanitized['form_notice_enabled'] = !empty($input['form_notice_enabled']);
        } else {
            $sanitized['form_notice_enabled'] = isset($existing['form_notice_enabled']) ? (bool) $existing['form_notice_enabled'] : true;
        }

        return $sanitized;
    }

    /**
     * Reload integrations after settings are saved.
     *
     * @param array $old_value Old settings value.
     * @param array $value New settings value.
     * @return void
     */
    public function reload_integrations($old_value, $value)
    {
        // Clear any existing integration instances by calling load_integrations.
        // This will reload the integrations with new settings.
        if (class_exists('\WeSpamfighter\Plugin')) {
            $plugin = \WeSpamfighter\Plugin::get_instance();
            $plugin->load_integrations();

            // Ensure notification cron jobs are scheduled (in case notification_type changed).
            if (method_exists($plugin, 'ensure_notification_cron_jobs')) {
                $plugin->ensure_notification_cron_jobs();
            }
        }
    }
}
