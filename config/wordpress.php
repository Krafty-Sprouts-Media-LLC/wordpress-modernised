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

];
