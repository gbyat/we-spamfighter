<?php

/**
 * Spam detection via WordPress AI Client (Connectors API).
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Detection;

use WeSpamfighter\Core\Logger;

/**
 * Uses wp_ai_client_prompt() and credentials from Settings → Connectors.
 */
class WpAiClientDetector
{

    /**
     * Plugin settings.
     *
     * @var array
     */
    private $settings = array();

    /**
     * Constructor.
     *
     * @param array $settings Plugin settings.
     */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Analyze content for spam.
     *
     * @param array|string $content Content data.
     * @param string       $expected_language Expected language code.
     * @return array
     */
    public function analyze($content, $expected_language = 'en')
    {
        if (! AiSpamDetector::is_wp_client_available()) {
            return array(
                'score'   => 0.0,
                'is_spam' => false,
                'reason'  => 'WordPress AI Client is not available',
                'error'   => true,
            );
        }

        $provider = sanitize_key((string) ($this->settings['ai_provider'] ?? ''));
        if ($provider === '' || ! AiSpamDetector::is_provider_usable($provider)) {
            return array(
                'score'   => 0.0,
                'is_spam' => false,
                'reason'  => 'AI provider connector is not configured',
                'error'   => true,
            );
        }

        $rate_limit_check = AiSpamDetector::check_rate_limit();
        if (empty($rate_limit_check['allowed'])) {
            Logger::get_instance()->warning(
                'AI rate limit exceeded',
                array('ip' => $rate_limit_check['ip'] ?? '')
            );
            return array(
                'score'   => 0.5,
                'is_spam' => false,
                'reason'  => 'Rate limit exceeded',
                'error'   => true,
            );
        }

        $content_text = $this->prepare_content($content);
        if ($content_text === '') {
            return array(
                'score'   => 0.0,
                'is_spam' => false,
                'reason'  => 'No content to analyze',
            );
        }

        $prompt = AiSpamDetector::build_spam_analysis_prompt($content_text, $expected_language);
        $schema = array(
            'type'       => 'object',
            'properties' => array(
                'spam_score'        => array('type' => 'number'),
                'is_spam'           => array('type' => 'boolean'),
                'reasoning'         => array('type' => 'string'),
                'confidence'        => array('type' => 'string'),
                'detected_language' => array('type' => 'string'),
            ),
            'required'   => array('spam_score', 'is_spam', 'reasoning'),
        );

        $builder = wp_ai_client_prompt($prompt)
            ->using_system_instruction('You are a spam detection expert. Analyze form submissions and provide accurate spam scores with reasoning.')
            ->using_temperature(0.3)
            ->using_max_tokens(500)
            ->using_provider($provider);

        $model_preferences = AiSpamDetector::parse_model_preference($this->settings['ai_model_preference'] ?? '');
        if (! empty($model_preferences) && method_exists($builder, 'using_model_preference')) {
            $builder = $builder->using_model_preference(...$model_preferences);
        }

        if (method_exists($builder, 'as_json_response')) {
            $builder = $builder->as_json_response($schema);
            $response = $builder->generate_text();
        } else {
            $response = $builder->generate_text();
        }

        if (is_wp_error($response)) {
            Logger::get_instance()->error(
                'WordPress AI Client call failed',
                array(
                    'provider' => $provider,
                    'error'    => $response->get_error_message(),
                    'code'     => $response->get_error_code(),
                )
            );

            return array(
                'score'    => 0.0,
                'is_spam'  => false,
                'reason'   => $response->get_error_message(),
                'error'    => true,
                'provider' => $provider,
                'backend'  => 'wp_connectors',
            );
        }

        $parsed = AiSpamDetector::parse_analysis_response((string) $response);
        $parsed['provider'] = $provider;
        $parsed['backend']  = 'wp_connectors';

        /**
         * Filters the parsed AI spam analysis result from the WordPress AI Client.
         *
         * @param array  $parsed   Parsed result.
         * @param string $provider Connector / provider ID.
         * @param array  $settings Plugin settings.
         */
        return apply_filters('we_spamfighter_wp_ai_analysis_result', $parsed, $provider, $this->settings);
    }

    /**
     * Prepare content from data (mirrors OpenAI detector).
     *
     * @param array|string $content Content data.
     * @return string
     */
    private function prepare_content($content)
    {
        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return '';
        }

        $content_parts = array();

        foreach ($content as $key => $value) {
            if (is_string($key) && strpos($key, '_') === 0) {
                continue;
            }

            if (is_array($value)) {
                $flat_values = array();
                array_walk_recursive(
                    $value,
                    static function ($item) use (&$flat_values) {
                        if (is_scalar($item) && (string) $item !== '') {
                            $flat_values[] = sanitize_text_field((string) $item);
                        }
                    }
                );
                $value = implode(', ', $flat_values);
            }

            if ($value === '' || $value === null || is_numeric($key)) {
                continue;
            }

            $content_parts[] = sanitize_text_field((string) $value);
        }

        return implode("\n", $content_parts);
    }
}
