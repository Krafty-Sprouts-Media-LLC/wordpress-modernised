<?php

/**
 * Facts about this install: whether it is multisite, and its WordPress API version.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/load.php and functions.php.
 */

/**
 * Determines whether Multisite is enabled.
 *
 * @since WM-0.1.0
 * @since WP-3.0.0
 *
 * @return bool True if Multisite is enabled, false otherwise.
 */
function is_multisite()
{
    if (defined('MULTISITE')) {
        return MULTISITE;
    }

    if (defined('SUBDOMAIN_INSTALL') || defined('VHOST') || defined('SUNRISE')) {
        return true;
    }

    return false;
}

/**
 * Returns the current WordPress version.
 *
 * Returns an unmodified value of `$wp_version`. Some plugins modify the global
 * in an attempt to improve security through obscurity. This practice can cause
 * errors in WordPress, so the ability to get an unmodified version is needed.
 *
 * @since WM-0.2.0 Changed: returns the WordPress API version this project matches (wp-api/version.php) instead of reading version.php from disk.
 * @since WP-6.7.0
 *
 * @return string The current WordPress version.
 */
function wp_get_wp_version()
{
    return WM_WP_API_VERSION;
}
