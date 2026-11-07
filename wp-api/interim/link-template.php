<?php

/**
 * Stand-in for get_permalink() until the rewrite step. Listed in
 * docs/stand-ins.md. It gives WordPress's plain addresses, the ones WordPress
 * itself uses when "pretty permalinks" are off: ?p=123, ?page_id=123, or
 * ?post_type=book&p=123.
 */
if (! function_exists('get_permalink')) {
    /**
     * Retrieves the full permalink for the current post or post ID.
     *
     * Stand-in: plain permalinks only, passed through WordPress's link filters.
     *
     * @since WM-0.5.0
     * @since WP-1.0.0
     *
     * @param  int|WP_Post  $post  Optional. Post ID or post object. Default is the global `$post`.
     * @param  bool  $leavename  Optional. Ignored by this stand-in. Default false.
     * @return string|false The permalink URL. False if the post does not exist.
     */
    function get_permalink($post = 0, $leavename = false)
    {
        $post = get_post($post);

        if (! $post) {
            return false;
        }

        if ($post->post_type === 'page') {
            /** This filter is documented in wp-includes/link-template.php */
            return apply_filters('page_link', home_url('?page_id='.$post->ID), $post->ID, false);
        }

        if ($post->post_type === 'post') {
            /** This filter is documented in wp-includes/link-template.php */
            return apply_filters('post_link', home_url('?p='.$post->ID), $post, $leavename);
        }

        $link = home_url(add_query_arg(['post_type' => $post->post_type, 'p' => $post->ID], ''));

        /** This filter is documented in wp-includes/link-template.php */
        return apply_filters('post_type_link', $link, $post, $leavename, false);
    }
}
