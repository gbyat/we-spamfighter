<?php

/**
 * Pattern-based spam detection.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Detection;

/**
 * Pattern analyzer class.
 */
class PatternAnalyzer
{

    /**
     * Analyze entry for spam patterns.
     *
     * @param array $entry Entry data.
     * @return array Analysis result.
     */
    public function analyze($entry, $options = array())
    {
        $options = wp_parse_args(
            is_array($options) ? $options : array(),
            array(
                'duplicate_check_enabled' => true,
                'duplicate_check_timeframe' => 24,
                'similar_domain_message_threshold' => 0.9,
                'similar_domain_message_min_prior_matches' => 1,
                'form_id' => 0,
                'business_terminology_signal_enabled' => true,
                'format_validity_checks_enabled' => false,
            )
        );

        // Apply field exclusion filter (e.g., for campaign/tracking fields).
        $excluded_fields = apply_filters('we_spamfighter_excluded_fields', array());

        // Remove excluded fields from analysis.
        if (!empty($excluded_fields) && is_array($excluded_fields)) {
            foreach ($excluded_fields as $field_name) {
                $excluded_value = isset($entry[$field_name]) && is_scalar($entry[$field_name])
                    ? (string) $entry[$field_name]
                    : '';
                unset($entry[$field_name]);

                // Also remove matching scalar values from grouped buckets if present.
                if (
                    $excluded_value !== ''
                    && isset($entry['_grouped'])
                    && is_array($entry['_grouped'])
                ) {
                    foreach ($entry['_grouped'] as $type => $values) {
                        if (! is_array($values)) {
                            continue;
                        }

                        $entry['_grouped'][ $type ] = array_values(
                            array_filter(
                                $values,
                                function ($value) use ($excluded_value) {
                                    return ! is_scalar($value) || (string) $value !== $excluded_value;
                                }
                            )
                        );
                    }
                }
            }
        }

        $score   = 0;
        $reasons = array();

        // Check for suspicious patterns.
        $checks = array(
            // Text-oriented checks
            'min_words'            => $this->check_min_words($entry),
            'excessive_caps'       => $this->check_excessive_caps($entry),
            'character_repetition' => $this->check_character_repetition($entry),
            'suspicious_keywords'  => $this->check_suspicious_keywords($entry),
            'marketing_outreach_spam' => $this->check_marketing_outreach_spam($entry),
            'suspicious_patterns'  => $this->check_suspicious_patterns($entry),

            // Disallow contact info in single-line text fields
            'url_in_text'          => $this->check_url_in_text_fields($entry),
            'email_in_text'        => $this->check_email_in_text_fields($entry),
            'phone_in_text'        => $this->check_phone_in_text_fields($entry),

            // Length limits for single-line text fields
            'text_length_limits'   => $this->check_text_length_limits($entry),
            'text_min_length'      => $this->check_text_min_length($entry),

            // New advanced checks
            'duplicate_message'    => ! empty($options['duplicate_check_enabled']) ? $this->check_duplicate_message($entry) : array('detected' => false),
            'similar_domain_repeated_message' => ! empty($options['duplicate_check_enabled']) ? $this->check_similar_domain_repeated_message($entry, $options) : array('detected' => false),
            'email_in_message'     => $this->check_email_in_message($entry),
            'all_caps_sentences'   => $this->check_all_caps_sentences($entry),
            'excessive_exclamations' => $this->check_excessive_exclamations($entry),
            'business_terminology' => ! empty($options['business_terminology_signal_enabled']) ? $this->check_business_terminology($entry) : array('detected' => false),

            // Email-specific
            'email_pattern'        => $this->check_email_pattern($entry),
            'email_field_validity' => ! empty($options['format_validity_checks_enabled']) ? $this->check_email_field_validity($entry) : array('detected' => false),

            // URL-specific (runs strict rules)
            'url_pattern'          => $this->check_url_pattern($entry),
            'website_field_validity' => ! empty($options['format_validity_checks_enabled']) ? $this->check_website_field_validity($entry) : array('detected' => false),

            // Generic
            'excessive_links'      => $this->check_excessive_links($entry),
        );

        foreach ($checks as $check_name => $result) {
            if ($result['detected']) {
                $score += $result['score'];
                $reasons[] = $result['reason'];
            }
        }

        // Normalize score to 0-1 range.
        $normalized_score = min($score / 100, 1.0);

        return array(
            'score'   => $normalized_score,
            'is_spam' => $normalized_score >= 0.7,
            'reasons' => $reasons,
            'details' => $checks,
        );
    }

    /**
     * Check for links in textarea fields.
     * Single link = soft warning (can be corrected), multiple = hard spam.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_excessive_links($entry)
    {
        $text_link_count     = 0;
        $textarea_link_count = 0;

        if (isset($entry['_grouped']) && is_array($entry['_grouped'])) {
            $text_values = isset($entry['_grouped']['text']) ? (array) $entry['_grouped']['text'] : array();
            $text_content = implode(' ', $text_values);

            if ($text_content !== '') {
                $text_link_count = $this->count_detected_links($text_content);
            }

            $textarea_values = isset($entry['_grouped']['textarea']) ? (array) $entry['_grouped']['textarea'] : array();
            $textarea_content = implode(' ', $textarea_values);

            if ($textarea_content !== '') {
                $textarea_link_count = $this->count_detected_links($textarea_content);
            }
        }

        // Fallback for legacy entries without grouping: treat long strings as textarea and short as text
        if (!isset($entry['_grouped'])) {
            foreach ($entry as $value) {
                if (!is_string($value) || $value === '') {
                    continue;
                }

                if (strlen($value) <= 120) {
                    $text_link_count += $this->count_detected_links($value);
                } else {
                    $textarea_link_count += $this->count_detected_links($value);
                }
            }
        }

        if (defined('WP_DEBUG') && (bool) constant('WP_DEBUG')) {
            if ($text_link_count > 0) {
                error_log('WE Spamfighter: Link detection (text fields) - Count: ' . $text_link_count);
            }
            if ($textarea_link_count > 0) {
                error_log('WE Spamfighter: Link detection (textarea) - Count: ' . $textarea_link_count);
            }
        }

        // Single-line text fields remain strict
        if ($text_link_count > 0) {
            return array(
                'detected' => true,
                'score'    => 60,
                'reason'   => sprintf('URL found in single-line text fields (%d)', $text_link_count),
            );
        }

        if ($textarea_link_count > 1) {
            return array(
                'detected'     => true,
                'score'        => 20,
                'reason'       => sprintf('Multiple links detected in message (%d)', $textarea_link_count),
                'soft_warning' => true,
            );
        }

        return array('detected' => false);
    }

    /**
     * Enforce sensible max lengths for single-line text fields.
     * Defaults via filters:
     * - we_spamfighter_text_warn_chars (default 120)
     * - we_spamfighter_text_block_chars (default 240)
     * - we_spamfighter_text_warn_words (default 12)
     * - we_spamfighter_text_block_words (default 24)
     *
     * Soft warning at warn thresholds (score 20), stronger at block thresholds (score 40).
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_text_length_limits($entry)
    {
        if (!isset($entry['_grouped']['text']) || !is_array($entry['_grouped']['text'])) {
            return array('detected' => false);
        }

        $text_values = array_filter((array) $entry['_grouped']['text'], function ($v) {
            return is_string($v) && $v !== '';
        });
        if (empty($text_values)) {
            return array('detected' => false);
        }

        $warn_chars  = (int) apply_filters('we_spamfighter_text_warn_chars', 120);
        $block_chars = (int) apply_filters('we_spamfighter_text_block_chars', 240);
        $warn_words  = (int) apply_filters('we_spamfighter_text_warn_words', 12);
        $block_words = (int) apply_filters('we_spamfighter_text_block_words', 24);

        $highest_level = 0; // 0 none, 1 warn, 2 block
        $hit_details   = array();

        foreach ($text_values as $value) {
            $length = strlen($value);
            $words  = preg_split('/\s+/u', trim($value));
            $word_count = is_array($words) ? count(array_filter($words)) : 0;

            if ($length > $block_chars || $word_count > $block_words) {
                $highest_level = max($highest_level, 2);
                $hit_details[] = sprintf('"%s" (%d chars, %d words) over block threshold', mb_substr($value, 0, 50), $length, $word_count);
            } elseif ($length > $warn_chars || $word_count > $warn_words) {
                $highest_level = max($highest_level, 1);
                $hit_details[] = sprintf('"%s" (%d chars, %d words) over warn threshold', mb_substr($value, 0, 50), $length, $word_count);
            }
        }

        if ($highest_level === 0) {
            return array('detected' => false);
        }

        if ($highest_level === 1) {
            return array(
                'detected'     => true,
                'score'        => 20,
                'reason'       => 'Long content in single-line text field (warning)',
                'soft_warning' => true,
                'details'      => $hit_details,
            );
        }

        return array(
            'detected' => true,
            'score'    => 40,
            'reason'   => 'Excessively long content in single-line text field',
            'details'  => $hit_details,
        );
    }

    /**
     * Enforce a minimum length for single-line text fields.
     * Filter: we_spamfighter_text_min_chars (default 3)
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_text_min_length($entry)
    {
        if (!isset($entry['_grouped']['text']) || !is_array($entry['_grouped']['text'])) {
            return array('detected' => false);
        }

        $min_chars = (int) apply_filters('we_spamfighter_text_min_chars', 3);
        if ($min_chars < 1) {
            $min_chars = 1;
        }

        $short_samples = array();
        foreach ((array) $entry['_grouped']['text'] as $value) {
            if (!is_string($value)) {
                continue;
            }
            $normalized = trim(wp_strip_all_tags($value));
            if ($normalized === '') {
                continue;
            }

            $len = strlen($normalized);
            if ($len < $min_chars && $this->is_meaningful_short_text($normalized)) {
                // Do not punish concise but meaningful inputs like "Hi".
                continue;
            }

            $max_low_quality_chars = (int) apply_filters('we_spamfighter_text_low_quality_max_chars', 10);
            $looks_low_quality     = $len <= $max_low_quality_chars && $this->is_low_quality_text_token($normalized);
            if (($len > 0 && $len < $min_chars) || $looks_low_quality) {
                $short_samples[] = sprintf('"%s" (%d chars)', mb_substr($normalized, 0, 20), $len);
            }
        }

        if (empty($short_samples)) {
            return array('detected' => false);
        }

        // Soft warning to allow correction; combines with other signals if needed
        return array(
            'detected'     => true,
            'score'        => 20,
            'reason'       => sprintf('Single-line text appears too short or low-quality (< %d chars)', $min_chars),
            'soft_warning' => true,
            'details'      => $short_samples,
        );
    }

    /**
     * Accept short values that still look meaningful (e.g. "Hi", "OK", "Ja").
     *
     * @param string $text Field value.
     * @return bool
     */
    private function is_meaningful_short_text($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return false;
        }

        // Two or more letters without special noise: likely meaningful short input.
        if (preg_match('/^\p{L}{2,}$/u', $text)) {
            return true;
        }

        // Very short phrase with letters and simple separators can still be valid.
        if (preg_match('/^\p{L}[\p{L}\s\-_.]{1,}$/u', $text) && ! preg_match('/\d/', $text)) {
            return true;
        }

        return false;
    }

    /**
     * Detect compact gibberish-like tokens often seen in low-effort spam.
     *
     * @param string $text Field value.
     * @return bool
     */
    private function is_low_quality_text_token($text)
    {
        $text = trim((string) $text);
        if ($text === '' || preg_match('/\s/u', $text)) {
            return false;
        }

        if (preg_match('/^(.)\1{2,}$/u', $text)) {
            return true; // e.g. "xxxx", "1111"
        }

        if (preg_match('/^(?=.*\p{L})(?=.*\d)[\p{L}\p{N}]{4,}$/u', $text)) {
            return true; // e.g. "ab12cd"
        }

        if (preg_match('/^[\p{N}\W_]+$/u', $text)) {
            return true; // symbols/numbers only
        }

        return false;
    }

    /**
     * Disallow URLs in single-line text fields.
     * One occurrence = soft warning (score 40). Multiple types will sum toward blocking.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_url_in_text_fields($entry)
    {
        if (!isset($entry['_grouped']['text']) || !is_array($entry['_grouped']['text'])) {
            return array('detected' => false);
        }

        $text_values = (array) $entry['_grouped']['text'];
        $content     = implode(' ', $text_values);

        // Whitelist ISO date-time tokens like 2025-11-26|08:00 or 2025-11-26 08:00 or 2025-11-26T08:00
        $iso_dt_pattern = '/\b\d{4}-\d{2}-\d{2}[ T\|]\d{2}:\d{2}\b/';
        $content_sanitized = preg_replace($iso_dt_pattern, '', $content);

        if (!empty($content_sanitized) && $this->count_detected_links($content_sanitized) > 0) {
            return array(
                'detected'     => true,
                'score'        => 40,
                'reason'       => 'URL-like content found in single-line text field',
                'soft_warning' => true,
            );
        }

        return array('detected' => false);
    }

    /**
     * Disallow email addresses in single-line text fields.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_email_in_text_fields($entry)
    {
        if (!isset($entry['_grouped']['text']) || !is_array($entry['_grouped']['text'])) {
            return array('detected' => false);
        }

        $text_values = (array) $entry['_grouped']['text'];
        $content     = implode(' ', $text_values);

        $match_count = preg_match_all('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $content);

        if ($match_count > 0) {
            return array(
                'detected' => true,
                'score'    => 60,
                'reason'   => sprintf('Email address found in non-email single-line text fields (%d)', $match_count),
            );
        }

        return array('detected' => false);
    }

    /**
     * Disallow phone numbers in single-line text fields.
     * Uses a conservative pattern: sequences with 7+ digits allowing separators.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_phone_in_text_fields($entry)
    {
        if (!isset($entry['_grouped']['text']) || !is_array($entry['_grouped']['text'])) {
            return array('detected' => false);
        }

        $text_values = (array) $entry['_grouped']['text'];
        $content     = implode(' ', $text_values);

        // Whitelist ISO date-time tokens like 2025-11-26|08:00 or 2025-11-26 08:00 or 2025-11-26T08:00
        $iso_dt_pattern   = '/\b\d{4}-\d{2}-\d{2}[ T\|]\d{2}:\d{2}\b/';
        $content_sanitized = preg_replace($iso_dt_pattern, '', $content);

        // Matches e.g. +43 660 1234567, (06151) 321509, 0688-205-181, 770 978 0991
        $phone_pattern = '/(?:(?:\+|00)?\d{1,3}[\s.-]?)?(?:\(?\d{2,4}\)?[\s.-]?)?\d(?:[\s.-]?\d){6,}/';
        if (!empty($content_sanitized) && preg_match($phone_pattern, $content_sanitized)) {
            return array(
                'detected'     => true,
                'score'        => 40,
                'reason'       => 'Phone-like number found in non-phone single-line text field',
                'soft_warning' => true,
            );
        }

        return array('detected' => false);
    }

    /**
     * Check that text contains at least a minimum number of words.
     *
     * This is a simple heuristic to block submissions like "asdf qwe" or
     * random characters. We count tokens that look like words (>=2 letters)
     * across all text fields.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_min_words($entry)
    {
        // Allow integrators to change the minimum via filter (default 5)
        $min_words = apply_filters('we_spamfighter_min_words', 5);

        // Prefer grouped textarea inputs (provided by integration)
        $text_values = array();
        if (isset($entry['_grouped']) && is_array($entry['_grouped'])) {
            $grouped     = $entry['_grouped'];
            $text_values = isset($grouped['textarea']) ? (array) $grouped['textarea'] : array();
        }

        // Fallback: if grouping not available, scan all values but only long-ish strings
        if (empty($text_values)) {
            foreach ($entry as $key => $value) {
                if ($key === '_grouped' || ! is_string($value)) {
                    continue;
                }
                if (is_email($value) || filter_var($value, FILTER_VALIDATE_URL)) {
                    continue; // skip non-text
                }
                if (strlen($value) >= 20) { // heuristic for textarea-like input
                    $text_values[] = $value;
                }
            }
        }

        $word_count = 0;
        foreach ($text_values as $value) {
            $tokens = preg_split('/\s+/u', trim($value));
            if (! is_array($tokens)) {
                continue;
            }
            foreach ($tokens as $token) {
                // Count tokens that look like words (>=2 letters). Unicode-aware.
                if (preg_match('/\p{L}{2,}/u', $token)) {
                    $word_count++;
                }
            }
        }

        if ($word_count < $min_words) {
            return array(
                'detected'     => true,
                'score'        => 20,
                'reason'       => sprintf('Not enough words in text fields (%d < %d)', $word_count, $min_words),
                'soft_warning' => true,
            );
        }

        return array('detected' => false);
    }

    /**
     * Check for suspicious keywords.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_suspicious_keywords($entry)
    {
        $content = strtolower($this->get_text_content($entry));

        $spam_keywords = array(
            'viagra',
            'cialis',
            'casino',
            'kasino',
            'kasinot',
            'gambling',
            'slot machine',
            'online betting',
            'jackpot',
            'poker',
            'lottery',
            'winner',
            'click here',
            'buy now',
            'limited time',
            'act now',
            'order now',
            'free money',
            'double your',
            'guarantee',
            'no risk',
            'discount',
            'pharmacy',
            'replica',
            'rolex',
            'weight loss',
            'make money',
            'work from home',
            'earn $',
            'seo service',
            'backlinks',
            'cheap',
        );

        $matches = 0;
        $found_keywords = array();

        foreach ($spam_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $matches++;
                $found_keywords[] = $keyword;
            }
        }

        if ($matches > 0) {
            return array(
                'detected' => true,
                'score'    => min($matches * 15, 50),
                'reason'   => sprintf('Suspicious keywords found: %s', implode(', ', $found_keywords)),
            );
        }

        return array('detected' => false);
    }

    /**
     * Detect typical SEO/sales outreach spam wording in message text.
     * Requires multiple matching phrases to avoid false positives.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_marketing_outreach_spam($entry)
    {
        $message_content = $this->get_textarea_content($entry);
        if ($message_content === '') {
            return array('detected' => false);
        }

        $content = strtolower($message_content);
        $seo_phrases = array(
            '/\bwe offer\b.*\bseo\b/s',
            '/\bseo services?\b/',
            '/\bgoogle rankings?\b/',
            '/\bif you are interested\b/',
            '/\bshare (our|the) price\b/',
            '/\bprice\s*(and|&)\s*proposa[l]?\b/',
            '/\bhappy to share\b.*\bproposal\b/s',
        );

        $seo_hits = 0;
        foreach ($seo_phrases as $pattern) {
            if (preg_match($pattern, $content)) {
                $seo_hits++;
            }
        }

        $min_hits = (int) apply_filters('we_spamfighter_marketing_outreach_min_hits', 2);
        if ($min_hits < 1) {
            $min_hits = 1;
        }

        if ($seo_hits >= $min_hits) {
            return array(
                'detected' => true,
                'score'    => 70,
                'reason'   => 'Marketing outreach spam pattern detected (SEO pitch)',
            );
        }

        // Catch "AI can increase your leads" video outreach style spam.
        $leadgen_phrases = array(
            '/\bdiscover how\b.*\b(ai|our ai)\b/s',
            '/\bskyrocket\b.*\bleads?\b/s',
            '/\bquick video\b/',
            '/\b(youtube\.com\/shorts|youtu\.be\/)\b/',
            '/\bimprove\b.*\bwebsite leads?\b/s',
        );
        $leadgen_hits = 0;
        foreach ($leadgen_phrases as $pattern) {
            if (preg_match($pattern, $content)) {
                $leadgen_hits++;
            }
        }

        $link_count_in_message = $this->count_detected_links($message_content);
        $min_leadgen_hits = (int) apply_filters('we_spamfighter_marketing_leadgen_min_hits', 2);
        if ($min_leadgen_hits < 1) {
            $min_leadgen_hits = 1;
        }

        if ($link_count_in_message > 0 && $leadgen_hits >= $min_leadgen_hits) {
            return array(
                'detected' => true,
                'score'    => 70,
                'reason'   => 'Marketing outreach spam pattern detected (lead-gen pitch)',
            );
        }

        // Catch get-rich-quick / trading-system scams (EN/DE variants).
        $money_scam_phrases = array(
            '/\b\d{1,3}(?:[.,]\d{3})?\s*(€|euro)\s*(pro tag|am tag|per day)\b/u',
            '/\b(geheim(?:e|en)?|secret)\b.*\b(handelssystem|trading system)\b/u',
            '/\bkeine erfahrung nötig\b/u',
            '/\b(top-banker|bankers?)\b/u',
            '/\b(jetzt hier klicken|click here now)\b/u',
            '/\b(kurze zeit|limited time)\b/u',
            '/\bgewinnteam\b/u',
        );
        $money_scam_hits = 0;
        foreach ($money_scam_phrases as $pattern) {
            if (preg_match($pattern, $content)) {
                $money_scam_hits++;
            }
        }
        if ($link_count_in_message > 0 && $money_scam_hits >= 2) {
            return array(
                'detected' => true,
                'score'    => 80,
                'reason'   => 'Marketing outreach spam pattern detected (money scam pitch)',
            );
        }

        // Catch blog/comment backlink spam.
        $comment_spam_phrases = array(
            '/\bthank you (a bunch )?for sharing\b/u',
            '/\bbookmarked\b/u',
            '/\blink exchange\b/u',
            '/\bkeep visiting this website\b/u',
            '/\bmost recent news posted here\b/u',
        );
        $comment_spam_hits = 0;
        foreach ($comment_spam_phrases as $pattern) {
            if (preg_match($pattern, $content)) {
                $comment_spam_hits++;
            }
        }
        $has_website_field = isset($entry['_grouped']['website']) && is_array($entry['_grouped']['website']) && ! empty(array_filter($entry['_grouped']['website']));
        if ($has_website_field && $comment_spam_hits >= 2) {
            return array(
                'detected' => true,
                'score'    => 70,
                'reason'   => 'Marketing outreach spam pattern detected (comment backlink spam)',
            );
        }

        // Catch adult/pharma promo spam in message/text fields.
        $full_text = strtolower($this->get_text_content($entry));
        $adult_pharma_patterns = array(
            '/\badult sites?\b/u',
            '/\bxxx sites?\b/u',
            '/\bmature audiences?\b/u',
            '/\blevitra\b/u',
            '/\bviagra\b/u',
            '/\bcialis\b/u',
            '/\bbuy\s+[a-z0-9-]+\s+online\b/u',
        );
        $adult_pharma_hits = 0;
        foreach ($adult_pharma_patterns as $pattern) {
            if (preg_match($pattern, $full_text)) {
                $adult_pharma_hits++;
            }
        }
        if ($adult_pharma_hits >= 2) {
            return array(
                'detected' => true,
                'score'    => 80,
                'reason'   => 'Marketing outreach spam pattern detected (adult/pharma promo)',
            );
        }

        return array('detected' => false);
    }

    /**
     * Check for excessive capital letters.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_excessive_caps($entry)
    {
        $content = $this->get_text_content($entry);

        if (strlen($content) < 20) {
            return array('detected' => false);
        }

        preg_match_all('/[A-Z]/', $content, $caps);
        preg_match_all('/[a-z]/', $content, $lower);

        $caps_count  = count($caps[0]);
        $lower_count = count($lower[0]);
        $total       = $caps_count + $lower_count;

        if ($total > 0) {
            $caps_ratio = $caps_count / $total;

            if ($caps_ratio > 0.5) {
                return array(
                    'detected' => true,
                    'score'    => 20,
                    'reason'   => sprintf('Excessive capital letters (%.0f%%)', $caps_ratio * 100),
                );
            }
        }

        return array('detected' => false);
    }

    /**
     * Check for suspicious patterns.
     * Only checks text/textarea fields to avoid false positives from phone numbers, etc.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_suspicious_patterns($entry)
    {
        // Only check text/textarea content (not phone, number, or other fields).
        $grouped = array();
        $text_content = '';
        if (isset($entry['_grouped']) && is_array($entry['_grouped'])) {
            $grouped      = $entry['_grouped'];
            $text_values  = isset($grouped['text']) ? (array) $grouped['text'] : array();
            $text_values  = array_merge($text_values, isset($grouped['textarea']) ? (array) $grouped['textarea'] : array());
            $text_content = implode(' ', $text_values);
        }

        // Fallback if no grouped data.
        if (empty($text_content)) {
            $text_content = $this->get_text_content($entry);
        }

        // Remove dedicated phone field values from the text content to avoid double-counting
        if (isset($grouped['phone']) && is_array($grouped['phone'])) {
            foreach ((array) $grouped['phone'] as $phone_value) {
                if (!is_string($phone_value) || $phone_value === '') {
                    continue;
                }
                $text_content = str_replace($phone_value, '', $text_content);
            }
        }

        $patterns = array(
            '/(\w)\1{5,}/'           => 'Excessive character repetition',
            '/<script/i'             => 'Script tag detected',
            '/\[url=/i'              => 'BBCode link detected',
            '/\{link:/i'             => 'Malformed link syntax',
        );

        foreach ($patterns as $pattern => $description) {
            if (preg_match($pattern, $text_content)) {
                return array(
                    'detected' => true,
                    'score'    => 25,
                    'reason'   => $description,
                );
            }
        }

        if ($this->has_mixed_special_character_noise($text_content)) {
            return array(
                'detected' => true,
                'score'    => 25,
                'reason'   => 'Excessive special characters',
            );
        }

        return array(
            'detected' => false,
        );
    }

    /**
     * Check email pattern.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_email_pattern($entry)
    {
        $email = '';

        if (isset($entry['_grouped']['email']) && is_array($entry['_grouped']['email'])) {
            foreach ($entry['_grouped']['email'] as $candidate) {
                if (is_string($candidate) && is_email($candidate)) {
                    $email = $candidate;
                    break;
                }
            }
        }

        // Find email field in flat map.
        if (empty($email)) {
            foreach ($entry as $key => $value) {
                if ('_grouped' === $key) {
                    continue;
                }
                if (is_email($value)) {
                    $email = $value;
                    break;
                }
            }
        }

        if (empty($email)) {
            return array('detected' => false);
        }

        // Check for disposable email domains.
        $disposable_domains = array(
            'tempmail.com',
            'throwaway.email',
            'guerrillamail.com',
            'mailinator.com',
            '10minutemail.com',
            'temp-mail.org',
            'yopmail.com',
            'maildrop.cc',
            'trashmail.com',
        );

        $domain = substr(strrchr($email, '@'), 1);

        if (in_array(strtolower($domain), $disposable_domains, true)) {
            return array(
                'detected' => true,
                'score'    => 40,
                'reason'   => 'Disposable email address detected',
            );
        }

        return array('detected' => false);
    }

    /**
     * Check URL/Website field patterns.
     * Treat URLs with query parameters as a soft signal; also flag shorteners, suspicious TLDs, raw IPs.
     * Only checks actual URL/website fields from user input, not text/textarea fields.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_url_pattern($entry)
    {
        // Only check URL/website fields, not text/textarea content.
        $url_values = array();
        if (isset($entry['_grouped']) && is_array($entry['_grouped'])) {
            $grouped    = $entry['_grouped'];
            $url_values = isset($grouped['website']) ? (array) $grouped['website'] : array();
        }

        if (empty($url_values)) {
            return array('detected' => false);
        }

        $score   = 0;
        $reasons = array();

        foreach ($url_values as $url) {
            if (empty($url) || !is_string($url)) {
                continue;
            }

            // Parameters in URL fields are a weak signal (tracking/referrer links can be legitimate).
            if (strpos($url, '?') !== false) {
                $score    = max($score, 20);
                $reasons[] = 'URL with parameters in website field';
            }

            // Shorteners
            $shorteners = array('bit.ly', 'tinyurl.com', 'goo.gl', 't.co', 'ow.ly', 'is.gd', 'buff.ly', 'adf.ly');
            $host = $this->extract_url_host($url);
            foreach ($shorteners as $s) {
                if ($host !== '' && ($host === $s || substr($host, -strlen('.' . $s)) === '.' . $s)) {
                    $score     = max($score, 60);
                    $reasons[] = 'URL shortener detected (' . $s . ')';
                    break;
                }
            }

            // Suspicious TLDs
            $tlds = array('.xyz', '.top', '.work', '.click', '.link', '.gq', '.ml', '.ga', '.cf', '.tk');
            foreach ($tlds as $tld) {
                if (strtolower(substr($url, -strlen($tld))) === $tld) {
                    $score    = max($score, 50);
                    $reasons[] = 'Suspicious TLD (' . $tld . ')';
                    break;
                }
            }

            // Raw IP address in URL
            if (preg_match('#https?://\d{1,3}(?:\.\d{1,3}){3}#', $url)) {
                $score    = max($score, 60);
                $reasons[] = 'IP address in URL';
            }
        }

        if ($score > 0) {
            return array(
                'detected' => true,
                'score'    => min($score, 100),
                'reason'   => implode(', ', array_unique($reasons)),
                'soft_warning' => $score <= 20,
            );
        }

        return array('detected' => false);
    }

    /**
     * Validate website field(s): must be proper URLs and must not be email addresses.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_website_field_validity($entry)
    {
        if (!isset($entry['_grouped']['website']) || !is_array($entry['_grouped']['website'])) {
            return array('detected' => false);
        }

        $issues  = array();
        $score   = 0;
        foreach ((array) $entry['_grouped']['website'] as $url) {
            if (!is_string($url) || $url === '') {
                continue;
            }

            // Email mistakenly in website field
            if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $url)) {
                $issues[] = 'Email address provided in website field';
                $score    = max($score, 40);
                continue;
            }

            // Invalid URL format (allow missing scheme but must resemble domain.tld)
            $valid = filter_var($url, FILTER_VALIDATE_URL);
            if (!$valid) {
                // Accept bare domains like example.com optionally with path
                if (!preg_match('/^(?:https?:\/\/)?(?:[a-z0-9-]+\.)+[a-z]{2,}(?:\/[\S]*)?$/i', $url)) {
                    $issues[] = 'Invalid website URL format';
                    $score    = max($score, 20);
                }
            }
        }

        if (!empty($issues)) {
            return array(
                'detected'     => true,
                'score'        => min($score, 60),
                'reason'       => implode('; ', array_unique($issues)),
                'soft_warning' => $score <= 20,
            );
        }

        return array('detected' => false);
    }

    /**
     * Validate email field(s): must be valid email and not contain URLs.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_email_field_validity($entry)
    {
        // Collect email-like fields either from grouping or flat scan fallback
        $emails = array();
        if (isset($entry['_grouped']['email']) && is_array($entry['_grouped']['email'])) {
            $emails = (array) $entry['_grouped']['email'];
        } else {
            foreach ($entry as $value) {
                if (is_string($value) && is_email($value)) {
                    $emails[] = $value;
                }
            }
        }

        if (empty($emails)) {
            return array('detected' => false);
        }

        $issues = array();
        $score  = 0;
        foreach ($emails as $email) {
            if (!is_string($email) || $email === '') {
                continue;
            }
            // Contains URL pattern → clearly wrong
            if (preg_match('/https?:\/\//i', $email) || preg_match('/www\./i', $email)) {
                $issues[] = 'URL found in email field';
                $score    = max($score, 40);
            }
            // Validate email format
            if (!is_email($email)) {
                $issues[] = 'Invalid email address format';
                $score    = max($score, 40);
            }
        }

        if (!empty($issues)) {
            return array(
                'detected' => true,
                'score'    => min($score, 60),
                'reason'   => implode('; ', array_unique($issues)),
            );
        }

        return array('detected' => false);
    }

    /**
     * Check for length anomalies.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_length_anomalies($entry)
    {
        foreach ($entry as $value) {
            if (is_string($value) && strlen($value) > 5000) {
                return array(
                    'detected' => true,
                    'score'    => 15,
                    'reason'   => 'Unusually long field content',
                );
            }
        }

        return array('detected' => false);
    }

    /**
     * Check for character repetition.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_character_repetition($entry)
    {
        $content = $this->get_textarea_content($entry);
        if ($content === '') {
            return array('detected' => false);
        }

        // Check for repeated words based on density, not absolute count
        $words = preg_split('/\s+/', $content);
        $total_words = count($words);
        $word_counts = array_count_values($words);

        foreach ($word_counts as $word => $count) {
            if (strlen($word) > 3) {
                // Calculate word density (percentage of total words)
                $density = ($count / $total_words) * 100;

                // Flag if word appears in more than 15% of text (adjustable threshold)
                if ($density > 15) {
                    return array(
                        'detected' => true,
                        'score'    => 15,
                        'reason'   => sprintf('Word "%s" repeated %d times (%.1f%% of text)', $word, $count, $density),
                    );
                }
            }
        }

        return array('detected' => false);
    }

    /**
     * Check for duplicate messages.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_duplicate_message($entry)
    {
        // Only check textarea content for duplicates
        $message_content = '';
        if (isset($entry['_grouped']) && is_array($entry['_grouped'])) {
            $grouped = $entry['_grouped'];
            $message_content = isset($grouped['textarea']) ? implode(' ', (array) $grouped['textarea']) : '';
        }

        if (empty($message_content)) {
            return array('detected' => false);
        }

        // Determine submitter identifier to scope duplicates cautiously
        $submitter_id = '';
        // Prefer email if available
        if (isset($entry['_grouped']['email']) && is_array($entry['_grouped']['email']) && !empty($entry['_grouped']['email'][0])) {
            $submitter_id = strtolower(trim($entry['_grouped']['email'][0]));
        } else {
            // Fallback: scan flat entry for any email-like value
            foreach ($entry as $val) {
                if (is_string($val) && is_email($val)) {
                    $submitter_id = strtolower(trim($val));
                    break;
                }
            }
        }

        // If no email, cautiously fall back to IP if available
        if (empty($submitter_id)) {
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $submitter_id = $ip;
        }

        // Create hash of message content and scope by submitter identifier
        $message_hash = md5(strtolower(trim($message_content)));
        $scope_hash   = md5((string) $submitter_id);
        $duplicate_key = 'we_spamfighter_message_' . $message_hash . '_' . $scope_hash;

        // Check if this exact message was submitted before by the same submitter
        $previous_submission = get_transient($duplicate_key);

        if ($previous_submission) {
            // Lower score to avoid blocking solely on a single duplicate
            return array(
                'detected' => true,
                'score'    => 40,
                'reason'   => 'Identical message submitted previously by same submitter',
            );
        }

        // Store this message hash for 24 hours for this submitter
        $day_in_seconds = defined('DAY_IN_SECONDS') ? (int) constant('DAY_IN_SECONDS') : 86400;
        set_transient($duplicate_key, time(), $day_in_seconds);

        return array('detected' => false);
    }

    /**
     * Detect repeated similar outreach from the same email domain.
     *
     * Hard combined signal: similar message content + same sender domain + repeated
     * within the configured duplicate window.
     *
     * @param array $entry Entry data.
     * @param array $options Analyzer options.
     * @return array
     */
    private function check_similar_domain_repeated_message($entry, $options = array())
    {
        $message_content = $this->get_textarea_content($entry);
        if ($message_content === '') {
            return array('detected' => false);
        }

        $tokens = preg_split('/\s+/u', trim($message_content));
        $token_count = is_array($tokens) ? count(array_filter($tokens)) : 0;
        if (strlen($message_content) < 80 || $token_count < 12) {
            return array('detected' => false);
        }

        $domain = $this->extract_sender_email_domain($entry);
        if ($domain === '') {
            return array('detected' => false);
        }

        $form_id          = isset($options['form_id']) ? absint($options['form_id']) : 0;
        $timeframe_hours  = isset($options['duplicate_check_timeframe']) ? max(1, (int) $options['duplicate_check_timeframe']) : 24;
        $ttl_seconds      = $timeframe_hours * 3600;
        $history_key      = 'we_spamfighter_domain_similar_' . md5($domain . '|' . (string) $form_id);
        $history          = get_transient($history_key);
        $history          = is_array($history) ? $history : array();
        $now              = time();

        $history = array_values(
            array_filter(
                $history,
                function ($item) use ($now, $ttl_seconds) {
                    if (! is_array($item) || empty($item['time'])) {
                        return false;
                    }

                    return ((int) $item['time']) >= ($now - $ttl_seconds);
                }
            )
        );

        $normalized_message = $this->normalize_message_for_similarity($message_content);
        if ($normalized_message === '') {
            return array('detected' => false);
        }

        $base_similarity_threshold = isset($options['similar_domain_message_threshold'])
            ? (float) $options['similar_domain_message_threshold']
            : 0.9;
        $similarity_threshold = (float) apply_filters(
            'we_spamfighter_similar_domain_message_threshold',
            $base_similarity_threshold,
            $domain,
            $form_id
        );
        $base_min_prior_matches = isset($options['similar_domain_message_min_prior_matches'])
            ? (int) $options['similar_domain_message_min_prior_matches']
            : 1;
        $min_prior_matches = (int) apply_filters(
            'we_spamfighter_similar_domain_message_min_prior_matches',
            $base_min_prior_matches,
            $domain,
            $form_id
        );
        if ($similarity_threshold < 0.5) {
            $similarity_threshold = 0.5;
        } elseif ($similarity_threshold > 0.99) {
            $similarity_threshold = 0.99;
        }
        if ($min_prior_matches < 1) {
            $min_prior_matches = 1;
        }

        $similar_matches = 0;
        foreach ($history as $item) {
            if (! isset($item['message']) || ! is_string($item['message'])) {
                continue;
            }

            $similarity = $this->compute_message_similarity($normalized_message, $item['message']);
            if ($similarity >= $similarity_threshold) {
                $similar_matches++;
            }
        }

        $history[] = array(
            'time'    => $now,
            'message' => $normalized_message,
        );
        if (count($history) > 25) {
            $history = array_slice($history, -25);
        }

        set_transient($history_key, $history, $ttl_seconds);

        if ($similar_matches >= $min_prior_matches) {
            return array(
                'detected' => true,
                'score'    => 100,
                'reason'   => 'Repeated similar message from same sender domain',
            );
        }

        return array('detected' => false);
    }

    /**
     * Check for email addresses in message content (not allowed).
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_email_in_message($entry)
    {
        // Get message content
        $message_content = '';
        if (isset($entry['_grouped']) && is_array($entry['_grouped'])) {
            $grouped = $entry['_grouped'];
            $message_content = isset($grouped['textarea']) ? implode(' ', (array) $grouped['textarea']) : '';
        }

        if (empty($message_content)) {
            return array('detected' => false);
        }

        $match_count = preg_match_all('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $message_content);

        if ($match_count <= 1) {
            return array('detected' => false);
        }

        return array(
            'detected'     => true,
            'score'        => 20,
            'reason'       => sprintf('Multiple email addresses found in message (%d)', $match_count),
            'soft_warning' => true,
        );
    }

    /**
     * Check for all-caps sentences.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_all_caps_sentences($entry)
    {
        // Only check textarea content
        $message_content = '';
        if (isset($entry['_grouped']) && is_array($entry['_grouped'])) {
            $grouped = $entry['_grouped'];
            $message_content = isset($grouped['textarea']) ? implode(' ', (array) $grouped['textarea']) : '';
        }

        if (empty($message_content)) {
            return array('detected' => false);
        }

        // Split into sentences
        $sentences = preg_split('/[.!?]+/', $message_content);

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) <= 10) {
                continue;
            }

            // Technical spec snippets (e.g. NOM/MAX, ZE-25-SN, RPM, SR0473) are common
            // in legitimate B2B requests and should not count as "shouting".
            $tokens = preg_split('/\s+/u', $sentence);
            if (! is_array($tokens) || empty($tokens)) {
                continue;
            }

            $technical_tokens = 0;
            $natural_word_tokens = 0;

            foreach ($tokens as $token) {
                $token = trim((string) $token, " \t\n\r\0\x0B,;:()[]{}");
                if ($token === '') {
                    continue;
                }

                $looks_technical = preg_match('/\d/', $token)
                    || preg_match('/^[A-Z0-9][A-Z0-9\/_.:+=-]{1,}$/', $token)
                    || preg_match('/^[A-Z]{2,}\-\d+[A-Z0-9-]*$/', $token);

                if ($looks_technical) {
                    $technical_tokens++;
                    continue;
                }

                if (preg_match('/\p{L}{3,}/u', $token) && ! preg_match('/\d/', $token)) {
                    $natural_word_tokens++;
                }
            }

            if ($natural_word_tokens < 4) {
                continue;
            }

            $token_count = count(array_filter($tokens, function ($token) {
                return trim((string) $token) !== '';
            }));
            if ($token_count <= 0) {
                continue;
            }

            $technical_ratio = $technical_tokens / $token_count;
            if ($technical_ratio >= 0.4) {
                continue;
            }

            // Check if sentence is all caps (excluding punctuation and spaces)
            $clean_sentence = preg_replace('/[^a-zA-Z]/', '', $sentence);
            if (!empty($clean_sentence) && $clean_sentence === strtoupper($clean_sentence)) {
                return array(
                    'detected' => true,
                    'score'    => 25,
                    'reason'   => 'All-caps sentence detected',
                );
            }
        }

        return array('detected' => false);
    }

    /**
     * Check for excessive exclamation marks.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_excessive_exclamations($entry)
    {
        // Only check textarea content
        $message_content = '';
        if (isset($entry['_grouped']) && is_array($entry['_grouped'])) {
            $grouped = $entry['_grouped'];
            $message_content = isset($grouped['textarea']) ? implode(' ', (array) $grouped['textarea']) : '';
        }

        if (empty($message_content)) {
            return array('detected' => false);
        }

        // Check for 5+ exclamation marks in sequence
        if (preg_match('/!{5,}/', $message_content)) {
            return array(
                'detected' => true,
                'score'    => 15,
                'reason'   => 'Excessive exclamation marks (5+ in sequence)',
            );
        }

        return array('detected' => false);
    }

    /**
     * Check for business spam terminology.
     *
     * @param array $entry Entry data.
     * @return array
     */
    private function check_business_terminology($entry)
    {
        // Only check textarea content
        $message_content = '';
        if (isset($entry['_grouped']) && is_array($entry['_grouped'])) {
            $grouped = $entry['_grouped'];
            $message_content = isset($grouped['textarea']) ? implode(' ', (array) $grouped['textarea']) : '';
        }

        if (empty($message_content)) {
            return array('detected' => false);
        }

        $content_lower = strtolower($message_content);

        $business_terms = array(
            'net 30',
            'credit application',
            'purchasing officer',
            'payment term',
            'feasible',
            'dear sales team',
            'kind regards',
            'best regards',
            'ascent resources',
        );

        $matches = 0;
        $found_terms = array();

        foreach ($business_terms as $term) {
            if (strpos($content_lower, $term) !== false) {
                $matches++;
                $found_terms[] = $term;
            }
        }

        if ($matches > 0) {
            return array(
                'detected' => true,
                'score'    => min($matches * 5, 20), // Max 20 points
                'reason'   => sprintf('Business terminology found: %s', implode(', ', $found_terms)),
            );
        }

        return array('detected' => false);
    }

    /**
     * Get all text content from entry.
     *
     * @param array $entry Entry data.
     * @return string
     */
    private function get_text_content($entry)
    {
        if (isset($entry['_grouped']) && is_array($entry['_grouped'])) {
            $grouped = $entry['_grouped'];
            $text_values = array();

            foreach (array('text', 'textarea') as $field_type) {
                if (!isset($grouped[$field_type]) || !is_array($grouped[$field_type])) {
                    continue;
                }

                foreach ($grouped[$field_type] as $value) {
                    if (is_string($value) && $value !== '') {
                        $text_values[] = $value;
                    }
                }
            }

            if (!empty($text_values)) {
                return implode(' ', $text_values);
            }
        }

        $content_parts = array();

        foreach ($entry as $key => $value) {
            // Skip grouped helper structure to avoid counting labels/arrays as text
            if ($key === '_grouped') {
                continue;
            }

            if (is_array($value)) {
                // Flatten only string leaves; do not cast arrays to strings
                $flat = $this->flatten_string_values($value);
                if (!empty($flat)) {
                    $content_parts[] = implode(' ', $flat);
                }
                continue;
            }

            if (is_string($value)) {
                $content_parts[] = $value;
            }
        }

        return implode(' ', $content_parts);
    }

    /**
     * Collect textarea content only for semantically rich checks.
     *
     * @param array $entry Entry data.
     * @return string
     */
    private function get_textarea_content($entry)
    {
        if (isset($entry['_grouped']['textarea']) && is_array($entry['_grouped']['textarea'])) {
            return implode(' ', array_filter($entry['_grouped']['textarea'], function ($value) {
                return is_string($value) && $value !== '';
            }));
        }

        return '';
    }

    /**
     * Recursively collect string leaves from a mixed array value.
     *
     * @param mixed $value
     * @return array
     */
    private function flatten_string_values($value)
    {
        $result = array();
        if (is_array($value)) {
            foreach ($value as $v) {
                if (is_array($v)) {
                    $result = array_merge($result, $this->flatten_string_values($v));
                } elseif (is_string($v)) {
                    $result[] = $v;
                }
            }
        } elseif (is_string($value)) {
            $result[] = $value;
        }
        return $result;
    }

    /**
     * Count URL-like items after normalizing text for false-positive reduction.
     *
     * @param string $content Raw content.
     * @return int
     */
    private function count_detected_links($content)
    {
        if (! is_string($content) || trim($content) === '') {
            return 0;
        }

        $normalized = $this->normalize_text_for_link_detection($content);
        if ($normalized === '') {
            return 0;
        }

        $matches = array();
        preg_match_all(
            '/(?:https?:\/\/[^\s<>"\']+|www\.[^\s<>"\']+|(?<!@)\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\/[^\s<>"\']*)/',
            $normalized,
            $matches
        );

        return isset($matches[0]) && is_array($matches[0]) ? count($matches[0]) : 0;
    }

    /**
     * Normalize content before URL detection.
     *
     * Removes patterns that frequently look like domains but are not links,
     * such as legal company suffixes in German B2B context.
     *
     * @param string $content Raw content.
     * @return string
     */
    private function normalize_text_for_link_detection($content)
    {
        $normalized = strtolower((string) $content);

        // Do not count domains inside email addresses as URL links.
        $normalized = preg_replace('/\b[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,63}\b/', ' ', $normalized);

        // Ignore common legal-form abbreviations that are not URLs.
        $normalized = preg_replace('/\bco\.kg\b/', ' ', $normalized);
        $normalized = preg_replace('/\bgmbh\s*&\s*co\.kg\b/', ' ', $normalized);
        $normalized = preg_replace('/\bse\s*&\s*co\.kg\b/', ' ', $normalized);
        $normalized = preg_replace('/\bag\s*&\s*co\.kg\b/', ' ', $normalized);
        $normalized = preg_replace('/\bfa\.[a-z0-9-]+\b/', ' ', $normalized);

        $normalized = preg_replace('/\s+/u', ' ', (string) $normalized);
        return trim((string) $normalized);
    }

    /**
     * Extract sender email domain from grouped or flat entry values.
     *
     * @param array $entry Entry data.
     * @return string
     */
    private function extract_sender_email_domain($entry)
    {
        $emails = array();
        if (isset($entry['_grouped']['email']) && is_array($entry['_grouped']['email'])) {
            $emails = (array) $entry['_grouped']['email'];
        } else {
            foreach ($entry as $value) {
                if (is_string($value) && is_email($value)) {
                    $emails[] = $value;
                }
            }
        }

        foreach ($emails as $email) {
            if (! is_string($email) || ! is_email($email)) {
                continue;
            }

            $domain = substr(strrchr(strtolower(trim($email)), '@'), 1);
            if ($domain !== '' && $domain !== false) {
                return sanitize_text_field($domain);
            }
        }

        return '';
    }

    /**
     * Normalize message text for similarity comparison.
     *
     * @param string $message Message text.
     * @return string
     */
    private function normalize_message_for_similarity($message)
    {
        $message = strtolower((string) $message);
        $message = preg_replace('/https?:\/\/\S+/i', '', $message);
        $message = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', '', $message);
        $message = preg_replace('/\s+/u', ' ', (string) $message);

        return trim((string) $message);
    }

    /**
     * Compute similarity score between 0.0 and 1.0.
     *
     * @param string $left Left message.
     * @param string $right Right message.
     * @return float
     */
    private function compute_message_similarity($left, $right)
    {
        if ($left === '' || $right === '') {
            return 0.0;
        }

        similar_text($left, $right, $percent);
        return max(0.0, min(((float) $percent) / 100.0, 1.0));
    }

    /**
     * Detect mixed special-character noise while ignoring signature separators.
     *
     * @param string $text Content text.
     * @return bool
     */
    private function has_mixed_special_character_noise($text)
    {
        if (! is_string($text) || trim($text) === '') {
            return false;
        }

        $matches = array();
        preg_match_all('/[^\w\s]{5,}/u', $text, $matches);
        if (empty($matches[0]) || ! is_array($matches[0])) {
            return false;
        }

        foreach ($matches[0] as $chunk) {
            $chunk = (string) $chunk;
            if ($chunk === '') {
                continue;
            }

            // Ignore common signature separators, e.g. "-----", "_____", "=====".
            if (preg_match('/^([\-_=*])\1{4,}$/', $chunk)) {
                continue;
            }

            $unique_specials = array_unique(preg_split('//u', $chunk, -1, PREG_SPLIT_NO_EMPTY));
            if (is_array($unique_specials) && count($unique_specials) >= 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract normalized host from URL-like value.
     *
     * @param string $url URL or domain-like value.
     * @return string
     */
    private function extract_url_host($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $candidate = $url;
        if (! preg_match('#^https?://#i', $candidate)) {
            $candidate = 'https://' . ltrim($candidate, '/');
        }

        $host = wp_parse_url($candidate, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return '';
        }

        return strtolower($host);
    }
}
