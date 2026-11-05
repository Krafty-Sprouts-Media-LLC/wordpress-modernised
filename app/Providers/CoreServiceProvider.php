<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Hooks\HookBus;
use App\Core\Hooks\WordPressErrors;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the WordPress layer (the doors in wp-api/) into Laravel: the hook bus,
 * WP_DEBUG from the app's debug setting, and WordPress's notices into the log.
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HookBus::class);

        if (! defined('WP_DEBUG')) {
            define('WP_DEBUG', (bool) config('app.debug'));
        }
    }

    public function boot(): void
    {
        // Strings and static callbacks only: WordPress's hook engine ignores an
        // identical callback added twice, so booting the app again (as every
        // test does) never duplicates them.
        add_filter('wp_trigger_error_trigger_error', [WordPressErrors::class, 'shouldTrigger'], 10, 4);
        add_action('wp_trigger_error_always_run', [WordPressErrors::class, 'log'], 10, 3);
    }
}
