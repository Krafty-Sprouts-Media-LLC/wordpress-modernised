<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Hooks\HookBus;
use App\Core\Hooks\WordPressErrors;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
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

        $this->bootDatabase();
    }

    /**
     * Whether this PHP version can use PostgreSQL safely. Before 8.4, PDO's own
     * SQL scanner reads \' inside a string as an escaped quote, so a value ending
     * in a backslash followed by one containing ? or :name is silently corrupted.
     */
    public static function supportsPostgres(int $phpVersionId): bool
    {
        return $phpVersionId >= 80400;
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

        if ($config['driver'] === 'pgsql' && ! self::supportsPostgres(PHP_VERSION_ID)) {
            throw new RuntimeException('PostgreSQL needs PHP 8.4 or newer: PHP 8.3 can silently corrupt some queries (docs/divergences.md).');
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
