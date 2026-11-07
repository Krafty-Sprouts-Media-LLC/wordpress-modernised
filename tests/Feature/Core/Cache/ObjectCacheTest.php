<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Cache;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use WP_Object_Cache;

/**
 * WordPress's object cache runs on Laravel's cache: memory for the request by
 * default, as in WordPress, and any Laravel store when one is configured. A
 * fresh WP_Object_Cache stands in for the next request; Laravel's array store
 * lives for the whole process, so it stands in for Redis.
 */
final class ObjectCacheTest extends TestCase
{
    private function usePersistentStore(): void
    {
        config(['wordpress.object_cache' => 'array']);
    }

    public function test_memory_only_by_default(): void
    {
        (new WP_Object_Cache)->set('k', 'v', 'g');

        $this->assertFalse((new WP_Object_Cache)->get('k', 'g'));
        $this->assertFalse(wp_using_ext_object_cache());
    }

    public function test_a_persistent_store_is_shared_between_requests(): void
    {
        $this->usePersistentStore();

        (new WP_Object_Cache)->set('k', ['v'], 'g');

        $found = null;
        $this->assertSame(['v'], (new WP_Object_Cache)->get('k', 'g', false, $found));
        $this->assertTrue($found);
    }

    public function test_non_persistent_groups_never_reach_the_store(): void
    {
        $this->usePersistentStore();

        $first = new WP_Object_Cache;
        $first->add_non_persistent_groups(['counts']);
        $first->set('k', 'v', 'counts');

        $this->assertSame('v', $first->get('k', 'counts'));
        $this->assertFalse((new WP_Object_Cache)->get('k', 'counts'));
    }

    public function test_flushing_keeps_laravels_own_entries(): void
    {
        $this->usePersistentStore();
        Cache::store('array')->forever('laravel-key', 'kept');

        $cache = new WP_Object_Cache;
        $cache->set('a', 1, 'posts');
        $cache->set('b', 2, 'terms');

        $cache->flush_group('posts');
        $this->assertFalse((new WP_Object_Cache)->get('a', 'posts'));
        $this->assertSame(2, (new WP_Object_Cache)->get('b', 'terms'));

        $cache->flush();
        $this->assertFalse((new WP_Object_Cache)->get('b', 'terms'));
        $this->assertSame('kept', Cache::store('array')->get('laravel-key'));
    }

    public function test_unserializable_values_stay_in_memory(): void
    {
        $this->usePersistentStore();
        $cache = new WP_Object_Cache;
        $closure = fn () => 1;

        $this->assertTrue($cache->set('k', $closure, 'g'));
        // A copy, as WordPress clones every object it caches.
        $this->assertSame(1, ($cache->get('k', 'g'))());
        $this->assertFalse((new WP_Object_Cache)->get('k', 'g'));
    }

    public function test_odd_keys_work_with_a_persistent_store(): void
    {
        $this->usePersistentStore();

        foreach (['with spaces', 'a/b\\c:d', str_repeat('x', 300)] as $key) {
            (new WP_Object_Cache)->set($key, $key, 'g');
            $this->assertSame($key, (new WP_Object_Cache)->get($key, 'g'), $key);
        }
    }

    public function test_expiry_applies_in_a_persistent_store(): void
    {
        $this->usePersistentStore();

        (new WP_Object_Cache)->set('k', 'v', 'g', 1);
        $this->travel(2)->seconds();

        $this->assertFalse((new WP_Object_Cache)->get('k', 'g'));
    }

    public function test_add_and_replace_see_the_persistent_store(): void
    {
        $this->usePersistentStore();
        (new WP_Object_Cache)->set('k', 'first', 'g');

        $next = new WP_Object_Cache;
        $this->assertFalse($next->add('k', 'second', 'g'));
        $this->assertTrue($next->replace('k', 'third', 'g'));
        $this->assertSame('third', (new WP_Object_Cache)->get('k', 'g'));
    }

    public function test_a_persistent_store_is_reported_to_wordpress(): void
    {
        $this->usePersistentStore();
        wp_cache_init();

        $this->assertTrue(wp_using_ext_object_cache());
    }
}
