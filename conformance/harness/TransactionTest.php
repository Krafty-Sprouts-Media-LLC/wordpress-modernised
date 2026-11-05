<?php

use Illuminate\Support\Facades\DB;

/**
 * Every WordPress test runs inside a database transaction that is rolled back
 * afterwards, as in WordPress's own test suite.
 */
final class TransactionTest extends WP_UnitTestCase
{
    public function test_each_test_runs_in_a_transaction()
    {
        $this->assertTrue(DB::connection()->getPdo()->inTransaction());
    }

    public function test_writes_are_rolled_back_a()
    {
        DB::table(config('wordpress.table_prefix').'options')->insert([
            'option_name' => 'wm_rollback_probe', 'option_value' => '1', 'autoload' => 'off',
        ]);

        $this->assertSame(1, DB::table(config('wordpress.table_prefix').'options')->where('option_name', 'wm_rollback_probe')->count());
    }

    /**
     * @depends test_writes_are_rolled_back_a
     */
    public function test_writes_are_rolled_back_b()
    {
        $this->assertSame(0, DB::table(config('wordpress.table_prefix').'options')->where('option_name', 'wm_rollback_probe')->count());
    }
}
