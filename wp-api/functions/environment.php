<?php

/**
 * Facts about this install: whether it is multisite, its WordPress API version,
 * and whether it is being installed.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/load.php and functions.php.
 */

/**
 * Determines whether Multisite is enabled.
 *
 * @since WM-0.2.0
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

/**
 * Checks or sets whether WordPress is in "installation" mode.
 *
 * If the `WP_INSTALLING` constant is defined during the bootstrap, `wp_installing()` will default to `true`.
 *
 * @since WM-0.4.0
 * @since WP-4.4.0
 *
 * @param  bool  $is_installing  Optional. True to set WP into Installing mode, false to turn Installing mode off.
 *                               Omit this parameter if you only want to fetch the current status.
 * @return bool True if WP is installing, otherwise false. When a `$is_installing` is passed, the function will
 *              report whether WP was in installing mode prior to the change to `$is_installing`.
 */
function wp_installing($is_installing = null)
{
    static $installing = null;

    // Support for the `WP_INSTALLING` constant, defined before WP is loaded.
    if (is_null($installing)) {
        $installing = defined('WP_INSTALLING') && WP_INSTALLING;
    }

    if (! is_null($is_installing)) {
        $old_installing = $installing;
        $installing = $is_installing;

        return (bool) $old_installing;
    }

    return (bool) $installing;
}

/**
 * Determines whether the current request is for an administrative interface page.
 *
 * Does not check if the user is an administrator; use current_user_can()
 * for checking roles and capabilities.
 *
 * For more information on this and similar theme functions, check out
 * the {@link https://developer.wordpress.org/themes/basics/conditional-tags/
 * Conditional Tags} article in the Theme Developer Handbook.
 *
 * @since WM-0.5.0
 * @since WP-1.5.1
 *
 * @global WP_Screen $current_screen WordPress current screen object.
 *
 * @return bool True if inside WordPress administration interface, false otherwise.
 */
function is_admin()
{
    if (isset($GLOBALS['current_screen'])) {
        return $GLOBALS['current_screen']->in_admin();
    } elseif (defined('WP_ADMIN')) {
        return WP_ADMIN;
    }

    return false;
}
