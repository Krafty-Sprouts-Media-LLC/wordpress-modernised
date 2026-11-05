<?php

/**
 * Small value helpers: absint(), and the mbstring encoding switch wpdb uses for byte lengths.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/load.php and functions.php.
 */

/**
 * Converts a value to non-negative integer.
 *
 * @since WM-0.1.0
 * @since WP-2.5.0
 *
 * @param  mixed  $maybeint  Data you wish to have converted to a non-negative integer.
 * @return int A non-negative integer.
 */
function absint($maybeint)
{
    return abs((int) $maybeint);
}

/**
 * Sets the mbstring internal encoding to a binary safe encoding when func_overload
 * is enabled.
 *
 * When mbstring.func_overload is in use for multi-byte encodings, the results from
 * strlen() and similar functions respect the utf8 characters, causing binary data
 * to return incorrect lengths.
 *
 * This function overrides the mbstring encoding to a binary-safe encoding, and
 * resets it to the users expected encoding afterwards through the
 * `reset_mbstring_encoding` function.
 *
 * It is safe to recursively call this function, however each
 * `mbstring_binary_safe_encoding()` call must be followed up with an equal number
 * of `reset_mbstring_encoding()` calls.
 *
 * @since WM-0.1.0
 * @since WP-3.7.0
 * @see reset_mbstring_encoding()
 *
 * @param  bool  $reset  Optional. Whether to reset the encoding back to a previously-set encoding.
 *                       Default false.
 */
function mbstring_binary_safe_encoding($reset = false)
{
    static $encodings = [];
    static $overloaded = null;

    if (is_null($overloaded)) {
        if (function_exists('mb_internal_encoding')
            && ((int) ini_get('mbstring.func_overload') & 2)
        ) {
            $overloaded = true;
        } else {
            $overloaded = false;
        }
    }

    if ($overloaded === false) {
        return;
    }

    if (! $reset) {
        $encoding = mb_internal_encoding();
        array_push($encodings, $encoding);
        mb_internal_encoding('ISO-8859-1');
    }

    if ($reset && $encodings) {
        $encoding = array_pop($encodings);
        mb_internal_encoding($encoding);
    }
}

/**
 * Resets the mbstring internal encoding to a users previously set encoding.
 *
 * @see mbstring_binary_safe_encoding()
 * @since WM-0.1.0
 * @since WP-3.7.0
 */
function reset_mbstring_encoding()
{
    mbstring_binary_safe_encoding(true);
}

/**
 * Tests if a given path is a stream URL
 *
 * @since WM-0.1.0
 * @since WP-3.5.0
 *
 * @param  string  $path  The resource path or URL.
 * @return bool True if the path is a stream URL.
 */
function wp_is_stream($path)
{
    $scheme_separator = strpos($path, '://');

    if ($scheme_separator === false) {
        // $path isn't a stream.
        return false;
    }

    $stream = substr($path, 0, $scheme_separator);

    return in_array($stream, stream_get_wrappers(), true);
}

/**
 * Normalizes a filesystem path.
 *
 * On windows systems, replaces backslashes with forward slashes
 * and forces upper-case drive letters.
 * Allows for two leading slashes for Windows network shares, but
 * ensures that all other duplicate slashes are reduced to a single.
 *
 * @since WM-0.1.0
 * @since WP-3.9.0
 * @since WP-4.4.0 Ensures upper-case drive letters on Windows systems.
 * @since WP-4.5.0 Allows for Windows network shares.
 * @since WP-4.9.7 Allows for PHP file wrappers.
 * @since WP-7.0.0 Uses a static cache to store normalized paths.
 *
 * @param  string  $path  Path to normalize.
 * @return string Normalized path.
 */
function wp_normalize_path($path): string
{
    $path = (string) $path;

    static $cache = [];
    if (isset($cache[$path])) {
        return $cache[$path];
    }

    $original_path = $path;
    $wrapper = '';

    if (wp_is_stream($path)) {
        [$wrapper, $path] = explode('://', $path, 2);

        $wrapper .= '://';
    }

    // Standardize all paths to use '/'.
    $path = str_replace('\\', '/', $path);

    // Replace multiple slashes down to a singular, allowing for network shares having two slashes.
    $path = (string) preg_replace('|(?<=.)/+|', '/', $path);

    // Windows paths should uppercase the drive letter.
    if (substr($path, 1, 1) === ':') {
        $path = ucfirst($path);
    }

    $cache[$original_path] = $wrapper.$path;

    return $cache[$original_path];
}
