<?php

/**
 * Single-site stand-ins for WordPress's multisite questions, until multisite
 * is decided (owner's decision B: single site first). Listed in docs/stand-ins.md.
 * On a single site WordPress answers both with true before looking anything up.
 */
if (! function_exists('is_main_site')) {
    /**
     * Determines whether a site is the main site of the current network.
     *
     * Stand-in: single site only, so always true.
     *
     * @since WM-0.2.0
     * @since WP-3.0.0
     *
     * @param  int  $site_id  Optional. Site ID to test. Defaults to current site.
     * @param  int  $network_id  Optional. Network ID of the network to check for.
     * @return bool True if $site_id is the main site of the network, or if not running Multisite.
     */
    function is_main_site($site_id = null, $network_id = null)
    {
        return true;
    }
}

if (! function_exists('is_main_network')) {
    /**
     * Determines whether a network is the main network of the Multisite installation.
     *
     * Stand-in: single site only, so always true.
     *
     * @since WM-0.2.0
     * @since WP-3.7.0
     *
     * @param  int  $network_id  Optional. Network ID to test. Defaults to current network.
     * @return bool True if $network_id is the main network, or if not running Multisite.
     */
    function is_main_network($network_id = null)
    {
        return true;
    }
}
