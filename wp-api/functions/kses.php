<?php

/**
 * WordPress's HTML entity and URL-protocol rules (kses): the named entities it
 * keeps, wp_kses_normalize_entities() (for esc_html() and esc_attr()), and
 * wp_kses_bad_protocol() (for esc_url()).
 * The rest of kses, HTML filtering itself, comes with its own step (the
 * wp_kses() stand-in is in wp-api/interim/kses.php).
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/kses.php.
 */

/**
 * @var string[] $allowedentitynames Array of KSES allowed HTML entity names.
 *
 * @since WP-1.0.0
 */
$allowedentitynames = [
    'nbsp',
    'iexcl',
    'cent',
    'pound',
    'curren',
    'yen',
    'brvbar',
    'sect',
    'uml',
    'copy',
    'ordf',
    'laquo',
    'not',
    'shy',
    'reg',
    'macr',
    'deg',
    'plusmn',
    'acute',
    'micro',
    'para',
    'middot',
    'cedil',
    'ordm',
    'raquo',
    'iquest',
    'Agrave',
    'Aacute',
    'Acirc',
    'Atilde',
    'Auml',
    'Aring',
    'AElig',
    'Ccedil',
    'Egrave',
    'Eacute',
    'Ecirc',
    'Euml',
    'Igrave',
    'Iacute',
    'Icirc',
    'Iuml',
    'ETH',
    'Ntilde',
    'Ograve',
    'Oacute',
    'Ocirc',
    'Otilde',
    'Ouml',
    'times',
    'Oslash',
    'Ugrave',
    'Uacute',
    'Ucirc',
    'Uuml',
    'Yacute',
    'THORN',
    'szlig',
    'agrave',
    'aacute',
    'acirc',
    'atilde',
    'auml',
    'aring',
    'aelig',
    'ccedil',
    'egrave',
    'eacute',
    'ecirc',
    'euml',
    'igrave',
    'iacute',
    'icirc',
    'iuml',
    'eth',
    'ntilde',
    'ograve',
    'oacute',
    'ocirc',
    'otilde',
    'ouml',
    'divide',
    'oslash',
    'ugrave',
    'uacute',
    'ucirc',
    'uuml',
    'yacute',
    'thorn',
    'yuml',
    'quot',
    'amp',
    'lt',
    'gt',
    'apos',
    'OElig',
    'oelig',
    'Scaron',
    'scaron',
    'Yuml',
    'circ',
    'tilde',
    'ensp',
    'emsp',
    'thinsp',
    'zwnj',
    'zwj',
    'lrm',
    'rlm',
    'ndash',
    'mdash',
    'lsquo',
    'rsquo',
    'sbquo',
    'ldquo',
    'rdquo',
    'bdquo',
    'dagger',
    'Dagger',
    'permil',
    'lsaquo',
    'rsaquo',
    'euro',
    'fnof',
    'Alpha',
    'Beta',
    'Gamma',
    'Delta',
    'Epsilon',
    'Zeta',
    'Eta',
    'Theta',
    'Iota',
    'Kappa',
    'Lambda',
    'Mu',
    'Nu',
    'Xi',
    'Omicron',
    'Pi',
    'Rho',
    'Sigma',
    'Tau',
    'Upsilon',
    'Phi',
    'Chi',
    'Psi',
    'Omega',
    'alpha',
    'beta',
    'gamma',
    'delta',
    'epsilon',
    'zeta',
    'eta',
    'theta',
    'iota',
    'kappa',
    'lambda',
    'mu',
    'nu',
    'xi',
    'omicron',
    'pi',
    'rho',
    'sigmaf',
    'sigma',
    'tau',
    'upsilon',
    'phi',
    'chi',
    'psi',
    'omega',
    'thetasym',
    'upsih',
    'piv',
    'bull',
    'hellip',
    'prime',
    'Prime',
    'oline',
    'frasl',
    'weierp',
    'image',
    'real',
    'trade',
    'alefsym',
    'larr',
    'uarr',
    'rarr',
    'darr',
    'harr',
    'crarr',
    'lArr',
    'uArr',
    'rArr',
    'dArr',
    'hArr',
    'forall',
    'part',
    'exist',
    'empty',
    'nabla',
    'isin',
    'notin',
    'ni',
    'prod',
    'sum',
    'minus',
    'lowast',
    'radic',
    'prop',
    'infin',
    'ang',
    'and',
    'or',
    'cap',
    'cup',
    'int',
    'sim',
    'cong',
    'asymp',
    'ne',
    'equiv',
    'le',
    'ge',
    'sub',
    'sup',
    'nsub',
    'sube',
    'supe',
    'oplus',
    'otimes',
    'perp',
    'sdot',
    'lceil',
    'rceil',
    'lfloor',
    'rfloor',
    'lang',
    'rang',
    'loz',
    'spades',
    'clubs',
    'hearts',
    'diams',
    'sup1',
    'sup2',
    'sup3',
    'frac14',
    'frac12',
    'frac34',
    'there4',
];

$GLOBALS['allowedentitynames'] = $allowedentitynames;

/**
 * @var string[] $allowedxmlentitynames Array of KSES allowed XML entity names.
 *
 * @since WP-5.5.0
 */
$allowedxmlentitynames = [
    'amp',
    'lt',
    'gt',
    'apos',
    'quot',
];

$GLOBALS['allowedxmlentitynames'] = $allowedxmlentitynames;

/**
 * Converts and fixes HTML entities.
 *
 * This function normalizes HTML entities. It will convert `AT&T` to the correct
 * `AT&amp;T`, `&#00058;` to `&#058;`, `&#XYZZY;` to `&amp;#XYZZY;` and so on.
 *
 * When `$context` is set to 'xml', HTML entities are converted to their code points.  For
 * example, `AT&T&hellip;&#XYZZY;` is converted to `AT&amp;T…&amp;#XYZZY;`.
 *
 * @since WM-0.5.0
 * @since WP-1.0.0
 * @since WP-5.5.0 Added `$context` parameter.
 *
 * @param  string  $content  Content to normalize entities.
 * @param  string  $context  Context for normalization. Can be either 'html' or 'xml'.
 *                           Default 'html'.
 * @return string Content with normalized entities.
 */
function wp_kses_normalize_entities($content, $context = 'html')
{
    // Disarm all entities by converting & to &amp;
    $content = str_replace('&', '&amp;', $content);

    /*
     * Decode any character references that are now double-encoded.
     *
     * It's important that the following normalizations happen in the correct order.
     *
     * At this point, all `&` have been transformed to `&amp;`. Double-encoded named character
     * references like `&amp;amp;` will be decoded back to their single-encoded form `&amp;`.
     *
     * First, numeric (decimal and hexadecimal) character references must be handled so that
     * `&amp;#09;` becomes `&#9;`. If the named character references were handled first, there
     * would be no way to know whether the double-encoded character reference had been produced
     * in this function or was the original input.
     *
     * Consider the two examples, first with named entity decoding followed by numeric
     * entity decoding. We'll use U+002E FULL STOP (.) in our example, this table follows the
     * string processing from left to right:
     *
     * | Input        | &-encoded        | Named ref double-decoded  | Numeric ref double-decoded |
     * | ------------ | ---------------- | ------------------------- | -------------------------- |
     * | `&#x2E;`     | `&amp;#x2E;`     | `&amp;#x2E;`              | `&#x2E;`                   |
     * | `&amp;#x2E;` | `&amp;amp;#x2E;` | `&amp;#x2E;`              | `&#x2E;`                   |
     *
     * Notice in the example above that different inputs result in the same result. The second case
     * was not normalized and produced HTML that is semantically different from the input.
     *
     * | Input        | &-encoded        |  Numeric ref double-decoded | Named ref double-decoded |
     * | ------------ | ---------------- | --------------------------- | ------------------------ |
     * | `&#x2E;`     | `&amp;#x2E;`     | `&#x2E;`                    | `&#x2E;`                 |
     * | `&amp;#x2E;` | `&amp;amp;#x2E;` | `&amp;amp;#x2E;`            | `&amp;#x2E;`             |
     *
     * Here, each input is normalized to an appropriate output.
     */
    $content = preg_replace_callback('/&amp;#(0*[1-9][0-9]{0,6});/', 'wp_kses_normalize_entities2', $content);
    $content = preg_replace_callback('/&amp;#[Xx](0*[1-9A-Fa-f][0-9A-Fa-f]{0,5});/', 'wp_kses_normalize_entities3', $content);
    if ($context === 'xml') {
        $content = preg_replace_callback('/&amp;([A-Za-z]{2,8}[0-9]{0,2});/', 'wp_kses_xml_named_entities', $content);
    } else {
        $content = preg_replace_callback('/&amp;([A-Za-z]{2,8}[0-9]{0,2});/', 'wp_kses_named_entities', $content);
    }

    return $content;
}

/**
 * Callback for `wp_kses_normalize_entities()` regular expression.
 *
 * This function only accepts valid named entity references, which are finite,
 * case-sensitive, and highly scrutinized by HTML and XML validators.
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 *
 * @global array $allowedentitynames
 *
 * @param  array  $matches  preg_replace_callback() matches array.
 * @return string Correctly encoded entity.
 */
function wp_kses_named_entities($matches)
{
    global $allowedentitynames;

    if (empty($matches[1])) {
        return '';
    }

    $i = $matches[1];

    return (! in_array($i, $allowedentitynames, true)) ? "&amp;$i;" : "&$i;";
}

/**
 * Callback for `wp_kses_normalize_entities()` regular expression.
 *
 * This function only accepts valid named entity references, which are finite,
 * case-sensitive, and highly scrutinized by XML validators.  HTML named entity
 * references are converted to their code points.
 *
 * @since WM-0.5.0
 * @since WP-5.5.0
 *
 * @global array $allowedentitynames
 * @global array $allowedxmlentitynames
 *
 * @param  array  $matches  preg_replace_callback() matches array.
 * @return string Correctly encoded entity.
 */
function wp_kses_xml_named_entities($matches)
{
    global $allowedentitynames, $allowedxmlentitynames;

    if (empty($matches[1])) {
        return '';
    }

    $i = $matches[1];

    if (in_array($i, $allowedxmlentitynames, true)) {
        return "&$i;";
    } elseif (in_array($i, $allowedentitynames, true)) {
        return html_entity_decode("&$i;", ENT_HTML5);
    }

    return "&amp;$i;";
}

/**
 * Callback for `wp_kses_normalize_entities()` regular expression.
 *
 * This function helps `wp_kses_normalize_entities()` to only accept 16-bit
 * values and nothing more for `&#number;` entities.
 *
 * @ignore
 *
 * @since WM-0.5.0
 * @since WP-1.0.0
 *
 * @param  array  $matches  `preg_replace_callback()` matches array.
 * @return string Correctly encoded entity.
 */
function wp_kses_normalize_entities2($matches)
{
    if (empty($matches[1])) {
        return '';
    }

    $i = $matches[1];

    if (valid_unicode($i)) {
        $i = str_pad(ltrim($i, '0'), 3, '0', STR_PAD_LEFT);
        $i = "&#$i;";
    } else {
        $i = "&amp;#$i;";
    }

    return $i;
}

/**
 * Callback for `wp_kses_normalize_entities()` for regular expression.
 *
 * This function helps `wp_kses_normalize_entities()` to only accept valid Unicode
 * numeric entities in hex form.
 *
 * @since WM-0.5.0
 * @since WP-2.7.0
 *
 * @ignore
 *
 * @param  array  $matches  `preg_replace_callback()` matches array.
 * @return string Correctly encoded entity.
 */
function wp_kses_normalize_entities3($matches)
{
    if (empty($matches[1])) {
        return '';
    }

    $hexchars = $matches[1];

    return (! valid_unicode(hexdec($hexchars))) ? "&amp;#x$hexchars;" : '&#x'.ltrim($hexchars, '0').';';
}

/**
 * Determines if a Unicode codepoint is valid.
 *
 * The definition of a valid Unicode codepoint is taken from the XML definition:
 *
 * > Characters
 * >
 * > …
 * > Legal characters are tab, carriage return, line feed, and the legal characters of
 * > Unicode and ISO/IEC 10646.
 * > …
 * > Char ::= #x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD] | [#x10000-#x10FFFF]
 *
 * @since WM-0.5.0
 * @since WP-2.7.0
 * @see https://www.w3.org/TR/xml/#charsets
 *
 * @param  int  $i  Unicode codepoint.
 * @return bool Whether or not the codepoint is a valid Unicode codepoint.
 */
function valid_unicode($i)
{
    $i = (int) $i;

    return
        $i === 0x9 || // U+0009 HORIZONTAL TABULATION (HT)
        $i === 0xA || // U+000A LINE FEED (LF)
        $i === 0xD || // U+000D CARRIAGE RETURN (CR)
        /*
         * The valid Unicode characters according to the XML specification:
         *
         * > any Unicode character, excluding the surrogate blocks, FFFE, and FFFF.
         */
        ($i >= 0x20 && $i <= 0xD7FF) ||
        ($i >= 0xE000 && $i <= 0xFFFD) ||
        ($i >= 0x10000 && $i <= 0x10FFFF);
}

/**
 * Sanitizes a string and removed disallowed URL protocols.
 *
 * This function removes all non-allowed protocols from the beginning of the
 * string. It ignores whitespace and the case of the letters, and it does
 * understand HTML entities. It does its work recursively, so it won't be
 * fooled by a string like `javascript:javascript:alert(57)`.
 *
 * @since WM-0.5.0
 * @since WP-1.0.0
 *
 * @param  string  $content  Content to filter bad protocols from.
 * @param  string[]  $allowed_protocols  Array of allowed URL protocols.
 * @return string Filtered content.
 */
function wp_kses_bad_protocol($content, $allowed_protocols)
{
    $content = wp_kses_no_null($content);

    // Short-circuit if the string starts with `https://` or `http://`. Most common cases.
    if (
        (str_starts_with($content, 'https://') && in_array('https', $allowed_protocols, true)) ||
        (str_starts_with($content, 'http://') && in_array('http', $allowed_protocols, true))
    ) {
        return $content;
    }

    $iterations = 0;

    do {
        $original_content = $content;
        $content = wp_kses_bad_protocol_once($content, $allowed_protocols);
    } while ($original_content !== $content && ++$iterations < 6);

    if ($original_content !== $content) {
        return '';
    }

    return $content;
}

/**
 * Sanitizes content from bad protocols and other characters.
 *
 * This function searches for URL protocols at the beginning of the string, while
 * handling whitespace and HTML entities.
 *
 * @since WM-0.5.0
 * @since WP-1.0.0
 *
 * @param  string  $content  Content to check for bad protocols.
 * @param  string[]  $allowed_protocols  Array of allowed URL protocols.
 * @param  int  $count  Depth of call recursion to this function.
 * @return string Sanitized content.
 */
function wp_kses_bad_protocol_once($content, $allowed_protocols, $count = 1)
{
    $content = preg_replace('/(&#0*58(?![;0-9])|&#x0*3a(?![;a-f0-9]))/i', '$1;', $content);
    $content2 = preg_split('/:|&#0*58;|&#x0*3a;|&colon;/i', $content, 2);

    if (isset($content2[1]) && ! preg_match('%/\?%', $content2[0])) {
        $content = trim($content2[1]);
        $protocol = wp_kses_bad_protocol_once2($content2[0], $allowed_protocols);
        if ($protocol === 'feed:') {
            if ($count > 2) {
                return '';
            }
            $content = wp_kses_bad_protocol_once($content, $allowed_protocols, ++$count);
            if (empty($content)) {
                return $content;
            }
        }
        $content = $protocol.$content;
    }

    return $content;
}

/**
 * Callback for `wp_kses_bad_protocol_once()` regular expression.
 *
 * This function processes URL protocols, checks to see if they're in the
 * list of allowed protocols or not, and returns different data depending
 * on the answer.
 *
 * @ignore
 *
 * @since WM-0.5.0
 * @since WP-1.0.0
 *
 * @param  string  $scheme  URI scheme to check against the list of allowed protocols.
 * @param  string[]  $allowed_protocols  Array of allowed URL protocols.
 * @return string Sanitized content.
 */
function wp_kses_bad_protocol_once2($scheme, $allowed_protocols)
{
    $scheme = wp_kses_decode_entities($scheme);
    $scheme = preg_replace('/\s/', '', $scheme);
    $scheme = wp_kses_no_null($scheme);
    $scheme = strtolower($scheme);

    $allowed = array_any((array) $allowed_protocols, fn ($protocol) => strtolower($protocol) === $scheme);

    if ($allowed) {
        return "$scheme:";
    } else {
        return '';
    }
}

/**
 * Removes any invalid control characters in a text string.
 *
 * Also removes any instance of the `\0` string.
 *
 * @since WM-0.5.0
 * @since WP-1.0.0
 *
 * @param  string  $content  Content to filter null characters from.
 * @param  array  $options  Set 'slash_zero' => 'keep' when '\0' is allowed. Default is 'remove'.
 * @return string Filtered content.
 */
function wp_kses_no_null($content, $options = null)
{
    if (! isset($options['slash_zero'])) {
        $options = ['slash_zero' => 'remove'];
    }

    $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $content);
    if ($options['slash_zero'] === 'remove') {
        $content = preg_replace('/\\\\+0+/', '', $content);
    }

    return $content;
}

/**
 * Converts all numeric HTML entities to their named counterparts.
 *
 * This function decodes numeric HTML entities (`&#65;` and `&#x41;`).
 * It doesn't do anything with named entities like `&auml;`, but we don't
 * need them in the allowed URL protocols system anyway.
 *
 * @since WM-0.5.0
 * @since WP-1.0.0
 *
 * @param  string  $content  Content to change entities.
 * @return string Content after decoded entities.
 */
function wp_kses_decode_entities($content)
{
    $content = preg_replace_callback('/&#([0-9]+);/', '_wp_kses_decode_entities_chr', $content);
    $content = preg_replace_callback('/&#[Xx]([0-9A-Fa-f]+);/', '_wp_kses_decode_entities_chr_hexdec', $content);

    return $content;
}

/**
 * Regex callback for `wp_kses_decode_entities()`.
 *
 * @since WM-0.5.0
 * @since WP-2.9.0
 *
 * @ignore
 *
 * @param  array  $matches  preg match
 * @return string
 */
function _wp_kses_decode_entities_chr($matches)
{
    return chr($matches[1]);
}

/**
 * Regex callback for `wp_kses_decode_entities()`.
 *
 * @since WM-0.5.0
 * @since WP-2.9.0
 *
 * @ignore
 *
 * @param  array  $matches  preg match
 * @return string
 */
function _wp_kses_decode_entities_chr_hexdec($matches)
{
    return chr(hexdec($matches[1]));
}
