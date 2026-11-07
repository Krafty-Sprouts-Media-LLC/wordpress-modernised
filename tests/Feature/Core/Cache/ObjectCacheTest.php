<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Cache;

use App\Core\Cache\ObjectCache;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
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

    public function test_values_with_unlisted_classes_stay_in_memory(): void
    {
        $this->usePersistentStore();
        $cache = new WP_Object_Cache;

        $this->assertTrue($cache->set('k', ['x' => new ObjectCacheTestValue], 'g'));
        $this->assertInstanceOf(ObjectCacheTestValue::class, $cache->get('k', 'g')['x']);
        $this->assertFalse((new WP_Object_Cache)->get('k', 'g'));
    }

    public function test_listed_classes_and_plain_objects_are_stored(): void
    {
        $this->usePersistentStore();
        config(['wordpress.object_cache_classes' => [ObjectCacheTestValue::class]]);

        (new WP_Object_Cache)->set('k', ['a' => new ObjectCacheTestValue, 'b' => (object) ['id' => 1]], 'g');
        $value = (new WP_Object_Cache)->get('k', 'g');

        $this->assertInstanceOf(ObjectCacheTestValue::class, $value['a']);
        $this->assertSame(1, $value['b']->id);
    }

    public function test_a_tampered_store_cannot_build_unlisted_objects(): void
    {
        $this->usePersistentStore();
        $cache = new ObjectCache(Cache::store('array'));
        $storeKey = (new ReflectionMethod($cache, 'storeKey'))->invoke($cache, 'k', 'g');
        Cache::store('array')->forever($storeKey, serialize([new ObjectCacheTestValue]));

        $value = (new WP_Object_Cache)->get('k', 'g');

        $this->assertNotInstanceOf(ObjectCacheTestValue::class, $value);
        $this->assertFalse($value);
    }

    public function test_entries_without_expiry_still_leave_the_store_eventually(): void
    {
        $this->usePersistentStore();

        (new WP_Object_Cache)->set('k', 'v', 'g');
        $this->travel(config('wordpress.object_cache_max_ttl') + 1)->seconds();

        $this->assertFalse((new WP_Object_Cache)->get('k', 'g'));
    }

    public function test_a_write_after_another_requests_flush_is_seen(): void
    {
        $this->usePersistentStore();
        $first = new WP_Object_Cache;
        $first->get('anything', 'g');

        (new WP_Object_Cache)->flush();
        $first->set('k', 'fresh', 'g');

        $this->assertSame('fresh', (new WP_Object_Cache)->get('k', 'g'));
    }

    public function test_each_queued_job_starts_with_a_fresh_memory(): void
    {
        wp_cache_set('k', 'from an earlier job', 'g');

        event(new JobProcessing('sync', new SyncJob(app(), '{}', 'sync', 'default')));

        $this->assertFalse(wp_cache_get('k', 'g'));
    }

    public function test_wordpress_default_groups_stay_in_memory(): void
    {
        $this->usePersistentStore();
        wp_start_object_cache();
        wp_cache_set('k', 3, 'counts');

        wp_start_object_cache();

        $this->assertFalse(wp_cache_get('k', 'counts'));
    }

    public function test_a_persistent_store_is_reported_to_wordpress(): void
    {
        $this->usePersistentStore();
        wp_cache_init();

        $this->assertTrue(wp_using_ext_object_cache());
    }
}

/** A class no allow-list names unless a test adds it. */
final class ObjectCacheTestValue
{
    public string $name = 'value';
}
