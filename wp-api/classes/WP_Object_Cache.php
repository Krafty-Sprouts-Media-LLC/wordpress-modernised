<?php

/**
 * Object Cache API: WP_Object_Cache class.
 *
 * WordPress's object cache, with WordPress's methods, key checks and notices.
 * The work is done by App\Core\Cache\ObjectCache: memory for the request, plus
 * a Laravel cache store when one is configured (config wordpress.object_cache).
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/class-wp-object-cache.php.
 */

use App\Core\Cache\ObjectCache;

/**
 * Core class that implements an object cache.
 *
 * The Object Cache stores cached data in memory for the request and, when a
 * persistent store is configured, in that store for later requests. Data is
 * found by a key and a group.
 *
 * `cache`, `cache_hits`, `cache_misses` and `global_groups` are read live from
 * the engine, so they always match it.
 *
 * @since WM-0.4.0
 * @since WP-2.0.0
 *
 * @property-read array $cache Holds the cached objects for this request.
 * @property-read int $cache_hits The amount of times the cache data was already stored in the cache.
 * @property-read int $cache_misses Amount of times the cache did not have the request in cache.
 * @property-read string[] $global_groups List of global cache groups.
 */
#[AllowDynamicProperties]
class WP_Object_Cache
{
    /**
     * The engine doing the work.
     */
    private ObjectCache $wm_engine;

    /**
     * The blog prefix to prepend to keys in non-global groups.
     *
     * Always empty: there is no multisite (owner's decision B, step 3).
     *
     * @since WP-3.5.0
     *
     * @var string
     */
    private $blog_prefix = '';

    /**
     * Holds the value of is_multisite().
     *
     * @since WP-3.5.0
     *
     * @var bool
     */
    private $multisite;

    /**
     * Sets up object properties.
     *
     * @since WM-0.4.0
     * @since WP-2.0.8
     */
    public function __construct()
    {
        $this->wm_engine = ObjectCache::fromConfig();
        $this->multisite = is_multisite();
    }

    /**
     * Makes private properties readable for backward compatibility.
     *
     * @since WM-0.4.0
     * @since WP-4.0.0
     *
     * @param  string  $name  Property to get.
     * @return mixed Property.
     */
    public function __get($name)
    {
        return match ($name) {
            'cache' => $this->wm_engine->runtime(),
            'cache_hits' => $this->wm_engine->hits(),
            'cache_misses' => $this->wm_engine->misses(),
            'global_groups' => $this->wm_engine->globalGroups(),
            default => $this->$name,
        };
    }

    /**
     * Makes private properties settable for backward compatibility.
     *
     * The engine's own state (`cache`, the hit counts, `global_groups`) can't
     * be replaced from outside; those writes are ignored.
     *
     * @since WM-0.4.0
     * @since WP-4.0.0
     *
     * @param  string  $name  Property to set.
     * @param  mixed  $value  Property value.
     */
    public function __set($name, $value)
    {
        if (! in_array($name, ['cache', 'cache_hits', 'cache_misses', 'global_groups', 'wm_engine'], true)) {
            $this->$name = $value;
        }
    }

    /**
     * Makes private properties checkable for backward compatibility.
     *
     * @since WM-0.4.0
     * @since WP-4.0.0
     *
     * @param  string  $name  Property to check if set.
     * @return bool Whether the property is set.
     */
    public function __isset($name)
    {
        return in_array($name, ['cache', 'cache_hits', 'cache_misses', 'global_groups'], true) || isset($this->$name);
    }

    /**
     * Makes private properties un-settable for backward compatibility.
     *
     * @since WM-0.4.0
     * @since WP-4.0.0
     *
     * @param  string  $name  Property to unset.
     */
    public function __unset($name)
    {
        if ($name !== 'wm_engine') {
            unset($this->$name);
        }
    }

    /**
     * Whether values outlive the request (a persistent store is configured).
     *
     * @since WM-0.4.0
     *
     * @return bool
     */
    public function wm_is_persistent()
    {
        return $this->wm_engine->isPersistent();
    }

    /**
     * Serves as a utility function to determine whether a key is valid.
     *
     * @since WM-0.4.0
     * @since WP-6.1.0
     *
     * @param  int|string  $key  Cache key to check for validity.
     * @return bool Whether the key is valid.
     */
    protected function is_valid_key($key)
    {
        if (is_int($key)) {
            return true;
        }

        if (is_string($key) && trim($key) !== '') {
            return true;
        }

        $type = gettype($key);

        if (! function_exists('__')) {
            wp_load_translations_early();
        }

        $message = is_string($key)
            ? __('Cache key must not be an empty string.')
            /* translators: %s: The type of the given cache key. */
            : sprintf(__('Cache key must be an integer or a non-empty string, %s given.'), $type);

        _doing_it_wrong(
            sprintf('%s::%s', __CLASS__, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function']),
            $message,
            '6.1.0'
        );

        return false;
    }

    /**
     * Adds data to the cache if it doesn't already exist.
     *
     * @since WM-0.4.0
     * @since WP-2.0.0
     *
     * @param  int|string  $key  What to call the contents in the cache.
     * @param  mixed  $data  The contents to store in the cache.
     * @param  string  $group  Optional. Where to group the cache contents. Default 'default'.
     * @param  int  $expire  Optional. When to expire the cache contents, in seconds.
     *                       Default 0 (no expiration).
     * @return bool True on success, false if cache key and group already exist.
     */
    public function add($key, $data, $group = 'default', $expire = 0)
    {
        if (wp_suspend_cache_addition()) {
            return false;
        }

        if (! $this->is_valid_key($key)) {
            return false;
        }

        return $this->wm_engine->add($key, $data, $this->wm_group($group), (int) $expire);
    }

    /**
     * Adds multiple values to the cache in one call.
     *
     * @since WM-0.4.0
     * @since WP-6.0.0
     *
     * @param  array  $data  Array of keys and values to be added.
     * @param  string  $group  Optional. Where the cache contents are grouped. Default empty.
     * @param  int  $expire  Optional. When to expire the cache contents, in seconds.
     *                       Default 0 (no expiration).
     * @return bool[] Array of return values, grouped by key. Each value is either
     *                true on success, or false if cache key and group already exist.
     */
    public function add_multiple(array $data, $group = '', $expire = 0)
    {
        $values = [];

        foreach ($data as $key => $value) {
            $values[$key] = $this->add($key, $value, $group, $expire);
        }

        return $values;
    }

    /**
     * Replaces the contents in the cache, if contents already exist.
     *
     * @since WM-0.4.0
     * @since WP-2.0.0
     *
     * @param  int|string  $key  What to call the contents in the cache.
     * @param  mixed  $data  The contents to store in the cache.
     * @param  string  $group  Optional. Where to group the cache contents. Default 'default'.
     * @param  int  $expire  Optional. When to expire the cache contents, in seconds.
     *                       Default 0 (no expiration).
     * @return bool True if contents were replaced, false if original value does not exist.
     */
    public function replace($key, $data, $group = 'default', $expire = 0)
    {
        if (! $this->is_valid_key($key)) {
            return false;
        }

        return $this->wm_engine->replace($key, $data, $this->wm_group($group), (int) $expire);
    }

    /**
     * Sets the data contents into the cache.
     *
     * The cache contents are grouped by the $group parameter followed by the
     * $key. This allows for duplicate IDs in unique groups.
     *
     * @since WM-0.4.0 $expire is honoured when a persistent store is configured.
     * @since WP-2.0.0
     *
     * @param  int|string  $key  What to call the contents in the cache.
     * @param  mixed  $data  The contents to store in the cache.
     * @param  string  $group  Optional. Where to group the cache contents. Default 'default'.
     * @param  int  $expire  Optional. When to expire the cache contents, in seconds.
     *                       Default 0 (no expiration).
     * @return bool True if contents were set, false if key is invalid.
     */
    public function set($key, $data, $group = 'default', $expire = 0)
    {
        if (! $this->is_valid_key($key)) {
            return false;
        }

        return $this->wm_engine->set($key, $data, $this->wm_group($group), (int) $expire);
    }

    /**
     * Sets multiple values to the cache in one call.
     *
     * @since WM-0.4.0
     * @since WP-6.0.0
     *
     * @param  array  $data  Array of key and value to be set.
     * @param  string  $group  Optional. Where the cache contents are grouped. Default empty.
     * @param  int  $expire  Optional. When to expire the cache contents, in seconds.
     *                       Default 0 (no expiration).
     * @return bool[] Array of return values, grouped by key. Each value is always true.
     */
    public function set_multiple(array $data, $group = '', $expire = 0)
    {
        $values = [];

        foreach ($data as $key => $value) {
            $values[$key] = $this->set($key, $value, $group, $expire);
        }

        return $values;
    }

    /**
     * Retrieves the cache contents, if it exists.
     *
     * @since WM-0.4.0 $force reads past memory to the persistent store, when one is configured.
     * @since WP-2.0.0
     *
     * @param  int|string  $key  The key under which the cache contents are stored.
     * @param  string  $group  Optional. Where the cache contents are grouped. Default 'default'.
     * @param  bool  $force  Optional. Whether to force an update of the local cache
     *                       from the persistent cache. Default false.
     * @param  bool  $found  Optional. Whether the key was found in the cache (passed by reference).
     *                       Disambiguates a return of false, a storable value. Default null.
     * @return mixed|false The cache contents on success, false on failure to retrieve contents.
     */
    public function get($key, $group = 'default', $force = false, &$found = null)
    {
        if (! $this->is_valid_key($key)) {
            return false;
        }

        return $this->wm_engine->get($key, $this->wm_group($group), (bool) $force, $found);
    }

    /**
     * Retrieves multiple values from the cache in one call.
     *
     * @since WM-0.4.0
     * @since WP-5.5.0
     *
     * @param  array  $keys  Array of keys under which the cache contents are stored.
     * @param  string  $group  Optional. Where the cache contents are grouped. Default 'default'.
     * @param  bool  $force  Optional. Whether to force an update of the local cache
     *                       from the persistent cache. Default false.
     * @return array Array of return values, grouped by key. Each value is either
     *               the cache contents on success, or false on failure.
     */
    public function get_multiple($keys, $group = 'default', $force = false)
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $group, $force);
        }

        return $values;
    }

    /**
     * Removes the contents of the cache key in the group.
     *
     * If the cache key does not exist in the group, then nothing will happen.
     *
     * @since WM-0.4.0
     * @since WP-2.0.0
     *
     * @param  int|string  $key  What the contents in the cache are called.
     * @param  string  $group  Optional. Where the cache contents are grouped. Default 'default'.
     * @param  bool  $deprecated  Optional. Unused. Default false.
     * @return bool True on success, false if the contents were not deleted.
     */
    public function delete($key, $group = 'default', $deprecated = false)
    {
        if (! $this->is_valid_key($key)) {
            return false;
        }

        return $this->wm_engine->delete($key, $this->wm_group($group));
    }

    /**
     * Deletes multiple values from the cache in one call.
     *
     * @since WM-0.4.0
     * @since WP-6.0.0
     *
     * @param  array  $keys  Array of keys to be deleted.
     * @param  string  $group  Optional. Where the cache contents are grouped. Default empty.
     * @return bool[] Array of return values, grouped by key. Each value is either
     *                true on success, or false if the contents were not deleted.
     */
    public function delete_multiple(array $keys, $group = '')
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->delete($key, $group);
        }

        return $values;
    }

    /**
     * Increments numeric cache item's value.
     *
     * @since WM-0.4.0
     * @since WP-3.3.0
     *
     * @param  int|string  $key  The cache key to increment.
     * @param  int  $offset  Optional. The amount by which to increment the item's value.
     *                       Default 1.
     * @param  string  $group  Optional. The group the key is in. Default 'default'.
     * @return int|false The item's new value on success, false on failure.
     */
    public function incr($key, $offset = 1, $group = 'default')
    {
        if (! $this->is_valid_key($key)) {
            return false;
        }

        return $this->wm_engine->incr($key, (int) $offset, $this->wm_group($group));
    }

    /**
     * Decrements numeric cache item's value.
     *
     * @since WM-0.4.0
     * @since WP-3.3.0
     *
     * @param  int|string  $key  The cache key to decrement.
     * @param  int  $offset  Optional. The amount by which to decrement the item's value.
     *                       Default 1.
     * @param  string  $group  Optional. The group the key is in. Default 'default'.
     * @return int|false The item's new value on success, false on failure.
     */
    public function decr($key, $offset = 1, $group = 'default')
    {
        if (! $this->is_valid_key($key)) {
            return false;
        }

        return $this->wm_engine->decr($key, (int) $offset, $this->wm_group($group));
    }

    /**
     * Clears the object cache of all data.
     *
     * @since WM-0.4.0 Also invalidates the persistent store's WordPress entries, keeping Laravel's own.
     * @since WP-2.0.0
     *
     * @return true Always returns true.
     */
    public function flush()
    {
        return $this->wm_engine->flush();
    }

    /**
     * Removes all cache items in a group.
     *
     * @since WM-0.4.0
     * @since WP-6.1.0
     *
     * @param  string  $group  Name of group to remove from cache.
     * @return true Always returns true.
     */
    public function flush_group($group)
    {
        return $this->wm_engine->flushGroup((string) $group);
    }

    /**
     * Removes all cache items from memory, leaving the persistent store alone.
     *
     * @since WM-0.4.0
     *
     * @return true Always returns true.
     */
    public function flush_runtime()
    {
        return $this->wm_engine->flushRuntime();
    }

    /**
     * Sets the list of global cache groups.
     *
     * @since WM-0.4.0
     * @since WP-3.0.0
     *
     * @param  string|string[]  $groups  List of groups that are global.
     */
    public function add_global_groups($groups)
    {
        $this->wm_engine->addGlobalGroups((array) $groups);
    }

    /**
     * Sets the list of groups that stay in memory and never reach the
     * persistent store.
     *
     * @since WM-0.4.0
     *
     * @param  string|string[]  $groups  List of groups that are to be ignored.
     */
    public function add_non_persistent_groups($groups)
    {
        $this->wm_engine->addNonPersistentGroups((array) $groups);
    }

    /**
     * Switches the internal blog ID.
     *
     * This changes the blog ID used to create keys in blog specific groups.
     *
     * @since WM-0.4.0
     * @since WP-3.5.0
     *
     * @param  int  $blog_id  Blog ID.
     */
    public function switch_to_blog($blog_id)
    {
        $blog_id = (int) $blog_id;
        $this->blog_prefix = $this->multisite ? $blog_id.':' : '';
    }

    /**
     * Resets cache keys.
     *
     * @since WM-0.4.0
     * @since WP-3.0.0
     * @deprecated WP-3.5.0 Use WP_Object_Cache::switch_to_blog()
     * @see switch_to_blog()
     */
    public function reset()
    {
        _deprecated_function(__FUNCTION__, '3.5.0', 'WP_Object_Cache::switch_to_blog()');

        // Clear out non-global caches since the blog ID has changed.
        $global = $this->wm_engine->globalGroups();

        foreach (array_keys($this->wm_engine->runtime()) as $group) {
            if (! isset($global[$group])) {
                $this->wm_engine->flushRuntimeGroup($group);
            }
        }
    }

    /**
     * Echoes the stats of the caching.
     *
     * Gives the cache hits, and cache misses. Also prints every cached group,
     * key and the data.
     *
     * @since WM-0.4.0
     * @since WP-2.0.0
     */
    public function stats()
    {
        echo '<p>';
        echo '<strong>Cache Hits:</strong> '.$this->wm_engine->hits().'<br />';
        echo '<strong>Cache Misses:</strong> '.$this->wm_engine->misses().'<br />';
        echo '</p>';
        echo '<ul>';

        foreach ($this->wm_engine->runtime() as $group => $cache) {
            echo '<li><strong>Group:</strong> '.htmlspecialchars((string) $group, ENT_QUOTES).' - ( '.number_format(strlen(serialize($cache)) / 1024, 2).'k )</li>';
        }

        echo '</ul>';
    }

    /**
     * WordPress's rule: an empty group means 'default'.
     */
    private function wm_group($group): string
    {
        return empty($group) ? 'default' : (string) $group;
    }
}
