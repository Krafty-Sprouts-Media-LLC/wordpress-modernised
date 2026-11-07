<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * $wpdb's methods on PostgreSQL: safe escaping, WordPress's empty dates and
 * column casing, IDs and counts like MySQL, and a clear note when a plugin's
 * own SQL is MySQL-only. Runs only with DB_CONNECTION=pgsql.
 */
final class WpdbPostgresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL only (DB_CONNECTION=pgsql).');
        }
    }

    public function test_escaping_is_postgresql_safe(): void
    {
        global $wpdb;

        // The escaping relies on standard strings (backslash is ordinary text).
        $this->assertSame('on', $wpdb->get_var('SHOW standard_conforming_strings'));

        $values = ["O'Reilly", 'a\\b', "\\'", "'; DROP TABLE wp_options; --", 'tab\there', 'Café 🎉'];

        foreach ($values as $i => $value) {
            $wpdb->insert($wpdb->options, ['option_name' => "wm_esc_{$i}", 'option_value' => $value, 'autoload' => 'off']);
            $this->assertSame($value, $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", "wm_esc_{$i}")));

            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", $value.'!', "wm_esc_{$i}"));
            $this->assertSame($value.'!', $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", "wm_esc_{$i}")));
        }

        $this->assertNotNull($wpdb->get_var("SELECT to_regclass('{$wpdb->options}')"));
    }

    public function test_the_deprecated_escape_door_is_postgresql_safe(): void
    {
        global $wpdb;

        // Old plugins still pass request data through $wpdb->escape() (addslashes in WordPress).
        $payload = "' UNION SELECT current_user::text COLLATE wp_ci --";
        $wpdb->insert($wpdb->options, ['option_name' => 'wm_old', 'option_value' => 'kept', 'autoload' => 'off']);

        $found = @$wpdb->get_col("SELECT option_value FROM {$wpdb->options} WHERE option_name = '".$wpdb->escape($payload)."'");
        $this->assertSame([], $found);

        $this->assertSame([], @$wpdb->get_col("SELECT option_value FROM {$wpdb->options} WHERE option_name = '".$wpdb->_weak_escape("x' OR option_name = option_name --")."'"));
    }

    public function test_escaping_holds_even_if_standard_strings_are_switched_off(): void
    {
        global $wpdb;

        $wpdb->query('SET standard_conforming_strings = off');

        try {
            $value = "\\'; DROP TABLE wp_options; --";
            $wpdb->insert($wpdb->options, ['option_name' => 'wm_scs', 'option_value' => $value, 'autoload' => 'off']);
            $this->assertSame($value, $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wm_scs'"));
        } finally {
            $wpdb->query('SET standard_conforming_strings = on');
        }
    }

    public function test_backslash_then_placeholder_characters_are_not_corrupted(): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->options, ['option_name' => 'wm_a', 'option_value' => 'ends\\', 'autoload' => 'off']);
        $wpdb->insert($wpdb->options, ['option_name' => 'wm_b', 'option_value' => 'has? :name', 'autoload' => 'off']);

        $row = $wpdb->get_row("SELECT 'x\\' AS a, 'y? :name' AS b");
        $this->assertSame('x\\', $row->a);
        $this->assertSame('y? :name', $row->b);
    }

    public function test_identifier_placeholders_work(): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->options, ['option_name' => 'wm_i', 'option_value' => 'v', 'autoload' => 'off']);

        $this->assertSame('v', $wpdb->get_var($wpdb->prepare('SELECT %i FROM %i WHERE %i = %s', 'option_value', $wpdb->options, 'option_name', 'wm_i')));
    }

    public function test_explicit_ids_set_insert_id_and_move_the_counter(): void
    {
        global $wpdb;

        // The first insert into wp_posts in this session, inside the test's transaction.
        $this->assertSame(1, $wpdb->insert($wpdb->posts, $this->postRow(['ID' => 500, 'post_title' => 'Imported'])));
        $this->assertSame(500, $wpdb->insert_id);

        $wpdb->insert($wpdb->posts, $this->postRow(['post_title' => 'Next']));
        $this->assertGreaterThan(500, $wpdb->insert_id);

        // The surrounding transaction is still usable.
        $this->assertSame(2, (int) DB::table($wpdb->posts)->count());
    }

    public function test_plugin_tables_are_not_reshaped(): void
    {
        global $wpdb;

        DB::statement("CREATE TABLE {$wpdb->prefix}myplugin (id bigserial PRIMARY KEY, post_date timestamp NULL, label text)");

        $wpdb->insert($wpdb->prefix.'myplugin', ['label' => 'x']);
        $this->assertGreaterThan(0, $wpdb->insert_id);

        $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}myplugin");
        $this->assertObjectHasProperty('id', $row);
        $this->assertNull($row->post_date);
    }

    public function test_empty_date_text_in_other_columns_is_kept(): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->options, ['option_name' => 'wm_zero', 'option_value' => '0000-00-00 00:00:00', 'autoload' => 'off']);

        $this->assertSame('0000-00-00 00:00:00', $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wm_zero'"));
    }

    public function test_replace_only_removes_rows_that_really_clash(): void
    {
        global $wpdb;

        DB::statement("CREATE TABLE {$wpdb->prefix}wm_names (site int NOT NULL, name text NOT NULL, live boolean NOT NULL DEFAULT true)");
        DB::statement("CREATE UNIQUE INDEX wm_names_site_lower ON {$wpdb->prefix}wm_names (site, lower(name))");
        DB::statement("CREATE UNIQUE INDEX wm_names_live ON {$wpdb->prefix}wm_names (name) WHERE live");
        DB::table($wpdb->prefix.'wm_names')->insert([['site' => 1, 'name' => 'a'], ['site' => 1, 'name' => 'b']]);

        $wpdb->suppress_errors(true);
        $wpdb->replace($wpdb->prefix.'wm_names', ['site' => 1, 'name' => 'c']);
        $wpdb->suppress_errors(false);

        $this->assertSame(['a', 'b', 'c'], DB::table($wpdb->prefix.'wm_names')->orderBy('name')->pluck('name')->all());
    }

    public function test_accents_compare_like_mysql(): void
    {
        global $wpdb;

        // MySQL's utf8mb4_unicode_520_ci ignores accents as well as case.
        $wpdb->insert($wpdb->posts, $this->postRow(['post_name' => 'café']));

        $this->assertSame('1', $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name = 'cafe'"));
    }

    public function test_nul_bytes_are_removed_on_postgresql(): void
    {
        global $wpdb;

        // PostgreSQL text cannot hold NUL bytes; they are removed (docs/divergences.md).
        $wpdb->insert($wpdb->options, ['option_name' => 'wm_nul', 'option_value' => "x\0y", 'autoload' => 'off']);

        $this->assertSame('xy', $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wm_nul'"));
    }

    public function test_empty_dates_read_back_as_wordpress_expects(): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->posts, $this->postRow(['post_title' => 'Draft', 'post_date_gmt' => '0000-00-00 00:00:00']));

        $this->assertNull(DB::table($wpdb->posts)->value('post_date_gmt'));
        $this->assertSame('0000-00-00 00:00:00', $wpdb->get_var("SELECT post_date_gmt FROM {$wpdb->posts}"));
        $this->assertSame('Draft', $wpdb->get_var($wpdb->prepare("SELECT post_title FROM {$wpdb->posts} WHERE post_date_gmt IS NULL OR post_date_gmt = %s", '0001-01-01 00:00:00')));

        $row = $wpdb->get_row("SELECT * FROM {$wpdb->posts}");
        $this->assertObjectHasProperty('ID', $row);
        $this->assertSame('0000-00-00 00:00:00', $row->post_modified);
    }

    public function test_ids_and_counts_match_mysql(): void
    {
        global $wpdb;

        $this->assertSame(1, $wpdb->insert($wpdb->options, ['option_name' => 'wm_a', 'option_value' => '1', 'autoload' => 'off']));
        $first = $wpdb->insert_id;
        $this->assertGreaterThan(0, $first);

        $wpdb->insert($wpdb->options, ['option_name' => 'wm_b', 'option_value' => '2', 'autoload' => 'off']);
        $this->assertSame($first + 1, $wpdb->insert_id);

        $this->assertSame(1, $wpdb->update($wpdb->options, ['option_value' => '3'], ['option_name' => 'wm_a']));
        $this->assertSame(0, $wpdb->update($wpdb->options, ['option_value' => '3'], ['option_name' => 'wm_missing']));

        // REPLACE on an existing unique key: MySQL deletes and inserts, so 2 rows are affected.
        $this->assertSame(2, $wpdb->replace($wpdb->options, ['option_name' => 'wm_a', 'option_value' => '4', 'autoload' => 'off']));
        $this->assertSame('4', $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wm_a'"));
        $this->assertSame(1, $wpdb->replace($wpdb->options, ['option_name' => 'wm_c', 'option_value' => '5', 'autoload' => 'off']));

        $this->assertSame(1, $wpdb->delete($wpdb->options, ['option_name' => 'wm_b']));
    }

    public function test_a_failed_replace_keeps_the_original_row(): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->options, ['option_name' => 'wm_keep', 'option_value' => 'original', 'autoload' => 'off']);

        // A column that doesn't exist makes the insert half fail after the delete half.
        $wpdb->suppress_errors(true);
        $this->assertFalse($wpdb->replace($wpdb->options, ['option_name' => 'wm_keep', 'no_such_column' => 'x']));
        $wpdb->suppress_errors(false);

        // MySQL's REPLACE is all-or-nothing: the old row must survive.
        $this->assertSame('original', $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wm_keep'"));
    }

    public function test_mysql_only_raw_sql_fails_with_a_clear_note(): void
    {
        global $wpdb;

        $wpdb->suppress_errors(true);
        $this->assertFalse($wpdb->query("SELECT `ID` FROM {$wpdb->posts} LIMIT 0, 1"));
        $wpdb->suppress_errors(false);

        $this->assertStringContainsString('MySQL-only syntax', $wpdb->last_error);
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
