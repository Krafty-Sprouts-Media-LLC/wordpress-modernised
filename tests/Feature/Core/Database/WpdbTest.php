<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use stdClass;
use Tests\TestCase;
use wpdb;

/**
 * $wpdb, the door plugins use for the database, runs on Laravel's one
 * connection and keeps WordPress's shapes.
 */
final class WpdbTest extends TestCase
{
    use RefreshDatabase;

    public function test_wpdb_shares_laravels_transaction(): void
    {
        global $wpdb;

        $this->assertSame(DB::connection()->getPdo(), $wpdb->dbh);

        DB::beginTransaction();
        $wpdb->insert($wpdb->options, ['option_name' => 'wm_probe', 'option_value' => '1', 'autoload' => 'off']);
        DB::rollBack();

        $this->assertSame('0', $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", 'wm_probe')));
    }

    public function test_four_byte_text_round_trips(): void
    {
        global $wpdb;

        $title = 'Café 🎉 日本';
        $wpdb->insert($wpdb->posts, $this->postRow(['post_title' => $title]));

        $this->assertSame($title, DB::table($wpdb->posts)->value('post_title'));
        $this->assertSame($title, $wpdb->get_var("SELECT post_title FROM {$wpdb->posts}"));
    }

    public function test_reports_version_and_capabilities(): void
    {
        global $wpdb;

        $this->assertMatchesRegularExpression('/^\d+\.\d+(\.\d+)?$/', $wpdb->db_version());
        $this->assertTrue((bool) $wpdb->has_cap('utf8mb4'));
        $this->assertTrue((bool) $wpdb->has_cap('utf8mb4_520'));
        $this->assertSame(DB::connection()->getDriverName() === 'mariadb', str_contains($wpdb->db_server_info(), 'MariaDB'));
    }

    public function test_query_results_have_wordpress_shapes(): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->posts, $this->postRow(['post_title' => 'Hello', 'menu_order' => 3]));
        $this->assertGreaterThan(0, $wpdb->insert_id);

        $objects = $wpdb->get_results("SELECT ID, post_title, menu_order FROM {$wpdb->posts}");
        $this->assertSame(1, $wpdb->num_rows);
        $this->assertInstanceOf(stdClass::class, $objects[0]);
        $this->assertSame('3', $objects[0]->menu_order);

        $arrays = $wpdb->get_results("SELECT post_title FROM {$wpdb->posts}", ARRAY_A);
        $this->assertSame([['post_title' => 'Hello']], $arrays);

        $this->assertSame(['Hello'], $wpdb->get_col("SELECT post_title FROM {$wpdb->posts}"));

        $wpdb->get_results("SELECT ID, post_title, menu_order FROM {$wpdb->posts}");
        $this->assertSame('post_title', $wpdb->get_col_info('name', 1));
    }

    public function test_a_second_database_gets_its_own_connection(): void
    {
        $config = DB::connection()->getConfig();
        $other = new wpdb($config['username'], $config['password'], 'wordpress_conformance', $config['host'].':'.$config['port']);

        $this->assertNotSame(DB::connection()->getPdo(), $other->dbh);
        $this->assertSame('1', $other->get_var('SELECT 1'));
    }

    public function test_errors_are_reported_like_wordpress(): void
    {
        global $wpdb;

        $wpdb->suppress_errors(true);
        $this->assertFalse($wpdb->query('SELECT * FROM wm_no_such_table'));
        $this->assertStringContainsString("wm_no_such_table' doesn't exist", $wpdb->last_error);
        $wpdb->suppress_errors(false);
    }

    /**
     * The columns a post row needs (WordPress's NOT NULL text columns have no default).
     *
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
