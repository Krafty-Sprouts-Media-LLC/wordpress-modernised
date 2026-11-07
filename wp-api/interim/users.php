<?php

/**
 * Stand-ins for the current user until the logins step. Listed in
 * docs/stand-ins.md. Nobody is logged in: the user ID is 0 and every
 * capability check says no, as for a visitor in WordPress.
 */
if (! function_exists('get_current_user_id')) {
    /**
     * Gets the current user's ID.
     *
     * Stand-in: nobody is logged in yet.
     *
     * @since WM-0.5.0
     * @since WP-MU (3.0.0)
     *
     * @return int The current user's ID, or 0 if no user is logged in.
     */
    function get_current_user_id()
    {
        return 0;
    }
}

if (! function_exists('is_user_logged_in')) {
    /**
     * Determines whether the current visitor is a logged in user.
     *
     * Stand-in: nobody is logged in yet.
     *
     * @since WM-0.5.0
     * @since WP-2.0.0
     *
     * @return bool True if user is logged in, false if not logged in.
     */
    function is_user_logged_in()
    {
        return false;
    }
}

if (! function_exists('current_user_can')) {
    /**
     * Returns whether the current user has the specified capability.
     *
     * Stand-in: nobody is logged in yet, so the answer is always no.
     *
     * @since WM-0.5.0
     * @since WP-2.0.0
     *
     * @param  string  $capability  Capability name.
     * @param  mixed  ...$args  Optional further parameters, typically starting with an object ID.
     * @return bool Whether the current user has the given capability.
     */
    function current_user_can($capability, ...$args)
    {
        return false;
    }
}
