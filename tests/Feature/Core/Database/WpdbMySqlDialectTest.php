<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * On MySQL and MariaDB, $wpdb builds exactly the SQL WordPress builds: the
 * PostgreSQL support must not change a byte of it.
 */
final class WpdbMySqlDialectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->markTestSkipped('MySQL and MariaDB only.');
        }
    }

    public function test_mysql_dialect_is_unchanged(): void
    {
        global $wpdb;

        $this->assertSame('`wp_posts`', $wpdb->quote_identifier('wp_posts'));
        $this->assertSame('`a``b`', $wpdb->quote_identifier('a`b'));
        $this->assertSame("O\\'Reilly", $wpdb->_real_escape("O'Reilly"));

        $wpdb->replace($wpdb->options, ['option_name' => 'wm_m', 'option_value' => 'v', 'autoload' => 'off']);
        $this->assertSame("REPLACE INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES ('wm_m', 'v', 'off')", $wpdb->last_query);

        $wpdb->update($wpdb->options, ['option_value' => 'w'], ['option_name' => 'wm_m']);
        $this->assertSame("UPDATE `{$wpdb->options}` SET `option_value` = 'w' WHERE `option_name` = 'wm_m'", $wpdb->last_query);

        $wpdb->delete($wpdb->options, ['option_name' => 'wm_m']);
        $this->assertSame("DELETE FROM `{$wpdb->options}` WHERE `option_name` = 'wm_m'", $wpdb->last_query);
    }
}
