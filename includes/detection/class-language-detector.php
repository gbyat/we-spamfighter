<?php

/**
 * Simple language detection (without external APIs).
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Detection;

/**
 * Language detector class.
 */
class LanguageDetector
{

    /**
     * Detect language from text content using heuristics.
     *
     * @param array|string $content Content to analyze.
     * @return string Detected language code (2 characters, e.g., 'en', 'de', 'ru').
     */
    public static function detect_language($content)
    {
        // Prepare content text.
        $content_text = self::prepare_content($content);

        if (empty($content_text)) {
            return '';
        }

        // Remove URLs and email addresses to avoid false positives.
        $content_text = preg_replace('/https?:\/\/[^\s]+/', '', $content_text);
        $content_text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '', $content_text);

        // Check for Cyrillic (Russian, Bulgarian, Ukrainian, etc.).
        if (preg_match('/[\x{0400}-\x{04FF}]/u', $content_text)) {
            // Count Cyrillic characters.
            $cyrillic_count = preg_match_all('/[\x{0400}-\x{04FF}]/u', $content_text);
            $total_chars = mb_strlen(preg_replace('/\s+/', '', $content_text));

            // If more than 30% Cyrillic, it's likely Russian or another Cyrillic language.
            if ($total_chars > 0 && ($cyrillic_count / $total_chars) > 0.3) {
                // Check for specific Russian indicators.
                if (preg_match('/[а-яА-ЯёЁ]/u', $content_text)) {
                    return 'ru';
                }
            }
        }

        // Check for Chinese characters.
        if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $content_text)) {
            return 'zh';
        }

        // Check for Japanese characters (Hiragana, Katakana, Kanji).
        if (preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FAF}]/u', $content_text)) {
            return 'ja';
        }

        // Check for Korean characters.
        if (preg_match('/[\x{AC00}-\x{D7AF}]/u', $content_text)) {
            return 'ko';
        }

        // Check for Arabic characters.
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $content_text)) {
            return 'ar';
        }

        // Check for Thai characters.
        if (preg_match('/[\x{0E00}-\x{0E7F}]/u', $content_text)) {
            return 'th';
        }

        // For Latin-based languages, use common word patterns.
        // This is less accurate but better than nothing.
        $lower_text = mb_strtolower($content_text);

        // Common German words/patterns.
        $german_patterns = array(
            '/\b(der|die|das|und|ist|für|auf|mit|zu|den|von|sich|nicht|dem|auch|es|an|werden|aus|ein|einer|eines|einen|einem|einer|wird|wie|im|in|zur|zur|zum|über|dass|kann|dann|wenn|haben|nur|oder|aber|vor|nach|bis|seit|durch|bei|gegen|ohne|um|unter|zwischen|für|während|trotz|wegen|dank|gemäß|entsprechend|bezüglich|hinsichtlich|anlässlich|zufolge|gegenüber|außerhalb|innerhalb|oberhalb|unterhalb|diesseits|jenseits|beiderseits|abseits|längs|entlang|entgegen|gemäß|zuzüglich|einschließlich|ausschließlich|ungeachtet|unbeschadet|vorbehaltlich|zwecks|mittels|vermöge|kraft|laut|zufolge|gemäß|entsprechend|bezüglich|hinsichtlich|anlässlich|zufolge|gegenüber|außerhalb|innerhalb|oberhalb|unterhalb|diesseits|jenseits|beiderseits|abseits|längs|entlang|entgegen|gemäß|zuzüglich|einschließlich|ausschließlich|ungeachtet|unbeschadet|vorbehaltlich|zwecks|mittels|vermöge|kraft|laut)\b/u',
            '/\b(ich|du|er|sie|es|wir|ihr|sie)\b/u',
            '/ä|ö|ü|ß/',
        );
        $german_score = 0;
        foreach ($german_patterns as $pattern) {
            if (preg_match($pattern, $lower_text)) {
                $german_score++;
            }
        }
        if ($german_score >= 2) {
            return 'de';
        }

        // Common French words/patterns.
        $french_patterns = array(
            '/\b(le|la|les|un|une|des|de|du|et|à|dans|pour|sur|avec|sans|par|parmi|pendant|depuis|jusqu\'?à|avant|après|entre|sous|au-dessus|au-dessous|hors|vers|chez|selon|malgré|grâce|à cause|en raison|à l\'?égard|envers|contre|pour|en faveur|au profit|au détriment|à l\'?insu|sans l\'?avis|avec l\'?accord|sans l\'?accord|avec l\'?autorisation|sans l\'?autorisation|avec l\'?approbation|sans l\'?approbation|avec l\'?assentiment|sans l\'?assentiment|avec l\'?aval|sans l\'?aval|avec l\'?onction|sans l\'?onction|avec l\'?assistance|sans l\'?assistance|avec l\'?aide|sans l\'?aide|avec le concours|sans le concours|avec l\'?appui|sans l\'?appui|avec le soutien|sans le soutien|avec l\'?encouragement|sans l\'?encouragement|avec l\'?appui|sans l\'?appui|avec le soutien|sans le soutien|avec l\'?encouragement|sans l\'?encouragement|avec l\'?appui|sans l\'?appui|avec le soutien|sans le soutien|avec l\'?encouragement|sans l\'?encouragement)\b/u',
            '/\b(je|tu|il|elle|nous|vous|ils|elles)\b/u',
            '/[àâäéèêëïîôùûüÿç]/',
        );
        $french_score = 0;
        foreach ($french_patterns as $pattern) {
            if (preg_match($pattern, $lower_text)) {
                $french_score++;
            }
        }
        if ($french_score >= 2) {
            return 'fr';
        }

        // Common Spanish words/patterns.
        $spanish_patterns = array(
            '/\b(el|la|los|las|un|una|unos|unas|y|de|del|en|a|por|para|con|sin|sobre|bajo|entre|hacia|desde|hasta|durante|mediante|según|contra|frente|tras|durante|mediante|según|contra|frente|tras|durante|mediante|según|contra|frente|tras)\b/u',
            '/\b(yo|tú|él|ella|nosotros|vosotros|ellos|ellas)\b/u',
            '/[áéíóúñü¿¡]/',
        );
        $spanish_score = 0;
        foreach ($spanish_patterns as $pattern) {
            if (preg_match($pattern, $lower_text)) {
                $spanish_score++;
            }
        }
        if ($spanish_score >= 2) {
            return 'es';
        }

        // Common Italian words/patterns.
        $italian_patterns = array(
            '/\b(il|la|lo|gli|le|un|una|uno|e|di|del|della|dei|delle|in|a|da|per|con|su|sopra|sotto|tra|fra|durante|mentre|prima|dopo|verso|lungo|attraverso|oltre|entro|oltre|fino|secondo|contro|senza|tranne|eccetto|salvo|oltre|invece|invece di|al posto|a causa|per via|grazie|nonostante|malgrado|benché|sebbene|anche se|pure se|come se|quasi|pressoché|circa|verso|intorno|vicino|lontano|davanti|dietro|accanto|dentro|fuori|sopra|sotto|su|giù|destra|sinistra|avanti|indietro|qui|qua|là|laggiù|dove|dove|dovunque|ovunque|da dove|verso dove|fino|quando|mentre|mentre|finché|fino a quando|dopo|prima|appena|subito|immediatamente|presto|tardi|sempre|mai|spesso|raramente|sempre|mai|spesso|raramente)\b/u',
            '/[àèéìíîòóù]/',
        );
        $italian_score = 0;
        foreach ($italian_patterns as $pattern) {
            if (preg_match($pattern, $lower_text)) {
                $italian_score++;
            }
        }
        if ($italian_score >= 2) {
            return 'it';
        }

        // If no specific pattern matches, check if it's mostly ASCII (likely English or other Latin-based language).
        // Default to English if we can't determine.
        $non_ascii_count = 0;
        $total_chars = mb_strlen($content_text);
        for ($i = 0; $i < $total_chars; $i++) {
            $char = mb_substr($content_text, $i, 1);
            $ord = mb_ord($char);
            // Count non-ASCII characters.
            if ($ord > 127 && $ord !== 0x00A0) { // Exclude non-breaking space.
                $non_ascii_count++;
            }
        }

        // If less than 10% non-ASCII, likely English or similar.
        if ($total_chars > 0 && ($non_ascii_count / $total_chars) < 0.1) {
            return 'en';
        }

        // If we can't determine, return empty string.
        return '';
    }

    /**
     * Minimum character length before locale mismatch uses a language guess.
     */
    const LOCALE_MISMATCH_MIN_CHARS = 50;

    /**
     * Detect language for "mark different language as spam" only.
     *
     * Uses comment body or form free text, excluding name/contact fields, so that
     * foreign-looking names alone do not trigger a mismatch on single-language sites.
     *
     * @param array|string $content Same shape as passed to detect_language().
     * @param int|null     $form_id Optional CF7 form post ID (for filters / future use).
     * @return string Language code or empty if skipped / unknown.
     */
    public static function detect_language_for_locale_mismatch($content, $form_id = null)
    {
        $text = self::get_text_for_locale_mismatch($content, $form_id);
        $text = trim($text);
        $min  = (int) apply_filters('we_spamfighter_locale_mismatch_min_chars', self::LOCALE_MISMATCH_MIN_CHARS, $form_id);
        if ($min < 20) {
            $min = 20;
        }
        if (mb_strlen($text) < $min) {
            return '';
        }

        return self::detect_language($text);
    }

    /**
     * Build text sample for locale mismatch (no identity-only fields).
     *
     * @param array|string $content Raw entry data.
     * @param int|null     $form_id Optional CF7 form post ID.
     * @return string
     */
    private static function get_text_for_locale_mismatch($content, $form_id = null)
    {
        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return '';
        }

        // Comment integration: use body only (author names must not drive language).
        if (! empty($content['comment']) && is_string($content['comment'])) {
            return $content['comment'];
        }

        $prose_only = (bool) apply_filters('we_spamfighter_locale_mismatch_prose_only', true, $content, $form_id);

        $excluded = apply_filters(
            'we_spamfighter_locale_mismatch_excluded_keys',
            array(
                'anrede',
                'titel',
                'salutation',
                'vorname',
                'zuname',
                'firstname',
                'lastname',
                'middlename',
                'name',
                'your-name',
                'your_name',
                'nickname',
                'nachname',
                'surname',
                'givenname',
                'familyname',
                'email',
                'e-mail',
                'emailadresse',
                'mail',
                'your-email',
                'your_email',
                'telefon',
                'tel',
                'phone',
                'telephone',
                'mobile',
                'mobil',
                'fax',
                'handy',
                'website',
                'url',
                'homepage',
                'your-url',
                'your_url',
                'geburtsdatum',
                'geburtstag',
                'birthday',
                'birthdate',
                'birth',
            )
        );

        $excluded = array_map('strtolower', (array) $excluded);

        $parts = array();
        foreach ($content as $key => $value) {
            if (! is_string($key) || '' === $key || is_numeric($key)) {
                continue;
            }
            if (0 === strpos($key, '_')) {
                continue;
            }

            $key_lower = strtolower($key);
            if (0 === strpos($key_lower, 'file-')) {
                continue;
            }
            if (in_array($key_lower, $excluded, true)) {
                continue;
            }

            if ($prose_only && ! self::is_locale_mismatch_prose_field_key($key_lower)) {
                continue;
            }

            if (is_array($value)) {
                $flat  = self::flatten_to_strings($value);
                $value = implode(', ', array_filter($flat));
            } else {
                $value = sanitize_text_field((string) $value);
            }

            if ('' !== $value) {
                $parts[] = $value;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Whether a form field key should contribute text for locale mismatch (CF7-style arrays).
     *
     * @param string $key_lower Lowercase field name.
     * @return bool
     */
    private static function is_locale_mismatch_prose_field_key($key_lower)
    {
        if (false !== strpos($key_lower, 'textarea')) {
            return true;
        }

        $names = apply_filters(
            'we_spamfighter_locale_mismatch_prose_field_names',
            array(
                'your-message',
                'your_message',
                'message',
                'nachricht',
                'mitteilung',
                'meldung',
                'beschreibung',
                'anmerkung',
                'inquiry',
                'details',
                'kommentar',
                'body',
                'notes',
                'note',
                'frage',
                'question',
                'feedback',
                'remarks',
                'remark',
                'additional',
                'additional_information',
                'additional-information',
                'zusaetzliche',
                'zusätzliche',
                'sonstige',
                'sonstiges',
                'bemerkung',
            )
        );

        $names = array_map('strtolower', (array) $names);

        return in_array($key_lower, $names, true);
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
