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

    /*
    |--------------------------------------------------------------------------
    | Object cache store
    |--------------------------------------------------------------------------
    |
    | The Laravel cache store WordPress's object cache (wp_cache_*()) keeps its
    | values in between requests, such as 'redis'. Empty: memory for the
    | request only, as in WordPress. With a store, transients live there too,
    | and WordPress and Laravel share one cache.
    |
    */

    'object_cache' => env('WP_OBJECT_CACHE_STORE'),

    /*
    | Classes the object cache may rebuild from that store, besides plain
    | objects, WordPress's own value classes and Laravel's
    | cache.serializable_classes. A cached value holding any other class stays
    | in memory for the request, so a store someone else can write to can't be
    | used to plant objects that run code when read.
    */

    'object_cache_classes' => [],

    /*
    | Seconds a cached value with no expiry stays in that store (default: a
    | week). Flushing leaves old entries behind under an old version number;
    | this lets them expire even on stores that never evict (database, file).
    */

    'object_cache_max_ttl' => (int) env('WP_OBJECT_CACHE_MAX_TTL', 604800),

];
