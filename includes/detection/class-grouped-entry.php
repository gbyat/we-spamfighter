<?php

/**
 * Normalize form submissions into a shared field-type grouping for pattern analysis.
 *
 * Integrations (CF7, comments, future Gravity Forms) build the same `_grouped` shape
 * so PatternAnalyzer can run without form-plugin-specific logic.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Detection;

/**
 * Build grouped entry payloads for PatternAnalyzer.
 */
class GroupedEntry
{

    /**
     * Empty grouped bucket structure.
     *
     * @return array<string,array<int,string>>
     */
    public static function empty_grouped()
    {
        return array(
            'text'     => array(),
            'textarea' => array(),
            'email'    => array(),
            'website'  => array(),
            'phone'    => array(),
            'upload'   => array(),
        );
    }

    /**
     * Build entry from Contact Form 7 posted data and form tags.
     *
     * @param array              $posted_data  CF7 posted data.
     * @param \WPCF7_ContactForm $contact_form Form instance.
     * @return array Flat entry with `_grouped` key.
     */
    public static function from_cf7(array $posted_data, $contact_form)
    {
        $entry   = array();
        $grouped = self::empty_grouped();
        $type_map = self::cf7_basetype_map($contact_form);

        $skip_keys = array(
            '_wpcf7',
            '_wpcf7_version',
            '_wpcf7_locale',
            '_wpcf7_unit_tag',
            '_wpcf7_container_post',
            '_wpcf7_posted_data_hash',
        );

        foreach ($posted_data as $name => $value) {
            if (! is_string($name) || '' === $name || in_array($name, $skip_keys, true)) {
                continue;
            }
            if (0 === strpos($name, '_')) {
                continue;
            }

            $flat = self::flatten_scalar($value);
            if ('' === $flat) {
                continue;
            }

            $entry[ $name ] = $flat;
            $bucket           = self::cf7_basetype_to_bucket(isset($type_map[ $name ]) ? $type_map[ $name ] : '');
            $grouped[ $bucket ][] = $flat;
        }

        $entry['_grouped'] = $grouped;

        /**
         * Filters the grouped CF7 entry before pattern analysis.
         *
         * @param array              $entry        Entry with `_grouped`.
         * @param array              $posted_data  Raw posted data.
         * @param \WPCF7_ContactForm $contact_form Form instance.
         */
        return apply_filters('we_spamfighter_grouped_entry_cf7', $entry, $posted_data, $contact_form);
    }

    /**
     * Build entry from WordPress comment data.
     *
     * @param array $commentdata Comment fields from pre_comment_approved.
     * @return array
     */
    public static function from_comment(array $commentdata)
    {
        $entry   = array();
        $grouped = self::empty_grouped();

        $author = isset($commentdata['comment_author']) ? sanitize_text_field((string) $commentdata['comment_author']) : '';
        if ('' !== $author) {
            $entry['author'] = $author;
            $grouped['text'][] = $author;
        }

        $email = isset($commentdata['comment_author_email']) ? sanitize_email((string) $commentdata['comment_author_email']) : '';
        if ('' !== $email && is_email($email)) {
            $entry['email'] = $email;
            $grouped['email'][] = $email;
        }

        $url = isset($commentdata['comment_author_url']) ? esc_url_raw((string) $commentdata['comment_author_url']) : '';
        if ('' !== $url) {
            $entry['url'] = $url;
            $grouped['website'][] = $url;
        }

        $comment = isset($commentdata['comment_content']) ? wp_strip_all_tags((string) $commentdata['comment_content']) : '';
        if ('' !== $comment) {
            $entry['comment'] = $comment;
            $grouped['textarea'][] = $comment;
        }

        $entry['_grouped'] = $grouped;

        /**
         * Filters the grouped comment entry before pattern analysis.
         *
         * @param array $entry       Entry with `_grouped`.
         * @param array $commentdata Original comment data.
         */
        return apply_filters('we_spamfighter_grouped_entry_comment', $entry, $commentdata);
    }

    /**
     * Best-effort grouping from a flat key/value entry (legacy / unknown forms).
     *
     * @param array $flat_entry Field name => string value.
     * @return array
     */
    public static function from_flat(array $flat_entry)
    {
        $entry   = array();
        $grouped = self::empty_grouped();

        foreach ($flat_entry as $key => $value) {
            if ('_grouped' === $key || ! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            $flat = self::flatten_scalar($value);
            if ('' === $flat) {
                continue;
            }

            $entry[ $key ] = $flat;
            $bucket        = self::guess_bucket_from_key_and_value($key, $flat);
            $grouped[ $bucket ][] = $flat;
        }

        $entry['_grouped'] = $grouped;

        /**
         * Filters grouped entry built from a flat field map.
         *
         * @param array $entry      Entry with `_grouped`.
         * @param array $flat_entry Original flat map.
         */
        return apply_filters('we_spamfighter_grouped_entry_flat', $entry, $flat_entry);
    }

    /**
     * Map CF7 basetype to grouped bucket name.
     *
     * @param string $basetype CF7 tag basetype.
     * @return string
     */
    private static function cf7_basetype_to_bucket($basetype)
    {
        switch ($basetype) {
            case 'email':
                return 'email';
            case 'url':
                return 'website';
            case 'textarea':
                return 'textarea';
            case 'tel':
                return 'phone';
            case 'file':
                return 'upload';
            default:
                return 'text';
        }
    }

    /**
     * Build CF7 field name => basetype map.
     *
     * @param \WPCF7_ContactForm|null $contact_form Form.
     * @return array<string,string>
     */
    private static function cf7_basetype_map($contact_form)
    {
        $map = array();
        if (! $contact_form || ! is_object($contact_form) || ! method_exists($contact_form, 'scan_form_tags')) {
            return $map;
        }

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
     * Guess bucket when form metadata is unavailable.
     *
     * @param string $key   Field key.
     * @param string $value Field value.
     * @return string
     */
    private static function guess_bucket_from_key_and_value($key, $value)
    {
        $key_lower = strtolower((string) $key);

        if (is_email($value) || false !== strpos($key_lower, 'email') || false !== strpos($key_lower, 'e-mail')) {
            return 'email';
        }

        if (filter_var($value, FILTER_VALIDATE_URL) || false !== strpos($key_lower, 'url') || false !== strpos($key_lower, 'website')) {
            return 'website';
        }

        if (
            false !== strpos($key_lower, 'message')
            || false !== strpos($key_lower, 'comment')
            || false !== strpos($key_lower, 'body')
            || false !== strpos($key_lower, 'nachricht')
            || strlen($value) > 120
        ) {
            return 'textarea';
        }

        if (false !== strpos($key_lower, 'phone') || false !== strpos($key_lower, 'tel')) {
            return 'phone';
        }

        return 'text';
    }

    /**
     * Flatten a posted scalar or array value to a single string.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    private static function flatten_scalar($value)
    {
        if (is_array($value)) {
            $parts = array();
            foreach ($value as $item) {
                $s = self::flatten_scalar($item);
                if ('' !== $s) {
                    $parts[] = $s;
                }
            }
            return trim(implode(', ', $parts));
        }

        return trim(sanitize_text_field((string) $value));
    }
}
