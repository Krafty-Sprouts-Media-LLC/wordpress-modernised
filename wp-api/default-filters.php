<?php

/**
 * WordPress's default filters and actions, for the areas rebuilt so far.
 * Lines join as their areas land; each keeps WordPress's priority and
 * argument count. Callbacks are strings, so registering them again on a
 * second boot (as every test does) never duplicates them.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/default-filters.php.
 *
 * @since WM-0.5.0
 */
function wm_add_default_filters(): void
{
    // Options and transients.
    add_filter('wp_default_autoload_value', 'wp_filter_default_autoload_value_via_option_size', 10, 4);
    add_action('delete_expired_transients', 'delete_expired_transients');

    // Titles and slugs.
    add_filter('sanitize_title', 'sanitize_title_with_dashes', 10, 3);

    // Dates: the site's offset from UTC follows its time zone.
    add_filter('pre_option_gmt_offset', 'wp_timezone_override_offset');
}
