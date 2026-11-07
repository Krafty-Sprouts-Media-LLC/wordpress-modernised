<?php

/**
 * Stand-in for sanitize_option() until the formatting step. Listed in docs/stand-ins.md.
 */
if (! function_exists('sanitize_option')) {
    /**
     * Sanitizes various option values based on the nature of the option.
     *
     * Stand-in: runs only the `sanitize_option_{$option}` filter, which is all
     * WordPress does for options it has no rule for. WordPress's own rules for
     * its core options (admin_email, siteurl, posts_per_page, …) come with the
     * formatting step.
     *
     * @since WM-0.4.0
     * @since WP-2.0.5
     *
     * @param  string  $option  The name of the option.
     * @param  mixed  $value  The unsanitized value.
     * @return mixed Sanitized value.
     */
    function sanitize_option($option, $value)
    {
        $original_value = $value;

        /**
         * Filters an option value following sanitization.
         *
         * @since WP-2.3.0
         * @since WP-4.3.0 Added the `$original_value` parameter.
         *
         * @param  mixed  $value  The sanitized option value.
         * @param  string  $option  The option name.
         * @param  mixed  $original_value  The original value passed to the function.
         */
        return apply_filters("sanitize_option_{$option}", $value, $option, $original_value);
    }
}
