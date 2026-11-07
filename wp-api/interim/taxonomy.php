<?php

/**
 * Stand-ins for the taxonomy API (categories and tags) until the terms step.
 * Listed in docs/stand-ins.md. They answer "there are no taxonomies", so code
 * that asks (wp_insert_post(), post types) skips categories and tags, as
 * WordPress does for a post type without them.
 */
if (! function_exists('get_object_taxonomies')) {
    /**
     * Returns the names or objects of the taxonomies which are registered for the requested object or object type.
     *
     * Stand-in: there are no taxonomies yet.
     *
     * @since WM-0.5.0
     * @since WP-2.3.0
     *
     * @param  string|string[]|WP_Post  $object_type  Name of the type of taxonomy object, or an object (row from posts).
     * @param  string  $output  Optional. The type of output to return in the array. Default 'names'.
     * @return string[]|WP_Taxonomy[] The names or objects of all taxonomies of `$object_type`.
     */
    function get_object_taxonomies($object_type, $output = 'names')
    {
        return [];
    }
}

if (! function_exists('is_object_in_taxonomy')) {
    /**
     * Determines if the given object type is associated with the given taxonomy.
     *
     * Stand-in: there are no taxonomies yet.
     *
     * @since WM-0.5.0
     * @since WP-3.0.0
     *
     * @param  string  $object_type  Object type string.
     * @param  string  $taxonomy  Single taxonomy name.
     * @return bool True if object is associated with the taxonomy, otherwise false.
     */
    function is_object_in_taxonomy($object_type, $taxonomy)
    {
        return false;
    }
}

if (! function_exists('taxonomy_exists')) {
    /**
     * Determines whether the taxonomy name exists.
     *
     * Stand-in: there are no taxonomies yet.
     *
     * @since WM-0.5.0
     * @since WP-3.0.0
     *
     * @param  string  $taxonomy  Name of taxonomy object.
     * @return bool Whether the taxonomy exists.
     */
    function taxonomy_exists($taxonomy)
    {
        return false;
    }
}

if (! function_exists('get_taxonomy')) {
    /**
     * Retrieves the taxonomy object of $taxonomy.
     *
     * Stand-in: there are no taxonomies yet.
     *
     * @since WM-0.5.0
     * @since WP-2.3.0
     *
     * @param  string  $taxonomy  Name of taxonomy object to return.
     * @return WP_Taxonomy|false The taxonomy object or false if $taxonomy doesn't exist.
     */
    function get_taxonomy($taxonomy)
    {
        return false;
    }
}

if (! function_exists('register_taxonomy_for_object_type')) {
    /**
     * Adds an already registered taxonomy to an object type.
     *
     * Stand-in: there are no taxonomies yet, so there is nothing to add.
     *
     * @since WM-0.5.0
     * @since WP-3.0.0
     *
     * @param  string  $taxonomy  Name of taxonomy object.
     * @param  string  $object_type  Name of the object type.
     * @return bool True if successful, false if not.
     */
    function register_taxonomy_for_object_type($taxonomy, $object_type)
    {
        return false;
    }
}

if (! function_exists('unregister_taxonomy_for_object_type')) {
    /**
     * Removes an already registered taxonomy from an object type.
     *
     * Stand-in: there are no taxonomies yet, so there is nothing to remove.
     *
     * @since WM-0.5.0
     * @since WP-3.7.0
     *
     * @param  string  $taxonomy  Name of taxonomy object.
     * @param  string  $object_type  Name of the object type.
     * @return bool True if successful, false if not.
     */
    function unregister_taxonomy_for_object_type($taxonomy, $object_type)
    {
        return false;
    }
}

if (! function_exists('wp_delete_object_term_relationships')) {
    /**
     * Unlinks the object from the taxonomy or taxonomies.
     *
     * Stand-in: there are no taxonomies yet, so there are no links to remove.
     *
     * @since WM-0.5.0
     * @since WP-2.3.0
     *
     * @param  int  $object_id  The term object ID that refers to the term.
     * @param  string|array  $taxonomies  List of taxonomy names or single taxonomy name.
     */
    function wp_delete_object_term_relationships($object_id, $taxonomies)
    {
        // Nothing to remove until the terms step.
    }
}

if (! function_exists('clean_object_term_cache')) {
    /**
     * Removes the taxonomy relationship to terms from the cache.
     *
     * Stand-in: there are no taxonomies yet, so nothing is cached.
     *
     * @since WM-0.5.0
     * @since WP-2.3.0
     *
     * @param  int|array  $object_ids  Single or list of term object ID(s).
     * @param  array|string  $object_type  The taxonomy object type.
     */
    function clean_object_term_cache($object_ids, $object_type)
    {
        // Nothing cached until the terms step.
    }
}

if (! function_exists('update_object_term_cache')) {
    /**
     * Updates the cache for the given term object ID(s).
     *
     * Stand-in: there are no taxonomies yet, so there is nothing to cache.
     *
     * @since WM-0.5.0
     * @since WP-2.3.0
     *
     * @param  string|int[]  $object_ids  Comma-separated list or array of term object IDs.
     * @param  string|string[]  $object_type  The taxonomy object type or array of the same.
     * @return void|false Void on success or if the `$object_ids` parameter is empty,
     *                    false if all of the terms in `$object_ids` are already cached.
     */
    function update_object_term_cache($object_ids, $object_type)
    {
        // Nothing to cache until the terms step.
    }
}
