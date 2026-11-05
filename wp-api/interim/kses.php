<?php

/**
 * Stand-in for wp_kses() until the HTML-filtering step. Listed in docs/stand-ins.md.
 */
if (! function_exists('wp_kses')) {
    /**
     * Strips HTML tags except the allowed ones.
     *
     * Stand-in: unlike WordPress, it does not filter attributes or URL
     * protocols. Only wp_trigger_error() calls it, on its own messages.
     *
     * @since WM-0.1.0
     * @since WP-1.0.0
     *
     * @param  string  $content  Text content to filter.
     * @param  array<string, array<string, bool>>  $allowed_html  Allowed tags, keyed by tag name.
     * @param  string[]  $allowed_protocols  Ignored by this stand-in.
     * @return string Filtered content.
     */
    function wp_kses($content, $allowed_html, $allowed_protocols = [])
    {
        return strip_tags((string) $content, array_keys((array) $allowed_html));
    }
}
