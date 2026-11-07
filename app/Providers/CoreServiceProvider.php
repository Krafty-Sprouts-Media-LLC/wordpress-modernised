<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Database\QueryCounter;
use App\Core\Hooks\HookBus;
use App\Core\Hooks\WordPressErrors;
use App\Core\Options\Events\OptionAdded;
use App\Core\Options\Events\OptionDeleted;
use App\Core\Options\Events\OptionUpdated;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use wpdb;

/**
 * Wires the WordPress layer (the doors in wp-api/) into Laravel: the hook bus,
 * WP_DEBUG from the app's debug setting, WordPress's notices into the log, and
 * the global $wpdb on Laravel's own database connection.
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HookBus::class);

        if (! defined('WP_DEBUG')) {
            define('WP_DEBUG', (bool) config('app.debug'));
        }

        if (! defined('WP_DEBUG_DISPLAY')) {
            define('WP_DEBUG_DISPLAY', (bool) config('wordpress.debug_display'));
        }
    }

    public function boot(): void
    {
        // Strings and static callbacks only: WordPress's hook engine ignores an
        // identical callback added twice, so booting the app again (as every
        // test does) never duplicates them.
        add_filter('wp_trigger_error_trigger_error', [WordPressErrors::class, 'shouldTrigger'], 10, 4);
        add_action('wp_trigger_error_always_run', [WordPressErrors::class, 'log'], 10, 3);

        // WordPress's default filters and actions for the areas built (wp-api/default-filters.php).
        wm_add_default_filters();

        $this->bridgeOptionEvents();

        // WordPress's object cache before the database, as wp-settings.php does.
        wp_start_object_cache();

        // A queue worker boots once and runs many jobs: each starts with a fresh
        // memory, as each WordPress request does, so no job sees another's options.
        $this->app['events']->listen(JobProcessing::class, static fn () => wp_cache_flush_runtime());

        $this->bootDatabase();

        QueryCounter::register($this->app['events']);
    }

    /**
     * Announces option changes once: the doors dispatch Laravel events, and
     * these bridges fire WordPress's actions with the same arguments.
     */
    private function bridgeOptionEvents(): void
    {
        $hooks = $this->app->make(HookBus::class);

        $hooks->bridge(OptionAdded::class, 'added_option', fn (OptionAdded $event) => [$event->option, $event->value]);
        $hooks->bridge(OptionUpdated::class, 'updated_option', fn (OptionUpdated $event) => [$event->option, $event->oldValue, $event->value]);
        $hooks->bridge(OptionDeleted::class, 'deleted_option', fn (OptionDeleted $event) => [$event->option]);
    }

    /**
     * Defines WordPress's database settings from Laravel's, and creates the
     * global $wpdb on Laravel's connection, as wp-settings.php does with
     * require_wp_db() and wp_set_wpdb_vars(). Each boot gets a fresh $wpdb, so
     * it always uses this app's connection.
     */
    private function bootDatabase(): void
    {
        $config = DB::connection()->getConfig();

        if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb', 'pgsql'], true)) {
            return;
        }

        foreach ([
            'DB_NAME' => $config['database'],
            'DB_USER' => $config['username'],
            'DB_PASSWORD' => $config['password'],
            'DB_HOST' => $config['host'].':'.$config['port'],
            'DB_CHARSET' => $config['charset'],
            'DB_COLLATE' => '',
        ] as $name => $value) {
            if (! defined($name)) {
                define($name, $value);
            }
        }

        $GLOBALS['table_prefix'] = config('wordpress.table_prefix');
        $GLOBALS['wpdb'] = new wpdb($config['username'], $config['password'], $config['database'], $config['host'].':'.$config['port']);

        wp_set_wpdb_vars();
    }
}
