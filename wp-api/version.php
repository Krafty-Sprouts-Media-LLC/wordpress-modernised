<?php

/**
 * The WordPress API version this project matches, as plugins read it.
 *
 * Plugins check $wp_version (and get_bloginfo( 'version' )) to decide which
 * WordPress functions they can use, so it names the WordPress release whose
 * behaviour the doors match, not this project's own version (WM-x.y.z).
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/version.php.
 */

// Declared global: Composer includes this file inside a function.
global $wp_version, $wp_db_version, $tinymce_version, $required_php_version, $required_php_extensions, $required_mysql_version;

/**
 * The WordPress version string.
 *
 * @since WM-0.2.0
 * @since WP-1.2.0
 *
 * @global string $wp_version
 */
$wp_version = '7.1.4';

// The same, unmodifiable, for wp_get_wp_version(): some plugins change the global.
define('WM_WP_API_VERSION', '7.1.4');

/**
 * Holds the WordPress DB revision, increments when changes are made to the WordPress DB schema.
 *
 * @since WM-0.2.0
 * @since WP-2.1.0
 *
 * @global int $wp_db_version
 */
$wp_db_version = 61833;

/**
 * Holds the TinyMCE version.
 *
 * @since WM-0.2.0
 * @since WP-2.1.0
 *
 * @global string $tinymce_version
 */
$tinymce_version = '49110-20250317';

/**
 * Holds the minimum PHP version required by this project (Laravel 13's minimum).
 *
 * @since WM-0.2.0 Changed: 8.3 (WordPress 7.1.4 requires 7.4). See docs/divergences.md.
 * @since WP-2.5.0
 *
 * @global string $required_php_version
 */
$required_php_version = '8.3';

/**
 * Holds the names of required PHP extensions.
 *
 * @since WM-0.2.0
 * @since WP-6.5.0
 *
 * @global string[] $required_php_extensions
 */
$required_php_extensions = ['json', 'hash'];

/**
 * Holds the minimum required MySQL version.
 *
 * @since WM-0.2.0
 * @since WP-2.5.0
 *
 * @global string $required_mysql_version
 */
$required_mysql_version = '5.5.5';
