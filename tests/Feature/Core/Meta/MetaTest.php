<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Meta;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The metadata API stores rows through Laravel's query builder, so it behaves
 * the same on every database. Meta doesn't need its object to exist, so these
 * tests attach it to a bare post ID.
 */
final class MetaTest extends TestCase
{
    use RefreshDatabase;

    private const POST_ID = 4242;

    public function test_meta_round_trips_on_this_database(): void
    {
        foreach (["it's", ['a' => [1, 2]], 'a:1:{i:0;s:1:"x";}', '0', ''] as $value) {
            delete_metadata('post', self::POST_ID, 'wm_key');
            $this->assertIsInt(add_metadata('post', self::POST_ID, 'wm_key', $value));
            wp_cache_flush();
            $this->assertSame($value, get_metadata('post', self::POST_ID, 'wm_key', true));
        }
    }

    public function test_unique_keys_multiple_values_and_deletion(): void
    {
        add_metadata('post', self::POST_ID, 'wm_list', 'one');
        add_metadata('post', self::POST_ID, 'wm_list', 'two');
        $this->assertFalse(add_metadata('post', self::POST_ID, 'wm_list', 'three', true));
        $this->assertSame(['one', 'two'], get_metadata('post', self::POST_ID, 'wm_list'));

        $this->assertTrue(update_metadata('post', self::POST_ID, 'wm_list', 'TWO', 'two'));
        $this->assertSame(['one', 'TWO'], get_metadata('post', self::POST_ID, 'wm_list'));

        $this->assertTrue(delete_metadata('post', self::POST_ID, 'wm_list', 'one'));
        $this->assertSame(['TWO'], get_metadata('post', self::POST_ID, 'wm_list'));
    }

    public function test_meta_by_id_has_wordpress_shapes(): void
    {
        $id = add_metadata('post', self::POST_ID, 'wm_mid', ['x' => 1]);

        $row = get_metadata_by_mid('post', $id);
        $this->assertSame((string) $id, $row->meta_id);
        $this->assertSame((string) self::POST_ID, $row->post_id);
        $this->assertSame(['x' => 1], $row->meta_value);

        $this->assertTrue(update_metadata_by_mid('post', $id, 'changed'));
        $this->assertTrue(delete_metadata_by_mid('post', $id));
        $this->assertFalse(get_metadata_by_mid('post', $id));
    }
}
