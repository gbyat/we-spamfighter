<?php

/**
 * CF7 field-type aware heuristics (single-line text vs URL field, etc.).
 *
 * Supplements Contact Form 7 validation without replacing it: [text] fields
 * accept almost any content, so we add spam scores for obvious abuse patterns.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Detection;

/**
 * Analyze posted CF7 data using form tag basetypes.
 */
class Cf7FieldTypeHeuristic
{

    /**
     * Default max length for single-line text fields (characters).
     */
    const DEFAULT_TEXT_LINE_MAX = 400;

    /**
     * Analyze posted data against CF7 form tag definitions.
     *
     * @param array              $posted_data  Raw posted data from submission.
     * @param \WPCF7_ContactForm $contact_form Contact form instance.
     * @param array              $settings     Plugin settings.
     * @return array{score:float,details:array,checks_performed:int,reasons:array}
     */
    public static function analyze(array $posted_data, $contact_form, array $settings = array())
    {
        $defaults = array(
            'score'            => 0.0,
            'details'          => array(),
            'checks_performed' => 0,
            'reasons'          => array(),
        );

        if (! $contact_form || ! is_object($contact_form) || ! method_exists($contact_form, 'scan_form_tags')) {
            return $defaults;
        }

        $enabled = ! isset($settings['enable_cf7_fieldtype_check']) || ! empty($settings['enable_cf7_fieldtype_check']);
        if (! $enabled) {
            return $defaults;
        }

        try {
            return self::analyze_inner($posted_data, $contact_form, $settings, $defaults);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && constant('WP_DEBUG')) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('WE Spamfighterin Cf7FieldTypeHeuristic: ' . $e->getMessage());
            }
            return $defaults;
        }
    }

    /**
     * Inner analysis (throws on unexpected CF7/API issues).
     *
     * @param array              $posted_data  Posted data.
     * @param \WPCF7_ContactForm $contact_form Form.
     * @param array              $settings     Settings.
     * @param array              $defaults     Empty result shape.
     * @return array
     */
    private static function analyze_inner(array $posted_data, $contact_form, array $settings, array $defaults)
    {
        $max_len = isset($settings['cf7_text_line_max_length']) ? (int) $settings['cf7_text_line_max_length'] : self::DEFAULT_TEXT_LINE_MAX;
        if ($max_len < 80) {
            $max_len = 80;
        }

        $type_map = self::build_basetype_map($contact_form);
        if (empty($type_map)) {
            return $defaults;
        }

        $total_score = 0.0;
        $details     = array();
        $reasons     = array();
        $performed   = 0;

        foreach ($posted_data as $name => $value) {
            if (! is_string($name) || '' === $name || is_numeric($name)) {
                continue;
            }
            if (0 === strpos($name, '_')) {
                continue;
            }

            $name_lower = strtolower($name);
            if (0 === strpos($name_lower, 'file-')) {
                continue;
            }

            $basetype = isset($type_map[ $name ]) ? $type_map[ $name ] : '';
            if ('' === $basetype) {
                continue;
            }

            $flat = self::flatten_posted_value($value);
            if ('' === $flat) {
                continue;
            }

            switch ($basetype) {
                case 'text':
                    $r = self::check_text_line_value($flat, $max_len, $name);
                    if ($r['score'] > 0) {
                        $total_score += $r['score'];
                        ++$performed;
                        $details[ $name ] = $r;
                        $reasons           = array_merge($reasons, $r['reasons']);
                    }
                    break;

                case 'url':
                    $r = self::check_url_field_value($flat, $name);
                    if ($r['score'] > 0) {
                        $total_score += $r['score'];
                        ++$performed;
                        $details[ $name ] = $r;
                        $reasons           = array_merge($reasons, $r['reasons']);
                    }
                    break;

                default:
                    break;
            }
        }

        return array(
            'score'            => min(1.0, $total_score),
            'details'          => $details,
            'checks_performed' => $performed,
            'reasons'          => array_values(array_unique($reasons)),
        );
    }

    /**
     * Map field name => CF7 basetype.
     *
     * @param \WPCF7_ContactForm $contact_form Form.
     * @return array<string,string>
     */
    private static function build_basetype_map($contact_form)
    {
        $map  = array();
        $tags = $contact_form->scan_form_tags();
        if (! is_array($tags)) {
            return $map;
        }

        foreach ($tags as $tag) {
            if (! is_object($tag) || empty($tag->name)) {
                continue;
            }
            $bt = isset($tag->basetype) ? (string) $tag->basetype : '';
            if ('' !== $bt) {
                $map[ $tag->name ] = $bt;
            }
        }

        return $map;
    }

    /**
     * Flatten posted value to a single string.
     *
     * @param mixed $value Posted value.
     * @return string
     */
    private static function flatten_posted_value($value)
    {
        if (is_array($value)) {
            $parts = array();
            foreach ($value as $v) {
                $s = self::flatten_posted_value($v);
                if ('' !== $s) {
                    $parts[] = $s;
                }
            }
            return trim(implode(', ', $parts));
        }

        return trim(sanitize_text_field((string) $value));
    }

    /**
     * Rules for [text] and similar single-line fields.
     *
     * @param string $text    Flat value.
     * @param int    $max_len Max characters.
     * @param string $name    Field name (for details).
     * @return array{score:float,reasons:array,basetype:string}
     */
    private static function check_text_line_value($text, $max_len, $name)
    {
        $score    = 0.0;
        $reasons  = array();

        if (mb_strlen($text) > $max_len) {
            $score += 0.25;
            $reasons[] = sprintf(
                /* translators: 1: field name, 2: character limit */
                __('Text field "%1$s" exceeds typical single-line length (%2$d characters)', 'we-spamfighter'),
                $name,
                $max_len
            );
        }

        if (preg_match('/https?:\/\//i', $text) || preg_match('/\bwww\.[a-z0-9]/i', $text)) {
            $score += 0.45;
            $reasons[] = sprintf(
                /* translators: %s: field name */
                __('URL-like content in text field "%s" (unusual for a single line)', 'we-spamfighter'),
                $name
            );
        }

        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $text)) {
            $score += 0.35;
            $reasons[] = sprintf(
                /* translators: %s: field name */
                __('Email-like pattern in text field "%s" (use an email field instead)', 'we-spamfighter'),
                $name
            );
        }

        return array(
            'score'    => min(1.0, $score),
            'reasons'  => $reasons,
            'basetype' => 'text',
        );
    }

    /**
     * Rules for dedicated [url] fields (query strings, redirect patterns).
     *
     * @param string $url  URL value.
     * @param string $name Field name.
     * @return array{score:float,reasons:array,basetype:string}
     */
    private static function check_url_field_value($url, $name)
    {
        $score     = 0.0;
        $reasons   = array();
        $url_trim  = trim($url);

        if ('' === $url_trim) {
            return array(
                'score'    => 0.0,
                'reasons'  => array(),
                'basetype' => 'url',
            );
        }

        $query = '';
        $for_parse = $url_trim;
        if (! preg_match('#^https?://#i', $for_parse)) {
            $for_parse = 'http://' . ltrim($for_parse, '/');
        }
        $q = wp_parse_url($for_parse, PHP_URL_QUERY);
        if (is_string($q) && '' !== $q) {
            $query = $q;
        }

        if (strlen($query) > 80) {
            $score += 0.2;
            $reasons[] = sprintf(
                /* translators: %s: field name */
                __('Long query string in URL field "%s"', 'we-spamfighter'),
                $name
            );
        }

        $q_lower           = strtolower($query);
        $suspicious_params = array(
            'redirect=',
            'url=http',
            'goto=',
            'hs=',
            'token=',
            'next=',
            'return=',
            'return_url=',
        );
        foreach ($suspicious_params as $needle) {
            if (strpos($q_lower, $needle) !== false) {
                $score += 0.3;
                $reasons[] = sprintf(
                    /* translators: 1: field name, 2: matched pattern */
                    __('Suspicious URL parameter pattern in field "%1$s" (%2$s)', 'we-spamfighter'),
                    $name,
                    $needle
                );
                break;
            }
        }

        return array(
            'score'    => min(1.0, $score),
            'reasons'  => $reasons,
            'basetype' => 'url',
        );
    }
}
