<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

/**
 * Bootstrap for the checker: boots the real Laravel app, then loads
 * WordPress 7.1.4's test utilities so its own tests run against our code.
 */
define('CHECKER_STARTED_AT', time());
define('WP_DEBUG', true);
// WordPress's test configuration shows errors (its default); the app defaults this to off (docs/divergences.md).
define('WP_DEBUG_DISPLAY', true);
define('WP_TESTS_CHECKOUT', __DIR__.'/wordpress-develop');
// WordPress's install folder, as code loading wp-admin/includes/upgrade.php expects (stand-in: docs/stand-ins.md).
define('ABSPATH', __DIR__.'/abspath/');

$root = dirname(__DIR__);

if (! is_dir(WP_TESTS_CHECKOUT)) {
    fwrite(STDERR, "WordPress's tests are missing. Run: composer conformance:fetch\n");
    exit(1);
}

// Point Laravel's package discovery at this project's own vendor folder, so it
// never registers dev-only providers that are installed only in the root.
@mkdir(__DIR__.'/cache', 0777, true);

// Rebuild Laravel's package and service caches on every run. Laravel only
// rebuilds them when missing, and nothing here runs package:discover, so a
// provider added or removed in the app would otherwise be missed.
@unlink(__DIR__.'/cache/packages.php');
@unlink(__DIR__.'/cache/services.php');

foreach ([
    'APP_ENV' => 'testing',
    // The checker's own database, migrated fresh on every run, and no writes to the app's log.
    'DB_DATABASE' => 'wordpress_conformance',
    'LOG_CHANNEL' => 'null',
    'COMPOSER_VENDOR_DIR' => __DIR__.'/vendor',
    // Relative to the project root: Laravel does not treat Windows drive paths as absolute here.
    'APP_PACKAGES_CACHE' => 'conformance/cache/packages.php',
    'APP_SERVICES_CACHE' => 'conformance/cache/services.php',
] as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require __DIR__.'/vendor/autoload.php';

// Until wp-api/load.php is in the app's Composer autoload, load it directly.
if (! defined('WM_WP_API_LOADED') && is_file($root.'/wp-api/load.php')) {
    require $root.'/wp-api/load.php';
}

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Laravel's bootstrap installs its own error and exception handlers, which turn
// PHP notices into exceptions and send deprecations to its log. PHPUnit 9 only
// installs its handler when none is set, so give error handling back to PHP and
// PHPUnit, as in WordPress's own test environment.
restore_error_handler();
restore_exception_handler();

// A fresh database for every run: WordPress's tables plus Laravel's own.
if (Artisan::call('migrate:fresh', ['--force' => true]) !== 0) {
    fwrite(STDERR, Artisan::output());
    exit(1);
}

// Stand-ins first: WordPress's utils.php declares a class that extends wpdb.
require __DIR__.'/includes/stand-ins.php';

require WP_TESTS_CHECKOUT.'/tests/phpunit/includes/utils.php';
require WP_TESTS_CHECKOUT.'/tests/phpunit/includes/mock-invokable.php';
require WP_TESTS_CHECKOUT.'/tests/phpunit/includes/phpunit6/compat.php';
require WP_TESTS_CHECKOUT.'/tests/phpunit/includes/exceptions.php';

require __DIR__.'/includes/WP_UnitTestCase.php';
