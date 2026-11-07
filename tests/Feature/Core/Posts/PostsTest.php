<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Posts;

use App\Core\Posts\Events\PostDeleted;
use App\Core\Posts\Events\PostSaved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Posts are stored through Laravel's query builder on every database, keep
 * WordPress's shapes, and announce saving and deleting once to plugins and
 * Laravel alike.
 */
final class PostsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // On a brand-new database the tables appear after the app has started,
        // so register WordPress's default post types here as start-up would.
        create_initial_post_types();
    }

    public function test_posts_and_meta_round_trip_on_this_database(): void
    {
        $id = wp_insert_post(['post_title' => 'Hello World', 'post_status' => 'publish', 'post_content' => "it's"]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
        $this->assertSame($id, get_post($id)->ID);
        $this->assertSame('hello-world', get_post($id)->post_name);
        $this->assertSame("it's", get_post($id)->post_content);

        foreach (["it's", ['a' => [1, 2]], 'a:1:{i:0;s:1:"x";}'] as $value) {
            update_post_meta($id, 'wm_key', $value);
            wp_cache_flush();
            $this->assertSame($value, get_post_meta($id, 'wm_key', true));
        }
    }

    public function test_slugs_stay_unique(): void
    {
        $first = wp_insert_post(['post_title' => 'Same', 'post_status' => 'publish']);
        $second = wp_insert_post(['post_title' => 'Same', 'post_status' => 'publish']);

        $this->assertSame('same', get_post($first)->post_name);
        $this->assertSame('same-2', get_post($second)->post_name);
    }

    public function test_trash_untrash_and_delete(): void
    {
        $id = wp_insert_post(['post_title' => 'Bin me', 'post_status' => 'publish']);

        // As in WordPress, both return the post as it was before the change.
        $this->assertSame('publish', wp_trash_post($id)->post_status);
        $this->assertSame('trash', get_post($id)->post_status);
        wp_untrash_post($id);
        $this->assertSame('draft', get_post($id)->post_status);
        $this->assertInstanceOf(\WP_Post::class, wp_delete_post($id, true));
        $this->assertNull(get_post($id));
    }

    public function test_saving_is_announced_once_to_both_sides(): void
    {
        $laravel = 0;
        $wordpress = 0;
        Event::listen(PostSaved::class, function () use (&$laravel) {
            $laravel++;
        });
        add_action('save_post', function () use (&$wordpress) {
            $wordpress++;
        });

        wp_insert_post(['post_title' => 'x']);

        $this->assertSame([1, 1], [$laravel, $wordpress]);
    }

    public function test_deleting_is_announced_once_to_both_sides(): void
    {
        $id = wp_insert_post(['post_title' => 'x']);
        $laravel = 0;
        $wordpress = 0;
        Event::listen(PostDeleted::class, function () use (&$laravel) {
            $laravel++;
        });
        add_action('deleted_post', function () use (&$wordpress) {
            $wordpress++;
        });

        wp_delete_post($id, true);

        $this->assertSame([1, 1], [$laravel, $wordpress]);
    }
}
