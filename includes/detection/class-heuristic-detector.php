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
     * @param array        $context Optional context data (referrer, user_agent, etc.).
     * @return array Analysis result with score and details.
     */
    public static function analyze($content, $settings = array(), $context = array())
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
        // Default to enabled if not set (for backward compatibility during migration).
        $enable_link = !empty($settings['enable_link_check']) || (!isset($settings['enable_link_check']) && empty($settings['disable_link_check']));
        if ($enable_link) {
            $link_check = self::check_links($content_text);
            if ($link_check['score'] > 0) {
                $total_score += $link_check['score'];
                $details['link_check'] = $link_check;
                $checks_performed++;
            }
        }

        // Check 2: Character Patterns.
        $enable_character = !empty($settings['enable_character_check']) || (!isset($settings['enable_character_check']) && empty($settings['disable_character_check']));
        if ($enable_character) {
            $char_check = self::check_character_patterns($content_text);
            if ($char_check['score'] > 0) {
                $total_score += $char_check['score'];
                $details['character_check'] = $char_check;
                $checks_performed++;
            }
        }

        // Check 2b: Author/name field patterns (when structured content available).
        if ($enable_character && is_array($content)) {
            $author_value = $content['author'] ?? $content['your-name'] ?? $content['name'] ?? $content['from'] ?? $content['sender'] ?? '';
            if (!empty($author_value) && is_string($author_value)) {
                $author_check = self::check_author_name_patterns($author_value);
                if ($author_check['score'] > 0) {
                    $total_score += $author_check['score'];
                    $details['author_check'] = $author_check;
                    $checks_performed++;
                }
            }
        }

        // Check 3: Spam Phrases.
        $enable_phrase = !empty($settings['enable_phrase_check']) || (!isset($settings['enable_phrase_check']) && empty($settings['disable_phrase_check']));
        if ($enable_phrase) {
            $phrase_check = self::check_spam_phrases($content_text);
            if ($phrase_check['score'] > 0) {
                $total_score += $phrase_check['score'];
                $details['phrase_check'] = $phrase_check;
                $checks_performed++;
            }
        }

        // Check 4: Email Patterns.
        $enable_email = !empty($settings['enable_email_check']) || (!isset($settings['enable_email_check']) && empty($settings['disable_email_check']));
        if ($enable_email) {
            $email_check = self::check_email_patterns($content_text);
            if ($email_check['score'] > 0) {
                $total_score += $email_check['score'];
                $details['email_check'] = $email_check;
                $checks_performed++;
            }
        }

        // Check 5: Referrer Analysis.
        $enable_referrer = !empty($settings['enable_referrer_check']) || (!isset($settings['enable_referrer_check']) && empty($settings['disable_referrer_check']));
        if ($enable_referrer) {
            $referrer = $context['referrer'] ?? (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
            $referrer_check = self::check_referrer($referrer);
            if ($referrer_check['score'] > 0) {
                $total_score += $referrer_check['score'];
                $details['referrer_check'] = $referrer_check;
                $checks_performed++;
            }
        }

        // Check 6: User Agent Analysis.
        $enable_user_agent = !empty($settings['enable_user_agent_check']) || (!isset($settings['enable_user_agent_check']) && empty($settings['disable_user_agent_check']));
        if ($enable_user_agent) {
            $user_agent = $context['user_agent'] ?? (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
            $user_agent_check = self::check_user_agent($user_agent);
            if ($user_agent_check['score'] > 0) {
                $total_score += $user_agent_check['score'];
                $details['user_agent_check'] = $user_agent_check;
                $checks_performed++;
            }
        }

        // Check 7: Content Length Analysis.
        $enable_content_length = !empty($settings['enable_content_length_check']) || (!isset($settings['enable_content_length_check']) && empty($settings['disable_content_length_check']));
        if ($enable_content_length) {
            $length_check = self::check_content_length($content_text);
            if ($length_check['score'] > 0) {
                $total_score += $length_check['score'];
                $details['content_length_check'] = $length_check;
                $checks_performed++;
            }
        }

        // Check 7b: Empty message body (CF7/forms) - spam often has name/email/subject filled but message empty.
        if (is_array($content) && $enable_content_length) {
            $empty_msg_check = self::check_empty_message_body($content);
            if ($empty_msg_check['score'] > 0) {
                $total_score += $empty_msg_check['score'];
                $details['empty_message_check'] = $empty_msg_check;
                $checks_performed++;
            }
        }

        // Check 8: Mixed Script Detection.
        $enable_mixed_script = !empty($settings['enable_mixed_script_check']) || (!isset($settings['enable_mixed_script_check']) && empty($settings['disable_mixed_script_check']));
        if ($enable_mixed_script) {
            $mixed_script_check = self::check_mixed_scripts($content_text);
            if ($mixed_script_check['score'] > 0) {
                $total_score += $mixed_script_check['score'];
                $details['mixed_script_check'] = $mixed_script_check;
                $checks_performed++;
            }
        }

        // Check 9: Unicode Anomalies.
        $enable_unicode = !empty($settings['enable_unicode_check']) || (!isset($settings['enable_unicode_check']) && empty($settings['disable_unicode_check']));
        if ($enable_unicode) {
            $unicode_check = self::check_unicode_anomalies($content_text);
            if ($unicode_check['score'] > 0) {
                $total_score += $unicode_check['score'];
                $details['unicode_check'] = $unicode_check;
                $checks_performed++;
            }
        }

        // Check 10: Numbers/Letters Only.
        $enable_numbers_letters = !empty($settings['enable_numbers_letters_only_check']) || (!isset($settings['enable_numbers_letters_only_check']) && empty($settings['disable_numbers_letters_only_check']));
        if ($enable_numbers_letters) {
            $numbers_letters_check = self::check_numbers_letters_only($content_text);
            if ($numbers_letters_check['score'] > 0) {
                $total_score += $numbers_letters_check['score'];
                $details['numbers_letters_only_check'] = $numbers_letters_check;
                $checks_performed++;
            }
        }

        // Check 11: IP Address in Content.
        $enable_ip_in_content = !empty($settings['enable_ip_in_content_check']) || (!isset($settings['enable_ip_in_content_check']) && empty($settings['disable_ip_in_content_check']));
        if ($enable_ip_in_content) {
            $ip_check = self::check_ip_in_content($content_text);
            if ($ip_check['score'] > 0) {
                $total_score += $ip_check['score'];
                $details['ip_in_content_check'] = $ip_check;
                $checks_performed++;
            }
        }

        // Check 12: Repeated Multilingual Content.
        $enable_repeated_multilingual = !empty($settings['enable_repeated_multilingual_check']) || (!isset($settings['enable_repeated_multilingual_check']) && empty($settings['disable_repeated_multilingual_check']));
        if ($enable_repeated_multilingual) {
            $repeated_check = self::check_repeated_multilingual($content_text);
            if ($repeated_check['score'] > 0) {
                $total_score += $repeated_check['score'];
                $details['repeated_multilingual_check'] = $repeated_check;
                $checks_performed++;
            }
        }

        // Apply combination bonus if multiple checks found issues.
        $checks_with_issues = 0;
        if (isset($details['link_check']) && $details['link_check']['score'] > 0) {
            $checks_with_issues++;
        }
        if (isset($details['character_check']) && $details['character_check']['score'] > 0) {
            $checks_with_issues++;
        }
        if (isset($details['author_check']) && $details['author_check']['score'] > 0) {
            $checks_with_issues++;
        }
        if (isset($details['phrase_check']) && $details['phrase_check']['score'] > 0) {
            $checks_with_issues++;
        }
        if (isset($details['email_check']) && $details['email_check']['score'] > 0) {
            $checks_with_issues++;
        }
        if (isset($details['referrer_check']) && $details['referrer_check']['score'] > 0) {
            $checks_with_issues++;
        }
        if (isset($details['user_agent_check']) && $details['user_agent_check']['score'] > 0) {
            $checks_with_issues++;
        }
        if (isset($details['content_length_check']) && $details['content_length_check']['score'] > 0) {
            $checks_with_issues++;
        }
        if (isset($details['empty_message_check']) && $details['empty_message_check']['score'] > 0) {
            $checks_with_issues++;
        }
        if (isset($details['mixed_script_check']) && $details['mixed_script_check']['score'] > 0) {
            $checks_with_issues++;
        }
        if (isset($details['unicode_check']) && $details['unicode_check']['score'] > 0) {
            $checks_with_issues++;
        }
        if (isset($details['numbers_letters_only_check']) && $details['numbers_letters_only_check']['score'] > 0) {
            $checks_with_issues++;
        }
        if (isset($details['ip_in_content_check']) && $details['ip_in_content_check']['score'] > 0) {
            $checks_with_issues++;
        }
        if (isset($details['repeated_multilingual_check']) && $details['repeated_multilingual_check']['score'] > 0) {
            $checks_with_issues++;
        }

        // Combination bonus: if 3 or more checks found issues, add bonus.
        if ($checks_with_issues >= 3) {
            $total_score += 0.2;
            $details['combination_bonus'] = array(
                'score' => 0.2,
                /* translators: %d: Number of checks that found issues */
                'reason' => sprintf(__('Multiple suspicious patterns detected (%d checks)', 'we-spamfighter'), $checks_with_issues),
            );
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

        // Find all URLs (with protocol and www. without protocol).
        preg_match_all('/https?:\/\/[^\s<>"\'\]\[\)]+/i', $text, $urls_proto);
        preg_match_all('/\bwww\.[a-zA-Z0-9][-a-zA-Z0-9]*(?:\.[a-zA-Z0-9][-a-zA-Z0-9]*)+(?:\/[^\s<>"\'\]\[\)]*)?/i', $text, $urls_www);
        $urls = array_unique(array_merge($urls_proto[0] ?? array(), $urls_www[0] ?? array()));
        $url_count = count($urls);
        $text_length = mb_strlen($text);

        // Too many links.
        if ($url_count > 5) {
            $score += 0.4;
            /* translators: %d: Number of links */
            $reasons[] = sprintf(__('Too many links (%d)', 'we-spamfighter'), $url_count);
        } elseif ($url_count > 3) {
            $score += 0.2;
            /* translators: %d: Number of links */
            $reasons[] = sprintf(__('Multiple links (%d)', 'we-spamfighter'), $url_count);
        }

        // High link-to-text ratio.
        if ($text_length > 0 && $url_count > 0) {
            $link_length = array_sum(array_map('mb_strlen', $urls));
            $ratio = $link_length / $text_length;
            if ($ratio > 0.5) {
                $score += 0.5;
                $reasons[] = __('Very high link-to-text ratio', 'we-spamfighter');
            } elseif ($ratio > 0.3) {
                $score += 0.3;
                $reasons[] = __('High link-to-text ratio', 'we-spamfighter');
            }
        }

        // Check for suspicious URL patterns (full URL including path).
        $suspicious_url_patterns = array(
            'yandex.com/poll',   // Poll links used for spam/phishing
            'google.com/o',      // Fake Google redirect paths (phishing)
        );
        foreach ($urls as $url) {
            $url_lower = strtolower($url);
            foreach ($suspicious_url_patterns as $pattern) {
                if (strpos($url_lower, $pattern) !== false) {
                    $score += 0.4;
                    /* translators: %s: Suspicious URL pattern */
                    $reasons[] = sprintf(__('Suspicious URL pattern: %s', 'we-spamfighter'), $pattern);
                    break;
                }
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
            'sho.cat', // Short domain, often used for spam
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
            '.lv', // Latvia (often used for spam)
            '.ru', // Russia (often used for spam)
            '.su', // Soviet Union (often used for spam)
            '.info', // Often used for spam
            '.biz', // Often used for spam
            '.cc', // Cocos Islands (often used for spam)
            '.cat', // Catalan domain, sometimes used for spam
        );

        foreach ($urls as $url) {
            $domain = parse_url((strpos($url, '//') !== false ? '' : 'http://') . $url, PHP_URL_HOST);
            if (! $domain) {
                $domain = preg_replace('#/.*#', '', $url);
            }
            if (empty($domain)) {
                continue;
            }

            $domain_lower = strtolower($domain);

            // Check for URL shorteners.
            foreach ($suspicious_domains as $sus_domain) {
                if (strpos($domain_lower, $sus_domain) !== false) {
                    $score += 0.3;
                    /* translators: %s: URL shortener domain */
                    $reasons[] = sprintf(__('URL shortener detected: %s', 'we-spamfighter'), $sus_domain);
                    break;
                }
            }

            // Check for suspicious TLDs.
            foreach ($suspicious_tlds as $sus_tld) {
                if (substr($domain_lower, -strlen($sus_tld)) === $sus_tld) {
                    // Higher score for very suspicious TLDs.
                    if (in_array($sus_tld, array('.ru', '.su', '.tk', '.ml', '.ga', '.cf', '.gq'))) {
                        $score += 0.3;
                    } else {
                        $score += 0.2;
                    }
                    /* translators: %s: Suspicious top-level domain */
                    $reasons[] = sprintf(__('Suspicious TLD: %s', 'we-spamfighter'), $sus_tld);
                    break;
                }
            }

            // Check for suspicious domain patterns (new/unknown domains with links in foreign language context).
            // This is a heuristic: if domain looks like it could be spam-related.
            if (preg_match('/[a-z]{6,}\.[a-z]{2,}$/i', $domain_lower)) {
                // Check for suspicious patterns in domain name.
                if (preg_match('/(tarif|recipe|recipe|cook|food|health|weight|diet|pills|drug|pharma|casino|poker|bet|loan|credit|debt|money|free|win|prize)/i', $domain_lower)) {
                    $score += 0.3;
                    $reasons[] = __('Suspicious domain pattern detected', 'we-spamfighter');
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

        // Emojis/icons in content (suspicious in comments/author names - spammers use for visibility).
        $emoji_count = preg_match_all('/[\x{1F300}-\x{1F9FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', $text);
        if ($emoji_count >= 1) {
            $score += ($emoji_count >= 5 ? 0.4 : 0.2);
            /* translators: %d: Number of emojis or icons */
            $reasons[] = sprintf(__('Emojis/icons detected (%d) - common in spam', 'we-spamfighter'), $emoji_count);
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => array_unique($reasons),
        );
    }

    /**
     * Check author/name field for suspicious patterns.
     * Long names, URLs in names, and emojis are common spam indicators.
     *
     * @param string $author Author or name value.
     * @return array Check result.
     */
    private static function check_author_name_patterns($author)
    {
        $score = 0.0;
        $reasons = array();
        $author = trim($author);

        if (empty($author)) {
            return array('score' => 0.0, 'reasons' => array());
        }

        // Extremely long author/name (spam often uses long promotional text as "name").
        if (mb_strlen($author) > 60) {
            $score += 0.4;
            /* translators: %d: Author name length in characters */
            $reasons[] = sprintf(__('Extremely long author/name (%d characters) - common spam pattern', 'we-spamfighter'), mb_strlen($author));
        } elseif (mb_strlen($author) > 40) {
            $score += 0.25;
            /* translators: %d: Author name length in characters */
            $reasons[] = sprintf(__('Very long author/name (%d characters)', 'we-spamfighter'), mb_strlen($author));
        }

        // URL/link in author name - virtually only occurs with spam, definitive indicator.
        if (preg_match('/https?:\/\//i', $author) || preg_match('/\bwww\./i', $author)) {
            $score += 0.8;
            $reasons[] = __('URL/link in author name - definitive spam indicator', 'we-spamfighter');
        }

        // Emojis/icons in author name (used by spammers for visibility).
        $emoji_count = preg_match_all('/[\x{1F300}-\x{1F9FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', $author);
        if ($emoji_count >= 1) {
            $score += 0.3;
            /* translators: %d: Number of emojis or icons in author name */
            $reasons[] = sprintf(__('Emojis/icons in author name (%d) - common in spam', 'we-spamfighter'), $emoji_count);
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
            // Adult/dating spam (high priority).
            'adult dating',
            'sex dating',
            'dating for sex',
            'adult dating.',
            'sex dating.',
            'dating for sex.',
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
            // AI / lead-gen cold pitch (English) — generic contact forms.
            'skyrocket your website',
            'skyrocket your leads',
            'skyrocket your traffic',
            'discover how our ai',
            'discover how ai can',
            'in this quick video',
            'watch this short video',
            'explode your sales',
            '10x your leads',
            '10x your traffic',
        );

        $phrase_count = 0;
        foreach ($spam_phrases as $phrase) {
            if (stripos($text_lower, $phrase) !== false) {
                $phrase_count++;
                /* translators: %s: Detected spam phrase */
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
                /* translators: 1: Keyword, 2: Repeat count */
                $reasons[] = sprintf(__('Keyword stuffing: "%1$s" repeated %2$d times', 'we-spamfighter'), $word, $count);
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
            /* translators: %d: Number of email addresses */
            $reasons[] = sprintf(__('Multiple email addresses (%d)', 'we-spamfighter'), $email_count);
        }

        // Suspicious email providers.
        $suspicious_providers = array(
            'tmxttvmail',  // Disposable/random mail pattern
            'temp-mail',
            'guerrillamail',
            'mailinator',
            'throwaway',
            '10minutemail',
            'tempmail',
            'fakemail',
            'trashmail',
            'inbox.lv', // Latvia email provider (often used for spam)
            'inbox',
            'mail.ru',
            'yandex.ru',
            'rambler.ru',
        );

        foreach ($emails[0] as $email) {
            $domain = substr(strrchr($email, '@'), 1);
            $domain_lower = strtolower($domain);

            foreach ($suspicious_providers as $provider) {
                // Check if domain starts with or contains the provider pattern.
                // For patterns ending with '.', check if domain starts with it.
                if (substr($provider, -1) === '.') {
                    if (strpos($domain_lower, $provider) === 0) {
                        $score += 0.3; // Lower score for free providers (they can be legitimate)
                        /* translators: %s: Free email provider domain */
                        $reasons[] = sprintf(__('Free email provider: %s', 'we-spamfighter'), rtrim($provider, '.'));
                        break;
                    }
                } else {
                    if (strpos($domain_lower, $provider) !== false) {
                        $score += 0.4;
                        /* translators: %s: Suspicious email provider domain */
                        $reasons[] = sprintf(__('Suspicious email provider: %s', 'we-spamfighter'), $provider);
                        break;
                    }
                }
            }

            // Random character pattern in email (suspicious).
            $local_part = substr($email, 0, strpos($email, '@'));

            // Very short local part with only numbers (e.g., "123@")
            if (preg_match('/^\d{1,4}$/', $local_part)) {
                $score += 0.4;
                $reasons[] = __('Very suspicious email pattern (numbers only)', 'we-spamfighter');
            }

            // Random character pattern (long alphanumeric with many numbers).
            if (preg_match('/^[a-z0-9]{10,}$/i', $local_part) && preg_match_all('/[0-9]/', $local_part) > 3) {
                $score += 0.2;
                $reasons[] = __('Suspicious random email pattern', 'we-spamfighter');
            }

            // Suspicious pattern: Name followed by numbers (e.g., "antonio.campanelli197")
            // This is a common spam pattern where spammers add random numbers to legitimate-sounding names.
            if (preg_match('/^[a-z]+\.[a-z]+\d{2,}$/i', $local_part) || preg_match('/^[a-z]+\d{3,}$/i', $local_part)) {
                $score += 0.3;
                $reasons[] = __('Suspicious email pattern (name with trailing numbers)', 'we-spamfighter');
            }

            // Suspicious: Many numbers in local part (more than 4 digits total).
            $digit_count = preg_match_all('/\d/', $local_part);
            if ($digit_count > 4 && mb_strlen($local_part) > 8) {
                $score += 0.2;
                /* translators: %d: Number of digits in the email local part */
                $reasons[] = sprintf(__('Suspicious email pattern (%d digits in local part)', 'we-spamfighter'), $digit_count);
            }

            // Suspicious TLDs in email domain (common spam domains).
            // Use the same list as in check_links() for consistency.
            $suspicious_tlds = array(
                '.ru',
                '.tk',
                '.ml',
                '.ga',
                '.cf',
                '.gq',
                '.xyz',
                '.top',
                '.lv',
                '.su',
                '.info',
                '.biz',
                '.cc',
                '.click',
                '.download',
                '.stream',
                '.online',
                '.site',
                '.website',
                '.cat', // Include .cat as well
            );
            foreach ($suspicious_tlds as $sus_tld) {
                if (substr($domain_lower, -strlen($sus_tld)) === $sus_tld) {
                    // Higher score for very suspicious TLDs.
                    if (in_array($sus_tld, array('.ru', '.su', '.tk', '.ml', '.ga', '.cf', '.gq', '.lv'))) {
                        $score += 0.4;
                    } else {
                        $score += 0.3;
                    }
                    /* translators: %s: Suspicious email domain TLD */
                    $reasons[] = sprintf(__('Suspicious email domain TLD: %s', 'we-spamfighter'), $sus_tld);
                    break;
                }
            }

            // Suspicious domain patterns (numbers in domain like "2mail2.ru").
            if (preg_match('/\d/', $domain_lower) && !preg_match('/^mail\d+\./i', $domain_lower)) {
                // Numbers in domain name (but not common patterns like mail.ru)
                $score += 0.2;
                $reasons[] = __('Suspicious email domain with numbers', 'we-spamfighter');
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

    /**
     * Check referrer for spam indicators.
     *
     * @param string $referrer HTTP Referer header value.
     * @return array Check result.
     */
    private static function check_referrer($referrer)
    {
        $score = 0.0;
        $reasons = array();

        // Missing referrer (direct access or bot).
        if (empty($referrer)) {
            $score += 0.2;
            $reasons[] = __('Missing referrer (direct access or bot)', 'we-spamfighter');
            return array(
                'score'   => min(1.0, $score),
                'reasons' => $reasons,
            );
        }

        $referrer_lower = strtolower($referrer);

        // Check if referrer is from same domain (legitimate).
        $site_url = site_url();
        $site_domain = parse_url($site_url, PHP_URL_HOST);
        $referrer_domain = parse_url($referrer, PHP_URL_HOST);

        if ($site_domain && $referrer_domain && $site_domain === $referrer_domain) {
            // Same domain - not suspicious.
            return array(
                'score'   => 0.0,
                'reasons' => array(),
            );
        }

        // Suspicious referrer domains.
        $suspicious_referrers = array(
            'google.com', // Can be legitimate, but check for search spam
            'bing.com',
            'yahoo.com',
            'facebook.com',
            'twitter.com',
            'instagram.com',
        );

        // External referrer without proper source.
        if (! empty($referrer_domain) && ! in_array($referrer_domain, $suspicious_referrers)) {
            // Check for suspicious patterns in referrer URL.
            if (preg_match('/(click|redirect|url|link|short|go|spam|promo|offer|deal|free)/i', $referrer_lower)) {
                $score += 0.3;
                $reasons[] = __('Suspicious referrer URL pattern', 'we-spamfighter');
            }
        }

        // Referrer from suspicious domains.
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
        );

        foreach ($suspicious_domains as $sus_domain) {
            if (strpos($referrer_lower, $sus_domain) !== false) {
                $score += 0.4;
                /* translators: %s: URL shortener domain in referrer */
                $reasons[] = sprintf(__('Referrer from URL shortener: %s', 'we-spamfighter'), $sus_domain);
                break;
            }
        }

        // Referrer with suspicious TLDs.
        $suspicious_tlds = array('.tk', '.ml', '.ga', '.cf', '.gq', '.ru', '.su');
        foreach ($suspicious_tlds as $sus_tld) {
            if (substr($referrer_domain, -strlen($sus_tld)) === $sus_tld) {
                $score += 0.3;
                /* translators: %s: Suspicious TLD in referrer */
                $reasons[] = sprintf(__('Referrer from suspicious TLD: %s', 'we-spamfighter'), $sus_tld);
                break;
            }
        }

        // IP address as referrer (very suspicious).
        if ($referrer_domain && preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $referrer_domain)) {
            $score += 0.4;
            $reasons[] = __('Referrer is an IP address', 'we-spamfighter');
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => array_unique($reasons),
        );
    }

    /**
     * Check user agent for spam indicators.
     *
     * @param string $user_agent HTTP User-Agent header value.
     * @return array Check result.
     */
    private static function check_user_agent($user_agent)
    {
        $score = 0.0;
        $reasons = array();

        // Missing or empty user agent (very suspicious).
        if (empty($user_agent) || trim($user_agent) === '') {
            $score += 0.4;
            $reasons[] = __('Missing user agent (bot or script)', 'we-spamfighter');
            return array(
                'score'   => min(1.0, $score),
                'reasons' => $reasons,
            );
        }

        $user_agent_lower = strtolower($user_agent);

        // Known bot user agents (legitimate crawlers - lower score).
        $known_bots = array(
            'googlebot',
            'bingbot',
            'slurp',
            'duckduckbot',
            'baiduspider',
            'yandexbot',
            'sogou',
            'exabot',
            'facebot',
            'ia_archiver',
        );

        $is_known_bot = false;
        foreach ($known_bots as $bot) {
            if (strpos($user_agent_lower, $bot) !== false) {
                $is_known_bot = true;
                // Known bots are less suspicious, but still worth a small score.
                $score += 0.1;
                /* translators: %s: Known bot name */
                $reasons[] = sprintf(__('Known bot detected: %s', 'we-spamfighter'), $bot);
                break;
            }
        }

        // Suspicious bot patterns (not known legitimate bots).
        $suspicious_bot_patterns = array(
            'bot',
            'crawler',
            'spider',
            'scraper',
            'curl',
            'wget',
            'python',
            'java',
            'php',
            'ruby',
            'perl',
            'node',
            'go-http-client',
            'okhttp',
            'httpclient',
            'libwww',
            'lwp-trivial',
            'mechanize',
            'scrapy',
        );

        if (! $is_known_bot) {
            foreach ($suspicious_bot_patterns as $pattern) {
                if (strpos($user_agent_lower, $pattern) !== false) {
                    $score += 0.3;
                    /* translators: %s: Suspicious bot pattern */
                    $reasons[] = sprintf(__('Suspicious bot pattern: %s', 'we-spamfighter'), $pattern);
                    break; // Only count once.
                }
            }
        }

        // Very short user agent (suspicious).
        if (strlen($user_agent) < 20) {
            $score += 0.2;
            $reasons[] = __('Very short user agent string', 'we-spamfighter');
        }

        // Suspicious patterns in user agent.
        $suspicious_patterns = array(
            'spam',
            'hack',
            'test',
            'scraper',
            'automated',
            'script',
            'tool',
        );

        foreach ($suspicious_patterns as $pattern) {
            if (strpos($user_agent_lower, $pattern) !== false) {
                $score += 0.3;
                /* translators: %s: Suspicious pattern found in user agent */
                $reasons[] = sprintf(__('Suspicious pattern in user agent: %s', 'we-spamfighter'), $pattern);
                break;
            }
        }

        // Missing browser identification (no Mozilla, Chrome, Safari, Firefox, etc.).
        $browser_patterns = array('mozilla', 'chrome', 'safari', 'firefox', 'edge', 'opera', 'msie', 'trident');
        $has_browser_pattern = false;
        foreach ($browser_patterns as $browser) {
            if (strpos($user_agent_lower, $browser) !== false) {
                $has_browser_pattern = true;
                break;
            }
        }

        if (! $has_browser_pattern && strlen($user_agent) > 20) {
            // No browser pattern but user agent is long enough to have one.
            $score += 0.2;
            $reasons[] = __('Missing browser identification in user agent', 'we-spamfighter');
        }

        // Suspicious version patterns (too many dots or numbers).
        if (preg_match('/\d{1,2}\.\d{1,2}\.\d{1,2}\.\d{1,2}/', $user_agent)) {
            $score += 0.2;
            $reasons[] = __('Suspicious version pattern in user agent', 'we-spamfighter');
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => array_unique($reasons),
        );
    }

    /**
     * Check content length for anomalies.
     *
     * @param string $text Text to analyze.
     * @return array Check result.
     */
    private static function check_content_length($text)
    {
        $score = 0.0;
        $reasons = array();
        $length = mb_strlen($text);

        // Very short content (likely bot or test submission).
        if ($length > 0 && $length < 10) {
            $score += 0.3;
            /* translators: %d: Content length in characters */
            $reasons[] = sprintf(__('Very short content (%d characters)', 'we-spamfighter'), $length);
        }

        // Extremely long content (possibly spam dump or script injection attempt).
        if ($length > 5000) {
            $score += 0.3;
            /* translators: %d: Content length in characters */
            $reasons[] = sprintf(__('Extremely long content (%d characters)', 'we-spamfighter'), $length);
        }

        // Suspicious length range (often used by bots).
        if ($length >= 100 && $length <= 150) {
            // This is a common bot pattern, but not definitive, so lower score.
            $score += 0.1;
            $reasons[] = __('Suspicious content length (common bot pattern)', 'we-spamfighter');
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => array_unique($reasons),
        );
    }

    /**
     * Check for empty message body when other fields are filled (common spam pattern).
     *
     * @param array $content Structured content.
     * @return array Check result.
     */
    private static function check_empty_message_body($content)
    {
        $score = 0.0;
        $reasons = array();
        $msg_keys = array('your-message', 'message', 'comment', 'msg', 'text', 'nachricht', 'inhalt');
        $other_keys = array('your-name', 'name', 'author', 'your-email', 'email', 'your-subject', 'subject');

        $has_msg_field = false;
        $msg_empty = false;
        $filled_other = 0;

        foreach ($msg_keys as $key) {
            if (array_key_exists($key, $content)) {
                $has_msg_field = true;
                $val = is_array($content[$key]) ? implode('', $content[$key]) : (string) $content[$key];
                $msg_empty = trim($val) === '';
                break;
            }
        }

        if (!$has_msg_field || !$msg_empty) {
            return array('score' => 0.0, 'reasons' => array());
        }

        foreach ($other_keys as $key) {
            if (!empty($content[$key]) && trim((string) $content[$key]) !== '') {
                $filled_other++;
            }
        }

        if ($filled_other >= 2) {
            $score += 0.35;
            $reasons[] = __('Empty message body with name/email/subject filled - common spam pattern', 'we-spamfighter');
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => $reasons,
        );
    }

    /**
     * Check for mixed scripts (different character sets mixed together).
     *
     * @param string $text Text to analyze.
     * @return array Check result.
     */
    private static function check_mixed_scripts($text)
    {
        $score = 0.0;
        $reasons = array();

        // Detect different Unicode script blocks.
        $scripts = array(
            'latin' => 0,
            'cyrillic' => 0,
            'arabic' => 0,
            'hebrew' => 0,
            'chinese' => 0,
            'japanese' => 0,
            'korean' => 0,
            'greek' => 0,
            'devanagari' => 0,
            'thai' => 0,
        );

        // Count characters from different scripts.
        $length = mb_strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1);
            $code = self::get_unicode_code_point($char);

            // Latin (Basic Latin, Latin Extended).
            if (($code >= 0x0000 && $code <= 0x007F) || ($code >= 0x0080 && $code <= 0x024F)) {
                $scripts['latin']++;
            }
            // Cyrillic.
            elseif ($code >= 0x0400 && $code <= 0x04FF) {
                $scripts['cyrillic']++;
            }
            // Arabic.
            elseif ($code >= 0x0600 && $code <= 0x06FF) {
                $scripts['arabic']++;
            }
            // Hebrew.
            elseif ($code >= 0x0590 && $code <= 0x05FF) {
                $scripts['hebrew']++;
            }
            // Chinese (CJK Unified Ideographs).
            elseif ($code >= 0x4E00 && $code <= 0x9FFF) {
                $scripts['chinese']++;
            }
            // Japanese (Hiragana, Katakana).
            elseif (($code >= 0x3040 && $code <= 0x309F) || ($code >= 0x30A0 && $code <= 0x30FF)) {
                $scripts['japanese']++;
            }
            // Korean.
            elseif ($code >= 0xAC00 && $code <= 0xD7AF) {
                $scripts['korean']++;
            }
            // Greek.
            elseif ($code >= 0x0370 && $code <= 0x03FF) {
                $scripts['greek']++;
            }
            // Devanagari (Hindi, etc.).
            elseif ($code >= 0x0900 && $code <= 0x097F) {
                $scripts['devanagari']++;
            }
            // Thai.
            elseif ($code >= 0x0E00 && $code <= 0x0E7F) {
                $scripts['thai']++;
            }
        }

        // Count how many different scripts are present.
        $scripts_present = array_filter($scripts, function ($count) {
            return $count > 0;
        });
        $script_count = count($scripts_present);

        // Mixed scripts (more than one script type) is suspicious.
        if ($script_count > 1) {
            // Get script names for the reason.
            $script_names = array();
            foreach ($scripts as $name => $count) {
                if ($count > 0) {
                    $script_names[] = ucfirst($name);
                }
            }

            if ($script_count >= 3) {
                $score += 0.4;
                /* translators: %s: List of detected scripts */
                $reasons[] = sprintf(__('Multiple scripts detected (%s) - strong spam indicator', 'we-spamfighter'), implode(', ', $script_names));
            } else {
                $score += 0.2;
                /* translators: %s: List of detected scripts */
                $reasons[] = sprintf(__('Mixed scripts detected (%s)', 'we-spamfighter'), implode(', ', $script_names));
            }
        }

        // Cyrillic mixed with Latin is very common in spam.
        if ($scripts['cyrillic'] > 0 && $scripts['latin'] > 0) {
            $total_chars = $scripts['cyrillic'] + $scripts['latin'];
            $cyrillic_ratio = $scripts['cyrillic'] / $total_chars;

            // If there's significant Cyrillic mixed with Latin, it's very suspicious.
            if ($cyrillic_ratio > 0.1 && $cyrillic_ratio < 0.9) {
                $score += 0.3;
                $reasons[] = __('Cyrillic mixed with Latin characters (common spam pattern)', 'we-spamfighter');
            }
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => array_unique($reasons),
        );
    }

    /**
     * Get Unicode code point for a character.
     *
     * @param string $char Single character.
     * @return int Unicode code point.
     */
    private static function get_unicode_code_point($char)
    {
        $code = unpack('N', mb_convert_encoding($char, 'UCS-4BE', 'UTF-8'));
        return isset($code[1]) ? $code[1] : 0;
    }

    /**
     * Check for Unicode anomalies (zero-width spaces, control characters, etc.).
     *
     * @param string $text Text to analyze.
     * @return array Check result.
     */
    private static function check_unicode_anomalies($text)
    {
        $score = 0.0;
        $reasons = array();

        // Zero-width characters (often used for obfuscation).
        $zero_width_chars = array(
            "\xE2\x80\x8B", // Zero-width space
            "\xE2\x80\x8C", // Zero-width non-joiner
            "\xE2\x80\x8D", // Zero-width joiner
            "\xE2\x80\x8E", // Left-to-right mark
            "\xE2\x80\x8F", // Right-to-left mark
            "\xEF\xBB\xBF", // Zero-width no-break space (BOM)
        );

        foreach ($zero_width_chars as $char) {
            if (strpos($text, $char) !== false) {
                $score += 0.3;
                $reasons[] = __('Zero-width characters detected (obfuscation attempt)', 'we-spamfighter');
                break; // Only count once.
            }
        }

        // Control characters (except common ones like newline, tab).
        $control_char_count = 0;
        $length = mb_strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1);
            $code = self::get_unicode_code_point($char);

            // Control characters (0x0000-0x001F, except common ones) and DELETE (0x007F).
            if (($code >= 0x0000 && $code <= 0x001F && !in_array($code, array(0x0009, 0x000A, 0x000D))) || $code === 0x007F) {
                $control_char_count++;
            }
        }

        if ($control_char_count > 0) {
            $score += 0.3;
            /* translators: %d: Number of control characters */
            $reasons[] = sprintf(__('Control characters detected (%d)', 'we-spamfighter'), $control_char_count);
        }

        // Lookalike characters (homoglyphs) - using Unicode characters that look like ASCII.
        // This is more complex, but we can check for suspicious Unicode ranges.
        $homoglyph_patterns = array(
            // Cyrillic lookalikes for Latin.
            '/[авекмнорстух]/u', // Cyrillic letters that look like Latin (a, e, k, m, h, o, p, c, t, y, x)
        );

        foreach ($homoglyph_patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $score += 0.2;
                $reasons[] = __('Suspicious character lookalikes detected (homoglyph attack)', 'we-spamfighter');
                break;
            }
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => array_unique($reasons),
        );
    }

    /**
     * Check if content is only numbers or only letters.
     *
     * @param string $text Text to analyze.
     * @return array Check result.
     */
    private static function check_numbers_letters_only($text)
    {
        $score = 0.0;
        $reasons = array();

        // Remove whitespace and punctuation for analysis.
        $clean_text = preg_replace('/[\s\p{P}]/u', '', $text);

        if (empty($clean_text)) {
            return array(
                'score'   => 0.0,
                'reasons' => array(),
            );
        }

        // Check if only numbers.
        if (preg_match('/^[0-9]+$/', $clean_text) && mb_strlen($clean_text) > 5) {
            $score += 0.3;
            /* translators: %d: Number of digits */
            $reasons[] = sprintf(__('Content contains only numbers (%d digits)', 'we-spamfighter'), mb_strlen($clean_text));
        }

        // Check if only letters (no numbers, no punctuation after cleaning).
        if (preg_match('/^[a-zA-Z]+$/u', $clean_text) && mb_strlen($clean_text) > 20) {
            // This is less suspicious, but still worth noting for very long strings.
            if (mb_strlen($clean_text) > 100) {
                $score += 0.2;
                $reasons[] = __('Content contains only letters (no numbers or punctuation)', 'we-spamfighter');
            }
        }

        // Check if content is only repeating characters (e.g., "aaaaa" or "11111").
        if (mb_strlen($clean_text) > 5) {
            $first_char = mb_substr($clean_text, 0, 1);
            if (preg_match('/^' . preg_quote($first_char, '/') . '+$/u', $clean_text)) {
                $score += 0.4;
                $reasons[] = __('Content is only repeating characters', 'we-spamfighter');
            }
        }

        // Very short alphanumeric-only content (e.g. "dfnrrm", "4tm1lq") - common bot placeholder.
        if (preg_match('/^[a-zA-Z0-9]+$/u', $clean_text) && mb_strlen($clean_text) >= 4 && mb_strlen($clean_text) <= 12) {
            $score += 0.35;
            /* translators: %d: Content length in characters */
            $reasons[] = sprintf(__('Very short alphanumeric-only content (%d chars) - common bot pattern', 'we-spamfighter'), mb_strlen($clean_text));
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => array_unique($reasons),
        );
    }

    /**
     * Check for IP addresses in content (not in URLs).
     *
     * @param string $text Text to analyze.
     * @return array Check result.
     */
    private static function check_ip_in_content($text)
    {
        $score = 0.0;
        $reasons = array();

        // Find IP addresses (but exclude those in URLs).
        // First, remove URLs to avoid false positives.
        $text_without_urls = preg_replace('/https?:\/\/[^\s<>"\'\]\[\)]+/i', '', $text);

        // Look for IP address patterns (IPv4).
        if (preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $text_without_urls, $matches)) {
            $ip_count = count($matches[0]);

            if ($ip_count > 0) {
                // Validate IP addresses.
                $valid_ips = 0;
                foreach ($matches[0] as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        $valid_ips++;
                    }
                }

                if ($valid_ips > 0) {
                    if ($valid_ips >= 2) {
                        $score += 0.4;
                        /* translators: %d: Number of IP addresses */
                        $reasons[] = sprintf(__('Multiple IP addresses in content (%d)', 'we-spamfighter'), $valid_ips);
                    } else {
                        $score += 0.2;
                        $reasons[] = __('IP address found in content (not in URL)', 'we-spamfighter');
                    }
                }
            }
        }

        // Check for suspicious IP patterns (private/localhost IPs might be less suspicious).
        if (preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $text_without_urls, $matches)) {
            foreach ($matches[0] as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    // Private IP ranges are less suspicious (might be legitimate).
                    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        // Private IP, less suspicious but still note it.
                        $score += 0.1;
                        $reasons[] = __('Private IP address in content', 'we-spamfighter');
                    }
                }
            }
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => array_unique($reasons),
        );
    }

    /**
     * Check for repeated multilingual content (same message in multiple languages).
     *
     * @param string $text Text to analyze.
     * @return array Check result.
     */
    private static function check_repeated_multilingual($text)
    {
        $score = 0.0;
        $reasons = array();
        $text_length = mb_strlen($text);

        // Check for common separators used in multilingual spam (***, ---, ===, etc.).
        $separators = array('***', '---', '===', '___', '###', '///');
        $separator_count = 0;
        $separator_found = '';

        foreach ($separators as $sep) {
            $count = substr_count($text, $sep);
            if ($count > 0) {
                $separator_count += $count;
                if (empty($separator_found)) {
                    $separator_found = $sep;
                }
            }
        }

        // If we find separator patterns, it's likely multilingual spam.
        if ($separator_count >= 2) {
            $score += 0.4;
            /* translators: 1: Number of separator instances, 2: Separator string */
            $reasons[] = sprintf(__('Repeated separators detected (%1$d instances of "%2$s") - common in multilingual spam', 'we-spamfighter'), $separator_count, $separator_found);
        } elseif ($separator_count >= 1) {
            $score += 0.2;
            /* translators: %s: Separator pattern */
            $reasons[] = sprintf(__('Separator pattern detected ("%s") - possible multilingual spam', 'we-spamfighter'), $separator_found);
        }

        // Check for repeated URLs (same URL multiple times is suspicious for multilingual spam).
        preg_match_all('/https?:\/\/[^\s<>"\'\]\[\)]+/i', $text, $urls);
        $url_count = count($urls[0]);
        if ($url_count > 1) {
            $unique_urls = array_unique($urls[0]);
            $unique_count = count($unique_urls);

            // If we have multiple URLs but few unique ones, it's repeated (multilingual spam pattern).
            if ($url_count >= 3 && $unique_count <= 2) {
                $score += 0.3;
                /* translators: 1: Total URL count, 2: Unique URL count */
                $reasons[] = sprintf(__('Repeated URLs detected (%1$d total, %2$d unique) - common in multilingual spam', 'we-spamfighter'), $url_count, $unique_count);
            } elseif ($url_count >= 2 && $unique_count === 1) {
                $score += 0.5;
                /* translators: %d: Number of times the same URL was repeated */
                $reasons[] = sprintf(__('Same URL repeated %d times - strong multilingual spam indicator', 'we-spamfighter'), $url_count);
            }
        }

        // Check for suspiciously long content with repetitive structure (multilingual spam often has this).
        $sections = array();
        $section_count = 0;
        if ($text_length > 500) {
            // Split by common separators and check for similar length sections.
            $sections = preg_split('/\*{3,}|\-{3,}|={3,}/', $text);
            $sections = array_filter($sections, function ($section) {
                return mb_strlen(trim($section)) > 50; // Only count substantial sections.
            });
            $section_count = count($sections);

            if ($section_count >= 3) {
                // Check if sections have similar lengths (multilingual spam often has similar structure).
                $lengths = array_map('mb_strlen', $sections);
                $avg_length = array_sum($lengths) / $section_count;
                $variance = 0;
                foreach ($lengths as $len) {
                    $variance += pow($len - $avg_length, 2);
                }
                $variance = $variance / $section_count;
                $std_dev = sqrt($variance);

                // If sections have similar lengths (low variance), it's suspicious.
                if ($std_dev < ($avg_length * 0.3) && $section_count >= 3) {
                    $score += 0.3;
                    /* translators: %d: Number of sections */
                    $reasons[] = sprintf(__('Multiple sections with similar structure (%d sections) - multilingual spam pattern', 'we-spamfighter'), $section_count);
                }

                // Check for structural similarities (sentence count, paragraph count, etc.).
                // Multilingual spam often has the same structure in each language.
                $sentence_counts = array();
                $paragraph_counts = array();
                foreach ($sections as $section) {
                    // Count sentences (rough estimate: periods, exclamation, question marks).
                    $sentence_count = preg_match_all('/[.!?]+/', $section);
                    $sentence_counts[] = $sentence_count;

                    // Count paragraphs (double line breaks or single line breaks with empty lines).
                    $paragraph_count = preg_match_all('/\n\s*\n|\r\n\s*\r\n/', $section) + 1;
                    $paragraph_counts[] = $paragraph_count;
                }

                // If sentence counts are similar across sections (same structure), it's suspicious.
                if (count($sentence_counts) >= 3) {
                    $avg_sentences = array_sum($sentence_counts) / count($sentence_counts);
                    $sentence_variance = 0;
                    foreach ($sentence_counts as $count) {
                        $sentence_variance += pow($count - $avg_sentences, 2);
                    }
                    $sentence_variance = $sentence_variance / count($sentence_counts);
                    $sentence_std_dev = sqrt($sentence_variance);

                    // If sentence counts are very similar (low variance), it suggests same structure.
                    if ($avg_sentences > 3 && $sentence_std_dev < ($avg_sentences * 0.4)) {
                        $score += 0.2;
                        /* translators: %d: Number of sections */
                        $reasons[] = sprintf(__('Sections have similar sentence structure (%d sections) - suggests multilingual repetition', 'we-spamfighter'), $section_count);
                    }
                }

                // Check for common entities across sections (URLs, phone numbers, brand names, etc.).
                // Extract URLs from each section.
                $section_urls = array();
                foreach ($sections as $idx => $section) {
                    preg_match_all('/https?:\/\/[^\s<>"\'\]\[\)]+/i', $section, $matches);
                    $section_urls[$idx] = $matches[0];
                }

                // If all sections contain URLs and they're similar/identical, it's very suspicious.
                $sections_with_urls = 0;
                $total_unique_urls = array();
                foreach ($section_urls as $urls) {
                    if (!empty($urls)) {
                        $sections_with_urls++;
                        $total_unique_urls = array_merge($total_unique_urls, $urls);
                    }
                }
                $unique_url_count = count(array_unique($total_unique_urls));

                // If most sections have URLs but there are few unique URLs, it's repeated content.
                if ($sections_with_urls >= 2 && $section_count >= 3 && $unique_url_count <= 2) {
                    $score += 0.3;
                    /* translators: 1: Number of sections with URLs, 2: Unique URL count */
                    $reasons[] = sprintf(__('Similar URLs across multiple sections (%1$d sections with URLs, %2$d unique URLs) - multilingual repetition', 'we-spamfighter'), $sections_with_urls, $unique_url_count);
                }

                // Extract potential brand/product names (capitalized words, might appear in multiple languages).
                // This is a simple heuristic: look for capitalized words that appear in multiple sections.
                $section_keywords = array();
                foreach ($sections as $idx => $section) {
                    // Extract capitalized words (likely brand/product names).
                    preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', $section, $matches);
                    $keywords = array_map('strtolower', $matches[0]);
                    // Filter out common words and keep only substantial keywords.
                    $common_words = array('der', 'die', 'das', 'the', 'and', 'or', 'but', 'for', 'with', 'from', 'this', 'that', 'these', 'those', 'have', 'has', 'had', 'was', 'were', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'mais', 'pour', 'avec', 'sur', 'sous', 'dans', 'par');
                    $keywords = array_filter($keywords, function ($kw) use ($common_words) {
                        return mb_strlen($kw) > 3 && !in_array($kw, $common_words, true);
                    });
                    $section_keywords[$idx] = array_unique($keywords);
                }

                // Check if there are common keywords across sections (brand/product names).
                if (count($section_keywords) >= 3) {
                    $common_keywords = array();
                    $all_keywords = array();
                    foreach ($section_keywords as $keywords) {
                        $all_keywords = array_merge($all_keywords, $keywords);
                    }
                    $keyword_counts = array_count_values($all_keywords);

                    // Keywords that appear in at least 2 sections might be brand/product names.
                    foreach ($keyword_counts as $keyword => $count) {
                        if ($count >= 2) {
                            $common_keywords[] = $keyword;
                        }
                    }

                    // If we find common keywords across multiple sections, it suggests repeated content.
                    if (count($common_keywords) >= 2) {
                        $score += 0.2;
                        /* translators: %d: Number of common keywords */
                        $reasons[] = sprintf(__('Common keywords/brand names across sections (%d keywords) - suggests multilingual repetition', 'we-spamfighter'), count($common_keywords));
                    }
                }
            }
        }

        // Check for common multilingual spam patterns (greetings in different languages).
        $multilingual_greetings = array(
            'guten tag',
            'hello',
            'hi',
            'bonjour',
            'hola',
            'ciao',
            'grüße',
            'greetings',
            'salutations',
            'saludos',
        );
        $greeting_count = 0;
        $text_lower = mb_strtolower($text);
        foreach ($multilingual_greetings as $greeting) {
            if (stripos($text_lower, $greeting) !== false) {
                $greeting_count++;
            }
        }

        // If we find multiple greetings in different languages, it's suspicious.
        if ($greeting_count >= 3 && ($separator_count >= 1 || $section_count >= 3)) {
            $score += 0.3;
            /* translators: %d: Number of language greetings detected */
            $reasons[] = sprintf(__('Multiple language greetings detected (%d) combined with repeated structure - multilingual spam', 'we-spamfighter'), $greeting_count);
        }

        return array(
            'score'   => min(1.0, $score),
            'reasons' => array_unique($reasons),
        );
    }
}
