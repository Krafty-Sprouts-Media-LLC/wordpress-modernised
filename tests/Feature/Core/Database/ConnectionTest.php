<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Database;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App tests run against a real MySQL or MariaDB server, never SQLite.
 */
final class ConnectionTest extends TestCase
{
    public function test_app_tests_run_on_mysql_or_mariadb(): void
    {
        $this->assertContains(DB::connection()->getDriverName(), ['mysql', 'mariadb']);
        $this->assertSame(1, (int) DB::scalar('select 1'));
    }
}
