<?php

/**
 * Loads the doors: WordPress's global functions and WP_ classes.
 *
 * Required by Composer (autoload.files) for the app, and by the checker.
 * No Composer autoloading or Laravel inside wp-api/: every file is listed
 * here, in the order WordPress needs them.
 */
if (defined('WM_WP_API_LOADED')) {
    return;
}

define('WM_WP_API_LOADED', true);

require __DIR__.'/interim/kses.php';

require __DIR__.'/classes/WP_Hook.php';
require __DIR__.'/classes/WP_Filter_Sentinel.php';
require __DIR__.'/classes/WP_Exception.php';

require __DIR__.'/functions/hooks.php';
require __DIR__.'/functions/errors.php';
require __DIR__.'/functions/return-helpers.php';
