<?php

/**
 * The parts of the Comments API posts need now: a post's default comment and
 * ping status. The rest comes with the comments step.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/comment.php.
 */

/**
 * Gets the default comment status for a post type.
 *
 * @since WM-0.5.0
 * @since WP-4.3.0
 *
 * @param  string  $post_type  Optional. Post type. Default 'post'.
 * @param  string  $comment_type  Optional. Comment type. Default 'comment'.
 * @return string Either 'open' or 'closed'.
 */
function get_default_comment_status($post_type = 'post', $comment_type = 'comment')
{
    switch ($comment_type) {
        case 'pingback':
        case 'trackback':
            $supports = 'trackbacks';
            $option = 'ping';
            break;
        default:
            $supports = 'comments';
            $option = 'comment';
            break;
    }

    // Set the status.
    if ($post_type === 'page') {
        $status = 'closed';
    } elseif (post_type_supports($post_type, $supports)) {
        $status = get_option("default_{$option}_status");
    } else {
        $status = 'closed';
    }

    /**
     * Filters the default comment status for the given post type.
     *
     * @since WP-4.3.0
     *
     * @param  string  $status  Default status for the given post type,
     *                          either 'open' or 'closed'.
     * @param  string  $post_type  Post type. Default is `post`.
     * @param  string  $comment_type  Type of comment. Default is `comment`.
     */
    return apply_filters('get_default_comment_status', $status, $post_type, $comment_type);
}
