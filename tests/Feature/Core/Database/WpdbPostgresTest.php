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
