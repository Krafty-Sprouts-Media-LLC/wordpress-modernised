<?php

/**
 * Stand-ins for wp_die() and dead_db() until WordPress's error pages are
 * rebuilt. Listed in docs/stand-ins.md.
 */
if (! function_exists('wp_die')) {
    /**
     * Stops execution with a message.
     *
     * Stand-in: runs the handler from the `wp_die_handler` filter (as WordPress
     * does for normal requests), but does not yet pick AJAX, JSON, XML-RPC or
     * JSONP handlers, and the default handler throws WP_Exception, which Laravel
     * renders as an error page, instead of printing WordPress's HTML page.
     *
     * @since WM-0.2.0
     * @since WP-2.0.4
     *
     * @param  string|WP_Error  $message  Optional. Error message. Default empty string.
     * @param  string|int  $title  Optional. Error title or HTTP status code. Default empty string.
     * @param  string|array|int  $args  Optional. Arguments to control behavior. Default empty array.
     */
    function wp_die($message = '', $title = '', $args = [])
    {
        /** This filter is documented in WordPress 7.1.4's src/wp-includes/functions.php */
        $handler = apply_filters('wp_die_handler', '_default_wp_die_handler');

        call_user_func($handler, $message, $title, $args);
    }
}

if (! function_exists('_default_wp_die_handler')) {
    /**
     * Stand-in default handler: throws WP_Exception with the message as plain text.
     *
     * @since WM-0.2.0
     * @since WP-3.0.0
     *
     * @param  string|WP_Error  $message  Error message or WP_Error object.
     * @param  string  $title  Optional. Error title. Default empty string.
     * @param  string|array  $args  Optional. Arguments to control behavior. Default empty array.
     */
    function _default_wp_die_handler($message, $title = '', $args = [])
    {
        if (is_wp_error($message)) {
            $message = $message->get_error_message();
        }

        throw new WP_Exception(trim(strip_tags((string) $message)) ?: 'WordPress has failed.');
    }
}

if (! function_exists('dead_db')) {
    /**
     * Stops with "Error establishing a database connection".
     *
     * Stand-in: no wp-content/db-error.php template support yet.
     *
     * @since WM-0.2.0
     * @since WP-2.3.2
     */
    function dead_db()
    {
        wp_die(__('Error establishing a database connection'), '', ['response' => 500]);
    }
}
