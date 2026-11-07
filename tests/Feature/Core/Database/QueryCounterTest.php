<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

/**
 * One query count for the whole connection: queries the house runs through
 * Laravel count in WordPress's get_num_queries() and $wpdb->queries, just as
 * $wpdb's own do.
 */
final class QueryCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_laravel_queries_count_as_wordpress_queries(): void
    {
        global $wpdb;

        $before = get_num_queries();
        DB::table($wpdb->options)->count();

        $this->assertSame($before + 1, get_num_queries());
    }

    public function test_wpdb_queries_count_once(): void
    {
        global $wpdb;

        $before = get_num_queries();
        $wpdb->query('SELECT 1');

        $this->assertSame($before + 1, get_num_queries());
    }

    #[RunInSeparateProcess]
    public function test_saved_queries_include_laravel_queries(): void
    {
        define('SAVEQUERIES', true);
        $this->refreshApplication();

        global $wpdb;
        DB::table($wpdb->options)->where('option_name', 'wm_probe')->count();

        $queries = (array) $wpdb->queries;

        $this->assertStringContainsString('wm_probe', end($queries)[0] ?? '');
    }
}
