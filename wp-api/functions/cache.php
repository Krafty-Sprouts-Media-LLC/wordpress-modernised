<?php

/**
 * Object Cache API: the wp_cache_*() functions, which call the global
 * $wp_object_cache (WP_Object_Cache), plus the cache switches
 * wp_using_ext_object_cache(), wp_suspend_cache_addition() and
 * wp_suspend_cache_invalidation().
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/cache.php,
 * src/wp-includes/cache-compat.php, src/wp-includes/load.php and
 * src/wp-includes/functions.php.
 */

/**
 * Sets up Object Cache Global and assigns it.
 *
 * @since WM-0.4.0 Also tells WordPress whether a persistent store is configured.
 * @since WP-2.0.0
 *
 * @global WP_Object_Cache $wp_object_cache
 */
function wp_cache_init()
{
    $GLOBALS['wp_object_cache'] = new WP_Object_Cache;

    wp_using_ext_object_cache($GLOBALS['wp_object_cache']->wm_is_persistent());
}

/**
 * Starts the WordPress object cache, and sets WordPress's default global and
 * non-persistent groups.
 *
 * WM: there is no object-cache.php drop-in to load: a persistent store is the
 * WP_OBJECT_CACHE_STORE setting (docs/divergences.md). Called once at boot.
 *
 * @since WM-0.4.0
 * @since WP-3.0.0
 */
function wp_start_object_cache()
{
    wp_cache_init();

    wp_cache_add_global_groups(
        [
            'blog-details',
            'blog-id-cache',
            'blog-lookup',
            'blog_meta',
            'global-posts',
            'image_editor',
            'networks',
            'network-queries',
            'sites',
            'site-details',
            'site-options',
            'site-queries',
            'site-transient',
            'theme_files',
            'translation_files',
            'rss',
            'users',
            'user-queries',
            'user_meta',
            'useremail',
            'userlogins',
            'userslugs',
        ]
    );

    wp_cache_add_non_persistent_groups(['counts', 'plugins', 'theme_json']);
}

/**
 * Adds data to the cache, if the cache key doesn't already exist.
 *
 * @since WM-0.4.0
 * @since WP-2.0.0
 * @see WP_Object_Cache::add()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  int|string  $key  The cache key to use for retrieval later.
 * @param  mixed  $data  The data to add to the cache.
 * @param  string  $group  Optional. The group to add the cache to. Enables the same key
 *                         to be used across groups. Default empty.
 * @param  int  $expire  Optional. When the cache data should expire, in seconds.
 *                       Default 0 (no expiration).
 * @return bool True on success, false if cache key and group already exist.
 */
function wp_cache_add($key, $data, $group = '', $expire = 0)
{
    global $wp_object_cache;

    return $wp_object_cache->add($key, $data, $group, (int) $expire);
}

/**
 * Adds multiple values to the cache in one call.
 *
 * @since WM-0.4.0
 * @since WP-6.0.0
 * @see WP_Object_Cache::add_multiple()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  array  $data  Array of keys and values to be set.
 * @param  string  $group  Optional. Where the cache contents are grouped. Default empty.
 * @param  int  $expire  Optional. When to expire the cache contents, in seconds.
 *                       Default 0 (no expiration).
 * @return bool[] Array of return values, grouped by key. Each value is either
 *                true on success, or false if cache key and group already exist.
 */
function wp_cache_add_multiple(array $data, $group = '', $expire = 0)
{
    global $wp_object_cache;

    return $wp_object_cache->add_multiple($data, $group, $expire);
}

/**
 * Replaces the contents of the cache with new data.
 *
 * @since WM-0.4.0
 * @since WP-2.0.0
 * @see WP_Object_Cache::replace()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  int|string  $key  The key for the cache data that should be replaced.
 * @param  mixed  $data  The new data to store in the cache.
 * @param  string  $group  Optional. The group for the cache data that should be replaced.
 *                         Default empty.
 * @param  int  $expire  Optional. When to expire the cache contents, in seconds.
 *                       Default 0 (no expiration).
 * @return bool True if contents were replaced, false if original value does not exist.
 */
function wp_cache_replace($key, $data, $group = '', $expire = 0)
{
    global $wp_object_cache;

    return $wp_object_cache->replace($key, $data, $group, (int) $expire);
}

/**
 * Saves the data to the cache.
 *
 * Differs from wp_cache_add() and wp_cache_replace() in that it will always write data.
 *
 * @since WM-0.4.0
 * @since WP-2.0.0
 * @see WP_Object_Cache::set()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  int|string  $key  The cache key to use for retrieval later.
 * @param  mixed  $data  The contents to store in the cache.
 * @param  string  $group  Optional. Where to group the cache contents. Enables the same key
 *                         to be used across groups. Default empty.
 * @param  int  $expire  Optional. When to expire the cache contents, in seconds.
 *                       Default 0 (no expiration).
 * @return bool True on success, false on failure.
 */
function wp_cache_set($key, $data, $group = '', $expire = 0)
{
    global $wp_object_cache;

    return $wp_object_cache->set($key, $data, $group, (int) $expire);
}

/**
 * Sets multiple values to the cache in one call.
 *
 * @since WM-0.4.0
 * @since WP-6.0.0
 * @see WP_Object_Cache::set_multiple()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  array  $data  Array of keys and values to be set.
 * @param  string  $group  Optional. Where the cache contents are grouped. Default empty.
 * @param  int  $expire  Optional. When to expire the cache contents, in seconds.
 *                       Default 0 (no expiration).
 * @return bool[] Array of return values, grouped by key. Each value is either
 *                true on success, or false on failure.
 */
function wp_cache_set_multiple(array $data, $group = '', $expire = 0)
{
    global $wp_object_cache;

    return $wp_object_cache->set_multiple($data, $group, $expire);
}

/**
 * Retrieves the cache contents from the cache by key and group.
 *
 * @since WM-0.4.0
 * @since WP-2.0.0
 * @see WP_Object_Cache::get()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  int|string  $key  The key under which the cache contents are stored.
 * @param  string  $group  Optional. Where the cache contents are grouped. Default empty.
 * @param  bool  $force  Optional. Whether to force an update of the local cache
 *                       from the persistent cache. Default false.
 * @param  bool  $found  Optional. Whether the key was found in the cache (passed by reference).
 *                       Disambiguates a return of false, a storable value. Default null.
 * @return mixed|false The cache contents on success, false on failure to retrieve contents.
 */
function wp_cache_get($key, $group = '', $force = false, &$found = null)
{
    global $wp_object_cache;

    return $wp_object_cache->get($key, $group, $force, $found);
}

/**
 * Retrieves multiple values from the cache in one call.
 *
 * @since WM-0.4.0
 * @since WP-5.5.0
 * @see WP_Object_Cache::get_multiple()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  array  $keys  Array of keys under which the cache contents are stored.
 * @param  string  $group  Optional. Where the cache contents are grouped. Default empty.
 * @param  bool  $force  Optional. Whether to force an update of the local cache
 *                       from the persistent cache. Default false.
 * @return array Array of return values, grouped by key. Each value is either
 *               the cache contents on success, or false on failure.
 */
function wp_cache_get_multiple($keys, $group = '', $force = false)
{
    global $wp_object_cache;

    return $wp_object_cache->get_multiple($keys, $group, $force);
}

/**
 * Removes the cache contents matching key and group.
 *
 * @since WM-0.4.0
 * @since WP-2.0.0
 * @see WP_Object_Cache::delete()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  int|string  $key  What the contents in the cache are called.
 * @param  string  $group  Optional. Where the cache contents are grouped. Default empty.
 * @return bool True on successful removal, false on failure.
 */
function wp_cache_delete($key, $group = '')
{
    global $wp_object_cache;

    return $wp_object_cache->delete($key, $group);
}

/**
 * Deletes multiple values from the cache in one call.
 *
 * @since WM-0.4.0
 * @since WP-6.0.0
 * @see WP_Object_Cache::delete_multiple()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  array  $keys  Array of keys under which the cache to deleted.
 * @param  string  $group  Optional. Where the cache contents are grouped. Default empty.
 * @return bool[] Array of return values, grouped by key. Each value is either
 *                true on success, or false if the contents were not deleted.
 */
function wp_cache_delete_multiple(array $keys, $group = '')
{
    global $wp_object_cache;

    return $wp_object_cache->delete_multiple($keys, $group);
}

/**
 * Increments numeric cache item's value.
 *
 * @since WM-0.4.0
 * @since WP-3.3.0
 * @see WP_Object_Cache::incr()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  int|string  $key  The key for the cache contents that should be incremented.
 * @param  int  $offset  Optional. The amount by which to increment the item's value.
 *                       Default 1.
 * @param  string  $group  Optional. The group the key is in. Default empty.
 * @return int|false The item's new value on success, false on failure.
 */
function wp_cache_incr($key, $offset = 1, $group = '')
{
    global $wp_object_cache;

    return $wp_object_cache->incr($key, $offset, $group);
}

/**
 * Decrements numeric cache item's value.
 *
 * @since WM-0.4.0
 * @since WP-3.3.0
 * @see WP_Object_Cache::decr()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  int|string  $key  The cache key to decrement.
 * @param  int  $offset  Optional. The amount by which to decrement the item's value.
 *                       Default 1.
 * @param  string  $group  Optional. The group the key is in. Default empty.
 * @return int|false The item's new value on success, false on failure.
 */
function wp_cache_decr($key, $offset = 1, $group = '')
{
    global $wp_object_cache;

    return $wp_object_cache->decr($key, $offset, $group);
}

/**
 * Removes all cache items.
 *
 * @since WM-0.4.0 With a persistent store, Laravel's own entries are kept.
 * @since WP-2.0.0
 * @see WP_Object_Cache::flush()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @return bool True on success, false on failure.
 */
function wp_cache_flush()
{
    global $wp_object_cache;

    return $wp_object_cache->flush();
}

/**
 * Removes all cache items from the in-memory runtime cache.
 *
 * @since WM-0.4.0 Leaves the persistent store alone.
 * @since WP-6.0.0
 * @see WP_Object_Cache::flush_runtime()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @return bool True on success, false on failure.
 */
function wp_cache_flush_runtime()
{
    global $wp_object_cache;

    return $wp_object_cache->flush_runtime();
}

/**
 * Removes all cache items in a group, if the object cache implementation supports it.
 *
 * Before calling this function, always check for group flushing support using the
 * `wp_cache_supports( 'flush_group' )` function.
 *
 * @since WM-0.4.0
 * @since WP-6.1.0
 * @see WP_Object_Cache::flush_group()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  string  $group  Name of group to remove from cache.
 * @return bool True if group was flushed, false otherwise.
 */
function wp_cache_flush_group($group)
{
    global $wp_object_cache;

    return $wp_object_cache->flush_group($group);
}

/**
 * Determines whether the object cache implementation supports a particular feature.
 *
 * @since WM-0.4.0
 * @since WP-6.1.0
 *
 * @param  string  $feature  Name of the feature to check for. Possible values include:
 *                           'add_multiple', 'set_multiple', 'get_multiple', 'delete_multiple',
 *                           'flush_runtime', 'flush_group'.
 * @return bool True if the feature is supported, false otherwise.
 */
function wp_cache_supports($feature)
{
    switch ($feature) {
        case 'add_multiple':
        case 'set_multiple':
        case 'get_multiple':
        case 'delete_multiple':
        case 'flush_runtime':
        case 'flush_group':
            return true;

        default:
            return false;
    }
}

/**
 * Closes the cache.
 *
 * Does nothing, as in WordPress since 2.5; plugins replacing the cache may use it.
 *
 * @since WM-0.4.0
 * @since WP-2.0.0
 *
 * @return true Always returns true.
 */
function wp_cache_close()
{
    return true;
}

/**
 * Adds a group or set of groups to the list of global groups.
 *
 * @since WM-0.4.0
 * @since WP-2.6.0
 * @see WP_Object_Cache::add_global_groups()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  string|string[]  $groups  A group or an array of groups to add.
 */
function wp_cache_add_global_groups($groups)
{
    global $wp_object_cache;

    $wp_object_cache->add_global_groups($groups);
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 * @since WM-0.4.0 Non-persistent groups stay in memory when a persistent store is configured.
 * @since WP-2.6.0
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  string|string[]  $groups  A group or an array of groups to add.
 */
function wp_cache_add_non_persistent_groups($groups)
{
    global $wp_object_cache;

    $wp_object_cache->add_non_persistent_groups($groups);
}

/**
 * Switches the internal blog ID.
 *
 * This changes the blog id used to create keys in blog specific groups.
 *
 * @since WM-0.4.0
 * @since WP-3.5.0
 * @see WP_Object_Cache::switch_to_blog()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param  int  $blog_id  Site ID.
 */
function wp_cache_switch_to_blog($blog_id)
{
    global $wp_object_cache;

    $wp_object_cache->switch_to_blog($blog_id);
}

/**
 * Resets internal cache keys and structures.
 *
 * @since WM-0.4.0
 * @since WP-3.0.0
 * @deprecated WP-3.5.0 Use wp_cache_switch_to_blog()
 * @see WP_Object_Cache::reset()
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 */
function wp_cache_reset()
{
    _deprecated_function(__FUNCTION__, '3.5.0', 'wp_cache_switch_to_blog()');

    global $wp_object_cache;

    $wp_object_cache->reset();
}

/**
 * Retrieves cached data if valid and unchanged.
 *
 * @since WM-0.4.0
 * @since WP-6.9.0
 *
 * @param  string  $cache_key  The cache key used for storage and retrieval.
 * @param  string  $group  The cache group used for organizing data.
 * @param  string|string[]  $salt  The timestamp (or multiple timestamps if an array) indicating when the cache group(s) were last updated.
 * @return mixed|false The cached data if valid, or false if the cache does not exist or is outdated.
 */
function wp_cache_get_salted($cache_key, $group, $salt)
{
    $salt = is_array($salt) ? implode(':', $salt) : $salt;
    $cache = wp_cache_get($cache_key, $group);

    if (! is_array($cache)) {
        return false;
    }

    if (! isset($cache['salt']) || ! isset($cache['data']) || $salt !== $cache['salt']) {
        return false;
    }

    return $cache['data'];
}

/**
 * Stores salted data in the cache.
 *
 * @since WM-0.4.0
 * @since WP-6.9.0
 *
 * @param  string  $cache_key  The cache key under which to store the data.
 * @param  mixed  $data  The data to be cached.
 * @param  string  $group  The cache group to which the data belongs.
 * @param  string|string[]  $salt  The timestamp (or multiple timestamps if an array) indicating when the cache group(s) were last updated.
 * @param  int  $expire  Optional. When to expire the cache contents, in seconds.
 *                       Default 0 (no expiration).
 * @return bool True on success, false on failure.
 */
function wp_cache_set_salted($cache_key, $data, $group, $salt, $expire = 0)
{
    $salt = is_array($salt) ? implode(':', $salt) : $salt;

    return wp_cache_set(
        $cache_key,
        [
            'data' => $data,
            'salt' => $salt,
        ],
        $group,
        $expire
    );
}

/**
 * Retrieves multiple items from the cache, only considering valid and unchanged items.
 *
 * @since WM-0.4.0
 * @since WP-6.9.0
 *
 * @param  array  $cache_keys  Array of cache keys to retrieve.
 * @param  string  $group  The group of the cache keys.
 * @param  string|string[]  $salt  The timestamp (or multiple timestamps if an array) indicating when the cache group(s) were last updated.
 * @return array An associative array containing cache values. Values are `false` if they are not found or outdated.
 */
function wp_cache_get_multiple_salted($cache_keys, $group, $salt)
{
    $salt = is_array($salt) ? implode(':', $salt) : $salt;
    $cache = wp_cache_get_multiple($cache_keys, $group);

    foreach ($cache as $key => $value) {
        if (! is_array($value)) {
            $cache[$key] = false;

            continue;
        }

        if (! isset($value['salt'], $value['data']) || $salt !== $value['salt']) {
            $cache[$key] = false;

            continue;
        }

        $cache[$key] = $value['data'];
    }

    return $cache;
}

/**
 * Stores multiple pieces of salted data in the cache.
 *
 * @since WM-0.4.0
 * @since WP-6.9.0
 *
 * @param  mixed  $data  Data to be stored in the cache for all keys.
 * @param  string  $group  Group to which the cached data belongs.
 * @param  string|string[]  $salt  The timestamp (or multiple timestamps if an array) indicating when the cache group(s) were last updated.
 * @param  int  $expire  Optional. When to expire the cache contents, in seconds.
 *                       Default 0 (no expiration).
 * @return bool[] Array of return values, grouped by key. Each value is either
 *                true on success, or false on failure.
 */
function wp_cache_set_multiple_salted($data, $group, $salt, $expire = 0)
{
    $salt = is_array($salt) ? implode(':', $salt) : $salt;
    $new_cache = [];

    foreach ($data as $key => $value) {
        $new_cache[$key] = [
            'data' => $value,
            'salt' => $salt,
        ];
    }

    return wp_cache_set_multiple($new_cache, $group, $expire);
}

/**
 * Toggles `$_wp_using_ext_object_cache` on and off without directly
 * touching global.
 *
 * True when a persistent store is configured (config wordpress.object_cache),
 * set by wp_cache_init().
 *
 * @since WM-0.4.0
 * @since WP-3.7.0
 *
 * @global bool $_wp_using_ext_object_cache
 *
 * @param  bool  $using  Whether external object cache is being used.
 * @return bool The current 'using' setting.
 */
function wp_using_ext_object_cache($using = null)
{
    global $_wp_using_ext_object_cache;

    $current_using = $_wp_using_ext_object_cache;

    if ($using !== null) {
        $_wp_using_ext_object_cache = $using;
    }

    return $current_using;
}

/**
 * Temporarily suspends cache additions.
 *
 * Stops more data being added to the cache, but still allows cache retrieval.
 * This is useful for actions, such as imports, when a lot of data would otherwise
 * be almost uselessly added to the cache.
 *
 * Suspension lasts for a single page load at most. Remember to call this
 * function again if you wish to re-enable cache adds earlier.
 *
 * @since WM-0.4.0
 * @since WP-3.3.0
 *
 * @param  bool  $suspend  Optional. Suspends additions if true, re-enables them if false.
 *                         Defaults to not changing the current setting.
 * @return bool The current suspend setting.
 */
function wp_suspend_cache_addition($suspend = null)
{
    static $_suspend = false;

    if (is_bool($suspend)) {
        $_suspend = $suspend;
    }

    return $_suspend;
}

/**
 * Suspends cache invalidation.
 *
 * Turns cache invalidation on and off. Useful during imports where you don't want to do
 * invalidations every time a post is inserted. Callers must be sure that what they are
 * doing won't lead to an inconsistent cache when invalidation is suspended.
 *
 * @since WM-0.4.0
 * @since WP-2.7.0
 *
 * @global bool $_wp_suspend_cache_invalidation
 *
 * @param  bool  $suspend  Optional. Whether to suspend or enable cache invalidation. Default true.
 * @return bool The current suspend setting.
 */
function wp_suspend_cache_invalidation($suspend = true)
{
    global $_wp_suspend_cache_invalidation;

    $current_suspend = $_wp_suspend_cache_invalidation;
    $_wp_suspend_cache_invalidation = $suspend;

    return $current_suspend;
}

/**
 * Gets last changed date for the specified cache group.
 *
 * @since WM-0.5.0
 * @since WP-4.7.0
 *
 * @param  string  $group  Where the cache contents are grouped.
 * @return string UNIX timestamp with microseconds representing when the group was last changed.
 */
function wp_cache_get_last_changed($group)
{
    $last_changed = wp_cache_get('last_changed', $group);

    if ($last_changed) {
        return $last_changed;
    }

    return wp_cache_set_last_changed($group);
}

/**
 * Sets last changed date for the specified cache group to now.
 *
 * @since WM-0.5.0
 * @since WP-6.3.0
 *
 * @param  string  $group  Where the cache contents are grouped.
 * @return string UNIX timestamp when the group was last changed.
 */
function wp_cache_set_last_changed($group)
{
    $previous_time = wp_cache_get('last_changed', $group);

    $time = microtime();

    wp_cache_set('last_changed', $time, $group);

    /**
     * Fires after a cache group `last_changed` time is updated.
     * This may occur multiple times per page load and registered
     * actions must be performant.
     *
     * @since WP-6.3.0
     *
     * @param  string  $group  The cache group name.
     * @param  string  $time  The new last changed time (msec sec).
     * @param  string|false  $previous_time  The previous last changed time. False if not previously set.
     */
    do_action('wp_cache_set_last_changed', $group, $time, $previous_time);

    return $time;
}
