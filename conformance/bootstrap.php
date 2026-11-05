<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * Bootstrap for the checker: boots the real Laravel app, then loads
 * WordPress 7.1.4's test utilities so its own tests run against our code.
 */
define('WP_DEBUG', true);
define('WP_TESTS_CHECKOUT', __DIR__.'/wordpress-develop');

$root = dirname(__DIR__);

if (! is_dir(WP_TESTS_CHECKOUT)) {
    fwrite(STDERR, "WordPress's tests are missing. Run: composer conformance:fetch\n");
    exit(1);
}

// Point Laravel's package discovery at this project's own vendor folder, so it
// never registers dev-only providers that are installed only in the root.
@mkdir(__DIR__.'/cache', 0777, true);

foreach ([
    'APP_ENV' => 'testing',
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

// Stand-ins first: WordPress's utils.php declares a class that extends wpdb.
require __DIR__.'/includes/stand-ins.php';

require WP_TESTS_CHECKOUT.'/tests/phpunit/includes/utils.php';
require WP_TESTS_CHECKOUT.'/tests/phpunit/includes/mock-invokable.php';

require __DIR__.'/includes/WP_UnitTestCase.php';
