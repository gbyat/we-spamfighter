<?php

/**
 * Admin settings page.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Admin;

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
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook Page hook.
     */
    public function enqueue_scripts($hook)
    {
        // Check if we're on the settings page.
        if (strpos($hook, 'we-spamfighter-settings') === false && strpos($hook, 'we-spamfighter') === false) {
            return;
        }

        wp_enqueue_style(
            'we-spamfighter-admin',
            WE_SPAMFIGHTER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WE_SPAMFIGHTER_VERSION
        );

        wp_enqueue_script(
            'we-spamfighter-admin',
            WE_SPAMFIGHTER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WE_SPAMFIGHTER_VERSION,
            true
        );

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

        // OpenAI section.
        add_settings_section(
            'we_spamfighter_openai',
            __('OpenAI Integration', 'we-spamfighter'),
            array($this, 'render_openai_section'),
            'we-spamfighter'
        );

        add_settings_field(
            'openai_enabled',
            __('Enable OpenAI Detection', 'we-spamfighter'),
            array($this, 'render_checkbox_field'),
            'we-spamfighter',
            'we_spamfighter_openai',
            array(
                'field_id'    => 'openai_enabled',
                'description' => __('Use OpenAI to detect spam', 'we-spamfighter'),
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
                    __('Get your API key from <a href="%s" target="_blank">OpenAI Platform</a>', 'we-spamfighter'),
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
                'description' => __('Which AI model to use for spam detection', 'we-spamfighter'),
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
    }

    /**
     * Render general section.
     */
    public function render_general_section()
    {
        echo '<p>' . esc_html__('Configure general spam protection settings.', 'we-spamfighter') . '</p>';
    }

    /**
     * Render OpenAI section.
     */
    public function render_openai_section()
    {
        echo '<p>' . esc_html__('Configure OpenAI integration for spam detection.', 'we-spamfighter') . '</p>';
    }

    /**
     * Render notifications section.
     */
    public function render_notifications_section()
    {
        echo '<p>' . esc_html__('Configure email notifications for spam detections.', 'we-spamfighter') . '</p>';
    }

    /**
     * Render maintenance section.
     */
    public function render_maintenance_section()
    {
        echo '<p>' . esc_html__('Configure maintenance and cleanup settings.', 'we-spamfighter') . '</p>';
    }

    /**
     * Render checkbox field.
     *
     * @param array $args Field arguments.
     */
    public function render_checkbox_field($args)
    {
        $settings = get_option($this->option_name, array());
        $value    = isset($settings[$args['field_id']]) ? $settings[$args['field_id']] : false;

        printf(
            '<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label>',
            esc_attr($this->option_name),
            esc_attr($args['field_id']),
            checked($value, true, false),
            isset($args['description']) ? wp_kses_post($args['description']) : ''
        );
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
     * Render select field.
     *
     * @param array $args Field arguments.
     */
    public function render_select_field($args)
    {
        $settings = get_option($this->option_name, array());
        $value    = isset($settings[$args['field_id']]) ? $settings[$args['field_id']] : '';

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
     * Render settings page.
     */
    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

?>
        <div class="wrap">
            <h1><?php esc_html_e('WE Spamfighter Settings', 'we-spamfighter'); ?></h1>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('we_spamfighter_settings_group');
                do_settings_sections('we-spamfighter');
                submit_button();
                ?>
            </form>

            <hr />

            <h2><?php esc_html_e('Test OpenAI Connection', 'we-spamfighter'); ?></h2>
            <div>
                <p>
                    <?php esc_html_e('Test your OpenAI API connection and configuration.', 'we-spamfighter'); ?>
                </p>
                <button type="button" id="we-test-api-btn" class="button button-secondary">
                    <?php esc_html_e('Test Connection', 'we-spamfighter'); ?>
                </button>
                <span id="we-test-api-result" style="margin-left:15px;"></span>
            </div>
        </div>
<?php
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
        $api_key  = $settings['openai_api_key'] ?? '';
        $model    = $settings['openai_model'] ?? 'gpt-4o-mini';

        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'Please configure your OpenAI API key first.'));
        }

        if (! class_exists('\WeSpamfighter\Detection\OpenAI')) {
            wp_send_json_error(array('message' => 'Error: OpenAI class not found.'));
            return;
        }

        try {
            $detector = new \WeSpamfighter\Detection\OpenAI($api_key, $model);
            $result = $detector->analyze(
                array(
                    'message' => 'This is a test message to verify the API connection.',
                ),
                'en'
            );

            if (isset($result['error']) && $result['error']) {
                wp_send_json_error(array(
                    'message' => sprintf('OpenAI API test failed: %s', $result['reason']),
                ));
            } else {
                wp_send_json_success(array(
                    'message' => sprintf('OpenAI API connection successful! Test spam score: %.2f', $result['score']),
                    'score'   => $result['score'],
                    'details' => $result,
                ));
            }
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
        );

        foreach ($boolean_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? (bool) $input[$field] : false;
        }

        // Text fields - preserve existing value if not provided.
        if (isset($input['openai_api_key'])) {
            $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key']);
        } else {
            $sanitized['openai_api_key'] = isset($existing['openai_api_key']) ? $existing['openai_api_key'] : '';
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

        if (isset($input['log_retention_days'])) {
            $sanitized['log_retention_days'] = max(intval($input['log_retention_days']), 7);
        } else {
            $sanitized['log_retention_days'] = isset($existing['log_retention_days']) ? intval($existing['log_retention_days']) : 30;
        }

        if (isset($input['keep_data_on_uninstall'])) {
            $sanitized['keep_data_on_uninstall'] = (bool) $input['keep_data_on_uninstall'];
        } else {
            $sanitized['keep_data_on_uninstall'] = isset($existing['keep_data_on_uninstall']) ? (bool) $existing['keep_data_on_uninstall'] : false;
        }

        // Notification email.
        if (isset($input['notification_email'])) {
            $email = sanitize_email($input['notification_email']);
            $sanitized['notification_email'] = ! empty($email) ? $email : get_option('admin_email');
        } else {
            $sanitized['notification_email'] = isset($existing['notification_email']) ? $existing['notification_email'] : get_option('admin_email');
        }

        // Notification type.
        if (isset($input['notification_type'])) {
            $allowed_types = array('none', 'immediate', 'daily', 'weekly');
            $sanitized['notification_type'] = in_array($input['notification_type'], $allowed_types, true) ? $input['notification_type'] : 'none';
        } else {
            $sanitized['notification_type'] = isset($existing['notification_type']) ? $existing['notification_type'] : 'none';
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
            \WeSpamfighter\Plugin::get_instance()->load_integrations();
        }
    }
}
