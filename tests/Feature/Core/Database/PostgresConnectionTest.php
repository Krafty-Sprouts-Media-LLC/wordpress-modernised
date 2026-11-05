<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The app on PostgreSQL 18: Laravel's own tables work there as on MySQL.
 * Runs only with DB_CONNECTION=pgsql.
 */
final class PostgresConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL only (DB_CONNECTION=pgsql).');
        }
    }

    public function test_app_tests_run_on_postgresql_18(): void
    {
        $this->assertGreaterThanOrEqual(180000, (int) DB::scalar('SHOW server_version_num'));
    }

    public function test_laravel_tables_work_on_postgresql(): void
    {
        Cache::store('database')->put('wm_probe', 'cached', 60);
        $this->assertSame('cached', Cache::store('database')->get('wm_probe'));

        DB::table('sessions')->insert(['id' => 'wm-session', 'payload' => 'x', 'last_activity' => time()]);
        $this->assertSame('x', DB::table('sessions')->where('id', 'wm-session')->value('payload'));

        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
        $this->assertSame(1, DB::table('jobs')->count());
    }
}
