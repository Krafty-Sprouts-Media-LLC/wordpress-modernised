<?php

/**
 * Loads the doors: WordPress's global functions and WP_ classes.
 *
 * Required by Composer (autoload.files) for the app, and by the checker.
 * No Composer autoloading inside wp-api/: every file is listed here, in the
 * order WordPress needs them. Doors may call the house (App\Core\...), never
 * Laravel (Illuminate\...) directly.
 */
if (defined('WM_WP_API_LOADED')) {
    return;
}

define('WM_WP_API_LOADED', true);

require __DIR__.'/version.php';

require __DIR__.'/interim/kses.php';
require __DIR__.'/interim/l10n.php';
require __DIR__.'/interim/multisite.php';

require __DIR__.'/classes/WP_Hook.php';
require __DIR__.'/classes/WP_Filter_Sentinel.php';
require __DIR__.'/classes/WP_Exception.php';
require __DIR__.'/classes/WP_Error.php';
require __DIR__.'/classes/WP_List_Util.php';
require __DIR__.'/classes/wpdb.php';

require __DIR__.'/functions/hooks.php';
require __DIR__.'/functions/errors.php';
require __DIR__.'/functions/return-helpers.php';
require __DIR__.'/functions/list-helpers.php';
require __DIR__.'/functions/environment.php';
require __DIR__.'/functions/values.php';
require __DIR__.'/functions/database.php';
require __DIR__.'/functions/db-delta.php';
require __DIR__.'/functions/schema.php';

require __DIR__.'/interim/die.php';
