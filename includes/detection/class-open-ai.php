<?php

/**
 * OpenAI spam detection.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Detection;

use WeSpamfighter\Core\Logger;

/**
 * OpenAI detector class.
 */
class OpenAI
{

    /**
     * API key.
     *
     * @var string
     */
    private $api_key;

    /**
     * Model to use.
     *
     * @var string
     */
    private $model;

    /**
     * API endpoint.
     *
     * @var string
     */
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

    /**
     * Constructor.
     *
     * @param string $api_key API key.
     * @param string $model Model name.
     */
    public function __construct($api_key, $model = 'gpt-4o-mini')
    {
        // Allow API key to be set via wp-config.php for better security.
        if (defined('WE_SPAMFIGHTER_OPENAI_KEY') && ! empty(WE_SPAMFIGHTER_OPENAI_KEY)) {
            $this->api_key = WE_SPAMFIGHTER_OPENAI_KEY;
        } else {
            $this->api_key = $api_key;
        }

        // Sanitize model to prevent injection.
        $allowed_models = array('gpt-4o-mini', 'gpt-5-mini', 'gpt-4o', 'gpt-5', 'gpt-4-turbo', 'gpt-3.5-turbo');
        $this->model = in_array($model, $allowed_models, true) ? $model : 'gpt-4o-mini';
    }

    /**
     * Analyze content for spam.
     *
     * @param array  $content Content data.
     * @param string $expected_language Expected language code.
     * @return array Analysis result with score and reasoning.
     */
    public function analyze($content, $expected_language = 'en')
    {
        if (empty($this->api_key)) {
            Logger::get_instance()->error('OpenAI API key not configured');
            return array(
                'score'   => 0,
                'is_spam' => false,
                'reason'  => 'API key not configured',
            );
        }

        // Rate limiting to prevent API abuse.
        $rate_limit_check = $this->check_rate_limit();
        if (! $rate_limit_check['allowed']) {
            Logger::get_instance()->warning(
                'OpenAI rate limit exceeded',
                array('ip' => $rate_limit_check['ip'])
            );
            return array(
                'score'   => 0.5,
                'is_spam' => false,
                'reason'  => 'Rate limit exceeded',
                'error'   => true,
            );
        }

        // Prepare content for analysis.
        $content_text = $this->prepare_content($content);

        // Build prompt.
        $prompt = $this->build_prompt($content_text, $expected_language);

        // Call OpenAI Chat API.
        $response = $this->call_api($prompt);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_code = $response->get_error_code();

            Logger::get_instance()->error(
                'OpenAI API call failed',
                array(
                    'error' => $error_message,
                    'code'  => $error_code,
                )
            );

            return array(
                'score'   => 0,
                'is_spam' => false,
                'reason'  => $error_message,
                'error'   => true,
            );
        }

        return $this->parse_response($response);
    }

    /**
     * Prepare content from data.
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
            // Skip helper/technical keys.
            if (is_string($key) && strpos($key, '_') === 0) {
                continue;
            }

            // Recursively flatten arrays to scalars.
            if (is_array($value)) {
                $flat_values = $this->flatten_to_strings($value);
                $value = implode(', ', array_filter($flat_values, function ($v) {
                    return $v !== '' && $v !== null;
                }));
            }

            // Skip empty values and purely numeric keys.
            if ($value === '' || $value === null || is_numeric($key)) {
                continue;
            }

            $content_parts[] = $value;
        }

        return implode("\n", $content_parts);
    }

    /**
     * Flatten nested arrays to a list of strings.
     *
     * @param mixed $value Any value.
     * @return array List of string values.
     */
    private function flatten_to_strings($value)
    {
        $result = array();

        if (is_array($value)) {
            foreach ($value as $v) {
                $result = array_merge($result, $this->flatten_to_strings($v));
            }
        } elseif (is_scalar($value)) {
            $string = (string) $value;
            $string = sanitize_text_field($string);
            if ($string !== '') {
                $result[] = $string;
            }
        }

        return $result;
    }

    /**
     * Build AI prompt.
     *
     * @param string $content Content to analyze.
     * @param string $expected_language Expected language.
     * @return string
     */
    private function build_prompt($content, $expected_language)
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
            esc_html($content)
        );
    }

    /**
     * Call OpenAI API.
     *
     * @param string $prompt Prompt.
     * @return array|\WP_Error
     */
    private function call_api($prompt)
    {
        $body = array(
            'model'       => $this->model,
            'messages'    => array(
                array(
                    'role'    => 'system',
                    'content' => 'You are a spam detection expert. Analyze form submissions and provide accurate spam scores with reasoning.',
                ),
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                ),
            ),
            'temperature' => 0.3,
            'max_tokens'  => 500,
        );

        // Allow SSL verification to be disabled via filter (for testing only!).
        $sslverify = apply_filters('we_spamfighter_openai_sslverify', true);

        // Store start time for debugging.
        $start_time = microtime(true);

        $request_args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($body),
            'timeout' => 45,
            'httpversion' => '1.1',
            'sslverify' => $sslverify,
            'blocking' => true, // Ensure request is blocking
        );

        // Allow filtering of request arguments.
        $request_args = apply_filters('we_spamfighter_openai_request_args', $request_args, $this->api_endpoint);

        $response = wp_remote_post($this->api_endpoint, $request_args);

        // Check if wp_remote_post returned something unexpected (not array, not WP_Error).
        if (!is_wp_error($response) && !is_array($response)) {
            // This should never happen, but if it does, try fallback to direct cURL.
            $error_message = sprintf(
                'Unexpected response type from wp_remote_post: %s (value: %s). This might indicate a filter or plugin is interfering with HTTP requests. Trying cURL fallback...',
                gettype($response),
                var_export($response, true)
            );

            // Try direct cURL as fallback.
            if (function_exists('curl_init')) {
                $ch = curl_init($this->api_endpoint);
                if ($ch) {
                    curl_setopt_array(
                        $ch,
                        array(
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => wp_json_encode($body),
                            CURLOPT_HTTPHEADER => array(
                                'Authorization: Bearer ' . $this->api_key,
                                'Content-Type: application/json',
                            ),
                            CURLOPT_TIMEOUT => 45,
                            CURLOPT_SSL_VERIFYPEER => $sslverify,
                            CURLOPT_SSL_VERIFYHOST => $sslverify ? 2 : 0,
                        )
                    );

                    $curl_response = curl_exec($ch);
                    $curl_error = curl_error($ch);
                    $curl_info = curl_getinfo($ch);
                    curl_close($ch);

                    if ($curl_response && ! $curl_error && isset($curl_info['http_code']) && 200 === $curl_info['http_code']) {
                        $data = json_decode($curl_response, true);
                        if ($data) {
                            return $data;
                        }
                    }
                }
            }

            Logger::get_instance()->error(
                'OpenAI wp_remote_post returned unexpected type',
                array(
                    'type' => gettype($response),
                    'value' => $response,
                )
            );

            return new \WP_Error(
                'unexpected_response_type',
                $error_message
            );
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        // Check if response_code is valid (0 means request failed completely).
        if (!$response_code || 200 !== $response_code) {
            $body = wp_remote_retrieve_body($response);
            $error_detail = json_decode($body, true);
            $error_message = 'Unknown error';

            // Get error message from response.
            if (isset($error_detail['error']['message'])) {
                $error_message = $error_detail['error']['message'];
            } elseif (!empty($body)) {
                $error_message = $body;
            } elseif (!$response_code) {
                $error_message = 'Request failed - no response code (connection error?)';
            }

            Logger::get_instance()->error(
                'OpenAI API returned error',
                array(
                    'code' => $response_code ?: 0,
                    'error' => $error_message,
                )
            );

            return new \WP_Error(
                'api_error',
                sprintf('API error (%d): %s', $response_code ?: 0, $error_message)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data;
    }

    /**
     * Parse API response.
     *
     * @param array $response API response.
     * @return array
     */
    private function parse_response($response)
    {
        if (! isset($response['choices'][0]['message']['content'])) {
            return array(
                'score'   => 0,
                'is_spam' => false,
                'reason'  => 'Invalid API response',
                'error'   => true,
            );
        }

        $content = $response['choices'][0]['message']['content'];

        // Extract JSON from response.
        preg_match('/\{[^}]+\}/', $content, $matches);

        if (empty($matches)) {
            Logger::get_instance()->warning(
                'Could not parse OpenAI response',
                array('response' => $content)
            );

            return array(
                'score'   => 0,
                'is_spam' => false,
                'reason'  => 'Could not parse AI response',
                'error'   => true,
            );
        }

        $result = json_decode($matches[0], true);

        if (! $result) {
            return array(
                'score'   => 0,
                'is_spam' => false,
                'reason'  => 'Invalid JSON in response',
                'error'   => true,
            );
        }

        return array(
            'score'             => floatval($result['spam_score'] ?? 0),
            'is_spam'           => (bool) ($result['is_spam'] ?? false),
            'reason'            => sanitize_text_field($result['reasoning'] ?? ''),
            'confidence'        => sanitize_text_field($result['confidence'] ?? 'low'),
            'detected_language' => sanitize_text_field($result['detected_language'] ?? 'unknown'),
            'raw_response'      => $content,
        );
    }

    /**
     * Check rate limit for API calls.
     *
     * @return array Rate limit check result.
     */
    private function check_rate_limit()
    {
        $ip = $this->get_user_ip();

        // Use transients for rate limiting (60 requests per hour per IP).
        $transient_key = 'we_spam_rate_limit_' . md5($ip);
        $current_count = get_transient($transient_key);

        if (false === $current_count) {
            // First request in this hour.
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
            return array(
                'allowed' => true,
                'ip'      => $ip,
                'count'   => 1,
            );
        }

        // Maximum 60 requests per hour per IP.
        $max_requests = apply_filters('we_spamfighter_rate_limit_max', 60);

        if ($current_count >= $max_requests) {
            return array(
                'allowed' => false,
                'ip'      => $ip,
                'count'   => $current_count,
                'limit'   => $max_requests,
            );
        }

        // Increment counter.
        set_transient($transient_key, $current_count + 1, HOUR_IN_SECONDS);

        return array(
            'allowed' => true,
            'ip'      => $ip,
            'count'   => $current_count + 1,
        );
    }

    /**
     * Get user IP address.
     *
     * @return string
     */
    private function get_user_ip()
    {
        $ip = '';

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return $ip;
    }

    /**
     * Normalize language code to base language (e.g., 'en-US' → 'en', 'de_DE' → 'de').
     *
     * @param string $lang_code Language code.
     * @return string Normalized language code (2 characters).
     */
    public static function normalize_language_code($lang_code)
    {
        if (empty($lang_code) || 'unknown' === strtolower($lang_code)) {
            return '';
        }

        // Extract first 2 characters and convert to lowercase.
        $normalized = strtolower(substr($lang_code, 0, 2));

        // Return empty if not a valid 2-letter code.
        if (strlen($normalized) !== 2 || ! preg_match('/^[a-z]{2}$/', $normalized)) {
            return '';
        }

        return $normalized;
    }
}
