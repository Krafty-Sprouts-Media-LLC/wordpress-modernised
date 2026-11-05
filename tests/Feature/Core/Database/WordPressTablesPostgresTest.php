<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Database;

use App\Core\Database\WordPressSchema;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * WordPress's 12 tables on PostgreSQL: the same columns as WordPress 7.1.4,
 * PostgreSQL types that behave the way the doors expect, text compared
 * without case, and empty dates stored as NULL. Runs only with DB_CONNECTION=pgsql.
 */
final class WordPressTablesPostgresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL only (DB_CONNECTION=pgsql).');
        }
    }

    public function test_tables_have_wordpress_columns_on_postgresql(): void
    {
        $fixture = file_get_contents(base_path('tests/Fixtures/wordpress-7.1.4-schema.sql'));

        foreach (WordPressSchema::TABLES as $table) {
            preg_match('/CREATE TABLE wp_'.$table.' \((.*?)\n\) DEFAULT/s', $fixture, $m);
            preg_match_all('/^\s+([A-Za-z_]+) (bigint|int|tinyint|varchar|tinytext|text|mediumtext|longtext|datetime)/m', $m[1], $columns);

            $expected = array_map('strtolower', $columns[1]);
            $actual = DB::table('information_schema.columns')
                ->where('table_schema', 'public')
                ->where('table_name', 'wp_'.$table)
                ->orderBy('ordinal_position')
                ->pluck('column_name')
                ->all();

            $this->assertSame($expected, $actual, "Columns of wp_{$table} differ from WordPress 7.1.4.");
        }

        $this->assertSame('timestamp without time zone', $this->typeOf('wp_posts', 'post_date_gmt'));
        $this->assertSame('YES', $this->nullable('wp_posts', 'post_date_gmt'));
        $this->assertSame('wp_ci', $this->collationOf('wp_posts', 'post_name'));
        $this->assertSame('wp_ci', $this->collationOf('wp_options', 'option_value'));
    }

    public function test_text_compares_without_case_like_mysql(): void
    {
        DB::table('wp_posts')->insert($this->postRow(['post_name' => 'hello', 'post_title' => 'Hello world']));

        $this->assertSame(1, DB::table('wp_posts')->where('post_name', 'Hello')->count());
        $this->assertSame(1, DB::table('wp_posts')->where('post_title', 'like', 'hel%')->count());

        // A second option differing only in case is refused, as in MySQL.
        DB::table('wp_options')->insert(['option_name' => 'Foo', 'option_value' => '1', 'autoload' => 'off']);
        $this->assertThrows(
            fn () => DB::transaction(fn () => DB::table('wp_options')->insert(['option_name' => 'foo', 'option_value' => '2', 'autoload' => 'off'])),
            QueryException::class,
        );
    }

    public function test_empty_dates_are_null(): void
    {
        DB::table('wp_posts')->insert($this->postRow([]));

        $this->assertNull(DB::table('wp_posts')->value('post_date_gmt'));
    }

    private function typeOf(string $table, string $column): ?string
    {
        return DB::table('information_schema.columns')->where('table_name', $table)->where('column_name', $column)->value('data_type');
    }

    private function nullable(string $table, string $column): ?string
    {
        return DB::table('information_schema.columns')->where('table_name', $table)->where('column_name', $column)->value('is_nullable');
    }

    private function collationOf(string $table, string $column): ?string
    {
        return DB::table('information_schema.columns')->where('table_name', $table)->where('column_name', $column)->value('collation_name');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function postRow(array $overrides): array
    {
        return array_merge([
            'post_content' => '', 'post_title' => '', 'post_excerpt' => '', 'to_ping' => '',
            'pinged' => '', 'post_content_filtered' => '',
        ], $overrides);
    }
}
