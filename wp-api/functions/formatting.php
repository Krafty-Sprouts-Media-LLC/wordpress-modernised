<?php

/**
 * Text and argument helpers: keys, titles and slugs ("Hello World" becomes
 * hello-world), accents, UTF-8 checks, HTML escaping, and wp_parse_args().
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/formatting.php,
 * functions.php and compat.php.
 */

/**
 * Sanitizes a string key.
 *
 * Keys are used as internal identifiers. Lowercase alphanumeric characters,
 * dashes, and underscores are allowed.
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 *
 * @param  string  $key  String key.
 * @return string Sanitized key.
 *
 * @phpstan-return lowercase-string
 */
function sanitize_key($key)
{
    $sanitized_key = '';

    if (is_scalar($key)) {
        $sanitized_key = strtolower($key);
        $sanitized_key = preg_replace('/[^a-z0-9_\-]/', '', $sanitized_key);
    }

    /**
     * Filters a sanitized key string.
     *
     * @since WP-3.0.0
     *
     * @param  string  $sanitized_key  Sanitized key.
     * @param  string  $key  The key prior to sanitization.
     */
    return apply_filters('sanitize_key', $sanitized_key, $key);
}

/**
 * Sanitizes a string into a slug, which can be used in URLs or HTML attributes.
 *
 * By default, converts accent characters to ASCII characters and further
 * limits the output to alphanumeric characters, underscore (_) and dash (-)
 * through the {@see 'sanitize_title'} filter.
 *
 * If `$title` is empty and `$fallback_title` is set, the latter will be used.
 *
 * @since WM-0.5.0
 * @since WP-1.0.0
 *
 * @param  string  $title  The string to be sanitized.
 * @param  string  $fallback_title  Optional. A title to use if $title is empty. Default empty.
 * @param  string  $context  Optional. The operation for which the string is sanitized.
 *                           When set to 'save', the string runs through remove_accents().
 *                           Default 'save'.
 * @return string The sanitized string.
 */
function sanitize_title($title, $fallback_title = '', $context = 'save')
{
    $raw_title = $title;

    if ($context === 'save') {
        $title = remove_accents($title);
    }

    /**
     * Filters a sanitized title string.
     *
     * @since WP-1.2.0
     *
     * @param  string  $title  Sanitized title.
     * @param  string  $raw_title  The title prior to sanitization.
     * @param  string  $context  The context for which the title is being sanitized.
     */
    $title = apply_filters('sanitize_title', $title, $raw_title, $context);

    if ($title === '' || $title === false) {
        $title = $fallback_title;
    }

    return $title;
}

/**
 * Sanitizes a title, replacing whitespace and a few other characters with dashes.
 *
 * Limits the output to alphanumeric characters, underscore (_) and dash (-).
 * Whitespace becomes a dash.
 *
 * @since WM-0.5.0
 * @since WP-1.2.0
 *
 * @param  string  $title  The title to be sanitized.
 * @param  string  $raw_title  Optional. Not used. Default empty.
 * @param  string  $context  Optional. The operation for which the string is sanitized.
 *                           When set to 'save', additional entities are converted to hyphens
 *                           or stripped entirely. Default 'display'.
 * @return string The sanitized title.
 */
function sanitize_title_with_dashes($title, $raw_title = '', $context = 'display')
{
    $title = strip_tags($title);
    // Preserve escaped octets.
    $title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
    // Remove percent signs that are not part of an octet.
    $title = str_replace('%', '', $title);
    // Restore octets.
    $title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);

    if (wp_is_valid_utf8($title)) {
        if (function_exists('mb_strtolower')) {
            $title = mb_strtolower($title, 'UTF-8');
        }
        $title = utf8_uri_encode($title, 200);
    }

    $title = strtolower($title);

    if ($context === 'save') {
        // Convert &nbsp, non-breaking hyphen, &ndash, and &mdash to hyphens.
        $title = str_replace(['%c2%a0', '%e2%80%91', '%e2%80%93', '%e2%80%94'], '-', $title);
        // Convert &nbsp, non-breaking hyphen, &ndash, and &mdash HTML entities to hyphens.
        $title = str_replace(['&nbsp;', '&#8209;', '&#160;', '&ndash;', '&#8211;', '&mdash;', '&#8212;'], '-', $title);
        // Convert forward slash to hyphen.
        $title = str_replace('/', '-', $title);

        // Strip these characters entirely.
        $title = str_replace(
            [
                // Soft hyphens.
                '%c2%ad',
                // &iexcl and &iquest.
                '%c2%a1',
                '%c2%bf',
                // Angle quotes.
                '%c2%ab',
                '%c2%bb',
                '%e2%80%b9',
                '%e2%80%ba',
                // Curly quotes.
                '%e2%80%98',
                '%e2%80%99',
                '%e2%80%9c',
                '%e2%80%9d',
                '%e2%80%9a',
                '%e2%80%9b',
                '%e2%80%9e',
                '%e2%80%9f',
                // Bullet.
                '%e2%80%a2',
                // &copy, &reg, &deg, &hellip, and &trade.
                '%c2%a9',
                '%c2%ae',
                '%c2%b0',
                '%e2%80%a6',
                '%e2%84%a2',
                // Acute accents.
                '%c2%b4',
                '%cb%8a',
                '%cc%81',
                '%cd%81',
                // Grave accent, macron, caron.
                '%cc%80',
                '%cc%84',
                '%cc%8c',
                // Non-visible characters that display without a width.
                '%e2%80%8b', // Zero width space.
                '%e2%80%8c', // Zero width non-joiner.
                '%e2%80%8d', // Zero width joiner.
                '%e2%80%8e', // Left-to-right mark.
                '%e2%80%8f', // Right-to-left mark.
                '%e2%80%aa', // Left-to-right embedding.
                '%e2%80%ab', // Right-to-left embedding.
                '%e2%80%ac', // Pop directional formatting.
                '%e2%80%ad', // Left-to-right override.
                '%e2%80%ae', // Right-to-left override.
                '%ef%bb%bf', // Byte order mark.
                '%ef%bf%bc', // Object replacement character.
            ],
            '',
            $title
        );

        // Convert non-visible characters that display with a width to hyphen.
        $title = str_replace(
            [
                '%e2%80%80', // En quad.
                '%e2%80%81', // Em quad.
                '%e2%80%82', // En space.
                '%e2%80%83', // Em space.
                '%e2%80%84', // Three-per-em space.
                '%e2%80%85', // Four-per-em space.
                '%e2%80%86', // Six-per-em space.
                '%e2%80%87', // Figure space.
                '%e2%80%88', // Punctuation space.
                '%e2%80%89', // Thin space.
                '%e2%80%8a', // Hair space.
                '%e2%80%a8', // Line separator.
                '%e2%80%a9', // Paragraph separator.
                '%e2%80%af', // Narrow no-break space.
            ],
            '-',
            $title
        );

        // Convert &times to 'x'.
        $title = str_replace('%c3%97', 'x', $title);
    }

    // Remove HTML entities.
    $title = preg_replace('/&.+?;/', '', $title);
    $title = str_replace('.', '-', $title);

    $title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
    $title = preg_replace('/\s+/', '-', $title);
    $title = preg_replace('|-+|', '-', $title);
    $title = trim($title, '-');

    return $title;
}

/**
 * Converts all accent characters to ASCII characters.
 *
 * If there are no accent characters, then the string given is just returned.
 *
 * **Accent characters converted:**
 *
 * Currency signs:
 *
 * |   Code   | Glyph | Replacement |     Description     |
 * | -------- | ----- | ----------- | ------------------- |
 * | U+00A3   | £     | (empty)     | British Pound sign  |
 * | U+20AC   | €     | E           | Euro sign           |
 *
 * Decompositions for Latin-1 Supplement:
 *
 * |  Code   | Glyph | Replacement |               Description              |
 * | ------- | ----- | ----------- | -------------------------------------- |
 * | U+00AA  | ª     | a           | Feminine ordinal indicator             |
 * | U+00BA  | º     | o           | Masculine ordinal indicator            |
 * | U+00C0  | À     | A           | Latin capital letter A with grave      |
 * | U+00C1  | Á     | A           | Latin capital letter A with acute      |
 * | U+00C2  | Â     | A           | Latin capital letter A with circumflex |
 * | U+00C3  | Ã     | A           | Latin capital letter A with tilde      |
 * | U+00C4  | Ä     | A           | Latin capital letter A with diaeresis  |
 * | U+00C5  | Å     | A           | Latin capital letter A with ring above |
 * | U+00C6  | Æ     | AE          | Latin capital letter AE                |
 * | U+00C7  | Ç     | C           | Latin capital letter C with cedilla    |
 * | U+00C8  | È     | E           | Latin capital letter E with grave      |
 * | U+00C9  | É     | E           | Latin capital letter E with acute      |
 * | U+00CA  | Ê     | E           | Latin capital letter E with circumflex |
 * | U+00CB  | Ë     | E           | Latin capital letter E with diaeresis  |
 * | U+00CC  | Ì     | I           | Latin capital letter I with grave      |
 * | U+00CD  | Í     | I           | Latin capital letter I with acute      |
 * | U+00CE  | Î     | I           | Latin capital letter I with circumflex |
 * | U+00CF  | Ï     | I           | Latin capital letter I with diaeresis  |
 * | U+00D0  | Ð     | D           | Latin capital letter Eth               |
 * | U+00D1  | Ñ     | N           | Latin capital letter N with tilde      |
 * | U+00D2  | Ò     | O           | Latin capital letter O with grave      |
 * | U+00D3  | Ó     | O           | Latin capital letter O with acute      |
 * | U+00D4  | Ô     | O           | Latin capital letter O with circumflex |
 * | U+00D5  | Õ     | O           | Latin capital letter O with tilde      |
 * | U+00D6  | Ö     | O           | Latin capital letter O with diaeresis  |
 * | U+00D8  | Ø     | O           | Latin capital letter O with stroke     |
 * | U+00D9  | Ù     | U           | Latin capital letter U with grave      |
 * | U+00DA  | Ú     | U           | Latin capital letter U with acute      |
 * | U+00DB  | Û     | U           | Latin capital letter U with circumflex |
 * | U+00DC  | Ü     | U           | Latin capital letter U with diaeresis  |
 * | U+00DD  | Ý     | Y           | Latin capital letter Y with acute      |
 * | U+00DE  | Þ     | TH          | Latin capital letter Thorn             |
 * | U+00DF  | ß     | s           | Latin small letter sharp s             |
 * | U+00E0  | à     | a           | Latin small letter a with grave        |
 * | U+00E1  | á     | a           | Latin small letter a with acute        |
 * | U+00E2  | â     | a           | Latin small letter a with circumflex   |
 * | U+00E3  | ã     | a           | Latin small letter a with tilde        |
 * | U+00E4  | ä     | a           | Latin small letter a with diaeresis    |
 * | U+00E5  | å     | a           | Latin small letter a with ring above   |
 * | U+00E6  | æ     | ae          | Latin small letter ae                  |
 * | U+00E7  | ç     | c           | Latin small letter c with cedilla      |
 * | U+00E8  | è     | e           | Latin small letter e with grave        |
 * | U+00E9  | é     | e           | Latin small letter e with acute        |
 * | U+00EA  | ê     | e           | Latin small letter e with circumflex   |
 * | U+00EB  | ë     | e           | Latin small letter e with diaeresis    |
 * | U+00EC  | ì     | i           | Latin small letter i with grave        |
 * | U+00ED  | í     | i           | Latin small letter i with acute        |
 * | U+00EE  | î     | i           | Latin small letter i with circumflex   |
 * | U+00EF  | ï     | i           | Latin small letter i with diaeresis    |
 * | U+00F0  | ð     | d           | Latin small letter Eth                 |
 * | U+00F1  | ñ     | n           | Latin small letter n with tilde        |
 * | U+00F2  | ò     | o           | Latin small letter o with grave        |
 * | U+00F3  | ó     | o           | Latin small letter o with acute        |
 * | U+00F4  | ô     | o           | Latin small letter o with circumflex   |
 * | U+00F5  | õ     | o           | Latin small letter o with tilde        |
 * | U+00F6  | ö     | o           | Latin small letter o with diaeresis    |
 * | U+00F8  | ø     | o           | Latin small letter o with stroke       |
 * | U+00F9  | ù     | u           | Latin small letter u with grave        |
 * | U+00FA  | ú     | u           | Latin small letter u with acute        |
 * | U+00FB  | û     | u           | Latin small letter u with circumflex   |
 * | U+00FC  | ü     | u           | Latin small letter u with diaeresis    |
 * | U+00FD  | ý     | y           | Latin small letter y with acute        |
 * | U+00FE  | þ     | th          | Latin small letter Thorn               |
 * | U+00FF  | ÿ     | y           | Latin small letter y with diaeresis    |
 *
 * Decompositions for Latin Extended-A:
 *
 * |  Code   | Glyph | Replacement |                    Description                    |
 * | ------- | ----- | ----------- | ------------------------------------------------- |
 * | U+0100  | Ā     | A           | Latin capital letter A with macron                |
 * | U+0101  | ā     | a           | Latin small letter a with macron                  |
 * | U+0102  | Ă     | A           | Latin capital letter A with breve                 |
 * | U+0103  | ă     | a           | Latin small letter a with breve                   |
 * | U+0104  | Ą     | A           | Latin capital letter A with ogonek                |
 * | U+0105  | ą     | a           | Latin small letter a with ogonek                  |
 * | U+01006 | Ć     | C           | Latin capital letter C with acute                 |
 * | U+0107  | ć     | c           | Latin small letter c with acute                   |
 * | U+0108  | Ĉ     | C           | Latin capital letter C with circumflex            |
 * | U+0109  | ĉ     | c           | Latin small letter c with circumflex              |
 * | U+010A  | Ċ     | C           | Latin capital letter C with dot above             |
 * | U+010B  | ċ     | c           | Latin small letter c with dot above               |
 * | U+010C  | Č     | C           | Latin capital letter C with caron                 |
 * | U+010D  | č     | c           | Latin small letter c with caron                   |
 * | U+010E  | Ď     | D           | Latin capital letter D with caron                 |
 * | U+010F  | ď     | d           | Latin small letter d with caron                   |
 * | U+0110  | Đ     | D           | Latin capital letter D with stroke                |
 * | U+0111  | đ     | d           | Latin small letter d with stroke                  |
 * | U+0112  | Ē     | E           | Latin capital letter E with macron                |
 * | U+0113  | ē     | e           | Latin small letter e with macron                  |
 * | U+0114  | Ĕ     | E           | Latin capital letter E with breve                 |
 * | U+0115  | ĕ     | e           | Latin small letter e with breve                   |
 * | U+0116  | Ė     | E           | Latin capital letter E with dot above             |
 * | U+0117  | ė     | e           | Latin small letter e with dot above               |
 * | U+0118  | Ę     | E           | Latin capital letter E with ogonek                |
 * | U+0119  | ę     | e           | Latin small letter e with ogonek                  |
 * | U+011A  | Ě     | E           | Latin capital letter E with caron                 |
 * | U+011B  | ě     | e           | Latin small letter e with caron                   |
 * | U+011C  | Ĝ     | G           | Latin capital letter G with circumflex            |
 * | U+011D  | ĝ     | g           | Latin small letter g with circumflex              |
 * | U+011E  | Ğ     | G           | Latin capital letter G with breve                 |
 * | U+011F  | ğ     | g           | Latin small letter g with breve                   |
 * | U+0120  | Ġ     | G           | Latin capital letter G with dot above             |
 * | U+0121  | ġ     | g           | Latin small letter g with dot above               |
 * | U+0122  | Ģ     | G           | Latin capital letter G with cedilla               |
 * | U+0123  | ģ     | g           | Latin small letter g with cedilla                 |
 * | U+0124  | Ĥ     | H           | Latin capital letter H with circumflex            |
 * | U+0125  | ĥ     | h           | Latin small letter h with circumflex              |
 * | U+0126  | Ħ     | H           | Latin capital letter H with stroke                |
 * | U+0127  | ħ     | h           | Latin small letter h with stroke                  |
 * | U+0128  | Ĩ     | I           | Latin capital letter I with tilde                 |
 * | U+0129  | ĩ     | i           | Latin small letter i with tilde                   |
 * | U+012A  | Ī     | I           | Latin capital letter I with macron                |
 * | U+012B  | ī     | i           | Latin small letter i with macron                  |
 * | U+012C  | Ĭ     | I           | Latin capital letter I with breve                 |
 * | U+012D  | ĭ     | i           | Latin small letter i with breve                   |
 * | U+012E  | Į     | I           | Latin capital letter I with ogonek                |
 * | U+012F  | į     | i           | Latin small letter i with ogonek                  |
 * | U+0130  | İ     | I           | Latin capital letter I with dot above             |
 * | U+0131  | ı     | i           | Latin small letter dotless i                      |
 * | U+0132  | Ĳ     | IJ          | Latin capital ligature IJ                         |
 * | U+0133  | ĳ     | ij          | Latin small ligature ij                           |
 * | U+0134  | Ĵ     | J           | Latin capital letter J with circumflex            |
 * | U+0135  | ĵ     | j           | Latin small letter j with circumflex              |
 * | U+0136  | Ķ     | K           | Latin capital letter K with cedilla               |
 * | U+0137  | ķ     | k           | Latin small letter k with cedilla                 |
 * | U+0138  | ĸ     | k           | Latin small letter Kra                            |
 * | U+0139  | Ĺ     | L           | Latin capital letter L with acute                 |
 * | U+013A  | ĺ     | l           | Latin small letter l with acute                   |
 * | U+013B  | Ļ     | L           | Latin capital letter L with cedilla               |
 * | U+013C  | ļ     | l           | Latin small letter l with cedilla                 |
 * | U+013D  | Ľ     | L           | Latin capital letter L with caron                 |
 * | U+013E  | ľ     | l           | Latin small letter l with caron                   |
 * | U+013F  | Ŀ     | L           | Latin capital letter L with middle dot            |
 * | U+0140  | ŀ     | l           | Latin small letter l with middle dot              |
 * | U+0141  | Ł     | L           | Latin capital letter L with stroke                |
 * | U+0142  | ł     | l           | Latin small letter l with stroke                  |
 * | U+0143  | Ń     | N           | Latin capital letter N with acute                 |
 * | U+0144  | ń     | n           | Latin small letter N with acute                   |
 * | U+0145  | Ņ     | N           | Latin capital letter N with cedilla               |
 * | U+0146  | ņ     | n           | Latin small letter n with cedilla                 |
 * | U+0147  | Ň     | N           | Latin capital letter N with caron                 |
 * | U+0148  | ň     | n           | Latin small letter n with caron                   |
 * | U+0149  | ŉ     | n           | Latin small letter n preceded by apostrophe       |
 * | U+014A  | Ŋ     | N           | Latin capital letter Eng                          |
 * | U+014B  | ŋ     | n           | Latin small letter Eng                            |
 * | U+014C  | Ō     | O           | Latin capital letter O with macron                |
 * | U+014D  | ō     | o           | Latin small letter o with macron                  |
 * | U+014E  | Ŏ     | O           | Latin capital letter O with breve                 |
 * | U+014F  | ŏ     | o           | Latin small letter o with breve                   |
 * | U+0150  | Ő     | O           | Latin capital letter O with double acute          |
 * | U+0151  | ő     | o           | Latin small letter o with double acute            |
 * | U+0152  | Œ     | OE          | Latin capital ligature OE                         |
 * | U+0153  | œ     | oe          | Latin small ligature oe                           |
 * | U+0154  | Ŕ     | R           | Latin capital letter R with acute                 |
 * | U+0155  | ŕ     | r           | Latin small letter r with acute                   |
 * | U+0156  | Ŗ     | R           | Latin capital letter R with cedilla               |
 * | U+0157  | ŗ     | r           | Latin small letter r with cedilla                 |
 * | U+0158  | Ř     | R           | Latin capital letter R with caron                 |
 * | U+0159  | ř     | r           | Latin small letter r with caron                   |
 * | U+015A  | Ś     | S           | Latin capital letter S with acute                 |
 * | U+015B  | ś     | s           | Latin small letter s with acute                   |
 * | U+015C  | Ŝ     | S           | Latin capital letter S with circumflex            |
 * | U+015D  | ŝ     | s           | Latin small letter s with circumflex              |
 * | U+015E  | Ş     | S           | Latin capital letter S with cedilla               |
 * | U+015F  | ş     | s           | Latin small letter s with cedilla                 |
 * | U+0160  | Š     | S           | Latin capital letter S with caron                 |
 * | U+0161  | š     | s           | Latin small letter s with caron                   |
 * | U+0162  | Ţ     | T           | Latin capital letter T with cedilla               |
 * | U+0163  | ţ     | t           | Latin small letter t with cedilla                 |
 * | U+0164  | Ť     | T           | Latin capital letter T with caron                 |
 * | U+0165  | ť     | t           | Latin small letter t with caron                   |
 * | U+0166  | Ŧ     | T           | Latin capital letter T with stroke                |
 * | U+0167  | ŧ     | t           | Latin small letter t with stroke                  |
 * | U+0168  | Ũ     | U           | Latin capital letter U with tilde                 |
 * | U+0169  | ũ     | u           | Latin small letter u with tilde                   |
 * | U+016A  | Ū     | U           | Latin capital letter U with macron                |
 * | U+016B  | ū     | u           | Latin small letter u with macron                  |
 * | U+016C  | Ŭ     | U           | Latin capital letter U with breve                 |
 * | U+016D  | ŭ     | u           | Latin small letter u with breve                   |
 * | U+016E  | Ů     | U           | Latin capital letter U with ring above            |
 * | U+016F  | ů     | u           | Latin small letter u with ring above              |
 * | U+0170  | Ű     | U           | Latin capital letter U with double acute          |
 * | U+0171  | ű     | u           | Latin small letter u with double acute            |
 * | U+0172  | Ų     | U           | Latin capital letter U with ogonek                |
 * | U+0173  | ų     | u           | Latin small letter u with ogonek                  |
 * | U+0174  | Ŵ     | W           | Latin capital letter W with circumflex            |
 * | U+0175  | ŵ     | w           | Latin small letter w with circumflex              |
 * | U+0176  | Ŷ     | Y           | Latin capital letter Y with circumflex            |
 * | U+0177  | ŷ     | y           | Latin small letter y with circumflex              |
 * | U+0178  | Ÿ     | Y           | Latin capital letter Y with diaeresis             |
 * | U+0179  | Ź     | Z           | Latin capital letter Z with acute                 |
 * | U+017A  | ź     | z           | Latin small letter z with acute                   |
 * | U+017B  | Ż     | Z           | Latin capital letter Z with dot above             |
 * | U+017C  | ż     | z           | Latin small letter z with dot above               |
 * | U+017D  | Ž     | Z           | Latin capital letter Z with caron                 |
 * | U+017E  | ž     | z           | Latin small letter z with caron                   |
 * | U+017F  | ſ     | s           | Latin small letter long s                         |
 * | U+01A0  | Ơ     | O           | Latin capital letter O with horn                  |
 * | U+01A1  | ơ     | o           | Latin small letter o with horn                    |
 * | U+01AF  | Ư     | U           | Latin capital letter U with horn                  |
 * | U+01B0  | ư     | u           | Latin small letter u with horn                    |
 * | U+01CD  | Ǎ     | A           | Latin capital letter A with caron                 |
 * | U+01CE  | ǎ     | a           | Latin small letter a with caron                   |
 * | U+01CF  | Ǐ     | I           | Latin capital letter I with caron                 |
 * | U+01D0  | ǐ     | i           | Latin small letter i with caron                   |
 * | U+01D1  | Ǒ     | O           | Latin capital letter O with caron                 |
 * | U+01D2  | ǒ     | o           | Latin small letter o with caron                   |
 * | U+01D3  | Ǔ     | U           | Latin capital letter U with caron                 |
 * | U+01D4  | ǔ     | u           | Latin small letter u with caron                   |
 * | U+01D5  | Ǖ     | U           | Latin capital letter U with diaeresis and macron  |
 * | U+01D6  | ǖ     | u           | Latin small letter u with diaeresis and macron    |
 * | U+01D7  | Ǘ     | U           | Latin capital letter U with diaeresis and acute   |
 * | U+01D8  | ǘ     | u           | Latin small letter u with diaeresis and acute     |
 * | U+01D9  | Ǚ     | U           | Latin capital letter U with diaeresis and caron   |
 * | U+01DA  | ǚ     | u           | Latin small letter u with diaeresis and caron     |
 * | U+01DB  | Ǜ     | U           | Latin capital letter U with diaeresis and grave   |
 * | U+01DC  | ǜ     | u           | Latin small letter u with diaeresis and grave     |
 *
 * Decompositions for Latin Extended-B:
 *
 * |   Code   | Glyph | Replacement |                Description                |
 * | -------- | ----- | ----------- | ----------------------------------------- |
 * | U+018F   | Ə     | E           | Latin capital letter Ə                    |
 * | U+0259   | ǝ     | e           | Latin small letter ǝ                      |
 * | U+0218   | Ș     | S           | Latin capital letter S with comma below   |
 * | U+0219   | ș     | s           | Latin small letter s with comma below     |
 * | U+021A   | Ț     | T           | Latin capital letter T with comma below   |
 * | U+021B   | ț     | t           | Latin small letter t with comma below     |
 *
 * Vowels with diacritic (Chinese, Hanyu Pinyin):
 *
 * |   Code   | Glyph | Replacement |                      Description                      |
 * | -------- | ----- | ----------- | ----------------------------------------------------- |
 * | U+0251   | ɑ     | a           | Latin small letter alpha                              |
 * | U+1EA0   | Ạ     | A           | Latin capital letter A with dot below                 |
 * | U+1EA1   | ạ     | a           | Latin small letter a with dot below                   |
 * | U+1EA2   | Ả     | A           | Latin capital letter A with hook above                |
 * | U+1EA3   | ả     | a           | Latin small letter a with hook above                  |
 * | U+1EA4   | Ấ     | A           | Latin capital letter A with circumflex and acute      |
 * | U+1EA5   | ấ     | a           | Latin small letter a with circumflex and acute        |
 * | U+1EA6   | Ầ     | A           | Latin capital letter A with circumflex and grave      |
 * | U+1EA7   | ầ     | a           | Latin small letter a with circumflex and grave        |
 * | U+1EA8   | Ẩ     | A           | Latin capital letter A with circumflex and hook above |
 * | U+1EA9   | ẩ     | a           | Latin small letter a with circumflex and hook above   |
 * | U+1EAA   | Ẫ     | A           | Latin capital letter A with circumflex and tilde      |
 * | U+1EAB   | ẫ     | a           | Latin small letter a with circumflex and tilde        |
 * | U+1EA6   | Ậ     | A           | Latin capital letter A with circumflex and dot below  |
 * | U+1EAD   | ậ     | a           | Latin small letter a with circumflex and dot below    |
 * | U+1EAE   | Ắ     | A           | Latin capital letter A with breve and acute           |
 * | U+1EAF   | ắ     | a           | Latin small letter a with breve and acute             |
 * | U+1EB0   | Ằ     | A           | Latin capital letter A with breve and grave           |
 * | U+1EB1   | ằ     | a           | Latin small letter a with breve and grave             |
 * | U+1EB2   | Ẳ     | A           | Latin capital letter A with breve and hook above      |
 * | U+1EB3   | ẳ     | a           | Latin small letter a with breve and hook above        |
 * | U+1EB4   | Ẵ     | A           | Latin capital letter A with breve and tilde           |
 * | U+1EB5   | ẵ     | a           | Latin small letter a with breve and tilde             |
 * | U+1EB6   | Ặ     | A           | Latin capital letter A with breve and dot below       |
 * | U+1EB7   | ặ     | a           | Latin small letter a with breve and dot below         |
 * | U+1EB8   | Ẹ     | E           | Latin capital letter E with dot below                 |
 * | U+1EB9   | ẹ     | e           | Latin small letter e with dot below                   |
 * | U+1EBA   | Ẻ     | E           | Latin capital letter E with hook above                |
 * | U+1EBB   | ẻ     | e           | Latin small letter e with hook above                  |
 * | U+1EBC   | Ẽ     | E           | Latin capital letter E with tilde                     |
 * | U+1EBD   | ẽ     | e           | Latin small letter e with tilde                       |
 * | U+1EBE   | Ế     | E           | Latin capital letter E with circumflex and acute      |
 * | U+1EBF   | ế     | e           | Latin small letter e with circumflex and acute        |
 * | U+1EC0   | Ề     | E           | Latin capital letter E with circumflex and grave      |
 * | U+1EC1   | ề     | e           | Latin small letter e with circumflex and grave        |
 * | U+1EC2   | Ể     | E           | Latin capital letter E with circumflex and hook above |
 * | U+1EC3   | ể     | e           | Latin small letter e with circumflex and hook above   |
 * | U+1EC4   | Ễ     | E           | Latin capital letter E with circumflex and tilde      |
 * | U+1EC5   | ễ     | e           | Latin small letter e with circumflex and tilde        |
 * | U+1EC6   | Ệ     | E           | Latin capital letter E with circumflex and dot below  |
 * | U+1EC7   | ệ     | e           | Latin small letter e with circumflex and dot below    |
 * | U+1EC8   | Ỉ     | I           | Latin capital letter I with hook above                |
 * | U+1EC9   | ỉ     | i           | Latin small letter i with hook above                  |
 * | U+1ECA   | Ị     | I           | Latin capital letter I with dot below                 |
 * | U+1ECB   | ị     | i           | Latin small letter i with dot below                   |
 * | U+1ECC   | Ọ     | O           | Latin capital letter O with dot below                 |
 * | U+1ECD   | ọ     | o           | Latin small letter o with dot below                   |
 * | U+1ECE   | Ỏ     | O           | Latin capital letter O with hook above                |
 * | U+1ECF   | ỏ     | o           | Latin small letter o with hook above                  |
 * | U+1ED0   | Ố     | O           | Latin capital letter O with circumflex and acute      |
 * | U+1ED1   | ố     | o           | Latin small letter o with circumflex and acute        |
 * | U+1ED2   | Ồ     | O           | Latin capital letter O with circumflex and grave      |
 * | U+1ED3   | ồ     | o           | Latin small letter o with circumflex and grave        |
 * | U+1ED4   | Ổ     | O           | Latin capital letter O with circumflex and hook above |
 * | U+1ED5   | ổ     | o           | Latin small letter o with circumflex and hook above   |
 * | U+1ED6   | Ỗ     | O           | Latin capital letter O with circumflex and tilde      |
 * | U+1ED7   | ỗ     | o           | Latin small letter o with circumflex and tilde        |
 * | U+1ED8   | Ộ     | O           | Latin capital letter O with circumflex and dot below  |
 * | U+1ED9   | ộ     | o           | Latin small letter o with circumflex and dot below    |
 * | U+1EDA   | Ớ     | O           | Latin capital letter O with horn and acute            |
 * | U+1EDB   | ớ     | o           | Latin small letter o with horn and acute              |
 * | U+1EDC   | Ờ     | O           | Latin capital letter O with horn and grave            |
 * | U+1EDD   | ờ     | o           | Latin small letter o with horn and grave              |
 * | U+1EDE   | Ở     | O           | Latin capital letter O with horn and hook above       |
 * | U+1EDF   | ở     | o           | Latin small letter o with horn and hook above         |
 * | U+1EE0   | Ỡ     | O           | Latin capital letter O with horn and tilde            |
 * | U+1EE1   | ỡ     | o           | Latin small letter o with horn and tilde              |
 * | U+1EE2   | Ợ     | O           | Latin capital letter O with horn and dot below        |
 * | U+1EE3   | ợ     | o           | Latin small letter o with horn and dot below          |
 * | U+1EE4   | Ụ     | U           | Latin capital letter U with dot below                 |
 * | U+1EE5   | ụ     | u           | Latin small letter u with dot below                   |
 * | U+1EE6   | Ủ     | U           | Latin capital letter U with hook above                |
 * | U+1EE7   | ủ     | u           | Latin small letter u with hook above                  |
 * | U+1EE8   | Ứ     | U           | Latin capital letter U with horn and acute            |
 * | U+1EE9   | ứ     | u           | Latin small letter u with horn and acute              |
 * | U+1EEA   | Ừ     | U           | Latin capital letter U with horn and grave            |
 * | U+1EEB   | ừ     | u           | Latin small letter u with horn and grave              |
 * | U+1EEC   | Ử     | U           | Latin capital letter U with horn and hook above       |
 * | U+1EED   | ử     | u           | Latin small letter u with horn and hook above         |
 * | U+1EEE   | Ữ     | U           | Latin capital letter U with horn and tilde            |
 * | U+1EEF   | ữ     | u           | Latin small letter u with horn and tilde              |
 * | U+1EF0   | Ự     | U           | Latin capital letter U with horn and dot below        |
 * | U+1EF1   | ự     | u           | Latin small letter u with horn and dot below          |
 * | U+1EF2   | Ỳ     | Y           | Latin capital letter Y with grave                     |
 * | U+1EF3   | ỳ     | y           | Latin small letter y with grave                       |
 * | U+1EF4   | Ỵ     | Y           | Latin capital letter Y with dot below                 |
 * | U+1EF5   | ỵ     | y           | Latin small letter y with dot below                   |
 * | U+1EF6   | Ỷ     | Y           | Latin capital letter Y with hook above                |
 * | U+1EF7   | ỷ     | y           | Latin small letter y with hook above                  |
 * | U+1EF8   | Ỹ     | Y           | Latin capital letter Y with tilde                     |
 * | U+1EF9   | ỹ     | y           | Latin small letter y with tilde                       |
 *
 * German (`de_DE`), German formal (`de_DE_formal`), German (Switzerland) formal (`de_CH`),
 * German (Switzerland) informal (`de_CH_informal`), and German (Austria) (`de_AT`) locales:
 *
 * |   Code   | Glyph | Replacement |               Description               |
 * | -------- | ----- | ----------- | --------------------------------------- |
 * | U+00C4   | Ä     | Ae          | Latin capital letter A with diaeresis   |
 * | U+00E4   | ä     | ae          | Latin small letter a with diaeresis     |
 * | U+00D6   | Ö     | Oe          | Latin capital letter O with diaeresis   |
 * | U+00F6   | ö     | oe          | Latin small letter o with diaeresis     |
 * | U+00DC   | Ü     | Ue          | Latin capital letter U with diaeresis   |
 * | U+00FC   | ü     | ue          | Latin small letter u with diaeresis     |
 * | U+1E9E   | ẞ     | SS          | Latin capital letter sharp s            |
 * | U+00DF   | ß     | ss          | Latin small letter sharp s              |
 *
 * Danish (`da_DK`) locale:
 *
 * |   Code   | Glyph | Replacement |               Description               |
 * | -------- | ----- | ----------- | --------------------------------------- |
 * | U+00C6   | Æ     | Ae          | Latin capital letter AE                 |
 * | U+00E6   | æ     | ae          | Latin small letter ae                   |
 * | U+00D8   | Ø     | Oe          | Latin capital letter O with stroke      |
 * | U+00F8   | ø     | oe          | Latin small letter o with stroke        |
 * | U+00C5   | Å     | Aa          | Latin capital letter A with ring above  |
 * | U+00E5   | å     | aa          | Latin small letter a with ring above    |
 *
 * Catalan (`ca`) locale:
 *
 * |   Code   | Glyph | Replacement |               Description               |
 * | -------- | ----- | ----------- | --------------------------------------- |
 * | U+00B7   | l·l   | ll          | Flown dot (between two Ls)              |
 *
 * Serbian (`sr_RS`) and Bosnian (`bs_BA`) locales:
 *
 * |   Code   | Glyph | Replacement |               Description               |
 * | -------- | ----- | ----------- | --------------------------------------- |
 * | U+0110   | Đ     | DJ          | Latin capital letter D with stroke      |
 * | U+0111   | đ     | dj          | Latin small letter d with stroke        |
 *
 * @since WM-0.5.0
 * @since WP-1.2.1
 * @since WP-4.6.0 Added locale support for `de_CH`, `de_CH_informal`, and `ca`.
 * @since WP-4.7.0 Added locale support for `sr_RS`.
 * @since WP-4.8.0 Added locale support for `bs_BA`.
 * @since WP-5.7.0 Added locale support for `de_AT`.
 * @since WP-6.0.0 Added the `$locale` parameter.
 * @since WP-6.1.0 Added Unicode NFC encoding normalization support.
 * @since WP-7.0.0 Added capital Eszett (U+1E9E) support for German locales.
 *
 * @param  string  $text  Text that might have accent characters.
 * @param  string  $locale  Optional. The locale to use for accent removal. Some character
 *                          replacements depend on the locale being used (e.g. 'de_DE').
 *                          Defaults to the current locale.
 * @return string Filtered string with replaced "nice" characters.
 */
function remove_accents($text, $locale = '')
{
    if (! preg_match('/[\x80-\xff]/', $text)) {
        return $text;
    }

    if (wp_is_valid_utf8($text)) {

        /*
         * Unicode sequence normalization from NFD (Normalization Form Decomposed)
         * to NFC (Normalization Form [Pre]Composed), the encoding used in this function.
         */
        if (function_exists('normalizer_is_normalized')
            && function_exists('normalizer_normalize')
        ) {
            if (! normalizer_is_normalized($text)) {
                $text = normalizer_normalize($text);
            }
        }

        $chars = [
            // Decompositions for Latin-1 Supplement.
            'ª' => 'a',
            'º' => 'o',
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Æ' => 'AE',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ð' => 'D',
            'Ñ' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ý' => 'Y',
            'Þ' => 'TH',
            'ß' => 's',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'æ' => 'ae',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ð' => 'd',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ø' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ý' => 'y',
            'þ' => 'th',
            'ÿ' => 'y',
            'Ø' => 'O',
            // Decompositions for Latin Extended-A.
            'Ā' => 'A',
            'ā' => 'a',
            'Ă' => 'A',
            'ă' => 'a',
            'Ą' => 'A',
            'ą' => 'a',
            'Ć' => 'C',
            'ć' => 'c',
            'Ĉ' => 'C',
            'ĉ' => 'c',
            'Ċ' => 'C',
            'ċ' => 'c',
            'Č' => 'C',
            'č' => 'c',
            'Ď' => 'D',
            'ď' => 'd',
            'Đ' => 'D',
            'đ' => 'd',
            'Ē' => 'E',
            'ē' => 'e',
            'Ĕ' => 'E',
            'ĕ' => 'e',
            'Ė' => 'E',
            'ė' => 'e',
            'Ę' => 'E',
            'ę' => 'e',
            'Ě' => 'E',
            'ě' => 'e',
            'Ĝ' => 'G',
            'ĝ' => 'g',
            'Ğ' => 'G',
            'ğ' => 'g',
            'Ġ' => 'G',
            'ġ' => 'g',
            'Ģ' => 'G',
            'ģ' => 'g',
            'Ĥ' => 'H',
            'ĥ' => 'h',
            'Ħ' => 'H',
            'ħ' => 'h',
            'Ĩ' => 'I',
            'ĩ' => 'i',
            'Ī' => 'I',
            'ī' => 'i',
            'Ĭ' => 'I',
            'ĭ' => 'i',
            'Į' => 'I',
            'į' => 'i',
            'İ' => 'I',
            'ı' => 'i',
            'Ĳ' => 'IJ',
            'ĳ' => 'ij',
            'Ĵ' => 'J',
            'ĵ' => 'j',
            'Ķ' => 'K',
            'ķ' => 'k',
            'ĸ' => 'k',
            'Ĺ' => 'L',
            'ĺ' => 'l',
            'Ļ' => 'L',
            'ļ' => 'l',
            'Ľ' => 'L',
            'ľ' => 'l',
            'Ŀ' => 'L',
            'ŀ' => 'l',
            'Ł' => 'L',
            'ł' => 'l',
            'Ń' => 'N',
            'ń' => 'n',
            'Ņ' => 'N',
            'ņ' => 'n',
            'Ň' => 'N',
            'ň' => 'n',
            'ŉ' => 'n',
            'Ŋ' => 'N',
            'ŋ' => 'n',
            'Ō' => 'O',
            'ō' => 'o',
            'Ŏ' => 'O',
            'ŏ' => 'o',
            'Ő' => 'O',
            'ő' => 'o',
            'Œ' => 'OE',
            'œ' => 'oe',
            'Ŕ' => 'R',
            'ŕ' => 'r',
            'Ŗ' => 'R',
            'ŗ' => 'r',
            'Ř' => 'R',
            'ř' => 'r',
            'Ś' => 'S',
            'ś' => 's',
            'Ŝ' => 'S',
            'ŝ' => 's',
            'Ş' => 'S',
            'ş' => 's',
            'Š' => 'S',
            'š' => 's',
            'Ţ' => 'T',
            'ţ' => 't',
            'Ť' => 'T',
            'ť' => 't',
            'Ŧ' => 'T',
            'ŧ' => 't',
            'Ũ' => 'U',
            'ũ' => 'u',
            'Ū' => 'U',
            'ū' => 'u',
            'Ŭ' => 'U',
            'ŭ' => 'u',
            'Ů' => 'U',
            'ů' => 'u',
            'Ű' => 'U',
            'ű' => 'u',
            'Ų' => 'U',
            'ų' => 'u',
            'Ŵ' => 'W',
            'ŵ' => 'w',
            'Ŷ' => 'Y',
            'ŷ' => 'y',
            'Ÿ' => 'Y',
            'Ź' => 'Z',
            'ź' => 'z',
            'Ż' => 'Z',
            'ż' => 'z',
            'Ž' => 'Z',
            'ž' => 'z',
            'ſ' => 's',
            // Decompositions for Latin Extended-B.
            'Ə' => 'E',
            'ǝ' => 'e',
            'Ș' => 'S',
            'ș' => 's',
            'Ț' => 'T',
            'ț' => 't',
            // Euro sign.
            '€' => 'E',
            // GBP (Pound) sign.
            '£' => '',
            // Vowels with diacritic (Vietnamese). Unmarked.
            'Ơ' => 'O',
            'ơ' => 'o',
            'Ư' => 'U',
            'ư' => 'u',
            // Grave accent.
            'Ầ' => 'A',
            'ầ' => 'a',
            'Ằ' => 'A',
            'ằ' => 'a',
            'Ề' => 'E',
            'ề' => 'e',
            'Ồ' => 'O',
            'ồ' => 'o',
            'Ờ' => 'O',
            'ờ' => 'o',
            'Ừ' => 'U',
            'ừ' => 'u',
            'Ỳ' => 'Y',
            'ỳ' => 'y',
            // Hook.
            'Ả' => 'A',
            'ả' => 'a',
            'Ẩ' => 'A',
            'ẩ' => 'a',
            'Ẳ' => 'A',
            'ẳ' => 'a',
            'Ẻ' => 'E',
            'ẻ' => 'e',
            'Ể' => 'E',
            'ể' => 'e',
            'Ỉ' => 'I',
            'ỉ' => 'i',
            'Ỏ' => 'O',
            'ỏ' => 'o',
            'Ổ' => 'O',
            'ổ' => 'o',
            'Ở' => 'O',
            'ở' => 'o',
            'Ủ' => 'U',
            'ủ' => 'u',
            'Ử' => 'U',
            'ử' => 'u',
            'Ỷ' => 'Y',
            'ỷ' => 'y',
            // Tilde.
            'Ẫ' => 'A',
            'ẫ' => 'a',
            'Ẵ' => 'A',
            'ẵ' => 'a',
            'Ẽ' => 'E',
            'ẽ' => 'e',
            'Ễ' => 'E',
            'ễ' => 'e',
            'Ỗ' => 'O',
            'ỗ' => 'o',
            'Ỡ' => 'O',
            'ỡ' => 'o',
            'Ữ' => 'U',
            'ữ' => 'u',
            'Ỹ' => 'Y',
            'ỹ' => 'y',
            // Acute accent.
            'Ấ' => 'A',
            'ấ' => 'a',
            'Ắ' => 'A',
            'ắ' => 'a',
            'Ế' => 'E',
            'ế' => 'e',
            'Ố' => 'O',
            'ố' => 'o',
            'Ớ' => 'O',
            'ớ' => 'o',
            'Ứ' => 'U',
            'ứ' => 'u',
            // Dot below.
            'Ạ' => 'A',
            'ạ' => 'a',
            'Ậ' => 'A',
            'ậ' => 'a',
            'Ặ' => 'A',
            'ặ' => 'a',
            'Ẹ' => 'E',
            'ẹ' => 'e',
            'Ệ' => 'E',
            'ệ' => 'e',
            'Ị' => 'I',
            'ị' => 'i',
            'Ọ' => 'O',
            'ọ' => 'o',
            'Ộ' => 'O',
            'ộ' => 'o',
            'Ợ' => 'O',
            'ợ' => 'o',
            'Ụ' => 'U',
            'ụ' => 'u',
            'Ự' => 'U',
            'ự' => 'u',
            'Ỵ' => 'Y',
            'ỵ' => 'y',
            // Vowels with diacritic (Chinese, Hanyu Pinyin).
            'ɑ' => 'a',
            // Macron.
            'Ǖ' => 'U',
            'ǖ' => 'u',
            // Acute accent.
            'Ǘ' => 'U',
            'ǘ' => 'u',
            // Caron.
            'Ǎ' => 'A',
            'ǎ' => 'a',
            'Ǐ' => 'I',
            'ǐ' => 'i',
            'Ǒ' => 'O',
            'ǒ' => 'o',
            'Ǔ' => 'U',
            'ǔ' => 'u',
            'Ǚ' => 'U',
            'ǚ' => 'u',
            // Grave accent.
            'Ǜ' => 'U',
            'ǜ' => 'u',
        ];

        // Used for locale-specific rules.
        if (empty($locale)) {
            $locale = get_locale();
        }

        /*
         * German has various locales (de_DE, de_CH, de_AT, ...) with formal and informal variants.
         * There is no 3-letter locale like 'def', so checking for 'de' instead of 'de_' is safe,
         * since 'de' itself would be a valid locale too.
         */
        if (str_starts_with($locale, 'de')) {
            $chars['Ä'] = 'Ae';
            $chars['ä'] = 'ae';
            $chars['Ö'] = 'Oe';
            $chars['ö'] = 'oe';
            $chars['Ü'] = 'Ue';
            $chars['ü'] = 'ue';
            $chars['ẞ'] = 'SS';
            $chars['ß'] = 'ss';
        } elseif ($locale === 'da_DK') {
            $chars['Æ'] = 'Ae';
            $chars['æ'] = 'ae';
            $chars['Ø'] = 'Oe';
            $chars['ø'] = 'oe';
            $chars['Å'] = 'Aa';
            $chars['å'] = 'aa';
        } elseif ($locale === 'ca') {
            $chars['l·l'] = 'll';
        } elseif ($locale === 'sr_RS' || $locale === 'bs_BA') {
            $chars['Đ'] = 'DJ';
            $chars['đ'] = 'dj';
        }

        $text = strtr($text, $chars);
    } else {
        $chars = [];
        // Assume ISO-8859-1 if not UTF-8.
        $chars['in'] = "\x80\x83\x8a\x8e\x9a\x9e"
            ."\x9f\xa2\xa5\xb5\xc0\xc1\xc2"
            ."\xc3\xc4\xc5\xc7\xc8\xc9\xca"
            ."\xcb\xcc\xcd\xce\xcf\xd1\xd2"
            ."\xd3\xd4\xd5\xd6\xd8\xd9\xda"
            ."\xdb\xdc\xdd\xe0\xe1\xe2\xe3"
            ."\xe4\xe5\xe7\xe8\xe9\xea\xeb"
            ."\xec\xed\xee\xef\xf1\xf2\xf3"
            ."\xf4\xf5\xf6\xf8\xf9\xfa\xfb"
            ."\xfc\xfd\xff";

        $chars['out'] = 'EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy';

        $text = strtr($text, $chars['in'], $chars['out']);
        $double_chars = [];
        $double_chars['in'] = ["\x8c", "\x9c", "\xc6", "\xd0", "\xde", "\xdf", "\xe6", "\xf0", "\xfe"];
        $double_chars['out'] = ['OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th'];
        $text = str_replace($double_chars['in'], $double_chars['out'], $text);
    }

    return $text;
}

/**
 * Checks for invalid UTF8 in a string.
 *
 * Note! This function only performs its work if the `blog_charset` is set
 * to UTF-8. For all other values it returns the input text unchanged.
 *
 * Note! Unless requested, this returns an empty string if the input contains
 * any sequences of invalid UTF-8. To replace invalid byte sequences, pass
 * `true` as the optional `$strip` parameter.
 *
 * Consider using {@see wp_scrub_utf8()} instead which does not depend on
 * the value of `blog_charset`.
 *
 * Example:
 *
 *     // The `blog_charset` is `latin1`, so this returns the input unchanged.
 *     $every_possible_input === wp_check_invalid_utf8( $every_possible_input );
 *
 *     // Valid strings come through unchanged.
 *     'test' === wp_check_invalid_utf8( 'test' );
 *
 *     $invalid = "the byte \xC0 is never allowed in a UTF-8 string.";
 *
 *     // Invalid strings are rejected outright.
 *     '' === wp_check_invalid_utf8( $invalid );
 *
 *     // “Stripping” invalid sequences produces the replacement character instead.
 *     "the byte \u{FFFD} is never allowed in a UTF-8 string." === wp_check_invalid_utf8( $invalid, true );
 *     'the byte � is never allowed in a UTF-8 string.' === wp_check_invalid_utf8( $invalid, true );
 *
 * @since WM-0.5.0
 * @since WP-2.8.0
 * @since WP-6.9.0 Stripping replaces invalid byte sequences with the Unicode replacement character U+FFFD (�).
 *
 * @param  string  $text  String which is expected to be encoded as UTF-8 unless `blog_charset` is another encoding.
 * @param  bool  $strip  Optional. Whether to replace invalid sequences of bytes with the Unicode replacement
 *                       character (U+FFFD `�`). Default `false` returns an empty string for invalid UTF-8 inputs.
 * @return string The checked text.
 */
function wp_check_invalid_utf8($text, $strip = false)
{
    $text = (string) $text;

    if (strlen($text) === 0) {
        return '';
    }

    // Store the site charset as a static to avoid multiple calls to get_option().
    static $is_utf8 = null;
    if (! isset($is_utf8)) {
        $is_utf8 = is_utf8_charset();
    }

    if (! $is_utf8 || wp_is_valid_utf8($text)) {
        return $text;
    }

    return $strip
        ? wp_scrub_utf8($text)
        : '';
}

/**
 * Checks to see if a string is utf8 encoded.
 *
 * NOTE: This function checks for 5-Byte sequences, UTF8
 *       has Bytes Sequences with a maximum length of 4.
 *
 * @author bmorel at ssi dot fr (modified)
 *
 * @since WM-0.5.0
 * @since WP-1.2.1
 * @deprecated WP-6.9.0 Use {@see wp_is_valid_utf8()} instead.
 *
 * @param  string  $str  The string to be checked.
 * @return bool True if $str fits a UTF-8 model, false otherwise.
 */
function seems_utf8($str)
{
    _deprecated_function(__FUNCTION__, '6.9.0', 'wp_is_valid_utf8()');

    mbstring_binary_safe_encoding();
    $length = strlen($str);
    reset_mbstring_encoding();

    for ($i = 0; $i < $length; $i++) {
        $c = ord($str[$i]);

        if ($c < 0x80) {
            $n = 0; // 0bbbbbbb
        } elseif (($c & 0xE0) === 0xC0) {
            $n = 1; // 110bbbbb
        } elseif (($c & 0xF0) === 0xE0) {
            $n = 2; // 1110bbbb
        } elseif (($c & 0xF8) === 0xF0) {
            $n = 3; // 11110bbb
        } elseif (($c & 0xFC) === 0xF8) {
            $n = 4; // 111110bb
        } elseif (($c & 0xFE) === 0xFC) {
            $n = 5; // 1111110b
        } else {
            return false; // Does not match any model.
        }

        for ($j = 0; $j < $n; $j++) { // n bytes matching 10bbbbbb follow?
            if ((++$i === $length) || ((ord($str[$i]) & 0xC0) !== 0x80)) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Encodes the Unicode values to be used in the URI.
 *
 * @since WM-0.5.0
 * @since WP-1.5.0
 * @since WP-5.8.3 Added the `encode_ascii_characters` parameter.
 *
 * @param  string  $utf8_string  String to encode.
 * @param  int  $length  Max length of the string.
 * @param  bool  $encode_ascii_characters  Whether to encode ascii characters such as < " '
 * @return string String with Unicode encoded for URI.
 */
function utf8_uri_encode($utf8_string, $length = 0, $encode_ascii_characters = false)
{
    $unicode = '';
    $values = [];
    $num_octets = 1;
    $unicode_length = 0;

    mbstring_binary_safe_encoding();
    $string_length = strlen($utf8_string);
    reset_mbstring_encoding();

    for ($i = 0; $i < $string_length; $i++) {

        $value = ord($utf8_string[$i]);

        if ($value < 128) {
            $char = chr($value);
            $encoded_char = $encode_ascii_characters ? rawurlencode($char) : $char;
            $encoded_char_length = strlen($encoded_char);
            if ($length && ($unicode_length + $encoded_char_length) > $length) {
                break;
            }
            $unicode .= $encoded_char;
            $unicode_length += $encoded_char_length;
        } else {
            if (count($values) === 0) {
                if ($value < 224) {
                    $num_octets = 2;
                } elseif ($value < 240) {
                    $num_octets = 3;
                } else {
                    $num_octets = 4;
                }
            }

            $values[] = $value;

            if ($length && ($unicode_length + ($num_octets * 3)) > $length) {
                break;
            }
            if (count($values) === $num_octets) {
                for ($j = 0; $j < $num_octets; $j++) {
                    $unicode .= '%'.dechex($values[$j]);
                }

                $unicode_length += $num_octets * 3;

                $values = [];
                $num_octets = 1;
            }
        }
    }

    return $unicode;
}

/**
 * Converts a number of special characters into their HTML entities.
 *
 * Specifically deals with: `&`, `<`, `>`, `"`, and `'`.
 *
 * `$quote_style` can be set to ENT_COMPAT to encode `"` to
 * `&quot;`, or ENT_QUOTES to do both. Default is ENT_NOQUOTES where no quotes are encoded.
 *
 * @since WM-0.5.0
 * @since WP-1.2.2
 * @since WP-5.5.0 `$quote_style` also accepts `ENT_XML1`.
 *
 * @param  string  $text  The text which is to be encoded.
 * @param  int|string  $quote_style  Optional. Converts double quotes if set to ENT_COMPAT,
 *                                   both single and double if set to ENT_QUOTES or none if set to ENT_NOQUOTES.
 *                                   Converts single and double quotes, as well as converting HTML
 *                                   named entities (that are not also XML named entities) to their
 *                                   code points if set to ENT_XML1. Also compatible with old values;
 *                                   converting single quotes if set to 'single',
 *                                   double if set to 'double' or both if otherwise set.
 *                                   Default is ENT_NOQUOTES.
 * @param  false|string  $charset  Optional. The character encoding of the string. Default false.
 * @param  bool  $double_encode  Optional. Whether to encode existing HTML entities. Default false.
 * @return string The encoded text with HTML entities.
 */
function _wp_specialchars($text, $quote_style = ENT_NOQUOTES, $charset = false, $double_encode = false)
{
    $text = (string) $text;

    if (strlen($text) === 0) {
        return '';
    }

    // Don't bother if there are no specialchars - saves some processing.
    if (! preg_match('/[&<>"\']/', $text)) {
        return $text;
    }

    // Account for the previous behavior of the function when the $quote_style is not an accepted value.
    if (empty($quote_style)) {
        $quote_style = ENT_NOQUOTES;
    } elseif ($quote_style === ENT_XML1) {
        $quote_style = ENT_QUOTES | ENT_XML1;
    } elseif (! in_array($quote_style, [ENT_NOQUOTES, ENT_COMPAT, ENT_QUOTES, 'single', 'double'], true)) {
        $quote_style = ENT_QUOTES;
    }

    $charset = _canonical_charset($charset ? $charset : get_option('blog_charset'));

    $_quote_style = $quote_style;

    if ($quote_style === 'double') {
        $quote_style = ENT_COMPAT;
        $_quote_style = ENT_COMPAT;
    } elseif ($quote_style === 'single') {
        $quote_style = ENT_NOQUOTES;
    }

    if (! $double_encode) {
        /*
         * Guarantee every &entity; is valid, convert &garbage; into &amp;garbage;
         * This is required for PHP < 5.4.0 because ENT_HTML401 flag is unavailable.
         */
        $text = wp_kses_normalize_entities($text, ($quote_style & ENT_XML1) ? 'xml' : 'html');
    }

    $text = htmlspecialchars($text, $quote_style, $charset, $double_encode);

    // Back-compat.
    if ($_quote_style === 'single') {
        $text = str_replace("'", '&#039;', $text);
    }

    return $text;
}

/**
 * Escaping for HTML blocks.
 *
 * @since WM-0.5.0
 * @since WP-2.8.0
 *
 * @param  string  $text
 * @return string Escaped text.
 */
function esc_html($text)
{
    $safe_text = wp_check_invalid_utf8($text);
    $safe_text = _wp_specialchars($safe_text, ENT_QUOTES);

    /**
     * Filters a string cleaned and escaped for output in HTML.
     *
     * Text passed to esc_html() is stripped of invalid or special characters
     * before output.
     *
     * @since WP-2.8.0
     *
     * @param  string  $safe_text  The text after it has been escaped.
     * @param  string  $text  The text prior to being escaped.
     */
    return apply_filters('esc_html', $safe_text, $text);
}

/**
 * Escaping for HTML attributes.
 *
 * @since WM-0.5.0
 * @since WP-2.8.0
 *
 * @param  string  $text
 * @return string Escaped text.
 */
function esc_attr($text)
{
    $safe_text = wp_check_invalid_utf8($text);
    $safe_text = _wp_specialchars($safe_text, ENT_QUOTES);

    /**
     * Filters a string cleaned and escaped for output in an HTML attribute.
     *
     * Text passed to esc_attr() is stripped of invalid or special characters
     * before output.
     *
     * @since WP-2.0.6
     *
     * @param  string  $safe_text  The text after it has been escaped.
     * @param  string  $text  The text prior to being escaped.
     */
    return apply_filters('attribute_escape', $safe_text, $text);
}

/**
 * i18n-friendly version of basename().
 *
 * @since WM-0.5.0
 * @since WP-3.1.0
 *
 * @param  string  $path  A path.
 * @param  string  $suffix  If the filename ends in suffix this will also be cut off.
 * @return string The base name of the given path.
 */
function wp_basename($path, $suffix = '')
{
    return urldecode(basename(str_replace(['%2F', '%5C'], '/', urlencode($path)), $suffix));
}

/**
 * Given a date in the timezone of the site, returns that date in UTC.
 *
 * Requires and returns a date in the Y-m-d H:i:s format.
 * Return format can be overridden using the $format parameter.
 *
 * @since WM-0.5.0
 * @since WP-1.2.0
 *
 * @param  string  $date_string  The date to be converted, in the timezone of the site.
 * @param  string  $format  The format string for the returned date. Default 'Y-m-d H:i:s'.
 * @return string Formatted version of the date, in UTC.
 */
function get_gmt_from_date($date_string, $format = 'Y-m-d H:i:s')
{
    $datetime = date_create($date_string, wp_timezone());

    if ($datetime === false) {
        return gmdate($format, 0);
    }

    return $datetime->setTimezone(new DateTimeZone('UTC'))->format($format);
}

/**
 * Given a date in UTC or GMT timezone, returns that date in the timezone of the site.
 *
 * Requires a date in the Y-m-d H:i:s format.
 * Default return format of 'Y-m-d H:i:s' can be overridden using the `$format` parameter.
 *
 * @since WM-0.5.0
 * @since WP-1.2.0
 *
 * @param  string  $date_string  The date to be converted, in UTC or GMT timezone.
 * @param  string  $format  The format string for the returned date. Default 'Y-m-d H:i:s'.
 * @return string Formatted version of the date, in the site's timezone.
 */
function get_date_from_gmt($date_string, $format = 'Y-m-d H:i:s')
{
    $datetime = date_create($date_string, new DateTimeZone('UTC'));

    if ($datetime === false) {
        return gmdate($format, 0);
    }

    return $datetime->setTimezone(wp_timezone())->format($format);
}

/**
 * Merges user defined arguments into defaults array.
 *
 * This function is used throughout WordPress to allow for both string or array
 * to be merged into another array.
 *
 * @since WM-0.5.0
 * @since WP-2.2.0
 * @since WP-2.3.0 `$args` can now also be an object.
 *
 * @param  string|array|object  $args  Value to merge with $defaults.
 * @param  array  $defaults  Optional. Array that serves as the defaults.
 *                           Default empty array.
 * @return array Merged user defined values with defaults.
 */
function wp_parse_args($args, $defaults = [])
{
    if (is_object($args)) {
        $parsed_args = get_object_vars($args);
    } elseif (is_array($args)) {
        $parsed_args = &$args;
    } else {
        wp_parse_str($args, $parsed_args);
    }

    if (is_array($defaults) && $defaults) {
        return array_merge($defaults, $parsed_args);
    }

    return $parsed_args;
}

/**
 * Converts a comma- or space-separated list of scalar values to an array.
 *
 * @since WM-0.5.0
 * @since WP-5.1.0
 *
 * @param  mixed[]|string  $input_list  List of values.
 * @return array Array of scalar values. A string is split into a list, while an array
 *               keeps its keys, so the result is not necessarily a list.
 *
 * @phpstan-return (
 *     $input_list is string ? list<string> : (
 *         $input_list is array<string> ? array<string> : array<scalar>
 *     )
 * )
 */
function wp_parse_list($input_list): array
{
    if (! is_array($input_list)) {
        $parsed_list = preg_split('/[\s,]+/', $input_list, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parsed_list) ? $parsed_list : [];
    }

    // Validate all entries of the list are scalar.
    $input_list = array_filter($input_list, 'is_scalar');

    return $input_list;
}

/**
 * Cleans up an array, comma- or space-separated list of IDs.
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 * @since WP-5.1.0 Refactored to use wp_parse_list().
 *
 * @param  mixed[]|string  $input_list  List of IDs.
 * @return int[] Sanitized array of IDs. May include zero. Keys are preserved
 *               from the input and `array_unique()` may leave gaps, so the
 *               result is not necessarily a list.
 *
 * @phpstan-return array<non-negative-int>
 */
function wp_parse_id_list($input_list): array
{
    $input_list = wp_parse_list($input_list);

    return array_unique(array_map('absint', $input_list));
}

/**
 * Retrieves a canonical form of the provided charset appropriate for passing to PHP
 * functions such as htmlspecialchars() and charset HTML attributes.
 *
 * @since WM-0.5.0
 * @since WP-3.6.0
 * @see https://core.trac.wordpress.org/ticket/23688
 *
 * @param  string  $charset  A charset name, e.g. "UTF-8", "Windows-1252", "SJIS".
 * @return string The canonical form of the charset.
 */
function _canonical_charset($charset)
{
    if (is_utf8_charset($charset)) {
        return 'UTF-8';
    }

    /*
     * Normalize the ISO-8859-1 family of languages.
     *
     * This is not required for htmlspecialchars(), as it properly recognizes all of
     * the input character sets that here are transformed into "ISO-8859-1".
     *
     * @todo Should this entire check be removed since it's not required for the stated purpose?
     * @todo Should WordPress transform other potential charset equivalents, such as "latin1"?
     */
    if (
        (strcasecmp('iso-8859-1', $charset) === 0) ||
        (strcasecmp('iso8859-1', $charset) === 0)
    ) {
        return 'ISO-8859-1';
    }

    return $charset;
}

/**
 * Indicates if a given slug for a character set represents the UTF-8
 * text encoding. If not provided, examines the current blog's charset.
 *
 * A charset is considered to represent UTF-8 if it is a case-insensitive
 * match of "UTF-8" with or without the hyphen.
 *
 * Example:
 *
 *     true  === is_utf8_charset( 'UTF-8' );
 *     true  === is_utf8_charset( 'utf8' );
 *     false === is_utf8_charset( 'latin1' );
 *     false === is_utf8_charset( 'UTF 8' );
 *
 *     // Only strings match.
 *     false === is_utf8_charset( [ 'charset' => 'utf-8' ] );
 *
 *     // Without a given charset, it depends on the site option "blog_charset".
 *     $is_utf8 = is_utf8_charset();
 *
 * @since WM-0.5.0
 * @since WP-6.6.0
 * @since WP-6.6.1 A wrapper for _is_utf8_charset
 * @see _is_utf8_charset
 *
 * @param  string|null  $blog_charset  Optional. Slug representing a text character encoding, or "charset".
 *                                     E.g. "UTF-8", "Windows-1252", "ISO-8859-1", "SJIS".
 *                                     Default value is to infer from "blog_charset" option.
 * @return bool Whether the slug represents the UTF-8 encoding.
 */
function is_utf8_charset($blog_charset = null)
{
    return _is_utf8_charset($blog_charset ?? get_option('blog_charset'));
}

/**
 * Indicates if a given slug for a character set represents the UTF-8 text encoding.
 *
 * A charset is considered to represent UTF-8 if it is a case-insensitive match
 * of "UTF-8" with or without the hyphen.
 *
 * Example:
 *
 *     true  === _is_utf8_charset( 'UTF-8' );
 *     true  === _is_utf8_charset( 'utf8' );
 *     false === _is_utf8_charset( 'latin1' );
 *     false === _is_utf8_charset( 'UTF 8' );
 *
 *     // Only strings match.
 *     false === _is_utf8_charset( [ 'charset' => 'utf-8' ] );
 *
 * `is_utf8_charset` should be used outside of this file.
 *
 * @ignore
 *
 * @since WM-0.5.0
 * @since WP-6.6.1
 *
 * @param  string  $charset_slug  Slug representing a text character encoding, or "charset".
 *                                E.g. "UTF-8", "Windows-1252", "ISO-8859-1", "SJIS".
 * @return bool Whether the slug represents the UTF-8 encoding.
 */
function _is_utf8_charset($charset_slug)
{
    if (! is_string($charset_slug)) {
        return false;
    }

    return
        strcasecmp('UTF-8', $charset_slug) === 0 ||
        strcasecmp('UTF8', $charset_slug) === 0;
}

/**
 * Builds a URL query based on an associative or indexed array.
 *
 * This is a convenient function for easily building URL queries.
 * It sets the separator to '&' and uses the _http_build_query() function.
 *
 * Unlike PHP's native http_build_query(), this function does NOT URL-encode
 * the keys or values. Callers are responsible for encoding values beforehand
 * with urlencode() or rawurlencode(), or late-escaping the output with
 * esc_url() before use.
 *
 * @since WM-0.5.0
 * @since WP-2.3.0
 * @see _http_build_query() Used to build the query.
 * @link https://www.php.net/manual/en/function.http-build-query.php for more on what
 *       http_build_query() does.
 *
 * @param  array  $data  Array of key/value pairs to build the query from.
 * @return string Query string, without URL encoding applied.
 */
function build_query($data)
{
    return _http_build_query($data, null, '&', '', false);
}

/**
 * From php.net (modified by Mark Jaquith to behave like the native PHP5 function).
 *
 * @since WM-0.5.0
 * @since WP-3.2.0
 * @see https://www.php.net/manual/en/function.http-build-query.php
 *
 * @param  array|object  $data  An array or object of data. Converted to array.
 * @param  string  $prefix  Optional. Numeric index. If set, start parameter numbering with it.
 *                          Default null.
 * @param  string  $sep  Optional. Argument separator; defaults to 'arg_separator.output'.
 *                       Default null.
 * @param  string  $key  Optional. Used to prefix key name. Default empty string.
 * @param  bool  $urlencode  Optional. Whether to use urlencode() in the result. Default true.
 * @return string The query string.
 */
function _http_build_query($data, $prefix = null, $sep = null, $key = '', $urlencode = true)
{
    $ret = [];

    foreach ((array) $data as $k => $v) {
        if ($urlencode) {
            $k = urlencode($k);
        }

        if (is_int($k) && $prefix !== null) {
            $k = $prefix.$k;
        }

        if (! empty($key)) {
            $k = $key.'%5B'.$k.'%5D';
        }

        if ($v === null) {
            continue;
        } elseif ($v === false) {
            $v = '0';
        }

        if (is_array($v) || is_object($v)) {
            array_push($ret, _http_build_query($v, '', $sep, $k, $urlencode));
        } elseif ($urlencode) {
            array_push($ret, $k.'='.urlencode($v));
        } else {
            array_push($ret, $k.'='.$v);
        }
    }

    if ($sep === null) {
        $sep = ini_get('arg_separator.output');
    }

    return implode($sep, $ret);
}

/**
 * Determines if the variable is a numeric-indexed array.
 *
 * Note! This answers a different question than {@see array_is_list()} and is
 *       more flexible to handle situations where some numeric array indices
 *       have been removed. A numeric-indexed array is only a “list” when the
 *       array keys form a contiguous range from zero to the highest key.
 *
 * Example:
 *
 *     true  === wp_is_numeric_array( array( 1, 2, 3, 4 ) );
 *     false === wp_is_numeric_array( array( 'name' => 'WordPress' ) );
 *
 *     // All-numeric keys vs. list.
 *     $above_two   = array_filter( array( 1, 2, 8, 9 ), fn ( $v ) => $v > 2 );
 *     $above_two === array( '2' => 8, '3' => 9 );
 *     true       === wp_is_numeric_array( $above_two );
 *     false      === array_is_list( $above_two );
 *
 * @since WM-0.5.0
 * @since WP-4.4.0
 *
 * @param  mixed  $data  Variable to check.
 * @return bool Whether the variable is a list.
 *
 * @phpstan-assert-if-true array<int, mixed> $data
 */
function wp_is_numeric_array($data): bool
{
    if (! is_array($data)) {
        return false;
    }

    return array_all($data, fn ($value, $key) => ! is_string($key));
}

/**
 * Retrieves a modified URL query string.
 *
 * You can rebuild the URL and append query variables to the URL query by using this function.
 * There are two ways to use this function; either a single key and value, or an associative array.
 *
 * Using a single key and value:
 *
 *     add_query_arg( 'key', 'value', 'http://example.com' );
 *
 * Using an associative array:
 *
 *     add_query_arg( array(
 *         'key1' => 'value1',
 *         'key2' => 'value2',
 *     ), 'http://example.com' );
 *
 * Omitting the URL from either use results in the current URL being used
 * (the value of `$_SERVER['REQUEST_URI']`).
 *
 * Values are expected to be encoded appropriately with urlencode() or rawurlencode().
 *
 * Setting any query variable's value to boolean false removes the key (see remove_query_arg()).
 *
 * Important: The return value of add_query_arg() is not escaped by default. Output should be
 * late-escaped with esc_url() or similar to help prevent vulnerability to cross-site scripting
 * (XSS) attacks.
 *
 * @since WM-0.5.0
 * @since WP-1.5.0
 * @since WP-5.3.0 Formalized the existing and already documented parameters
 *              by adding `...$args` to the function signature.
 *
 * @param  string|array  $key  Either a query variable key, or an associative array of query variables.
 * @param  string  $value  Optional. Either a query variable value, or a URL to act upon.
 * @param  string  $url  Optional. A URL to act upon.
 * @return string New URL query string (unescaped).
 */
function add_query_arg(...$args)
{
    if (is_array($args[0])) {
        if (count($args) < 2 || $args[1] === false) {
            $uri = $_SERVER['REQUEST_URI'];
        } else {
            $uri = $args[1];
        }
    } else {
        if (count($args) < 3 || $args[2] === false) {
            $uri = $_SERVER['REQUEST_URI'];
        } else {
            $uri = $args[2];
        }
    }

    $frag = strstr($uri, '#');
    if ($frag) {
        $uri = substr($uri, 0, -strlen($frag));
    } else {
        $frag = '';
    }

    if (stripos($uri, 'http://') === 0) {
        $protocol = 'http://';
        $uri = substr($uri, 7);
    } elseif (stripos($uri, 'https://') === 0) {
        $protocol = 'https://';
        $uri = substr($uri, 8);
    } else {
        $protocol = '';
    }

    if (str_contains($uri, '?')) {
        [$base, $query] = explode('?', $uri, 2);
        $base .= '?';
    } elseif ($protocol || ! str_contains($uri, '=')) {
        $base = $uri.'?';
        $query = '';
    } else {
        $base = '';
        $query = $uri;
    }

    wp_parse_str($query, $qs);
    $qs = urlencode_deep($qs); // This re-URL-encodes things that were already in the query string.
    if (is_array($args[0])) {
        foreach ($args[0] as $k => $v) {
            $qs[$k] = $v;
        }
    } else {
        $qs[$args[0]] = $args[1];
    }

    foreach ($qs as $k => $v) {
        if ($v === false) {
            unset($qs[$k]);
        }
    }

    $ret = build_query($qs);
    $ret = trim($ret, '?');
    $ret = preg_replace('#=(&|$)#', '$1', $ret);
    $ret = $protocol.$base.$ret.$frag;
    $ret = rtrim($ret, '?');
    $ret = str_replace('?#', '#', $ret);

    return $ret;
}

/**
 * Removes an item or items from a query string.
 *
 * Important: The return value of remove_query_arg() is not escaped by default. Output should be
 * late-escaped with esc_url() or similar to help prevent vulnerability to cross-site scripting
 * (XSS) attacks.
 *
 * @since WM-0.5.0
 * @since WP-1.5.0
 *
 * @param  string|string[]  $key  Query key or keys to remove.
 * @param  false|string  $query  Optional. When false uses the current URL. Default false.
 * @return string New URL query string.
 */
function remove_query_arg($key, $query = false)
{
    if (is_array($key)) { // Removing multiple keys.
        foreach ($key as $k) {
            $query = add_query_arg($k, false, $query);
        }

        return $query;
    }

    return add_query_arg($key, false, $query);
}

/**
 * Finds hierarchy loops using a callback function that maps object IDs to parent IDs.
 *
 * @since WM-0.5.0
 * @since WP-3.1.0
 *
 * @param  callable  $callback  Function that accepts ( ID, $callback_args ) and outputs parent_ID.
 * @param  int  $start  The ID to start the loop check at.
 * @param  int  $start_parent  The parent_ID of $start to use instead of calling $callback( $start ).
 *                             Use null to always use $callback.
 * @param  array  $callback_args  Optional. Additional arguments to send to $callback. Default empty array.
 * @return array IDs of all members of loop.
 */
function wp_find_hierarchy_loop($callback, $start, $start_parent, $callback_args = [])
{
    $override = is_null($start_parent) ? [] : [$start => $start_parent];

    $arbitrary_loop_member = wp_find_hierarchy_loop_tortoise_hare($callback, $start, $override, $callback_args);
    if (! $arbitrary_loop_member) {
        return [];
    }

    return wp_find_hierarchy_loop_tortoise_hare($callback, $arbitrary_loop_member, $override, $callback_args, true);
}

/**
 * Uses the "The Tortoise and the Hare" algorithm to detect loops.
 *
 * For every step of the algorithm, the hare takes two steps and the tortoise one.
 * If the hare ever laps the tortoise, there must be a loop.
 *
 * @since WM-0.5.0
 * @since WP-3.1.0
 *
 * @param  callable  $callback  Function that accepts ( ID, callback_arg, ... ) and outputs parent_ID.
 * @param  int  $start  The ID to start the loop check at.
 * @param  array  $override  Optional. An array of ( ID => parent_ID, ... ) to use instead of $callback.
 *                           Default empty array.
 * @param  array  $callback_args  Optional. Additional arguments to send to $callback. Default empty array.
 * @param  bool  $_return_loop  Optional. Return loop members or just detect presence of loop? Only set
 *                              to true if you already know the given $start is part of a loop (otherwise
 *                              the returned array might include branches). Default false.
 * @return mixed Scalar ID of some arbitrary member of the loop, or array of IDs of all members of loop if
 *               $_return_loop
 */
function wp_find_hierarchy_loop_tortoise_hare($callback, $start, $override = [], $callback_args = [], $_return_loop = false)
{
    $tortoise = $start;
    $hare = $start;
    $evanescent_hare = $start;
    $return = [];

    // Set evanescent_hare to one past hare. Increment hare two steps.
    while (
        $tortoise
    &&
        ($evanescent_hare = $override[$hare] ?? call_user_func_array($callback, array_merge([$hare], $callback_args)))
    &&
        ($hare = $override[$evanescent_hare] ?? call_user_func_array($callback, array_merge([$evanescent_hare], $callback_args)))
    ) {
        if ($_return_loop) {
            $return[$tortoise] = true;
            $return[$evanescent_hare] = true;
            $return[$hare] = true;
        }

        // Tortoise got lapped - must be a loop.
        if ($tortoise === $evanescent_hare || $tortoise === $hare) {
            return $_return_loop ? $return : $tortoise;
        }

        // Increment tortoise by one step.
        $tortoise = $override[$tortoise] ?? call_user_func_array($callback, array_merge([$tortoise], $callback_args));
    }

    return false;
}

/**
 * Parses a string into variables to be stored in an array.
 *
 * @since WM-0.5.0
 * @since WP-2.2.1
 *
 * @param  string  $input_string  The string to be parsed.
 * @param  array  $result  Variables will be stored in this array.
 */
function wp_parse_str($input_string, &$result)
{
    parse_str((string) $input_string, $result);

    /**
     * Filters the array of variables derived from a parsed string.
     *
     * @since WP-2.2.1
     *
     * @param  array  $result  The array populated with variables.
     */
    $result = apply_filters('wp_parse_str', $result);
}

/**
 * Navigates through an array, object, or scalar, and encodes the values to be used in a URL.
 *
 * @since WM-0.5.0
 * @since WP-2.2.0
 *
 * @param  mixed  $value  The array or string to be encoded.
 * @return mixed The encoded value.
 */
function urlencode_deep($value)
{
    return map_deep($value, 'urlencode');
}

/**
 * Determines whether to force SSL used for the Administration Screens.
 *
 * @since WM-0.5.0
 * @since WP-2.6.0
 *
 * @param  string|bool|null  $force  Optional. Whether to force SSL in admin screens. Default null.
 * @return bool True if forced, false if not forced.
 */
function force_ssl_admin($force = null)
{
    static $forced = false;

    if (! is_null($force)) {
        $old_forced = $forced;
        $forced = (bool) $force;

        return $old_forced;
    }

    return $forced;
}

/**
 * Sanitizes space or carriage return separated URLs that are used to send trackbacks.
 *
 * @since WM-0.5.0
 * @since WP-3.4.0
 *
 * @param  string  $to_ping  Space or carriage return separated URLs
 * @return string URLs starting with the http or https protocol, separated by a carriage return.
 */
function sanitize_trackback_urls($to_ping)
{
    $urls_to_ping = preg_split('/[\r\n\t ]/', trim($to_ping), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($urls_to_ping as $k => $url) {
        if (! preg_match('#^https?://.#i', $url)) {
            unset($urls_to_ping[$k]);
        }
    }
    $urls_to_ping = array_map('sanitize_url', $urls_to_ping);
    $urls_to_ping = implode("\n", $urls_to_ping);

    /**
     * Filters a list of trackback URLs following sanitization.
     *
     * The string returned here consists of a space or carriage return-delimited list
     * of trackback URLs.
     *
     * @since WP-3.4.0
     *
     * @param  string  $urls_to_ping  Sanitized space or carriage return separated URLs.
     * @param  string  $to_ping  Space or carriage return separated URLs before sanitization.
     */
    return apply_filters('sanitize_trackback_urls', $urls_to_ping, $to_ping);
}

/**
 * Checks and cleans a URL.
 *
 * A number of characters are removed from the URL. If the URL is for displaying
 * (the default behavior) ampersands are also replaced. The {@see 'clean_url'} filter
 * is applied to the returned cleaned URL.
 *
 * @since WM-0.5.0
 * @since WP-2.8.0
 * @since WP-6.9.0 Prepends `https://` to the URL if it does not already contain a scheme
 *              and the first item in `$protocols` is 'https'.
 *
 * @param  string  $url  The URL to be cleaned.
 * @param  string[]  $protocols  Optional. An array of acceptable protocols.
 *                               Defaults to return value of wp_allowed_protocols().
 * @param  string  $_context  Private. Use sanitize_url() for database usage.
 * @return string The cleaned URL after the {@see 'clean_url'} filter is applied.
 *                An empty string is returned if `$url` specifies a protocol other than
 *                those in `$protocols`, or if `$url` contains an empty string.
 */
function esc_url($url, $protocols = null, $_context = 'display')
{
    $original_url = $url;

    if ($url === '') {
        return $url;
    }

    $url = str_replace(' ', '%20', ltrim($url));
    $url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url);

    if ($url === '') {
        return $url;
    }

    if (stripos($url, 'mailto:') !== 0) {
        $strip = ['%0d', '%0a', '%0D', '%0A'];
        $url = _deep_replace($strip, $url);
    }

    $url = str_replace(';//', '://', $url);
    /*
     * If the URL doesn't appear to contain a scheme, we presume
     * it needs http:// prepended (unless it's a relative link
     * starting with /, # or ?, or a PHP file). If the first item
     * in $protocols is 'https', then https:// is prepended.
     */
    if (! str_contains($url, ':') && ! in_array($url[0], ['/', '#', '?'], true) &&
        ! preg_match('/^[a-z0-9-]+?\.php/i', $url)
    ) {
        $scheme = (is_array($protocols) && array_first($protocols) === 'https') ? 'https://' : 'http://';
        $url = $scheme.$url;
    }

    // Replace ampersands and single quotes only when displaying.
    if ($_context === 'display') {
        $url = wp_kses_normalize_entities($url);
        $url = str_replace('&amp;', '&#038;', $url);
        $url = str_replace("'", '&#039;', $url);
    }

    if (str_contains($url, '[') || str_contains($url, ']')) {

        $parsed = wp_parse_url($url);
        $front = '';

        if (isset($parsed['scheme'])) {
            $front .= $parsed['scheme'].'://';
        } elseif ($url[0] === '/') {
            $front .= '//';
        }

        if (isset($parsed['user'])) {
            $front .= $parsed['user'];
        }

        if (isset($parsed['pass'])) {
            $front .= ':'.$parsed['pass'];
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            $front .= '@';
        }

        if (isset($parsed['host'])) {
            $front .= $parsed['host'];
        }

        if (isset($parsed['port'])) {
            $front .= ':'.$parsed['port'];
        }

        $end_dirty = str_replace($front, '', $url);
        $end_clean = str_replace(['[', ']'], ['%5B', '%5D'], $end_dirty);
        $url = str_replace($end_dirty, $end_clean, $url);

    }

    if ($url[0] === '/') {
        $good_protocol_url = $url;
    } else {
        if (! is_array($protocols)) {
            $protocols = wp_allowed_protocols();
        }
        $good_protocol_url = wp_kses_bad_protocol($url, $protocols);
        if (strtolower($good_protocol_url) !== strtolower($url)) {
            return '';
        }
    }

    /**
     * Filters a string cleaned and escaped for output as a URL.
     *
     * @since WP-2.3.0
     *
     * @param  string  $good_protocol_url  The cleaned URL to be returned.
     * @param  string  $original_url  The URL prior to cleaning.
     * @param  string  $_context  If 'display', replace ampersands and single quotes only.
     */
    return apply_filters('clean_url', $good_protocol_url, $original_url, $_context);
}

/**
 * Sanitizes a URL for database or redirect usage.
 *
 * @since WM-0.5.0
 * @since WP-2.3.1
 * @since WP-2.8.0 Deprecated in favor of esc_url_raw().
 * @since WP-5.9.0 Restored (un-deprecated).
 * @since WP-6.9.0 Prepends `https://` to the URL if it does not already contain a scheme
 *              and the first item in `$protocols` is 'https'.
 * @see esc_url()
 *
 * @param  string  $url  The URL to be cleaned.
 * @param  string[]  $protocols  Optional. An array of acceptable protocols.
 *                               Defaults to return value of wp_allowed_protocols().
 * @return string The cleaned URL after esc_url() is run with the 'db' context.
 */
function sanitize_url($url, $protocols = null)
{
    return esc_url($url, $protocols, 'db');
}

/**
 * Performs a deep string replace operation to ensure the values in $search are no longer present.
 *
 * Repeats the replacement operation until it no longer replaces anything to remove "nested" values
 * e.g. $subject = '%0%0%0DDD', $search ='%0D', $result ='' rather than the '%0%0DD' that
 * str_replace would return
 *
 * @since WM-0.5.0
 * @since WP-2.8.1
 *
 * @param  string|array  $search  The value being searched for, otherwise known as the needle.
 *                                An array may be used to designate multiple needles.
 * @param  string  $subject  The string being searched and replaced on, otherwise known as the haystack.
 * @return string The string with the replaced values.
 */
function _deep_replace($search, $subject)
{
    $subject = (string) $subject;

    $count = 1;
    while ($count) {
        $subject = str_replace($search, '', $subject, $count);
    }

    return $subject;
}

/**
 * Retrieves a list of protocols to allow in HTML attributes.
 *
 * @since WM-0.5.0
 * @since WP-3.3.0
 * @since WP-4.3.0 Added 'webcal' to the protocols array.
 * @since WP-4.7.0 Added 'urn' to the protocols array.
 * @since WP-5.3.0 Added 'sms' to the protocols array.
 * @since WP-5.6.0 Added 'irc6' and 'ircs' to the protocols array.
 * @see wp_kses()
 * @see esc_url()
 *
 * @return string[] Array of allowed protocols. Defaults to an array containing 'http', 'https',
 *                  'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6', 'ircs', 'gopher', 'nntp', 'feed',
 *                  'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', and 'urn'.
 *                  This covers all common link protocols, except for 'javascript' which should not
 *                  be allowed for untrusted users.
 */
function wp_allowed_protocols()
{
    static $protocols = [];

    if (empty($protocols)) {
        $protocols = ['http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6', 'ircs', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn'];
    }

    if (! did_action('wp_loaded')) {
        /**
         * Filters the list of protocols allowed in HTML attributes.
         *
         * @since WP-3.0.0
         *
         * @param  string[]  $protocols  Array of allowed protocols e.g. 'http', 'ftp', 'tel', and more.
         */
        $protocols = array_unique((array) apply_filters('kses_allowed_protocols', $protocols));
    }

    return $protocols;
}

/**
 * A wrapper for PHP's parse_url() function that handles consistency in the return values
 * across PHP versions.
 *
 * Across various PHP versions, schemeless URLs containing a ":" in the query
 * are being handled inconsistently. This function works around those differences.
 *
 * @since WM-0.5.0
 * @since WP-4.4.0
 * @since WP-4.7.0 The `$component` parameter was added for parity with PHP's `parse_url()`.
 * @link https://www.php.net/manual/en/function.parse-url.php
 *
 * @param  string  $url  The URL to parse.
 * @param  int  $component  The specific component to retrieve. Use one of the PHP
 *                          predefined constants to specify which one.
 *                          Defaults to -1 (= return all parts as an array).
 *
 * @phpstan-param int<-1, 7> $component
 *
 * @return mixed False on parse failure; Array of URL components on success;
 *               When a specific component has been requested: null if the component
 *               doesn't exist in the given URL; a string or - in the case of
 *               PHP_URL_PORT - integer when it does. See parse_url()'s return values.
 *
 * @phpstan-return (
 *     $component is -1
 *         ? false|array{
 *               scheme?: string,
 *               host?: string,
 *               port?: int<0, 65535>,
 *               user?: string,
 *               pass?: string,
 *               path?: string,
 *               query?: string,
 *               fragment?: string,
 *           }
 *         : (
 *             $component is 2
 *                 ? int<0, 65535>|null
 *                 : string|null
 *         )
 * )
 */
function wp_parse_url($url, $component = -1)
{
    $to_unset = [];
    $url = (string) $url;

    if (str_starts_with($url, '//')) {
        $to_unset[] = 'scheme';
        $url = 'placeholder:'.$url;
    } elseif (str_starts_with($url, '/')) {
        $to_unset[] = 'scheme';
        $to_unset[] = 'host';
        $url = 'placeholder://placeholder'.$url;
    }

    $parts = parse_url($url);

    if ($parts === false) {
        // Parsing failure.
        return $parts;
    }

    // Remove the placeholder values.
    foreach ($to_unset as $key) {
        unset($parts[$key]);
    }

    return _get_component_from_parsed_url_array($parts, $component);
}

/**
 * Retrieves a specific component from a parsed URL array.
 *
 * @internal
 *
 * @since WM-0.5.0
 * @since WP-4.7.0
 * @link https://www.php.net/manual/en/function.parse-url.php
 *
 * @param  array|false  $url_parts  The parsed URL. Can be false if the URL failed to parse.
 * @param  int  $component  The specific component to retrieve. Use one of the PHP
 *                          predefined constants to specify which one.
 *                          Defaults to -1 (= return all parts as an array).
 *
 * @phpstan-param false|array{
 *     scheme?: string,
 *     host?: string,
 *     port?: int<0, 65535>,
 *     user?: string,
 *     pass?: string,
 *     path?: string,
 *     query?: string,
 *     fragment?: string,
 * } $url_parts
 * @phpstan-param int<-1, 7> $component
 *
 * @return mixed False on parse failure; Array of URL components on success;
 *               When a specific component has been requested: null if the component
 *               doesn't exist in the given URL; a string or - in the case of
 *               PHP_URL_PORT - integer when it does. See parse_url()'s return values.
 *
 * @phpstan-return (
 *     $component is -1
 *         ? false|array{
 *               scheme?: string,
 *               host?: string,
 *               port?: int<0, 65535>,
 *               user?: string,
 *               pass?: string,
 *               path?: string,
 *               query?: string,
 *               fragment?: string,
 *           }
 *         : (
 *             $component is 2
 *                 ? int<0, 65535>|null
 *                 : string|null
 *         )
 * )
 */
function _get_component_from_parsed_url_array($url_parts, $component = -1)
{
    if ($component === -1) {
        return $url_parts;
    }

    $key = _wp_translate_php_url_constant_to_key($component);
    if ($key !== false && is_array($url_parts) && isset($url_parts[$key])) {
        return $url_parts[$key];
    } else {
        return null;
    }
}

/**
 * Translates a PHP_URL_* constant to the named array keys PHP uses.
 *
 * @internal
 *
 * @since WM-0.5.0
 * @since WP-4.7.0
 * @link https://www.php.net/manual/en/url.constants.php
 *
 * @param  int  $constant  PHP_URL_* constant.
 *
 * @phpstan-param int<-1, 7> $constant
 *
 * @return string|false The named key or false.
 *
 * @phpstan-return 'scheme'|'host'|'port'|'user'|'pass'|'path'|'query'|'fragment'|false
 */
function _wp_translate_php_url_constant_to_key($constant)
{
    $translation = [
        PHP_URL_SCHEME => 'scheme',
        PHP_URL_HOST => 'host',
        PHP_URL_PORT => 'port',
        PHP_URL_USER => 'user',
        PHP_URL_PASS => 'pass',
        PHP_URL_PATH => 'path',
        PHP_URL_QUERY => 'query',
        PHP_URL_FRAGMENT => 'fragment',
    ];

    return $translation[$constant] ?? false;
}

if (! function_exists('array_first')) {
    /**
     * Polyfill for `array_first()` function added in PHP 8.5.
     *
     * Returns the first element of an array.
     *
     * @since WM-0.5.0
     * @since WP-6.9.0
     *
     * @param  array  $array  The array to get the first element from.
     * @return mixed|null The first element of the array, or null if the array is empty.
     */
    function array_first(array $array) // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
    {
        if (empty($array)) {
            return null;
        }

        foreach ($array as $value) {
            return $value;
        }
    }
}

/**
 * Retrieves IDs that are not already present in the cache.
 *
 * @since WM-0.5.0
 * @since WP-3.4.0
 * @since WP-6.1.0 This function is no longer marked as "private".
 *
 * @param  int[]  $object_ids  Array of IDs.
 * @param  string  $cache_group  The cache group to check against.
 * @return int[] Array of IDs not present in the cache.
 */
function _get_non_cached_ids($object_ids, $cache_group)
{
    $object_ids = array_filter($object_ids, '_validate_cache_id');
    $object_ids = array_unique(array_map('intval', $object_ids), SORT_NUMERIC);

    if (empty($object_ids)) {
        return [];
    }

    $non_cached_ids = [];
    $cache_values = wp_cache_get_multiple($object_ids, $cache_group);

    foreach ($cache_values as $id => $value) {
        if ($value === false) {
            $non_cached_ids[] = (int) $id;
        }
    }

    return $non_cached_ids;
}

/**
 * Escapes single quotes, `"`, `<`, `>`, `&`, and fixes line endings.
 *
 * Escapes text strings for echoing in JS. It is intended to be used for inline JS
 * (in a tag attribute, for example `onclick="..."`). Note that the strings have to
 * be in single quotes. The {@see 'js_escape'} filter is also applied here.
 *
 * @since WM-0.5.0
 * @since WP-2.8.0
 *
 * @param  string  $text  The text to be escaped.
 * @return string Escaped text.
 */
function esc_js($text)
{
    $safe_text = wp_check_invalid_utf8($text);
    $safe_text = _wp_specialchars($safe_text, ENT_COMPAT);
    $safe_text = preg_replace('/&#(x)?0*(?(1)27|39);?/i', "'", stripslashes($safe_text));
    $safe_text = str_replace("\r", '', $safe_text);
    $safe_text = str_replace("\n", '\\n', addslashes($safe_text));

    /**
     * Filters a string cleaned and escaped for output in JavaScript.
     *
     * Text passed to esc_js() is stripped of invalid or special characters,
     * and properly slashed for output.
     *
     * @since WP-2.0.6
     *
     * @param  string  $safe_text  The text after it has been escaped.
     * @param  string  $text  The text prior to being escaped.
     */
    return apply_filters('js_escape', $safe_text, $text);
}

/**
 * Add leading zeros when necessary.
 *
 * If you set the threshold to '4' and the number is '10', then you will get
 * back '0010'. If you set the threshold to '4' and the number is '5000', then you
 * will get back '5000'.
 *
 * Uses sprintf to append the amount of zeros based on the $threshold parameter
 * and the size of the number. If the number is large enough, then no zeros will
 * be appended.
 *
 * @since WM-0.5.0
 * @since WP-0.71
 *
 * @param  int  $number  Number to append zeros to if not greater than threshold.
 * @param  int  $threshold  Digit places number needs to be to not have zeros added.
 * @return string Adds leading zeros to number if needed.
 */
function zeroise($number, $threshold)
{
    return sprintf('%0'.$threshold.'s', $number);
}
