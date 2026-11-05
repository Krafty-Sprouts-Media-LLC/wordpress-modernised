<?php

/**
 * Settings for the WordPress layer.
 */
return [

    /*
    | The prefix of WordPress's tables, like $table_prefix in wp-config.php.
    | Plugins read it as $wpdb->prefix.
    */
    'table_prefix' => env('WP_TABLE_PREFIX', 'wp_'),

    /*
    | WP_DEBUG_DISPLAY: whether WordPress prints errors (such as database
    | errors) into the page when WP_DEBUG is on. Off by default, unlike
    | WordPress: Laravel shows errors itself, and printed database errors
    | leak query details (docs/divergences.md).
    */
    'debug_display' => (bool) env('WP_DEBUG_DISPLAY', false),

];
