<?php

/**
 * WordPress's unit constants, as wp_initial_constants() defines them: sizes in
 * bytes and spans of time in seconds. Plugins use them constantly.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/default-constants.php.
 *
 * @since WM-0.5.0
 * @since WP-4.4.0
 * @since WP-6.0.0 `PB_IN_BYTES`, `EB_IN_BYTES`, `ZB_IN_BYTES`, and `YB_IN_BYTES` were added.
 */
foreach ([
    'KB_IN_BYTES' => 1024,
    'MB_IN_BYTES' => 1024 ** 2,
    'GB_IN_BYTES' => 1024 ** 3,
    'TB_IN_BYTES' => 1024 ** 4,
    'PB_IN_BYTES' => 1024 ** 5,
    'EB_IN_BYTES' => 1024 ** 6,
    'ZB_IN_BYTES' => 1024 ** 7,
    'YB_IN_BYTES' => 1024 ** 8,
    // Spans of time. MONTH_IN_SECONDS assumes 30 days and YEAR_IN_SECONDS
    // ignores leap years, as in WordPress.
    'MINUTE_IN_SECONDS' => 60,
    'HOUR_IN_SECONDS' => 60 * 60,
    'DAY_IN_SECONDS' => 24 * 60 * 60,
    'WEEK_IN_SECONDS' => 7 * 24 * 60 * 60,
    'MONTH_IN_SECONDS' => 30 * 24 * 60 * 60,
    'YEAR_IN_SECONDS' => 365 * 24 * 60 * 60,
] as $name => $value) {
    if (! defined($name)) {
        define($name, $value);
    }
}
