<?php

declare(strict_types=1);

namespace App\Core\Database;

use Illuminate\Support\Facades\DB;

/**
 * The dialect of Laravel's default connection, for house repositories that
 * write WordPress's tables through the query builder and need the same
 * PostgreSQL shaping $wpdb does (WordPress's column names, empty dates).
 */
final class Dialects
{
    public static function current(): Dialect
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? new PostgresDialect((string) config('wordpress.table_prefix'))
            : new MySqlDialect;
    }
}
