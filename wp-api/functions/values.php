<?php

/**
 * Small value helpers: absint(), the mbstring encoding switch wpdb uses for
 * byte lengths, the serialization helpers options store values with, and the
 * slash helpers.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/load.php, functions.php
 * and formatting.php.
 */

/**
 * Converts a value to non-negative integer.
 *
 * @since WM-0.2.0
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
 * @since WM-0.2.0
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
 * @since WM-0.2.0
 * @since WP-3.7.0
 */
function reset_mbstring_encoding()
{
    mbstring_binary_safe_encoding(true);
}

/**
 * Tests if a given path is a stream URL
 *
 * @since WM-0.2.0
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
 * @since WM-0.2.0
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

/**
 * Serializes data, if needed.
 *
 * @since WM-0.4.0
 * @since WP-2.0.5
 *
 * @param  string|array|object  $data  Data that might be serialized.
 * @return mixed A scalar data.
 */
function maybe_serialize($data)
{
    if (is_array($data) || is_object($data)) {
        return serialize($data);
    }

    /*
     * Double serialization is required for backward compatibility.
     * See https://core.trac.wordpress.org/ticket/12930
     * Also the world will end. See WP 3.6.1.
     */
    if (is_serialized($data, false)) {
        return serialize($data);
    }

    return $data;
}

/**
 * Unserializes data only if it was serialized.
 *
 * Like WordPress, any class can be rebuilt: plugins store their own objects in
 * options. Moving stored values to JSON is a later, recorded change (owner's
 * decision B, 2026-11-07).
 *
 * @since WM-0.4.0
 * @since WP-2.0.0
 *
 * @param  string  $data  Data that might be unserialized.
 * @return mixed Unserialized data can be any type.
 */
function maybe_unserialize($data)
{
    if (is_serialized($data)) { // Don't attempt to unserialize data that wasn't serialized going in.
        return @unserialize(trim($data));
    }

    return $data;
}

/**
 * Checks value to find if it was serialized.
 *
 * If $data is not a string, then returned value will always be false.
 * Serialized data is always a string.
 *
 * @since WM-0.4.0
 * @since WP-2.0.5
 * @since WP-6.1.0 Added Enum support.
 *
 * @param  string  $data  Value to check to see if was serialized.
 * @param  bool  $strict  Optional. Whether to be strict about the end of the string. Default true.
 * @return bool False if not serialized and true if it was.
 */
function is_serialized($data, $strict = true)
{
    // If it isn't a string, it isn't serialized.
    if (! is_string($data)) {
        return false;
    }

    $data = trim($data);

    if ($data === 'N;') {
        return true;
    }

    if (strlen($data) < 4) {
        return false;
    }

    if ($data[1] !== ':') {
        return false;
    }

    if ($strict) {
        $lastc = substr($data, -1);

        if ($lastc !== ';' && $lastc !== '}') {
            return false;
        }
    } else {
        $semicolon = strpos($data, ';');
        $brace = strpos($data, '}');

        // Either ; or } must exist.
        if ($semicolon === false && $brace === false) {
            return false;
        }

        // But neither must be in the first X characters.
        if ($semicolon !== false && $semicolon < 3) {
            return false;
        }

        if ($brace !== false && $brace < 4) {
            return false;
        }
    }

    $token = $data[0];

    switch ($token) {
        case 's':
            if ($strict) {
                if (substr($data, -2, 1) !== '"') {
                    return false;
                }
            } elseif (! str_contains($data, '"')) {
                return false;
            }
            // Or else fall through.
            // no break
        case 'a':
        case 'O':
        case 'E':
            return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);

        case 'b':
        case 'i':
        case 'd':
            $end = $strict ? '$' : '';

            return (bool) preg_match("/^{$token}:[0-9.E+-]+;$end/", $data);
    }

    return false;
}

/**
 * Checks whether serialized data is of string type.
 *
 * @since WM-0.4.0
 * @since WP-2.0.5
 *
 * @param  string  $data  Serialized data.
 * @return bool False if not a serialized string, true if it is.
 */
function is_serialized_string($data)
{
    // if it isn't a string, it isn't a serialized string.
    if (! is_string($data)) {
        return false;
    }

    $data = trim($data);

    if (strlen($data) < 4) {
        return false;
    } elseif ($data[1] !== ':') {
        return false;
    } elseif (! str_ends_with($data, ';')) {
        return false;
    } elseif ($data[0] !== 's') {
        return false;
    } elseif (substr($data, -2, 1) !== '"') {
        return false;
    } else {
        return true;
    }
}

/**
 * Adds slashes to a string or recursively adds slashes to strings within an array.
 *
 * This should be used when preparing data for core API that expects slashed data.
 * This should not be used to escape data going directly into an SQL query.
 *
 * @since WM-0.4.0
 * @since WP-3.6.0
 * @since WP-5.5.0 Non-string values are left untouched.
 *
 * @param  string|array  $value  String or array of data to slash.
 * @return string|array Slashed `$value`, in the same type as supplied.
 */
function wp_slash($value)
{
    if (is_array($value)) {
        return array_map('wp_slash', $value);
    }

    if (is_string($value)) {
        return addslashes($value);
    }

    return $value;
}

/**
 * Removes slashes from a string or recursively removes slashes from strings within an array.
 *
 * This should be used to remove slashes from data passed to core API that
 * expects data to be unslashed.
 *
 * @since WM-0.4.0
 * @since WP-3.6.0
 *
 * @param  string|array  $value  String or array of data to unslash.
 * @return string|array Unslashed `$value`, in the same type as supplied.
 */
function wp_unslash($value)
{
    return stripslashes_deep($value);
}

/**
 * Navigates through an array, object, or scalar, and removes slashes from the values.
 *
 * @since WM-0.4.0
 * @since WP-2.0.0
 *
 * @param  mixed  $value  The value to be stripped.
 * @return mixed Stripped value.
 */
function stripslashes_deep($value)
{
    return map_deep($value, 'stripslashes_from_strings_only');
}

/**
 * Callback function for `stripslashes_deep()` which strips slashes from strings.
 *
 * @since WM-0.4.0
 * @since WP-4.4.0
 *
 * @param  mixed  $value  The array or string to be stripped.
 * @return mixed The stripped value.
 */
function stripslashes_from_strings_only($value)
{
    return is_string($value) ? stripslashes($value) : $value;
}

/**
 * Maps a function to all non-iterable elements of an array or an object.
 *
 * This is similar to `array_walk_recursive()` but acts upon objects too.
 *
 * @since WM-0.4.0
 * @since WP-4.4.0
 *
 * @param  mixed  $value  The array, object, or scalar.
 * @param  callable  $callback  The function to map onto $value.
 * @return mixed The value with the callback applied to all non-arrays and non-objects inside it.
 */
function map_deep($value, $callback)
{
    if (is_array($value)) {
        foreach ($value as $index => $item) {
            $value[$index] = map_deep($item, $callback);
        }
    } elseif (is_object($value)) {
        $object_vars = get_object_vars($value);

        foreach ($object_vars as $property_name => $property_value) {
            $value->$property_name = map_deep($property_value, $callback);
        }
    } else {
        $value = call_user_func($callback, $value);
    }

    return $value;
}

/**
 * Appends a trailing slash.
 *
 * Will remove trailing forward and backslashes if it exists already before adding
 * a trailing forward slash. This prevents double slashing a string or path.
 *
 * The primary use of this is for paths and thus should be used for paths. It is
 * not restricted to paths and offers no specific path support.
 *
 * @since WM-0.4.0
 * @since WP-1.2.0
 *
 * @param  string  $value  Value to which trailing slash will be added.
 * @return string String with trailing slash added.
 */
function trailingslashit($value)
{
    return untrailingslashit($value).'/';
}

/**
 * Removes trailing forward slashes and backslashes if they exist.
 *
 * The primary use of this is for paths and thus should be used for paths. It is
 * not restricted to paths and offers no specific path support.
 *
 * @since WM-0.4.0
 * @since WP-2.2.0
 *
 * @param  string  $value  Value from which trailing slashes will be removed.
 * @return string String without the trailing slashes.
 */
function untrailingslashit($value)
{
    return rtrim($value, '/\\');
}
