<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Plugins that run their own transactions and savepoints through
 * $wpdb->query() get MySQL's behaviour on every database. Each test steps out
 * of the test's own wrapping transaction and cleans up after itself.
 */
final class WpdbTransactionControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::rollBack(0); // Leave RefreshDatabase's transaction: these tests commit for real.
    }

    protected function tearDown(): void
    {
        global $wpdb;

        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wm_tx_%'");

        parent::tearDown();
    }

    public function test_a_plugins_own_commit_succeeds(): void
    {
        global $wpdb;

        $this->assertNotFalse($wpdb->query('START TRANSACTION'));
        $wpdb->insert($wpdb->options, ['option_name' => 'wm_tx_commit', 'option_value' => '1', 'autoload' => 'off']);
        $this->assertNotFalse($wpdb->query('COMMIT'), $wpdb->last_error);

        $this->assertSame('1', $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wm_tx_commit'"));
    }

    public function test_a_plugins_own_rollback_succeeds(): void
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');
        $wpdb->insert($wpdb->options, ['option_name' => 'wm_tx_rollback', 'option_value' => '1', 'autoload' => 'off']);
        $this->assertNotFalse($wpdb->query('ROLLBACK'), $wpdb->last_error);

        $this->assertNull($wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wm_tx_rollback'"));
    }

    public function test_a_plugins_own_savepoints_work(): void
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');
        $wpdb->insert($wpdb->options, ['option_name' => 'wm_tx_kept', 'option_value' => '1', 'autoload' => 'off']);
        $this->assertNotFalse($wpdb->query('SAVEPOINT plugin_point'), $wpdb->last_error);
        $wpdb->insert($wpdb->options, ['option_name' => 'wm_tx_undone', 'option_value' => '1', 'autoload' => 'off']);
        $this->assertNotFalse($wpdb->query('ROLLBACK TO SAVEPOINT plugin_point'), $wpdb->last_error);
        $wpdb->query('COMMIT');

        $this->assertSame('1', $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wm_tx_kept'"));
        $this->assertNull($wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wm_tx_undone'"));
    }
}
