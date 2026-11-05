<?php

/**
 * Stand-ins the checker needs before the real classes are rebuilt.
 * Each is listed in docs/stand-ins.md and removed when its real version exists.
 */
if (! class_exists('wpdb')) {
    /**
     * Stand-in for wpdb. WordPress's tests/phpunit/includes/utils.php declares
     * a class extending it at load time; nothing calls it yet. Replaced when
     * the database layer is built.
     */
    class wpdb {}
}

if (! class_exists('WP_Post')) {
    /**
     * Stand-in for WP_Post: copies the given fields onto itself. The hooks
     * tests only pass it around as a value. Replaced when posts are built.
     */
    #[AllowDynamicProperties]
    final class WP_Post
    {
        public function __construct($post)
        {
            foreach (get_object_vars($post) as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}
