<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WordPress's own table checker, dbDelta(), agrees with our migrations: given
 * WordPress's table definitions, it finds nothing to change.
 */
final class DbDeltaTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_and_wordpress_schema_agree(): void
    {
        $this->assertSame([], dbDelta(wp_get_db_schema('all'), false));
    }

    public function test_plugins_can_create_their_own_table(): void
    {
        global $wpdb;

        $table = $wpdb->prefix.'wm_plugin_items';
        $result = dbDelta("CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL auto_increment,
  name varchar(100) NOT NULL default '',
  PRIMARY KEY  (id)
) {$wpdb->get_charset_collate()};");

        $this->assertSame(["Created table {$table}"], array_values($result));
        $this->assertSame($table, $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)));

        $wpdb->query("DROP TABLE {$table}");
    }
}
