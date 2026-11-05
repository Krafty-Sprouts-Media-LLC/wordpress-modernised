<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Database;

use App\Providers\CoreServiceProvider;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;
use wpdb;

/**
 * $wpdb across the life of a connection: the app starts without touching the
 * database, and $wpdb recovers when Laravel disconnects or the server drops
 * the connection. No RefreshDatabase: these tests drop connections on purpose.
 */
final class WpdbConnectionLifecycleTest extends TestCase
{
    public function test_app_boots_without_a_reachable_database(): void
    {
        $name = DB::getDefaultConnection();
        $port = config("database.connections.{$name}.port");

        config(["database.connections.{$name}.port" => 3399]);
        DB::purge();

        try {
            app()->getProvider(CoreServiceProvider::class)->boot();

            $this->assertInstanceOf(wpdb::class, $GLOBALS['wpdb']);
        } finally {
            config(["database.connections.{$name}.port" => $port]);
            DB::purge();
            app()->getProvider(CoreServiceProvider::class)->boot();
        }
    }

    public function test_recovers_after_laravel_disconnects(): void
    {
        global $wpdb;

        DB::disconnect();

        $this->assertSame('1', $wpdb->get_var('SELECT 1'));
        $this->assertSame(DB::connection()->getPdo(), $wpdb->dbh);
    }

    public function test_recovers_after_the_server_drops_the_connection(): void
    {
        global $wpdb;

        $id = $wpdb->get_var('SELECT CONNECTION_ID()');
        $this->killConnection((int) $id);

        $this->assertSame('1', $wpdb->get_var('SELECT 1'));
        $this->assertNotSame($id, $wpdb->get_var('SELECT CONNECTION_ID()'));
    }

    public function test_two_plugin_connections_to_the_same_database_both_work(): void
    {
        $config = DB::connection()->getConfig();
        $host = $config['host'].':'.$config['port'];

        $first = new wpdb($config['username'], $config['password'], 'wordpress_conformance', $host);
        $second = new wpdb($config['username'], $config['password'], 'wordpress_conformance', $host);

        $this->assertSame('1', $first->get_var('SELECT 1'));
        $this->assertSame('1', $second->get_var('SELECT 1'));
    }

    public function test_select_can_switch_back_to_the_site_database(): void
    {
        global $wpdb;

        $site = DB::getDatabaseName();

        $wpdb->select('wordpress_conformance');
        $wpdb->select($site);

        $this->assertSame($site, DB::scalar('SELECT DATABASE()'));
    }

    /**
     * Kills a connection from a separate one, as a server timeout would.
     */
    private function killConnection(int $id): void
    {
        $config = DB::connection()->getConfig();
        $killer = new PDO(
            "mysql:host={$config['host']};port={$config['port']}",
            $config['username'],
            $config['password'],
        );
        $killer->exec("KILL {$id}");
    }
}
