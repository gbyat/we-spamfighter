<?php

/**
 * Heuristic spam detection (without external APIs).
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Detection;

/**
 * Heuristic detector class.
 */
class HeuristicDetector
{

    /**
     * Analyze content for spam using heuristics.
     *
     * @param array|string $content Content to analyze.
     * @param array        $settings Plugin settings.
     * @return array Analysis result with score and details.
     */
    public static function analyze($content, $settings = array())
    {
        // Prepare content text.
        $content_text = self::prepare_content($content);

        if (empty($content_text)) {
            return array(
                'score'   => 0.0,
                'is_spam' => false,
                'details' => array(),
            );
        }

        $total_score = 0.0;
        $details = array();
        $checks_performed = 0;

        // Check 1: Link Analysis.
        if (empty($settings['disable_link_check'])) {
            $link_check = self::check_links($content_text);
            if ($link_check['score'] > 0) {
                $total_score += $link_check['score'];
                $details['link_check'] = $link_check;
                $checks_performed++;
            }
        }

        // Check 2: Character Patterns.
        if (empty($settings['disable_character_check'])) {
            $char_check = self::check_character_patterns($content_text);
            if ($char_check['score'] > 0) {
                $total_score += $char_check['score'];
                $details['character_check'] = $char_check;
                $checks_performed++;
            }
        }

        // Check 3: Spam Phrases.
        if (empty($settings['disable_phrase_check'])) {
            $phrase_check = self::check_spam_phrases($content_text);
            if ($phrase_check['score'] > 0) {
                $total_score += $phrase_check['score'];
                $details['phrase_check'] = $phrase_check;
                $checks_performed++;
            }
        }

        // Check 4: Email Patterns.
        if (empty($settings['disable_email_check'])) {
            $email_check = self::check_email_patterns($content_text);
            if ($email_check['score'] > 0) {
                $total_score += $email_check['score'];
                $details['email_check'] = $email_check;
                $checks_performed++;
            }
        }

        // Normalize score to 0.0 - 1.0 range.
        $normalized_score = min(1.0, $total_score);

        // Determine if spam based on threshold.
        $threshold = (float) ($settings['heuristic_threshold'] ?? 0.6);
        $is_spam = $normalized_score >= $threshold;

        return array(
            'score'   => $normalized_score,
            'is_spam' => $is_spam,
            'details' => $details,
            'checks_performed' => $checks_performed,
        );
    }

    /**
     * Check for suspicious links.
     *
     * @param string $text Text to analyze.
     * @return array Check result.
     */
    private static function check_links($text)
    {
        $score = 0.0;
        $reasons = array();

        // Find all URLs.
        preg_match_all('/https?:\/\/[^\s<>"\'\]\[\)]+/i', $text, $urls);
        $url_count = count($urls[0]);
        $text_length = mb_strlen($text);

        // Too many links.
        if ($url_count > 5) {
            $score += 0.4;
            $reasons[] = sprintf(__('Too many links (%d)', 'we-spamfighter'), $url_count);
        } elseif ($url_count > 3) {
            $score += 0.2;
            $reasons[] = sprintf(__('Multiple links (%d)', 'we-spamfighter'), $url_count);
        }

        // High link-to-text ratio.
        if ($text_length > 0 && $url_count > 0) {
            $link_length = array_sum(array_map('mb_strlen', $urls[0]));
            $ratio = $link_length / $text_length;
            if ($ratio > 0.5) {
                $score += 0.5;
                $reasons[] = __('Very high link-to-text ratio', 'we-spamfighter');
            } elseif ($ratio > 0.3) {
                $score += 0.3;
                $reasons[] = __('High link-to-text ratio', 'we-spamfighter');
            }
        }

        // Check for URL shorteners and suspicious domains.
        $suspicious_domains = array(
            'bit.ly',
            'tinyurl.com',
            't.co',
            'goo.gl',
            'ow.ly',
            'buff.ly',
            'adf.ly',
            'adfly',
            'short.link',
            'cutt.ly',
            'is.gd',
            'v.gd',
            'rebrand.ly',
            'shorten.it',
            'tiny.cc',
            'shorturl.at',
        );

        $suspicious_tlds = array(
            '.tk',
            '.ml',
            '.ga',
            '.cf',
            '.gq',
            '.xyz',
            '.click',
            '.top',
            '.download',
            '.stream',
            '.online',
            '.site',
            '.website',
        );

        foreach ($urls[0] as $url) {
            $domain = parse_url($url, PHP_URL_HOST);
            if (! $domain) {
                continue;
            }

            $domain_lower = strtolower($domain);

            // Check for URL shorteners.
            foreach ($suspicious_domains as $sus_domain) {
                if (strpos($domain_lower, $sus_domain) !== false) {
                    $score += 0.3;
                    $reasons[] = sprintf(__('URL shortener detected: %s', 'we-spamfighter'), $sus_domain);
                    break;
                }
            }

            // Check for suspicious TLDs.
            foreach ($suspicious_tlds as $sus_tld) {
                if (substr($domain_lower, -strlen($sus_tld)) === $sus_tld) {
                    $score += 0.2;
                    $reasons[] = sprintf(__('Suspicious TLD: %s', 'we-spamfighter'), $sus_tld);
                    break;
                }
            }

            // Check for IP addresses as URLs.
            if (preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $domain)) {
                $score += 0.4;
                $reasons[] = __('IP address used as URL', 'we-spamfighter');
            }
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => array_unique($reasons),
        );
    }

    /**
     * Check for suspicious character patterns.
     *
     * @param string $text Text to analyze.
     * @return array Check result.
     */
    private static function check_character_patterns($text)
    {
        $score = 0.0;
        $reasons = array();

        // Repeated characters (e.g., "aaaaaa", "!!!!!!").
        if (preg_match('/(.)\1{4,}/', $text)) {
            $score += 0.3;
            $reasons[] = __('Repeated characters detected', 'we-spamfighter');
        }

        // ALL CAPS TEXT (more than 50% of text).
        $upper_count = mb_strlen(preg_replace('/[^A-ZÄÖÜÀÁÂÃÈÉÊÌÍÎÒÓÔÕÙÚÛ]/u', '', $text));
        $text_length = mb_strlen(preg_replace('/[^a-zA-ZÄÖÜäöüàáâãèéêìíîòóôõùúû]/u', '', $text));
        if ($text_length > 10) {
            $upper_ratio = $upper_count / $text_length;
            if ($upper_ratio > 0.8) {
                $score += 0.4;
                $reasons[] = __('Text is mostly in uppercase', 'we-spamfighter');
            } elseif ($upper_ratio > 0.5) {
                $score += 0.2;
                $reasons[] = __('High percentage of uppercase text', 'we-spamfighter');
            }
        }

        // Mixed case spam pattern (e.g., "LiKe ThIs").
        if (preg_match('/\b([A-Z][a-z]+ ){3,}[A-Z][a-z]+\b/', $text)) {
            $score += 0.3;
            $reasons[] = __('Suspicious mixed case pattern', 'we-spamfighter');
        }

        // Too many special characters.
        $special_chars = preg_match_all('/[!@#$%^&*()_+={}\[\]:;"\'<>?,.\/\\\-]/', $text);
        if ($text_length > 0) {
            $special_ratio = $special_chars / $text_length;
            if ($special_ratio > 0.3) {
                $score += 0.3;
                $reasons[] = __('Too many special characters', 'we-spamfighter');
            }
        }

        // Missing punctuation (very long sentences).
        $sentences = preg_split('/[.!?]+/', $text);
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (mb_strlen($sentence) > 200 && ! preg_match('/[.!?;:]/', $sentence)) {
                $score += 0.2;
                $reasons[] = __('Very long sentence without punctuation', 'we-spamfighter');
                break;
            }
        }

        // Excessive emojis.
        $emoji_count = preg_match_all('/[\x{1F300}-\x{1F9FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', $text);
        if ($emoji_count > 5) {
            $score += 0.2;
            $reasons[] = sprintf(__('Too many emojis (%d)', 'we-spamfighter'), $emoji_count);
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => array_unique($reasons),
        );
    }

    /**
     * Check for known spam phrases.
     *
     * @param string $text Text to analyze.
     * @return array Check result.
     */
    private static function check_spam_phrases($text)
    {
        $score = 0.0;
        $reasons = array();
        $text_lower = mb_strtolower($text);

        // Common spam phrases (multi-language).
        $spam_phrases = array(
            // English.
            'buy now',
            'click here',
            'free money',
            'make money fast',
            'work from home',
            'limited time offer',
            'act now',
            'guaranteed income',
            'risk free',
            'no credit check',
            'one weird trick',
            'doctors hate',
            'lose weight fast',
            'get rich quick',
            'miracle cure',
            'winner',
            'congratulations',
            'you have won',
            'claim your prize',
            'click below',
            'visit our website',
            'special promotion',
            // German.
            'jetzt kaufen',
            'klicken sie hier',
            'kostenlos geld',
            'schnell geld verdienen',
            'von zuhause arbeiten',
            'begrenztes angebot',
            'jetzt handeln',
            'garantiertes einkommen',
            'ohne kreditprüfung',
            'sie haben gewonnen',
            'gewinnspiel',
            'gratis',
            'kostenlos',
            // Generic.
            'urgent',
            'dringend',
            'important',
            'wichtig',
            'asap',
            'sofort',
            '100% free',
            '100% kostenlos',
            'no investment',
            'keine investition',
            'turn $1 into $1000',
            'from $0 to millionaire',
            // SEO spam.
            'best price',
            'lowest price',
            'cheap',
            'discount',
            'sale',
            'promotion',
            'best deal',
            'special offer',
            'limited offer',
            'hurry up',
        );

        $phrase_count = 0;
        foreach ($spam_phrases as $phrase) {
            if (stripos($text_lower, $phrase) !== false) {
                $phrase_count++;
                $reasons[] = sprintf(__('Spam phrase detected: "%s"', 'we-spamfighter'), $phrase);
            }
        }

        if ($phrase_count > 0) {
            if ($phrase_count >= 3) {
                $score += 0.6;
            } elseif ($phrase_count >= 2) {
                $score += 0.4;
            } else {
                $score += 0.2;
            }
        }

        // Keyword stuffing (repeated words).
        $words = preg_split('/\s+/', $text_lower);
        $word_counts = array_count_values(array_filter($words, function ($word) {
            return mb_strlen($word) > 3; // Ignore short words.
        }));

        foreach ($word_counts as $word => $count) {
            if ($count > 5 && mb_strlen($word) > 4) {
                $score += 0.3;
                $reasons[] = sprintf(__('Keyword stuffing: "%s" repeated %d times', 'we-spamfighter'), $word, $count);
                break; // Only count once.
            }
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => array_unique($reasons),
        );
    }

    /**
     * Check for suspicious email patterns.
     *
     * @param string $text Text to analyze.
     * @return array Check result.
     */
    private static function check_email_patterns($text)
    {
        $score = 0.0;
        $reasons = array();

        // Find email addresses.
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $emails);
        $email_count = count($emails[0]);

        // Multiple email addresses in content (suspicious for comments).
        if ($email_count > 1) {
            $score += 0.3;
            $reasons[] = sprintf(__('Multiple email addresses (%d)', 'we-spamfighter'), $email_count);
        }

        // Suspicious email providers.
        $suspicious_providers = array(
            'temp-mail',
            'guerrillamail',
            'mailinator',
            'throwaway',
            '10minutemail',
            'tempmail',
            'fakemail',
            'trashmail',
        );

        foreach ($emails[0] as $email) {
            $domain = substr(strrchr($email, '@'), 1);
            $domain_lower = strtolower($domain);

            foreach ($suspicious_providers as $provider) {
                if (strpos($domain_lower, $provider) !== false) {
                    $score += 0.4;
                    $reasons[] = sprintf(__('Suspicious email provider: %s', 'we-spamfighter'), $provider);
                    break;
                }
            }

            // Random character pattern in email (suspicious).
            $local_part = substr($email, 0, strpos($email, '@'));
            if (preg_match('/^[a-z0-9]{10,}$/i', $local_part) && preg_match_all('/[0-9]/', $local_part) > 3) {
                $score += 0.2;
                $reasons[] = __('Suspicious random email pattern', 'we-spamfighter');
            }
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => array_unique($reasons),
        );
    }

    /**
     * Prepare content from data (similar to OpenAI class).
     *
     * @param array|string $content Content data.
     * @return string
     */
    private static function prepare_content($content)
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
                $flat_values = self::flatten_to_strings($value);
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
    private static function flatten_to_strings($value)
    {
        $result = array();

        if (is_array($value)) {
            foreach ($value as $v) {
                $result = array_merge($result, self::flatten_to_strings($v));
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
}
