<?php

declare(strict_types=1);

namespace App\Core\Database;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Whether WordPress's tables can be used yet. Start-up work that reads them
 * (default post types, plugins) is skipped before `migrate` has created them,
 * and when the database can't be reached, so the app still boots and
 * `artisan` still runs.
 */
final class WordPressTables
{
    public static function ready(): bool
    {
        try {
            return Schema::hasTable(config('wordpress.table_prefix').'options');
        } catch (Throwable) {
            return false;
        }
    }
}
