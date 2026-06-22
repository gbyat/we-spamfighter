<?php

/**
 * AI spam detection facade (WordPress Connectors or direct OpenAI API).
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Detection;

use WeSpamfighter\Core\Logger;

/**
 * Routes spam analysis to the configured AI backend.
 */
class AiSpamDetector
{

    /**
     * Whether the WordPress AI Client is available (WordPress 7.0+).
     *
     * @return bool
     */
    public static function is_wp_client_available()
    {
        return function_exists('wp_ai_client_prompt');
    }

    /**
     * Registered AI provider connectors from WordPress (Settings → Connectors).
     *
     * @return array<string, string> Connector ID => display name.
     */
    public static function get_available_ai_providers()
    {
        if (! function_exists('wp_get_connectors')) {
            return array();
        }

        $providers = array();
        $connectors = wp_get_connectors();

        if (! is_array($connectors)) {
            return array();
        }

        foreach ($connectors as $id => $connector) {
            if (! is_string($id) || ! is_array($connector)) {
                continue;
            }
            if (($connector['type'] ?? '') !== 'ai_provider') {
                continue;
            }
            $providers[$id] = (string) ($connector['name'] ?? $id);
        }

        asort($providers);

        return $providers;
    }

    /**
     * Whether AI detection is enabled and configured for the active backend.
     *
     * @param array $settings Plugin settings.
     * @return bool
     */
    public static function is_enabled(array $settings)
    {
        if (empty($settings['openai_enabled'])) {
            return false;
        }

        if (self::uses_wp_connectors($settings)) {
            $provider = sanitize_key((string) ($settings['ai_provider'] ?? ''));
            return $provider !== '' && self::is_provider_usable($provider);
        }

        if (defined('WE_SPAMFIGHTER_OPENAI_KEY') && ! empty(WE_SPAMFIGHTER_OPENAI_KEY)) {
            return true;
        }

        return ! empty($settings['openai_api_key']);
    }

    /**
     * Active backend slug.
     *
     * @param array $settings Plugin settings.
     * @return string `wp_connectors` or `direct`.
     */
    public static function get_backend(array $settings)
    {
        $backend = (string) ($settings['ai_backend'] ?? 'direct');
        if ('wp_connectors' === $backend && self::is_wp_client_available()) {
            return 'wp_connectors';
        }
        return 'direct';
    }

    /**
     * @param array $settings Plugin settings.
     * @return bool
     */
    public static function uses_wp_connectors(array $settings)
    {
        return 'wp_connectors' === self::get_backend($settings);
    }

    /**
     * Check whether a connector/provider can run text generation.
     *
     * @param string $provider Connector / provider ID (e.g. mistral, openai).
     * @return bool
     */
    public static function is_provider_usable($provider)
    {
        $provider = sanitize_key((string) $provider);
        if ($provider === '') {
            return false;
        }

        if (! function_exists('wp_is_connector_registered') || ! wp_is_connector_registered($provider)) {
            return false;
        }

        if (! self::is_wp_client_available()) {
            return false;
        }

        $builder = wp_ai_client_prompt('test');
        if (! method_exists($builder, 'using_provider')) {
            return true;
        }

        $builder = $builder->using_provider($provider);
        if (method_exists($builder, 'is_supported_for_text_generation')) {
            return (bool) $builder->is_supported_for_text_generation();
        }

        return true;
    }

    /**
     * Analyze content for spam using the configured backend.
     *
     * @param array|string $content Content to analyze.
     * @param array        $settings Plugin settings.
     * @param string       $expected_language Expected language code.
     * @return array
     */
    public static function analyze($content, array $settings, $expected_language = 'en')
    {
        if (self::uses_wp_connectors($settings)) {
            $detector = new WpAiClientDetector($settings);
            return $detector->analyze($content, $expected_language);
        }

        $api_key = $settings['openai_api_key'] ?? '';
        $model   = $settings['openai_model'] ?? 'gpt-4o-mini';
        $detector = new OpenAI($api_key, $model);

        return $detector->analyze($content, $expected_language);
    }

    /**
     * Parse comma-separated model preference list.
     *
     * @param string $preference Raw setting value.
     * @return array<int, string>
     */
    public static function parse_model_preference($preference)
    {
        if (! is_string($preference) || trim($preference) === '') {
            return array();
        }

        $models = array_map('trim', explode(',', $preference));
        $models = array_filter(
            $models,
            static function ($model) {
                return is_string($model) && $model !== '' && preg_match('/^[a-zA-Z0-9._-]+$/', $model);
            }
        );

        return array_values($models);
    }

    /**
     * Shared spam-analysis prompt for all AI backends.
     *
     * @param string $content_text Flattened submission text.
     * @param string $expected_language Expected language code.
     * @return string
     */
    public static function build_spam_analysis_prompt($content_text, $expected_language)
    {
        $language_names = array(
            'en' => 'English',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'cs' => 'Czech',
            'ru' => 'Russian',
            'tr' => 'Turkish',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ko' => 'Korean',
        );

        $language_name = $language_names[$expected_language] ?? 'English';

        return sprintf(
            'Analyze the following form submission and determine if it is spam. Consider these factors:

1. Is the content coherent and meaningful?
2. Does it appear to be generated by AI or a bot (repetitive patterns, unnatural phrasing)?
3. Does it contain suspicious links or promotional content?
4. Is the language appropriate and consistent (expected language: %s)?
5. Does it seem like a genuine inquiry or message?
6. Check for common spam patterns (excessive keywords, weird character usage, SEO spam)

Form submission content:
---
%s
---

Respond ONLY with a JSON object in this exact format:
{
  "spam_score": 0.0,
  "is_spam": false,
  "reasoning": "Brief explanation",
  "confidence": "high/medium/low",
  "detected_language": "language code"
}

spam_score should be between 0.0 (definitely not spam) and 1.0 (definitely spam).
is_spam should be true if spam_score >= 0.7',
            esc_html($language_name),
            esc_html($content_text)
        );
    }

    /**
     * Normalize a model JSON/text response into the plugin result shape.
     *
     * @param string $content Raw model output.
     * @return array
     */
    public static function parse_analysis_response($content)
    {
        if (! is_string($content) || trim($content) === '') {
            return array(
                'score'   => 0.0,
                'is_spam' => false,
                'reason'  => 'Empty AI response',
                'error'   => true,
            );
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded) && isset($decoded['spam_score'])) {
            return self::format_parsed_result($decoded, $content);
        }

        preg_match('/\{[\s\S]*\}/', $content, $matches);
        if (empty($matches[0])) {
            Logger::get_instance()->warning(
                'Could not parse AI response',
                array('response' => $content)
            );

            return array(
                'score'   => 0.0,
                'is_spam' => false,
                'reason'  => 'Could not parse AI response',
                'error'   => true,
            );
        }

        $result = json_decode($matches[0], true);
        if (! is_array($result)) {
            return array(
                'score'   => 0.0,
                'is_spam' => false,
                'reason'  => 'Invalid JSON in AI response',
                'error'   => true,
            );
        }

        return self::format_parsed_result($result, $content);
    }

    /**
     * @param array  $result Decoded JSON result.
     * @param string $raw Raw response text.
     * @return array
     */
    private static function format_parsed_result(array $result, $raw)
    {
        return array(
            'score'             => (float) ($result['spam_score'] ?? 0),
            'is_spam'           => (bool) ($result['is_spam'] ?? false),
            'reason'            => sanitize_text_field($result['reasoning'] ?? ''),
            'confidence'        => sanitize_text_field($result['confidence'] ?? 'low'),
            'detected_language' => sanitize_text_field($result['detected_language'] ?? 'unknown'),
            'raw_response'      => $raw,
        );
    }

    /**
     * Rate limit check shared by AI backends.
     *
     * @return array{allowed:bool,ip:string,count?:int,limit?:int}
     */
    public static function check_rate_limit()
    {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        $transient_key   = 'we_spam_rate_limit_' . md5($ip);
        $current_count   = get_transient($transient_key);
        $max_requests    = (int) apply_filters('we_spamfighter_rate_limit_max', 60);

        if (false === $current_count) {
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
            return array(
                'allowed' => true,
                'ip'      => $ip,
                'count'   => 1,
            );
        }

        if ((int) $current_count >= $max_requests) {
            return array(
                'allowed' => false,
                'ip'      => $ip,
                'count'   => (int) $current_count,
                'limit'   => $max_requests,
            );
        }

        set_transient($transient_key, (int) $current_count + 1, HOUR_IN_SECONDS);

        return array(
            'allowed' => true,
            'ip'      => $ip,
            'count'   => (int) $current_count + 1,
        );
    }
}
