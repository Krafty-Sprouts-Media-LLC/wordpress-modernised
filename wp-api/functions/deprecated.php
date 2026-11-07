<?php

/**
 * Deprecated doors that plugins still call. Each raises WordPress's
 * deprecation notice and hands over to its replacement.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/deprecated.php.
 */

/**
 * Adds slashes to a string or recursively adds slashes to strings within an array.
 *
 * This function is just a wrapper for `wp_slash()`. It was originally related to
 * magic quotes functionality which was deprecated in PHP 5.3.0 and removed in PHP 5.4.0.
 *
 * @since WM-0.4.0
 * @since WP-0.71
 * @deprecated WP-7.0.0 Use wp_slash() instead.
 * @see wp_slash()
 *
 * @param  string|array  $gpc  String or array of data to slash.
 * @return string|array Slashed `$gpc`.
 */
function addslashes_gpc($gpc)
{
    _deprecated_function(__FUNCTION__, '7.0.0', 'wp_slash()');

    return wp_slash($gpc);
}
