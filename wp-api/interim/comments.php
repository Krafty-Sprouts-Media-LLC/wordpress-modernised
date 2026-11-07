<?php

/**
 * Stand-in for comment counting until the comments step. Listed in
 * docs/stand-ins.md. wp_delete_post() switches deferred counting on and off
 * around deleting a post's comments; with no comments API yet there are no
 * counts to update.
 */
if (! function_exists('wp_defer_comment_counting')) {
    /**
     * Determines whether to defer comment counting.
     *
     * Stand-in: remembers the setting; there are no comment counts to update yet.
     *
     * @since WM-0.5.0
     * @since WP-2.5.0
     *
     * @param  bool  $defer  Optional. Whether to defer comment counting.
     * @return bool Whether comment counting is deferred.
     */
    function wp_defer_comment_counting($defer = null)
    {
        static $_defer = false;

        if (is_bool($defer)) {
            $_defer = $defer;
        }

        return $_defer;
    }
}

if (! function_exists('wp_trash_post_comments')) {
    /**
     * Moves comments for a post to the Trash.
     *
     * Stand-in: there is no comments API yet, so a post has no comments to move.
     *
     * @since WM-0.5.0
     * @since WP-2.9.0
     *
     * @param  int|WP_Post|null  $post  Optional. Post ID or post object. Defaults to global $post.
     * @return mixed|void False on failure.
     */
    function wp_trash_post_comments($post = null)
    {
        return false;
    }
}

if (! function_exists('wp_untrash_post_comments')) {
    /**
     * Restores comments for a post from the Trash.
     *
     * Stand-in: there is no comments API yet, so a post has no comments to restore.
     *
     * @since WM-0.5.0
     * @since WP-2.9.0
     *
     * @param  int|WP_Post|null  $post  Optional. Post ID or post object. Defaults to global $post.
     * @return true|void
     */
    function wp_untrash_post_comments($post = null)
    {
        return true;
    }
}
