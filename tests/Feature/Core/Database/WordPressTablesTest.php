<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * WordPress's 12 single-site tables exist exactly as WordPress 7.1.4 defines
 * them, under the configured prefix, and Laravel keeps no users table of its own.
 */
final class WordPressTablesTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'commentmeta', 'comments', 'links', 'options', 'postmeta', 'posts',
        'term_relationships', 'term_taxonomy', 'termmeta', 'terms', 'usermeta', 'users',
    ];

    private const REFERENCE = 'wm_schema_reference';

    public function test_tables_match_wordpress_7_1_4(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->markTestSkipped("Compares against WordPress's MySQL table definitions; PostgreSQL's mapping is checked by WordPressTablesPostgresTest.");
        }

        DB::statement('DROP DATABASE IF EXISTS '.self::REFERENCE);
        DB::statement('CREATE DATABASE '.self::REFERENCE);

        try {
            $sql = preg_replace('/^--.*$/m', '', file_get_contents(base_path('tests/Fixtures/wordpress-7.1.4-schema.sql')));
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                DB::statement(str_replace('CREATE TABLE wp_', 'CREATE TABLE '.self::REFERENCE.'.wp_', $statement));
            }

            $current = DB::getDatabaseName();

            foreach (self::TABLES as $table) {
                $this->assertSame(
                    $this->describe(self::REFERENCE, 'wp_'.$table),
                    $this->describe($current, 'wp_'.$table),
                    "Table wp_{$table} differs from WordPress 7.1.4."
                );
            }
        } finally {
            DB::statement('DROP DATABASE IF EXISTS '.self::REFERENCE);
        }
    }

    public function test_tables_follow_configured_prefix(): void
    {
        config(['wordpress.table_prefix' => 'wmtest_']);
        $migration = require database_path('migrations/0001_01_02_000000_create_wordpress_tables.php');

        try {
            $migration->up();

            $this->assertTrue(Schema::hasTable('wmtest_posts'));
            $this->assertTrue(Schema::hasTable('wp_posts'));
        } finally {
            $migration->down();
        }

        $this->assertFalse(Schema::hasTable('wmtest_posts'));
        $this->assertTrue(Schema::hasTable('wp_posts'));
    }

    public function test_laravel_has_no_users_table_of_its_own(): void
    {
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
    }

    /**
     * A table's columns and indexes as information_schema reports them.
     *
     * @return array{columns: list<array<string, mixed>>, indexes: list<array<string, mixed>>, collation: string|null}
     */
    private function describe(string $schema, string $table): array
    {
        $columns = DB::select(
            'SELECT COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLLATION_NAME
             FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$schema, $table]
        );

        $indexes = DB::select(
            'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART
             FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$schema, $table]
        );

        $collation = DB::scalar(
            'SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$schema, $table]
        );

        return [
            'columns' => array_map(fn ($row) => (array) $row, $columns),
            'indexes' => array_map(fn ($row) => (array) $row, $indexes),
            'collation' => $collation,
        ];
    }
}
