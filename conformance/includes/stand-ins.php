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
