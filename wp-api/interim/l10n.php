<?php

/**
 * Stand-in for WordPress's early translation loader until the translation
 * step. Listed in docs/stand-ins.md. (`__()` itself is Laravel's for now: see
 * docs/stand-ins.md, "Known clash".)
 */
if (! function_exists('wp_load_translations_early')) {
    /**
     * Loads translations early, before WordPress has fully started.
     *
     * Stand-in: there are no WordPress translations to load yet, so it does nothing.
     *
     * @since WM-0.2.0
     * @since WP-3.4.0
     */
    function wp_load_translations_early()
    {
        // Nothing to load until the translation step.
    }
}
