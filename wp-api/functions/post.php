<?php

/**
 * Post API: reading, saving, trashing and deleting posts, their caches, and
 * the post meta wrappers.
 *
 * WordPress's flow (filters, actions, caches) is kept as it is. Rows are read
 * and written by the house, App\Core\Posts\PostRepository, through Laravel's
 * query builder, one call per query WordPress runs. save_post and deleted_post
 * are Laravel events, bridged to WordPress's actions (CoreServiceProvider).
 * Branches for categories, attachments, thumbnails and page templates are kept;
 * until their steps they answer through stand-ins (docs/stand-ins.md).
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/post.php, post-template.php
 * and revision.php.
 */

use App\Core\Meta\MetaRepository;
use App\Core\Posts\Events\PostDeleted;
use App\Core\Posts\Events\PostSaved;
use App\Core\Posts\PostRepository;
use Illuminate\Database\QueryException;

/**
 * Retrieves post data given a post ID or post object.
 *
 * See sanitize_post() for optional $filter values. Also, the parameter
 * `$post`, must be given as a variable, since it is passed by reference.
 *
 * @since WM-0.5.0
 * @since WP-1.5.1
 *
 * @global WP_Post $post Global post object.
 *
 * @param  int|object|null  $post  Optional. Post ID or post object. `null`, `false`, `0` and other PHP falsey values
 *                                 return the current global post inside the loop. A numerically valid post ID that
 *                                 points to a non-existent post returns `null`. Defaults to global $post.
 * @param  string  $output  Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which
 *                          correspond to a WP_Post object, an associative array, or a numeric array,
 *                          respectively. Default OBJECT.
 * @param  string  $filter  Optional. Type of filter to apply. Accepts 'raw', 'edit', 'db',
 *                          or 'display'. Default 'raw'.
 *
 * @phpstan-param int|numeric-string|WP_Post|null $post
 * @phpstan-param 'OBJECT'|'ARRAY_A'|'ARRAY_N' $output
 * @phpstan-param 'raw'|'edit'|'db'|'display' $filter
 *
 * @return WP_Post|array|null Type corresponding to $output on success or null on failure.
 *                            When $output is OBJECT, a `WP_Post` instance is returned.
 *
 * @phpstan-return (
 *     $output is 'ARRAY_A' ? non-empty-array<string, mixed>|null : (
 *         $output is 'ARRAY_N' ? non-empty-array<int, mixed>|null : (
 *             WP_Post|null
 *         )
 *     )
 * )
 */
function get_post($post = null, $output = OBJECT, $filter = 'raw')
{
    if (empty($post) && isset($GLOBALS['post'])) {
        $post = $GLOBALS['post'];
    }

    if ($post instanceof WP_Post) {
        $_post = $post;
    } elseif (is_object($post)) {
        /** @var stdClass $post */
        if (empty($post->filter)) {
            $_post = sanitize_post($post, 'raw');
            $_post = new WP_Post($_post);
        } elseif ($post->filter === 'raw') {
            $_post = new WP_Post($post);
        } elseif (isset($post->ID)) {
            $_post = WP_Post::get_instance((int) $post->ID);
        } else {
            $_post = null;
        }
    } elseif (is_numeric($post)) {
        $_post = WP_Post::get_instance((int) $post);
    } else {
        $_post = null;
    }

    if (! $_post) {
        return null;
    }

    $_post = $_post->filter($filter);
    if (! $_post) {
        return null;
    }

    if ($output === ARRAY_A) {
        return $_post->to_array();
    } elseif ($output === ARRAY_N) {
        return array_values($_post->to_array());
    }

    return $_post;
}

/**
 * Retrieves data from a post field based on Post ID.
 *
 * Examples of the post field will be, 'post_type', 'post_status', 'post_content',
 * etc and based off of the post object property or key names.
 *
 * The context values are based off of the taxonomy filter functions and
 * supported values are found within those functions.
 *
 * @since WM-0.5.0
 * @since WP-2.3.0
 * @since WP-4.5.0 The `$post` parameter was made optional.
 * @see sanitize_post_field()
 *
 * @param  string  $field  Post field name.
 * @param  int|WP_Post  $post  Optional. Post ID or post object. Defaults to global $post.
 * @param  string  $context  Optional. How to filter the field. Accepts 'raw', 'edit', 'db',
 *                           or 'display'. Default 'display'.
 *
 * @phpstan-param 'raw'|'edit'|'db'|'display' $context
 *
 * @return int|string|int[] The value of the post field on success, empty string on failure.
 *
 * @phpstan-return (
 *     $field is 'ID'|'post_parent'|'menu_order' ? int|'' : (
 *         $field is 'ancestors' ? non-negative-int[]|'' : string
 *     )
 * )
 */
function get_post_field($field, $post = null, $context = 'display')
{
    $post = get_post($post);

    if (! $post) {
        return '';
    }

    if (! isset($post->$field)) {
        return '';
    }

    return sanitize_post_field($field, $post->$field, $post->ID, $context);
}

/**
 * Retrieves the post status based on the post ID.
 *
 * If the post ID is of an attachment, then the parent post status will be given
 * instead.
 *
 * @since WM-0.5.0
 * @since WP-2.0.0
 *
 * @param  int|WP_Post  $post  Optional. Post ID or post object. Defaults to global $post.
 * @return string|false Post status on success, false on failure.
 */
function get_post_status($post = null)
{
    // Normalize the post object if necessary, skip normalization if called from get_sample_permalink().
    if (! $post instanceof WP_Post || ! isset($post->filter) || $post->filter !== 'sample') {
        $post = get_post($post);
    }

    if (! is_object($post)) {
        return false;
    }

    $post_status = $post->post_status;

    if (
        $post->post_type === 'attachment' &&
        $post_status === 'inherit'
    ) {
        if (
            $post->post_parent === 0 ||
            ! get_post($post->post_parent) ||
            $post->ID === $post->post_parent
        ) {
            // Unattached attachments with inherit status are assumed to be published.
            $post_status = 'publish';
        } elseif (get_post_status($post->post_parent) === 'trash') {
            // Get parent status prior to trashing.
            $post_status = get_post_meta($post->post_parent, '_wp_trash_meta_status', true);

            if (! $post_status) {
                // Assume publish as above.
                $post_status = 'publish';
            }
        } else {
            $post_status = get_post_status($post->post_parent);
        }
    } elseif (
        $post->post_type === 'attachment' &&
        ! in_array($post_status, ['private', 'trash', 'auto-draft'], true)
    ) {
        /*
         * Ensure uninherited attachments have a permitted status either 'private', 'trash', 'auto-draft'.
         * This is to match the logic in wp_insert_post().
         *
         * Note: 'inherit' is excluded from this check as it is resolved to the parent post's
         * status in the logic block above.
         */
        $post_status = 'publish';
    }

    /**
     * Filters the post status.
     *
     * @since WP-4.4.0
     * @since WP-5.7.0 The attachment post type is now passed through this filter.
     *
     * @param  string  $post_status  The post status.
     * @param  WP_Post  $post  The post object.
     */
    return apply_filters('get_post_status', $post_status, $post);
}

/**
 * Retrieves the post type of the current post or of a given post.
 *
 * @since WM-0.5.0
 * @since WP-2.1.0
 *
 * @param  int|WP_Post|null  $post  Optional. Post ID or post object. Default is global $post.
 * @return string|false Post type on success, false on failure.
 */
function get_post_type($post = null)
{
    $post = get_post($post);
    if ($post) {
        return $post->post_type;
    }

    return false;
}

/**
 * Returns the ID of the post's parent.
 *
 * @since WM-0.5.0
 * @since WP-3.1.0
 * @since WP-5.9.0 The `$post` parameter was made optional.
 *
 * @param  int|WP_Post|null  $post  Optional. Post ID or post object. Defaults to global $post.
 * @return int|false Post parent ID (which can be 0 if there is no parent),
 *                   or false if the post does not exist.
 */
function wp_get_post_parent_id($post = null)
{
    $post = get_post($post);

    if (! $post || is_wp_error($post)) {
        return false;
    }

    return (int) $post->post_parent;
}

/**
 * Retrieves the IDs of the ancestors of a post.
 *
 * @since WM-0.5.0
 * @since WP-2.5.0
 *
 * @param  int|WP_Post  $post  Post ID or post object.
 * @return int[] Array of ancestor IDs or empty array if there are none.
 *
 * @phpstan-return list<non-negative-int>
 */
function get_post_ancestors($post)
{
    $post = get_post($post);

    if (! $post || empty($post->post_parent) || $post->post_parent === $post->ID) {
        return [];
    }

    $ancestors = [];

    $id = $post->post_parent;
    $ancestors[] = $id;

    while ($ancestor = get_post($id)) {
        // Loop detection: If the ancestor has been seen before, break.
        if (empty($ancestor->post_parent) || $ancestor->post_parent === $post->ID
            || in_array($ancestor->post_parent, $ancestors, true)
        ) {
            break;
        }

        $id = $ancestor->post_parent;
        $ancestors[] = $id;
    }

    return $ancestors;
}

/**
 * Sanitizes every post field.
 *
 * If the context is 'raw', then the post object or array will get minimal
 * sanitization of the integer fields.
 *
 * @since WM-0.5.0
 * @since WP-2.3.0
 * @see sanitize_post_field()
 *
 * @param  object|WP_Post|array  $post  The post object or array
 * @param  string  $context  Optional. How to sanitize post fields.
 *                           Accepts 'raw', 'edit', 'db', 'display',
 *                           'attribute', or 'js'. Default 'display'.
 *
 * @phpstan-param stdClass|WP_Post|array<string, mixed> $post
 * @phpstan-param 'raw'|'edit'|'db'|'display'|'attribute'|'js' $context
 *
 * @return object|WP_Post|array The now sanitized post object or array (will be the
 *                              same type as `$post`).
 *
 * @phpstan-return (
 *     $post is WP_Post ? WP_Post : (
 *         $post is stdClass ? stdClass : array<string, mixed>
 *     )
 * )
 */
function sanitize_post($post, $context = 'display')
{
    if (is_object($post)) {
        // Check if post already filtered for this context.
        if (isset($post->filter) && $context === $post->filter) {
            return $post;
        }
        if (! isset($post->ID)) {
            $post->ID = 0;
        }
        foreach (array_keys(get_object_vars($post)) as $field) {
            $post->$field = sanitize_post_field($field, $post->$field, (int) $post->ID, $context);
        }
        $post->filter = $context;
    } elseif (is_array($post)) {
        // Check if post already filtered for this context.
        if (isset($post['filter']) && $context === $post['filter']) {
            return $post;
        }
        if (! isset($post['ID'])) {
            $post['ID'] = 0;
        }
        foreach (array_keys($post) as $field) {
            $post[$field] = sanitize_post_field($field, $post[$field], (int) $post['ID'], $context);
        }
        $post['filter'] = $context;
    }

    return $post;
}

/**
 * Sanitizes a post field based on context.
 *
 * Possible context values are:  'raw', 'edit', 'db', 'display', 'attribute' and
 * 'js'. The 'display' context is used by default. 'attribute' and 'js' contexts
 * are treated like 'display' when calling filters. The 'sample' value is used
 * for permalink previewing.
 *
 * @since WM-0.5.0
 * @since WP-2.3.0
 * @since WP-4.4.0 Like `sanitize_post()`, `$context` defaults to 'display'.
 *
 * @param  string  $field  The Post Object field name.
 * @param  mixed  $value  The Post Object value.
 * @param  int  $post_id  Post ID.
 * @param  string  $context  Optional. How to sanitize the field. Possible values are 'raw', 'edit', 'db', 'display',
 *                           'attribute' and 'js'. The 'sample' value is used for permalink previewing. Default 'display'.
 *
 * @phpstan-param 'raw'|'edit'|'db'|'display'|'attribute'|'js'|'sample' $context
 *
 * @return mixed Sanitized value.
 *
 * @phpstan-return (
 *     $field is 'ID'|'post_parent'|'menu_order' ? int : (
 *         $field is 'ancestors' ? non-negative-int[] : string
 *     )
 * )
 */
function sanitize_post_field($field, $value, $post_id, $context = 'display')
{
    $int_fields = ['ID', 'post_parent', 'menu_order'];
    if (in_array($field, $int_fields, true)) {
        $value = (int) $value;
    }

    // Fields which contain arrays of integers.
    $array_int_fields = ['ancestors'];
    if (in_array($field, $array_int_fields, true)) {
        $value = array_map('absint', (array) $value);

        return $value;
    }

    if ($context === 'raw') {
        return $value;
    }

    $prefixed = false;
    if (str_contains($field, 'post_')) {
        $prefixed = true;
        $field_no_prefix = str_replace('post_', '', $field);
    }

    if ($context === 'edit') {
        $format_to_edit = ['post_content', 'post_excerpt', 'post_title', 'post_password'];

        if ($prefixed) {

            /**
             * Filters the value of a specific post field to edit.
             *
             * The dynamic portion of the hook name, `$field`, refers to the post
             * field name. Possible filter names include:
             *
             *  - `edit_post_author`
             *  - `edit_post_date`
             *  - `edit_post_date_gmt`
             *  - `edit_post_content`
             *  - `edit_post_title`
             *  - `edit_post_excerpt`
             *  - `edit_post_status`
             *  - `edit_post_password`
             *  - `edit_post_name`
             *  - `edit_post_modified`
             *  - `edit_post_modified_gmt`
             *  - `edit_post_content_filtered`
             *  - `edit_post_parent`
             *  - `edit_post_type`
             *  - `edit_post_mime_type`
             *
             * @since WP-2.3.0
             *
             * @param  mixed  $value  Value of the post field.
             * @param  int  $post_id  Post ID.
             */
            $value = apply_filters("edit_{$field}", $value, $post_id);

            /**
             * Filters the value of a specific post field to edit.
             *
             * Only applied to post fields with a name which is prefixed with `post_`.
             *
             * The dynamic portion of the hook name, `$field_no_prefix`, refers to the
             * post field name minus the `post_` prefix. Possible filter names include:
             *
             *  - `author_edit_pre`
             *  - `date_edit_pre`
             *  - `date_gmt_edit_pre`
             *  - `content_edit_pre`
             *  - `title_edit_pre`
             *  - `excerpt_edit_pre`
             *  - `status_edit_pre`
             *  - `password_edit_pre`
             *  - `name_edit_pre`
             *  - `modified_edit_pre`
             *  - `modified_gmt_edit_pre`
             *  - `content_filtered_edit_pre`
             *  - `parent_edit_pre`
             *  - `type_edit_pre`
             *  - `mime_type_edit_pre`
             *
             * @since WP-2.3.0
             *
             * @param  mixed  $value  Value of the post field.
             * @param  int  $post_id  Post ID.
             */
            $value = apply_filters("{$field_no_prefix}_edit_pre", $value, $post_id);
        } else {
            /**
             * Filters the value of a specific post field to edit.
             *
             * Only applied to post fields not prefixed with `post_`.
             *
             * The dynamic portion of the hook name, `$field`, refers to the
             * post field name. Possible filter names include:
             *
             *  - `edit_post_ID`
             *  - `edit_post_ping_status`
             *  - `edit_post_pinged`
             *  - `edit_post_to_ping`
             *  - `edit_post_comment_count`
             *  - `edit_post_comment_status`
             *  - `edit_post_guid`
             *  - `edit_post_menu_order`
             *
             * @since WP-2.3.0
             *
             * @param  mixed  $value  Value of the post field.
             * @param  int  $post_id  Post ID.
             */
            $value = apply_filters("edit_post_{$field}", $value, $post_id);
        }

        if (in_array($field, $format_to_edit, true)) {
            if ($field === 'post_content') {
                $value = format_to_edit($value, user_can_richedit());
            } else {
                $value = format_to_edit($value);
            }
        } else {
            $value = esc_attr($value);
        }
    } elseif ($context === 'db') {
        if ($prefixed) {

            /**
             * Filters the value of a specific post field before saving.
             *
             * Only applied to post fields with a name which is prefixed with `post_`.
             *
             * The dynamic portion of the hook name, `$field`, refers to the post
             * field name. Possible filter names include:
             *
             *  - `pre_post_author`
             *  - `pre_post_date`
             *  - `pre_post_date_gmt`
             *  - `pre_post_content`
             *  - `pre_post_title`
             *  - `pre_post_excerpt`
             *  - `pre_post_status`
             *  - `pre_post_password`
             *  - `pre_post_name`
             *  - `pre_post_modified`
             *  - `pre_post_modified_gmt`
             *  - `pre_post_content_filtered`
             *  - `pre_post_parent`
             *  - `pre_post_type`
             *  - `pre_post_mime_type`
             *
             * @since WP-2.3.0
             *
             * @param  mixed  $value  Value of the post field.
             */
            $value = apply_filters("pre_{$field}", $value);

            /**
             * Filters the value of a specific field before saving.
             *
             * Only applied to post fields with a name which is prefixed with `post_`.
             *
             * The dynamic portion of the hook name, `$field_no_prefix`, refers to the
             * post field name minus the `post_` prefix. Possible filter names include:
             *
             *  - `author_save_pre`
             *  - `date_save_pre`
             *  - `date_gmt_save_pre`
             *  - `content_save_pre`
             *  - `title_save_pre`
             *  - `excerpt_save_pre`
             *  - `status_save_pre`
             *  - `password_save_pre`
             *  - `name_save_pre`
             *  - `modified_save_pre`
             *  - `modified_gmt_save_pre`
             *  - `content_filtered_save_pre`
             *  - `parent_save_pre`
             *  - `type_save_pre`
             *  - `mime_type_save_pre`
             *
             * @since WP-2.3.0
             *
             * @param  mixed  $value  Value of the post field.
             */
            $value = apply_filters("{$field_no_prefix}_save_pre", $value);
        } else {
            /**
             * Filters the value of a specific field before saving.
             *
             * Only applied to post fields with a name which is prefixed with `post_`.
             *
             * The dynamic portion of the hook name, `$field_no_prefix`, refers to the
             * post field name minus the `post_` prefix. Possible filter names include:
             *
             *  - `pre_post_ID`
             *  - `pre_post_comment_status`
             *  - `pre_post_ping_status`
             *  - `pre_post_to_ping`
             *  - `pre_post_pinged`
             *  - `pre_post_guid`
             *  - `pre_post_menu_order`
             *  - `pre_post_comment_count`
             *
             * @since WP-2.3.0
             *
             * @param  mixed  $value  Value of the post field.
             */
            $value = apply_filters("pre_post_{$field}", $value);

            /**
             * Filters the value of a specific post field before saving.
             *
             * Only applied to post fields with a name which is *not* prefixed with `post_`.
             *
             * The dynamic portion of the hook name, `$field`, refers to the post
             * field name. Possible filter names include:
             *
             *  - `ID_pre`
             *  - `comment_status_pre`
             *  - `ping_status_pre`
             *  - `to_ping_pre`
             *  - `pinged_pre`
             *  - `guid_pre`
             *  - `menu_order_pre`
             *  - `comment_count_pre`
             *
             * @since WP-2.3.0
             *
             * @param  mixed  $value  Value of the post field.
             */
            $value = apply_filters("{$field}_pre", $value);
        }
    } else {

        // Use display filters by default.
        if ($prefixed) {

            /**
             * Filters the value of a specific post field for display.
             *
             * Only applied to post fields with a name which is prefixed with `post_`.
             *
             * The dynamic portion of the hook name, `$field`, refers to the post
             * field name. Possible filter names include:
             *
             *  - `post_author`
             *  - `post_date`
             *  - `post_date_gmt`
             *  - `post_content`
             *  - `post_title`
             *  - `post_excerpt`
             *  - `post_status`
             *  - `post_password`
             *  - `post_name`
             *  - `post_modified`
             *  - `post_modified_gmt`
             *  - `post_content_filtered`
             *  - `post_parent`
             *  - `post_type`
             *  - `post_mime_type`
             *
             * @since WP-2.3.0
             *
             * @param  mixed  $value  Value of the prefixed post field.
             * @param  int  $post_id  Post ID.
             * @param  string  $context  Context for how to sanitize the field.
             *                           Accepts 'raw', 'edit', 'db', 'display',
             *                           'attribute', or 'js'. The 'sample' value is
             *                           used for permalink previewing. Default 'display'.
             */
            $value = apply_filters("{$field}", $value, $post_id, $context);
        } else {
            /**
             * Filters the value of a specific post field for display.
             *
             * Only applied to post fields name which is *not* prefixed with `post_`.
             *
             * The dynamic portion of the hook name, `$field`, refers to the post
             * field name. Possible filter names include:
             *
             *  - `post_ID`
             *  - `post_comment_status`
             *  - `post_ping_status`
             *  - `post_to_ping`
             *  - `post_pinged`
             *  - `post_guid`
             *  - `post_menu_order`
             *  - `post_comment_count`
             *
             * @since WP-2.3.0
             *
             * @param  mixed  $value  Value of the unprefixed post field.
             * @param  int  $post_id  Post ID
             * @param  string  $context  Context for how to sanitize the field.
             *                           Accepts 'raw', 'edit', 'db', 'display',
             *                           'attribute', or 'js'. The 'sample' value is
             *                           used for permalink previewing. Default 'display'.
             */
            $value = apply_filters("post_{$field}", $value, $post_id, $context);
        }

        if ($context === 'attribute') {
            $value = esc_attr($value);
        } elseif ($context === 'js') {
            $value = esc_js($value);
        }
    }

    // Restore the type for integer fields after esc_attr().
    if (in_array($field, $int_fields, true)) {
        $value = (int) $value;
    }

    return $value;
}

/**
 * Inserts or updates a post in the database.
 *
 * If the `$postarr` parameter contains an 'ID', the corresponding post will be updated.
 * If not, a new post will be created using the values provided.
 *
 * You can set the post date manually, by setting the values for 'post_date'
 * and 'post_date_gmt' keys. You can close the comments or open the comments by
 * setting the value for 'comment_status' key.
 *
 * @since WM-0.5.0
 * @since WP-1.0.0
 * @since WP-2.6.0 Added the `$wp_error` parameter to allow a WP_Error to be returned on failure.
 * @since WP-4.2.0 Support was added for encoding emoji in the post title, content, and excerpt.
 * @since WP-4.4.0 A 'meta_input' array can now be passed to `$postarr` to add post meta data.
 * @since WP-5.6.0 Added the `$fire_after_hooks` parameter.
 * @see sanitize_post()
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param  array  $postarr  {
 *                          An array of elements that make up a post to update or insert.
 *
 * @type int $ID                    The post ID. If equal to something other than 0,
 *           the post with that ID will be updated. Default 0.
 * @type int $post_author           The ID of the user who added the post. Default is
 *           the current user ID.
 * @type string $post_date             The date of the post. Default is the current time.
 * @type string $post_date_gmt         The date of the post in the GMT timezone. Default is
 *              the value of `$post_date`.
 * @type string $post_content          The post content. Default empty.
 * @type string $post_content_filtered The filtered post content. Default empty.
 * @type string $post_title            The post title. Default empty.
 * @type string $post_excerpt          The post excerpt. Default empty.
 * @type string $post_status           The post status. Default 'draft'.
 * @type string $post_type             The post type. Default 'post'.
 * @type string $comment_status        Whether the post can accept comments. Accepts 'open' or 'closed'.
 *              Default is the value of 'default_comment_status' option.
 * @type string $ping_status           Whether the post can accept pings. Accepts 'open' or 'closed'.
 *              Default is the value of 'default_ping_status' option.
 * @type string $post_password         The password to access the post. Default empty.
 * @type string $post_name             The post name. Default is the sanitized post title
 *              when creating a new post.
 * @type string $to_ping               Space or carriage return-separated list of URLs to ping.
 *              Default empty.
 * @type string $pinged                Space or carriage return-separated list of URLs that have
 *              been pinged. Default empty.
 * @type int $post_parent           Set this for the post it belongs to, if any. Default 0.
 * @type int $menu_order            The order the post should be displayed in. Default 0.
 * @type string $post_mime_type        The mime type of the post. Default empty.
 * @type string $guid                  Global Unique ID for referencing the post. Default empty.
 * @type int $import_id             The post ID to be used when inserting a new post.
 *           If specified, must not match any existing post ID. Default 0.
 * @type int[] $post_category         Array of category IDs.
 *             Defaults to value of the 'default_category' option.
 * @type array $tags_input            Array of tag names, slugs, or IDs. Default empty.
 * @type array $tax_input             An array of taxonomy terms keyed by their taxonomy name.
 *             If the taxonomy is hierarchical, the term list needs to be
 *             either an array of term IDs or a comma-separated string of IDs.
 *             If the taxonomy is non-hierarchical, the term list can be an array
 *             that contains term names or slugs, or a comma-separated string
 *             of names or slugs. This is because, in hierarchical taxonomy,
 *             child terms can have the same names with different parent terms,
 *             so the only way to connect them is using ID. Default empty.
 * @type array $meta_input            Array of post meta values keyed by their post meta key. Default empty.
 * @type string $page_template         Page template to use.
 *              }
 *
 * @param  bool  $wp_error  Optional. Whether to return a WP_Error on failure. Default false.
 * @param  bool  $fire_after_hooks  Optional. Whether to fire the after insert hooks. Default true.
 * @return int|WP_Error The post ID on success. The value 0 or WP_Error on failure.
 *
 * @phpstan-return (
 *     $wp_error is false ? int : int|WP_Error
 * )
 */
function wp_insert_post($postarr, $wp_error = false, $fire_after_hooks = true)
{
    global $wpdb;

    // Capture original pre-sanitized array for passing into filters.
    $unsanitized_postarr = $postarr;

    $user_id = get_current_user_id();

    $defaults = [
        'post_author' => $user_id,
        'post_content' => '',
        'post_content_filtered' => '',
        'post_title' => '',
        'post_excerpt' => '',
        'post_status' => 'draft',
        'post_type' => 'post',
        'comment_status' => '',
        'ping_status' => '',
        'post_password' => '',
        'to_ping' => '',
        'pinged' => '',
        'post_parent' => 0,
        'menu_order' => 0,
        'guid' => '',
        'import_id' => 0,
        'context' => '',
        'post_date' => '',
        'post_date_gmt' => '',
    ];

    $postarr = wp_parse_args($postarr, $defaults);

    unset($postarr['filter']);

    $postarr = sanitize_post($postarr, 'db');

    // Are we updating or creating?
    $post_id = 0;
    $update = false;
    $guid = $postarr['guid'];

    if (! empty($postarr['ID'])) {
        $update = true;

        // Get the post ID and GUID.
        $post_id = $postarr['ID'];
        $post_before = get_post($post_id);

        if (is_null($post_before)) {
            if ($wp_error) {
                return new WP_Error('invalid_post', __('Invalid post ID.'));
            }

            return 0;
        }

        $guid = get_post_field('guid', $post_id);
        $previous_status = get_post_field('post_status', $post_id);
    } else {
        $previous_status = 'new';
        $post_before = null;
    }

    $post_type = empty($postarr['post_type']) ? 'post' : $postarr['post_type'];

    $post_title = $postarr['post_title'];
    $post_content = $postarr['post_content'];
    $post_excerpt = $postarr['post_excerpt'];

    if (isset($postarr['post_name'])) {
        $post_name = $postarr['post_name'];
    } elseif ($update) {
        // For an update, don't modify the post_name if it wasn't supplied as an argument.
        $post_name = $post_before->post_name;
    }

    $maybe_empty = $post_type !== 'attachment'
        && ! $post_content && ! $post_title && ! $post_excerpt
        && post_type_supports($post_type, 'editor')
        && post_type_supports($post_type, 'title')
        && post_type_supports($post_type, 'excerpt');

    /**
     * Filters whether the post should be considered "empty".
     *
     * The post is considered "empty" if both:
     * 1. The post type supports the title, editor, and excerpt fields
     * 2. The title, editor, and excerpt fields are all empty
     *
     * Returning a truthy value from the filter will effectively short-circuit
     * the new post being inserted and return 0. If $wp_error is true, a WP_Error
     * will be returned instead.
     *
     * @since WP-3.3.0
     *
     * @param  bool  $maybe_empty  Whether the post should be considered "empty".
     * @param  array  $postarr  Array of post data.
     */
    if (apply_filters('wp_insert_post_empty_content', $maybe_empty, $postarr)) {
        if ($wp_error) {
            return new WP_Error('empty_content', __('Content, title, and excerpt are empty.'));
        } else {
            return 0;
        }
    }

    $post_status = empty($postarr['post_status']) ? 'draft' : $postarr['post_status'];

    if ($post_type === 'attachment' && ! in_array($post_status, ['inherit', 'private', 'trash', 'auto-draft'], true)) {
        $post_status = 'inherit';
    }

    if (! empty($postarr['post_category'])) {
        // Filter out empty terms.
        $post_category = array_filter($postarr['post_category']);
    } elseif ($update && ! isset($postarr['post_category'])) {
        $post_category = $post_before->post_category;
    }

    // Make sure we set a valid category.
    if (empty($post_category) || count($post_category) === 0 || ! is_array($post_category)) {
        // 'post' requires at least one category.
        if ($post_type === 'post' && $post_status !== 'auto-draft') {
            $post_category = [get_option('default_category')];
        } else {
            $post_category = [];
        }
    }

    /*
     * Don't allow contributors to set the post slug for pending review posts.
     *
     * For new posts check the primitive capability, for updates check the meta capability.
     */
    if ($post_status === 'pending') {
        $post_type_object = get_post_type_object($post_type);

        if (! $update && $post_type_object && ! current_user_can($post_type_object->cap->publish_posts)) {
            $post_name = '';
        } elseif ($update && ! current_user_can('publish_post', $post_id)) {
            $post_name = '';
        }
    }

    /*
     * Create a valid post name. Drafts and pending posts are allowed to have
     * an empty post name.
     */
    if (empty($post_name)) {
        if (! in_array($post_status, ['draft', 'pending', 'auto-draft'], true)) {
            $post_name = sanitize_title($post_title);
        } else {
            $post_name = '';
        }
    } else {
        // On updates, we need to check to see if it's using the old, fixed sanitization context.
        $check_name = sanitize_title($post_name, '', 'old-save');

        if ($update
            && strtolower(urlencode($post_name)) === $check_name
            && get_post_field('post_name', $post_id) === $check_name
        ) {
            $post_name = $check_name;
        } else { // New post, or slug has changed.
            $post_name = sanitize_title($post_name);
        }
    }

    /*
     * Resolve the post date from any provided post date or post date GMT strings;
     * if none are provided, the date will be set to now.
     */
    $post_date = wp_resolve_post_date($postarr['post_date'], $postarr['post_date_gmt']);

    if (! $post_date) {
        if ($wp_error) {
            return new WP_Error('invalid_date', __('Invalid date.'));
        } else {
            return 0;
        }
    }

    if (empty($postarr['post_date_gmt']) || $postarr['post_date_gmt'] === '0000-00-00 00:00:00') {
        if (! in_array($post_status, get_post_stati(['date_floating' => true]), true)) {
            $post_date_gmt = get_gmt_from_date($post_date);
        } else {
            $post_date_gmt = '0000-00-00 00:00:00';
        }
    } else {
        $post_date_gmt = $postarr['post_date_gmt'];
    }

    if ($update || $post_date === '0000-00-00 00:00:00') {
        $post_modified = current_time('mysql');
        $post_modified_gmt = current_time('mysql', true);
    } else {
        $post_modified = $post_date;
        $post_modified_gmt = $post_date_gmt;
    }

    if ($post_type !== 'attachment') {
        $now = gmdate('Y-m-d H:i:s');

        if ($post_status === 'publish') {
            if (strtotime($post_date_gmt) - strtotime($now) >= MINUTE_IN_SECONDS) {
                $post_status = 'future';
            }
        } elseif ($post_status === 'future') {
            if (strtotime($post_date_gmt) - strtotime($now) < MINUTE_IN_SECONDS) {
                $post_status = 'publish';
            }
        }
    }

    // Comment status.
    if (empty($postarr['comment_status'])) {
        if ($update) {
            $comment_status = 'closed';
        } else {
            $comment_status = get_default_comment_status($post_type);
        }
    } else {
        $comment_status = $postarr['comment_status'];
    }

    // These variables are needed by compact() later.
    $post_content_filtered = $postarr['post_content_filtered'];
    $post_author = $postarr['post_author'] ?? $user_id;
    $ping_status = empty($postarr['ping_status']) ? get_default_comment_status($post_type, 'pingback') : $postarr['ping_status'];
    $to_ping = isset($postarr['to_ping']) ? sanitize_trackback_urls($postarr['to_ping']) : '';
    $pinged = $postarr['pinged'] ?? '';
    $import_id = $postarr['import_id'] ?? 0;

    /*
     * The 'wp_insert_post_parent' filter expects all variables to be present.
     * Previously, these variables would have already been extracted
     */
    if (isset($postarr['menu_order'])) {
        $menu_order = (int) $postarr['menu_order'];
    } else {
        $menu_order = 0;
    }

    $post_password = $postarr['post_password'] ?? '';
    if ($post_status === 'private') {
        $post_password = '';
    }

    if (isset($postarr['post_parent'])) {
        $post_parent = (int) $postarr['post_parent'];
    } else {
        $post_parent = 0;
    }

    $new_postarr = array_merge(
        [
            'ID' => $post_id,
        ],
        compact(array_diff(array_keys($defaults), ['context', 'filter']))
    );

    /**
     * Filters the post parent -- used to check for and prevent hierarchy loops.
     *
     * @since WP-3.1.0
     *
     * @param  int  $post_parent  Post parent ID.
     * @param  int  $post_id  Post ID.
     * @param  array  $new_postarr  Array of parsed post data.
     * @param  array  $postarr  Array of sanitized, but otherwise unmodified post data.
     */
    $post_parent = apply_filters('wp_insert_post_parent', $post_parent, $post_id, $new_postarr, $postarr);

    /*
     * If the post is being untrashed and it has a desired slug stored in post meta,
     * reassign it.
     */
    if ($previous_status === 'trash' && $post_status !== 'trash') {
        $desired_post_slug = get_post_meta($post_id, '_wp_desired_post_slug', true);

        if ($desired_post_slug) {
            delete_post_meta($post_id, '_wp_desired_post_slug');
            $post_name = $desired_post_slug;
        }
    }

    // If a trashed post has the desired slug, change it and let this post have it.
    if ($post_status !== 'trash' && $post_name) {
        /**
         * Filters whether or not to add a `__trashed` suffix to the name of trashed posts that match the name of the updated post.
         *
         * @since WP-5.4.0
         *
         * @param  bool  $add_trashed_suffix  Whether to attempt to add the suffix.
         * @param  string  $post_name  The name of the post being updated.
         * @param  int  $post_id  Post ID.
         */
        $add_trashed_suffix = apply_filters('add_trashed_suffix_to_trashed_posts', true, $post_name, $post_id);

        if ($add_trashed_suffix) {
            wp_add_trashed_suffix_to_post_name_for_trashed_posts($post_name, $post_id);
        }
    }

    // When trashing an existing post, change its slug to allow non-trashed posts to use it.
    if ($post_status === 'trash' && $previous_status !== 'trash' && $previous_status !== 'new') {
        $post_name = wp_add_trashed_suffix_to_post_name_for_post($post_id);
    }

    $post_name = wp_unique_post_slug($post_name, $post_id, $post_status, $post_type, $post_parent);

    // Don't unslash.
    $post_mime_type = $postarr['post_mime_type'] ?? '';

    // Expected_slashed (everything!).
    $data = compact(
        'post_author',
        'post_date',
        'post_date_gmt',
        'post_content',
        'post_content_filtered',
        'post_title',
        'post_excerpt',
        'post_status',
        'post_type',
        'comment_status',
        'ping_status',
        'post_password',
        'post_name',
        'to_ping',
        'pinged',
        'post_modified',
        'post_modified_gmt',
        'post_parent',
        'menu_order',
        'post_mime_type',
        'guid'
    );

    $emoji_fields = ['post_title', 'post_content', 'post_excerpt'];

    foreach ($emoji_fields as $emoji_field) {
        if (isset($data[$emoji_field])) {
            $charset = $wpdb->get_col_charset($wpdb->posts, $emoji_field);

            // The 'utf8' character set is a deprecated alias of 'utf8mb3'. See <https://dev.mysql.com/doc/refman/8.4/en/charset-unicode-utf8.html>.
            if ($charset === 'utf8' || $charset === 'utf8mb3') {
                $data[$emoji_field] = wp_encode_emoji($data[$emoji_field]);
            }
        }
    }

    if ($post_type === 'attachment') {
        /**
         * Filters attachment post data before it is updated in or added to the database.
         *
         * @since WP-3.9.0
         * @since WP-5.4.1 The `$unsanitized_postarr` parameter was added.
         * @since WP-6.0.0 The `$update` parameter was added.
         *
         * @param  array  $data  An array of slashed, sanitized, and processed attachment post data.
         * @param  array  $postarr  An array of slashed and sanitized attachment post data, but not processed.
         * @param  array  $unsanitized_postarr  An array of slashed yet *unsanitized* and unprocessed attachment post data
         *                                      as originally passed to wp_insert_post().
         * @param  bool  $update  Whether this is an existing attachment post being updated.
         */
        $data = apply_filters('wp_insert_attachment_data', $data, $postarr, $unsanitized_postarr, $update);
    } else {
        /**
         * Filters slashed post data just before it is inserted into the database.
         *
         * @since WP-2.7.0
         * @since WP-5.4.1 The `$unsanitized_postarr` parameter was added.
         * @since WP-6.0.0 The `$update` parameter was added.
         *
         * @param  array  $data  An array of slashed, sanitized, and processed post data.
         * @param  array  $postarr  An array of sanitized (and slashed) but otherwise unmodified post data.
         * @param  array  $unsanitized_postarr  An array of slashed yet *unsanitized* and unprocessed post data as
         *                                      originally passed to wp_insert_post().
         * @param  bool  $update  Whether this is an existing post being updated.
         */
        $data = apply_filters('wp_insert_post_data', $data, $postarr, $unsanitized_postarr, $update);
    }

    $data = wp_unslash($data);
    $where = ['ID' => $post_id];

    if ($update) {
        /**
         * Fires immediately before an existing post is updated in the database.
         *
         * @since WP-2.5.0
         *
         * @param  int  $post_id  Post ID.
         * @param  array  $data  Array of unslashed post data.
         */
        do_action('pre_post_update', $post_id, $data);

        // WM: a database error is a Laravel exception; WordPress's false return is
        // rebuilt from it, with the database's message as the error data.
        try {
            $wm_db_result = app(PostRepository::class)->update($data, $where);
            $wm_db_error = '';
        } catch (QueryException $wm_exception) {
            $wm_db_result = false;
            $wm_db_error = $wm_exception->getMessage();
        }

        if ($wm_db_result === false) {
            if ($wp_error) {
                if ($post_type === 'attachment') {
                    $message = __('Could not update attachment in the database.');
                } else {
                    $message = __('Could not update post in the database.');
                }

                return new WP_Error('db_update_error', $message, $wm_db_error);
            } else {
                return 0;
            }
        }
    } else {
        // If there is a suggested ID, use it if not already present.
        if (! empty($import_id)) {
            $import_id = (int) $import_id;

            if (! app(PostRepository::class)->exists((int) $import_id)) {
                $data['ID'] = $import_id;
            }
        }

        /**
         * Fires immediately before a new post is inserted in the database.
         *
         * @since WP-6.9.0
         *
         * @param  array  $data  Array of unslashed post data.
         */
        do_action('pre_post_insert', $data);

        try {
            $wm_insert_id = app(PostRepository::class)->insert($data);
            $wm_db_error = '';
        } catch (QueryException $wm_exception) {
            $wm_insert_id = false;
            $wm_db_error = $wm_exception->getMessage();
        }

        if ($wm_insert_id === false) {
            if ($wp_error) {
                if ($post_type === 'attachment') {
                    $message = __('Could not insert attachment into the database.');
                } else {
                    $message = __('Could not insert post into the database.');
                }

                return new WP_Error('db_insert_error', $message, $wm_db_error);
            } else {
                return 0;
            }
        }

        $post_id = (int) $wm_insert_id;

        // Use the newly generated $post_id.
        $where = ['ID' => $post_id];
    }

    if (empty($data['post_name']) && ! in_array($data['post_status'], ['draft', 'pending', 'auto-draft'], true)) {
        $data['post_name'] = wp_unique_post_slug(sanitize_title($data['post_title'], $post_id), $post_id, $data['post_status'], $post_type, $post_parent);

        app(PostRepository::class)->update(['post_name' => $data['post_name']], $where);
        clean_post_cache($post_id);
    }

    if (is_object_in_taxonomy($post_type, 'category')) {
        wp_set_post_categories($post_id, $post_category);
    }

    if (isset($postarr['tags_input']) && is_object_in_taxonomy($post_type, 'post_tag')) {
        wp_set_post_tags($post_id, $postarr['tags_input']);
    }

    // Add default term for all associated custom taxonomies.
    if ($post_status !== 'auto-draft') {
        foreach (get_object_taxonomies($post_type, 'object') as $taxonomy => $tax_object) {

            if (! empty($tax_object->default_term)) {

                // Filter out empty terms.
                if (isset($postarr['tax_input'][$taxonomy]) && is_array($postarr['tax_input'][$taxonomy])) {
                    $postarr['tax_input'][$taxonomy] = array_filter($postarr['tax_input'][$taxonomy]);
                }

                // Passed custom taxonomy list overwrites the existing list if not empty.
                $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
                if (! empty($terms) && empty($postarr['tax_input'][$taxonomy])) {
                    $postarr['tax_input'][$taxonomy] = $terms;
                }

                if (empty($postarr['tax_input'][$taxonomy])) {
                    $default_term_id = get_option('default_term_'.$taxonomy);
                    if (! empty($default_term_id)) {
                        $postarr['tax_input'][$taxonomy] = [(int) $default_term_id];
                    }
                }
            }
        }
    }

    // New-style support for all custom taxonomies.
    if (! empty($postarr['tax_input'])) {
        foreach ($postarr['tax_input'] as $taxonomy => $tags) {
            $taxonomy_obj = get_taxonomy($taxonomy);

            if (! $taxonomy_obj) {
                /* translators: %s: Taxonomy name. */
                _doing_it_wrong(__FUNCTION__, sprintf(__('Invalid taxonomy: %s.'), $taxonomy), '4.4.0');

                continue;
            }

            // array = hierarchical, string = non-hierarchical.
            if (is_array($tags)) {
                $tags = array_filter($tags);
            }

            if (current_user_can($taxonomy_obj->cap->assign_terms)) {
                wp_set_post_terms($post_id, $tags, $taxonomy);
            }
        }
    }

    if (! empty($postarr['meta_input'])) {
        foreach ($postarr['meta_input'] as $field => $value) {
            update_post_meta($post_id, $field, $value);
        }
    }

    $current_guid = get_post_field('guid', $post_id);

    // Set GUID.
    if (! $update && $current_guid === '') {
        app(PostRepository::class)->update(['guid' => get_permalink($post_id)], $where);
    }

    if ($postarr['post_type'] === 'attachment') {
        if (! empty($postarr['file'])) {
            update_attached_file($post_id, $postarr['file']);
        }

        if (! empty($postarr['context'])) {
            add_post_meta($post_id, '_wp_attachment_context', $postarr['context'], true);
        }
    }

    // Set or remove featured image.
    if (isset($postarr['_thumbnail_id'])) {
        $thumbnail_support = current_theme_supports('post-thumbnails', $post_type) && post_type_supports($post_type, 'thumbnail') || $post_type === 'revision';

        if (! $thumbnail_support && $post_type === 'attachment' && $post_mime_type) {
            if (wp_attachment_is('audio', $post_id)) {
                $thumbnail_support = post_type_supports('attachment:audio', 'thumbnail') || current_theme_supports('post-thumbnails', 'attachment:audio');
            } elseif (wp_attachment_is('video', $post_id)) {
                $thumbnail_support = post_type_supports('attachment:video', 'thumbnail') || current_theme_supports('post-thumbnails', 'attachment:video');
            }
        }

        if ($thumbnail_support) {
            $thumbnail_id = (int) $postarr['_thumbnail_id'];
            if ($thumbnail_id === -1) {
                delete_post_thumbnail($post_id);
            } else {
                set_post_thumbnail($post_id, $thumbnail_id);
            }
        }
    }

    clean_post_cache($post_id);

    $post = get_post($post_id);

    if (! empty($postarr['page_template'])) {
        $post->page_template = $postarr['page_template'];
        $page_templates = wp_get_theme()->get_page_templates($post);

        if ($postarr['page_template'] !== 'default' && ! isset($page_templates[$postarr['page_template']])) {
            if ($wp_error) {
                return new WP_Error('invalid_page_template', __('Invalid page template.'));
            }

            update_post_meta($post_id, '_wp_page_template', 'default');
        } else {
            update_post_meta($post_id, '_wp_page_template', $postarr['page_template']);
        }
    }

    if ($postarr['post_type'] !== 'attachment') {
        wp_transition_post_status($data['post_status'], $previous_status, $post);
    } else {
        if ($update) {
            /**
             * Fires once an existing attachment has been updated.
             *
             * @since WP-2.0.0
             *
             * @param  int  $post_id  Attachment ID.
             */
            do_action('edit_attachment', $post_id);

            $post_after = get_post($post_id);

            /**
             * Fires once an existing attachment has been updated.
             *
             * @since WP-4.4.0
             *
             * @param  int  $post_id  Post ID.
             * @param  WP_Post  $post_after  Post object following the update.
             * @param  WP_Post  $post_before  Post object before the update.
             */
            do_action('attachment_updated', $post_id, $post_after, $post_before);
        } else {

            /**
             * Fires once an attachment has been added.
             *
             * @since WP-2.0.0
             *
             * @param  int  $post_id  Attachment ID.
             */
            do_action('add_attachment', $post_id);
        }

        return $post_id;
    }

    if ($update) {
        /**
         * Fires once an existing post has been updated.
         *
         * The dynamic portion of the hook name, `$post->post_type`, refers to
         * the post type slug.
         *
         * Possible hook names include:
         *
         *  - `edit_post_post`
         *  - `edit_post_page`
         *
         * @since WP-5.1.0
         *
         * @param  int  $post_id  Post ID.
         * @param  WP_Post  $post  Post object.
         */
        do_action("edit_post_{$post->post_type}", $post_id, $post);

        /**
         * Fires once an existing post has been updated.
         *
         * @since WP-1.2.0
         *
         * @param  int  $post_id  Post ID.
         * @param  WP_Post  $post  Post object.
         */
        do_action('edit_post', $post_id, $post);

        $post_after = get_post($post_id);

        /**
         * Fires once an existing post has been updated.
         *
         * @since WP-3.0.0
         *
         * @param  int  $post_id  Post ID.
         * @param  WP_Post  $post_after  Post object following the update.
         * @param  WP_Post  $post_before  Post object before the update.
         */
        do_action('post_updated', $post_id, $post_after, $post_before);
    }

    /**
     * Fires once a post has been saved.
     *
     * The dynamic portion of the hook name, `$post->post_type`, refers to
     * the post type slug.
     *
     * Possible hook names include:
     *
     *  - `save_post_post`
     *  - `save_post_page`
     *
     * @since WP-3.7.0
     *
     * @param  int  $post_id  Post ID.
     * @param  WP_Post  $post  Post object.
     * @param  bool  $update  Whether this is an existing post being updated.
     */
    do_action("save_post_{$post->post_type}", $post_id, $post, $update);

    /**
     * Fires once a post has been saved.
     *
     * @since WP-1.5.0
     *
     * @param  int  $post_id  Post ID.
     * @param  WP_Post  $post  Post object.
     * @param  bool  $update  Whether this is an existing post being updated.
     */
    // WM: announced as the Laravel event PostSaved, which HookBus bridges to this
    // action, so Laravel listeners and plugins both hear it once.
    event(new PostSaved($post_id, $post, $update));

    /**
     * Fires once a post has been saved.
     *
     * @since WP-2.0.0
     *
     * @param  int  $post_id  Post ID.
     * @param  WP_Post  $post  Post object.
     * @param  bool  $update  Whether this is an existing post being updated.
     */
    do_action('wp_insert_post', $post_id, $post, $update);

    if ($fire_after_hooks) {
        wp_after_insert_post($post, $update, $post_before);
    }

    return $post_id;
}

/**
 * Updates a post with new post data.
 *
 * The date does not have to be set for drafts. You can set the date and it will
 * not be overridden.
 *
 * @since WM-0.5.0
 * @since WP-1.0.0
 * @since WP-3.5.0 Added the `$wp_error` parameter to allow a WP_Error to be returned on failure.
 * @since WP-5.6.0 Added the `$fire_after_hooks` parameter.
 *
 * @param  array|object  $postarr  Optional. Post data. Arrays are expected to be escaped,
 *                                 objects are not. See wp_insert_post() for accepted arguments.
 *                                 Default array.
 * @param  bool  $wp_error  Optional. Whether to return a WP_Error on failure. Default false.
 * @param  bool  $fire_after_hooks  Optional. Whether to fire the after insert hooks. Default true.
 * @return int|WP_Error The post ID on success. The value 0 or WP_Error on failure.
 *
 * @phpstan-return (
 *     $wp_error is false ? int : int|WP_Error
 * )
 */
function wp_update_post($postarr = [], $wp_error = false, $fire_after_hooks = true)
{
    if (is_object($postarr)) {
        // Non-escaped post was passed.
        $postarr = get_object_vars($postarr);
        $postarr = wp_slash($postarr);
    }

    // First, get all of the original fields.
    $post = get_post($postarr['ID'], ARRAY_A);

    if (is_null($post)) {
        if ($wp_error) {
            return new WP_Error('invalid_post', __('Invalid post ID.'));
        }

        return 0;
    }

    // Escape data pulled from DB.
    $post = wp_slash($post);

    // Passed post category list overwrites existing category list if not empty.
    if (isset($postarr['post_category']) && is_array($postarr['post_category'])
        && count($postarr['post_category']) > 0
    ) {
        $post_cats = $postarr['post_category'];
    } else {
        $post_cats = $post['post_category'];
    }

    // Drafts shouldn't be assigned a date unless explicitly done so by the user.
    if (isset($post['post_status'])
        && in_array($post['post_status'], ['draft', 'pending', 'auto-draft'], true)
        && empty($postarr['edit_date']) && ($post['post_date_gmt'] === '0000-00-00 00:00:00')
    ) {
        $clear_date = true;
    } else {
        $clear_date = false;
    }

    // Merge old and new fields with new fields overwriting old ones.
    $postarr = array_merge($post, $postarr);
    $postarr['post_category'] = $post_cats;
    if ($clear_date) {
        $postarr['post_date'] = current_time('mysql');
        $postarr['post_date_gmt'] = '';
    }

    if ($postarr['post_type'] === 'attachment') {
        return wp_insert_attachment($postarr, false, 0, $wp_error);
    }

    // Discard 'tags_input' parameter if it's the same as existing post tags.
    if (isset($postarr['tags_input']) && is_object_in_taxonomy($postarr['post_type'], 'post_tag')) {
        $tags = get_the_terms($postarr['ID'], 'post_tag');
        $tag_names = [];

        if ($tags && ! is_wp_error($tags)) {
            $tag_names = wp_list_pluck($tags, 'name');
        }

        if ($postarr['tags_input'] === $tag_names) {
            unset($postarr['tags_input']);
        }
    }

    return wp_insert_post($postarr, $wp_error, $fire_after_hooks);
}

/**
 * Publishes a post by transitioning the post status.
 *
 * @since WM-0.5.0
 * @since WP-2.1.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param  int|WP_Post  $post  Post ID or post object.
 */
function wp_publish_post($post)
{

    $post = get_post($post);

    if (! $post) {
        return;
    }

    if ($post->post_status === 'publish') {
        return;
    }

    $post_before = get_post($post->ID);

    // Ensure at least one term is applied for taxonomies with a default term.
    foreach (get_object_taxonomies($post->post_type, 'object') as $taxonomy => $tax_object) {
        // Skip taxonomy if no default term is set.
        if (
            $taxonomy !== 'category' &&
            empty($tax_object->default_term)
        ) {
            continue;
        }

        // Do not modify previously set terms.
        if (! empty(get_the_terms($post, $taxonomy))) {
            continue;
        }

        if ($taxonomy === 'category') {
            $default_term_id = (int) get_option('default_category', 0);
        } else {
            $default_term_id = (int) get_option('default_term_'.$taxonomy, 0);
        }

        if (! $default_term_id) {
            continue;
        }
        wp_set_post_terms($post->ID, [$default_term_id], $taxonomy);
    }

    app(PostRepository::class)->update(['post_status' => 'publish'], ['ID' => $post->ID]);

    clean_post_cache($post->ID);

    $old_status = $post->post_status;
    $post->post_status = 'publish';
    wp_transition_post_status('publish', $old_status, $post);

    /** This action is documented in wp-includes/post.php */
    do_action("edit_post_{$post->post_type}", $post->ID, $post);

    /** This action is documented in wp-includes/post.php */
    do_action('edit_post', $post->ID, $post);

    /** This action is documented in wp-includes/post.php */
    do_action("save_post_{$post->post_type}", $post->ID, $post, true);

    /** This action is documented in wp-includes/post.php */
    event(new PostSaved($post->ID, $post, true));

    /** This action is documented in wp-includes/post.php */
    do_action('wp_insert_post', $post->ID, $post, true);

    wp_after_insert_post($post, true, $post_before);
}

/**
 * Moves a post or page to the Trash
 *
 * If Trash is disabled, the post or page is permanently deleted.
 *
 * @since WM-0.5.0
 * @since WP-2.9.0
 * @see wp_delete_post()
 *
 * @param  int  $post_id  Optional. Post ID. Default is the ID of the global `$post`
 *                        if `EMPTY_TRASH_DAYS` equals true.
 * @return WP_Post|false|null Post data on success, false or null on failure.
 */
function wp_trash_post($post_id = 0)
{
    if (! EMPTY_TRASH_DAYS) {
        return wp_delete_post($post_id, true);
    }

    $post = get_post($post_id);

    if (! $post) {
        return $post;
    }

    if ($post->post_status === 'trash') {
        return false;
    }

    $previous_status = $post->post_status;

    /**
     * Filters whether a post trashing should take place.
     *
     * @since WP-4.9.0
     * @since WP-6.3.0 Added the `$previous_status` parameter.
     *
     * @param  bool|null  $trash  Whether to go forward with trashing.
     * @param  WP_Post  $post  Post object.
     * @param  string  $previous_status  The status of the post about to be trashed.
     */
    $check = apply_filters('pre_trash_post', null, $post, $previous_status);

    if ($check !== null) {
        return $check;
    }

    /**
     * Fires before a post is sent to the Trash.
     *
     * @since WP-3.3.0
     * @since WP-6.3.0 Added the `$previous_status` parameter.
     *
     * @param  int  $post_id  Post ID.
     * @param  string  $previous_status  The status of the post about to be trashed.
     */
    do_action('wp_trash_post', $post_id, $previous_status);

    add_post_meta($post_id, '_wp_trash_meta_status', $previous_status);
    add_post_meta($post_id, '_wp_trash_meta_time', time());

    $post_updated = wp_update_post(
        [
            'ID' => $post_id,
            'post_status' => 'trash',
        ]
    );

    if (! $post_updated) {
        return false;
    }

    wp_trash_post_comments($post_id);

    /**
     * Fires after a post is sent to the Trash.
     *
     * @since WP-2.9.0
     * @since WP-6.3.0 Added the `$previous_status` parameter.
     *
     * @param  int  $post_id  Post ID.
     * @param  string  $previous_status  The status of the post at the point where it was trashed.
     */
    do_action('trashed_post', $post_id, $previous_status);

    return $post;
}

/**
 * Restores a post from the Trash.
 *
 * @since WM-0.5.0
 * @since WP-2.9.0
 * @since WP-5.6.0 An untrashed post is now returned to 'draft' status by default, except for
 *              attachments which are returned to their original 'inherit' status.
 *
 * @param  int  $post_id  Optional. Post ID. Default is the ID of the global `$post`.
 * @return WP_Post|false|null Post data on success, false or null on failure.
 */
function wp_untrash_post($post_id = 0)
{
    $post = get_post($post_id);

    if (! $post) {
        return $post;
    }

    $post_id = $post->ID;

    if ($post->post_status !== 'trash') {
        return false;
    }

    $previous_status = get_post_meta($post_id, '_wp_trash_meta_status', true);

    /**
     * Filters whether a post untrashing should take place.
     *
     * @since WP-4.9.0
     * @since WP-5.6.0 Added the `$previous_status` parameter.
     *
     * @param  bool|null  $untrash  Whether to go forward with untrashing.
     * @param  WP_Post  $post  Post object.
     * @param  string  $previous_status  The status of the post at the point where it was trashed.
     */
    $check = apply_filters('pre_untrash_post', null, $post, $previous_status);
    if ($check !== null) {
        return $check;
    }

    /**
     * Fires before a post is restored from the Trash.
     *
     * @since WP-2.9.0
     * @since WP-5.6.0 Added the `$previous_status` parameter.
     *
     * @param  int  $post_id  Post ID.
     * @param  string  $previous_status  The status of the post at the point where it was trashed.
     */
    do_action('untrash_post', $post_id, $previous_status);

    $new_status = ($post->post_type === 'attachment') ? 'inherit' : 'draft';

    /**
     * Filters the status that a post gets assigned when it is restored from the trash (untrashed).
     *
     * By default posts that are restored will be assigned a status of 'draft'. Return the value of `$previous_status`
     * in order to assign the status that the post had before it was trashed. The `wp_untrash_post_set_previous_status()`
     * function is available for this.
     *
     * Prior to WordPress 5.6.0, restored posts were always assigned their original status.
     *
     * @since WP-5.6.0
     *
     * @param  string  $new_status  The new status of the post being restored.
     * @param  int  $post_id  The ID of the post being restored.
     * @param  string  $previous_status  The status of the post at the point where it was trashed.
     */
    $post_status = apply_filters('wp_untrash_post_status', $new_status, $post_id, $previous_status);

    delete_post_meta($post_id, '_wp_trash_meta_status');
    delete_post_meta($post_id, '_wp_trash_meta_time');

    $post_updated = wp_update_post(
        [
            'ID' => $post_id,
            'post_status' => $post_status,
        ]
    );

    if (! $post_updated) {
        return false;
    }

    wp_untrash_post_comments($post_id);

    /**
     * Fires after a post is restored from the Trash.
     *
     * @since WP-2.9.0
     * @since WP-5.6.0 Added the `$previous_status` parameter.
     *
     * @param  int  $post_id  Post ID.
     * @param  string  $previous_status  The status of the post at the point where it was trashed.
     */
    do_action('untrashed_post', $post_id, $previous_status);

    return $post;
}

/**
 * Trashes or deletes a post or page.
 *
 * When the post and page is permanently deleted, everything that is tied to
 * it is deleted also. This includes comments, post meta fields, and terms
 * associated with the post.
 *
 * The post or page is moved to Trash instead of permanently deleted unless
 * Trash is disabled, item is already in the Trash, or $force_delete is true.
 *
 * @since WM-0.5.0
 * @since WP-1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @see wp_delete_attachment()
 * @see wp_trash_post()
 *
 * @param  int  $post_id  Post ID. (The default of 0 is for historical reasons; providing it is incorrect.)
 * @param  bool  $force_delete  Optional. Whether to bypass Trash and force deletion.
 *                              Default false.
 * @return WP_Post|false|null Post data on success, false or null on failure.
 */
function wp_delete_post($post_id = 0, $force_delete = false)
{

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        _doing_it_wrong(__FUNCTION__, __('The post ID must be greater than 0.'), '6.9.0');

        return false;
    }

    $post = app(PostRepository::class)->find((int) $post_id);

    if (! $post) {
        return $post;
    }

    $post = get_post($post);

    if (! $force_delete
        && ($post->post_type === 'post' || $post->post_type === 'page')
        && get_post_status($post_id) !== 'trash' && EMPTY_TRASH_DAYS
    ) {
        return wp_trash_post($post_id);
    }

    if ($post->post_type === 'attachment') {
        return wp_delete_attachment($post_id, $force_delete);
    }

    /**
     * Filters whether a post deletion should take place.
     *
     * @since WP-4.4.0
     *
     * @param  WP_Post|false|null  $check  Whether to go forward with deletion. Anything other than null will short-circuit deletion.
     * @param  WP_Post  $post  Post object.
     * @param  bool  $force_delete  Whether to bypass the Trash.
     */
    $check = apply_filters('pre_delete_post', null, $post, $force_delete);
    if ($check !== null) {
        return $check;
    }

    /**
     * Fires before a post is deleted, at the start of wp_delete_post().
     *
     * @since WP-3.2.0
     * @since WP-5.5.0 Added the `$post` parameter.
     * @see wp_delete_post()
     *
     * @param  int  $post_id  Post ID.
     * @param  WP_Post  $post  Post object.
     */
    do_action('before_delete_post', $post_id, $post);

    delete_post_meta($post_id, '_wp_trash_meta_status');
    delete_post_meta($post_id, '_wp_trash_meta_time');

    wp_delete_object_term_relationships($post_id, get_object_taxonomies($post->post_type));

    $parent_data = ['post_parent' => $post->post_parent];
    $parent_where = ['post_parent' => $post_id];

    if (is_post_type_hierarchical($post->post_type)) {
        // Point children of this page to its parent, also clean the cache of affected children.
        $children = app(PostRepository::class)->where(['post_parent' => (int) $post_id, 'post_type' => $post->post_type]);
        if ($children) {
            app(PostRepository::class)->update($parent_data, $parent_where + ['post_type' => $post->post_type]);
        }
    }

    // Do raw query. wp_get_post_revisions() is filtered.
    $revision_ids = app(PostRepository::class)->ids(['post_parent' => (int) $post_id, 'post_type' => 'revision']);

    // Use wp_delete_post (via wp_delete_post_revision) again. Ensures any meta/misplaced data gets cleaned up.
    foreach ($revision_ids as $revision_id) {
        wp_delete_post_revision($revision_id);
    }

    // Point all attachments to this post up one level.
    app(PostRepository::class)->update($parent_data, $parent_where + ['post_type' => 'attachment']);

    wp_defer_comment_counting(true);

    $comment_ids = app(PostRepository::class)->commentIds((int) $post_id);

    foreach ($comment_ids as $comment_id) {
        wp_delete_comment($comment_id, true);
    }

    wp_defer_comment_counting(false);

    $post_meta_ids = app(MetaRepository::class)->ids(_get_meta_table('post'), 'meta_id', ['post_id' => (int) $post_id]);

    foreach ($post_meta_ids as $mid) {
        delete_metadata_by_mid('post', $mid);
    }

    /**
     * Fires immediately before a post is deleted from the database.
     *
     * The dynamic portion of the hook name, `$post->post_type`, refers to
     * the post type slug.
     *
     * @since WP-6.6.0
     *
     * @param  int  $post_id  Post ID.
     * @param  WP_Post  $post  Post object.
     */
    do_action("delete_post_{$post->post_type}", $post_id, $post);

    /**
     * Fires immediately before a post is deleted from the database.
     *
     * @since WP-1.2.0
     * @since WP-5.5.0 Added the `$post` parameter.
     *
     * @param  int  $post_id  Post ID.
     * @param  WP_Post  $post  Post object.
     */
    do_action('delete_post', $post_id, $post);

    $result = app(PostRepository::class)->delete((int) $post_id);
    if (! $result) {
        return false;
    }

    /**
     * Fires immediately after a post is deleted from the database.
     *
     * The dynamic portion of the hook name, `$post->post_type`, refers to
     * the post type slug.
     *
     * @since WP-6.6.0
     *
     * @param  int  $post_id  Post ID.
     * @param  WP_Post  $post  Post object.
     */
    do_action("deleted_post_{$post->post_type}", $post_id, $post);

    /**
     * Fires immediately after a post is deleted from the database.
     *
     * @since WP-2.2.0
     * @since WP-5.5.0 Added the `$post` parameter.
     *
     * @param  int  $post_id  Post ID.
     * @param  WP_Post  $post  Post object.
     */
    // WM: announced as the Laravel event PostDeleted, bridged to this action.
    event(new PostDeleted($post_id, $post));

    clean_post_cache($post);

    if (is_post_type_hierarchical($post->post_type) && $children) {
        foreach ($children as $child) {
            clean_post_cache($child);
        }
    }

    wp_clear_scheduled_hook('publish_future_post', [$post_id]);

    /**
     * Fires after a post is deleted, at the conclusion of wp_delete_post().
     *
     * @since WP-3.2.0
     * @since WP-5.5.0 Added the `$post` parameter.
     * @see wp_delete_post()
     *
     * @param  int  $post_id  Post ID.
     * @param  WP_Post  $post  Post object.
     */
    do_action('after_delete_post', $post_id, $post);

    return $post;
}

/**
 * Adds a suffix if any trashed posts have a given slug.
 *
 * Store its desired (i.e. current) slug so it can try to reclaim it
 * if the post is untrashed.
 *
 * For internal use.
 *
 * @since WM-0.5.0
 * @since WP-4.5.0
 *
 * @param  string  $post_name  Post slug.
 * @param  int  $post_id  Optional. Post ID that should be ignored. Default 0.
 */
function wp_add_trashed_suffix_to_post_name_for_trashed_posts($post_name, $post_id = 0)
{
    // WM: WordPress asks get_posts() (WP_Query, the search step) for trashed
    // posts of any searchable type with this slug; the house answers that one
    // query directly, with the same post types 'any' means to WP_Query.
    $trashed_posts_with_desired_slug = array_map(
        'get_post',
        app(PostRepository::class)->trashedWithSlug(
            (string) $post_name,
            array_values(get_post_types(['exclude_from_search' => false])),
            (int) $post_id
        )
    );

    if (! empty($trashed_posts_with_desired_slug)) {
        foreach ($trashed_posts_with_desired_slug as $_post) {
            wp_add_trashed_suffix_to_post_name_for_post($_post);
        }
    }
}

/**
 * Adds a trashed suffix for a given post.
 *
 * Store its desired (i.e. current) slug so it can try to reclaim it
 * if the post is untrashed.
 *
 * For internal use.
 *
 * @since WM-0.5.0
 * @since WP-4.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param  WP_Post  $post  The post.
 * @return string New slug for the post.
 */
function wp_add_trashed_suffix_to_post_name_for_post($post)
{

    $post = get_post($post);

    if (str_ends_with($post->post_name, '__trashed')) {
        return $post->post_name;
    }
    add_post_meta($post->ID, '_wp_desired_post_slug', $post->post_name);
    $post_name = _truncate_post_slug($post->post_name, 191).'__trashed';
    app(PostRepository::class)->update(['post_name' => $post_name], ['ID' => $post->ID]);
    clean_post_cache($post->ID);

    return $post_name;
}

/**
 * Uses wp_checkdate to return a valid Gregorian-calendar value for post_date.
 * If post_date is not provided, this first checks post_date_gmt if provided,
 * then falls back to use the current time.
 *
 * For back-compat purposes in wp_insert_post, an empty post_date and an invalid
 * post_date_gmt will continue to return '1970-01-01 00:00:00' rather than false.
 *
 * @since WM-0.5.0
 * @since WP-5.7.0
 *
 * @param  string  $post_date  The date in mysql format (`Y-m-d H:i:s`).
 * @param  string  $post_date_gmt  The GMT date in mysql format (`Y-m-d H:i:s`).
 * @return string|false A valid Gregorian-calendar date string, or false on failure.
 */
function wp_resolve_post_date($post_date = '', $post_date_gmt = '')
{
    // If the date is empty, set the date to now.
    if (empty($post_date) || $post_date === '0000-00-00 00:00:00') {
        if (empty($post_date_gmt) || $post_date_gmt === '0000-00-00 00:00:00') {
            $post_date = current_time('mysql');
        } else {
            $post_date = get_date_from_gmt($post_date_gmt);
        }
    }

    // Validate the date.
    preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $post_date, $matches);

    if (empty($matches) || ! is_array($matches) || count($matches) < 4) {
        return false;
    }

    $valid_date = wp_checkdate($matches[2], $matches[3], $matches[1], $post_date);

    if (! $valid_date) {
        return false;
    }

    return $post_date;
}

/**
 * Computes a unique slug for the post, when given the desired slug and some post details.
 *
 * @since WM-0.5.0
 * @since WP-2.8.0
 *
 * @global wpdb       $wpdb       WordPress database abstraction object.
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @param  string  $slug  The desired slug (post_name).
 * @param  int  $post_id  Post ID.
 * @param  string  $post_status  No uniqueness checks are made if the post is still draft or pending.
 * @param  string  $post_type  Post type.
 * @param  int  $post_parent  Post parent ID.
 * @return string Unique slug for the post, based on $post_name (with a -1, -2, etc. suffix)
 */
function wp_unique_post_slug($slug, $post_id, $post_status, $post_type, $post_parent)
{
    if (in_array($post_status, ['draft', 'pending', 'auto-draft'], true)
        || ($post_status === 'inherit' && $post_type === 'revision') || $post_type === 'user_request'
    ) {
        return $slug;
    }

    /**
     * Filters the post slug before it is generated to be unique.
     *
     * Returning a non-null value will short-circuit the
     * unique slug generation, returning the passed value instead.
     *
     * @since WP-5.1.0
     *
     * @param  string|null  $override_slug  Short-circuit return value.
     * @param  string  $slug  The desired slug (post_name).
     * @param  int  $post_id  Post ID.
     * @param  string  $post_status  The post status.
     * @param  string  $post_type  Post type.
     * @param  int  $post_parent  Post parent ID.
     */
    $override_slug = apply_filters('pre_wp_unique_post_slug', null, $slug, $post_id, $post_status, $post_type, $post_parent);
    if ($override_slug !== null) {
        return $override_slug;
    }

    global $wp_rewrite;

    $original_slug = $slug;

    // WM: $wp_rewrite arrives with the rewrite step; until then, WP_Rewrite's
    // default feed names, so a post still can't take a feed's slug.
    $feeds = $wp_rewrite ? $wp_rewrite->feeds : ['feed', 'rdf', 'rss', 'rss2', 'atom'];
    if (! is_array($feeds)) {
        $feeds = [];
    }

    if ($post_type === 'attachment') {
        // Attachment slugs must be unique across all types.
        $post_name_check = app(PostRepository::class)->slugTaken($slug, (int) $post_id);

        /**
         * Filters whether the post slug would make a bad attachment slug.
         *
         * @since WP-3.1.0
         *
         * @param  bool  $bad_slug  Whether the slug would be bad as an attachment slug.
         * @param  string  $slug  The post slug.
         */
        $is_bad_attachment_slug = apply_filters('wp_unique_post_slug_is_bad_attachment_slug', false, $slug);

        if ($post_name_check
            || in_array($slug, $feeds, true) || $slug === 'embed'
            || $is_bad_attachment_slug
        ) {
            $suffix = 2;
            do {
                $alt_post_name = _truncate_post_slug($slug, 200 - (strlen($suffix) + 1))."-$suffix";
                $post_name_check = app(PostRepository::class)->slugTaken($alt_post_name, (int) $post_id);
                $suffix++;
            } while ($post_name_check);
            $slug = $alt_post_name;
        }
    } elseif (is_post_type_hierarchical($post_type)) {
        if ($post_type === 'nav_menu_item') {
            return $slug;
        }

        /*
         * Page slugs must be unique within their own trees. Pages are in a separate
         * namespace than posts so page slugs are allowed to overlap post slugs.
         */
        $post_name_check = app(PostRepository::class)->slugTaken($slug, (int) $post_id, [$post_type, 'attachment'], (int) $post_parent);

        /**
         * Filters whether the post slug would make a bad hierarchical post slug.
         *
         * @since WP-3.1.0
         *
         * @param  bool  $bad_slug  Whether the post slug would be bad in a hierarchical post context.
         * @param  string  $slug  The post slug.
         * @param  string  $post_type  Post type.
         * @param  int  $post_parent  Post parent ID.
         */
        $is_bad_hierarchical_slug = apply_filters('wp_unique_post_slug_is_bad_hierarchical_slug', false, $slug, $post_type, $post_parent);

        if ($post_name_check
            || in_array($slug, $feeds, true) || $slug === 'embed'
            || preg_match('@^('.($wp_rewrite ? $wp_rewrite->pagination_base : 'page').")?\d+$@", $slug)
            || $is_bad_hierarchical_slug
        ) {
            $suffix = 2;
            do {
                $alt_post_name = _truncate_post_slug($slug, 200 - (strlen($suffix) + 1))."-$suffix";
                $post_name_check = app(PostRepository::class)->slugTaken($alt_post_name, (int) $post_id, [$post_type, 'attachment'], (int) $post_parent);
                $suffix++;
            } while ($post_name_check);
            $slug = $alt_post_name;
        }
    } else {
        // Post slugs must be unique across all posts.
        $post_name_check = app(PostRepository::class)->slugTaken($slug, (int) $post_id, [$post_type]);

        $post = get_post($post_id);

        // Prevent new post slugs that could result in URLs that conflict with date archives.
        $conflicts_with_date_archive = false;
        if ($post_type === 'post' && (! $post || $post->post_name !== $slug) && preg_match('/^[0-9]+$/', $slug)) {
            $slug_num = (int) $slug;

            if ($slug_num) {
                $permastructs = array_values(array_filter(explode('/', get_option('permalink_structure'))));
                $postname_index = array_search('%postname%', $permastructs, true);

                /*
                * Potential date clashes are as follows:
                *
                * - Any integer in the first permastruct position could be a year.
                * - An integer between 1 and 12 that follows 'year' conflicts with 'monthnum'.
                * - An integer between 1 and 31 that follows 'monthnum' conflicts with 'day'.
                */
                if ($postname_index === 0 ||
                    ($postname_index && $permastructs[$postname_index - 1] === '%year%' && $slug_num < 13) ||
                    ($postname_index && $permastructs[$postname_index - 1] === '%monthnum%' && $slug_num < 32)
                ) {
                    $conflicts_with_date_archive = true;
                }
            }
        }

        /**
         * Filters whether the post slug would be bad as a flat slug.
         *
         * @since WP-3.1.0
         *
         * @param  bool  $bad_slug  Whether the post slug would be bad as a flat slug.
         * @param  string  $slug  The post slug.
         * @param  string  $post_type  Post type.
         */
        $is_bad_flat_slug = apply_filters('wp_unique_post_slug_is_bad_flat_slug', false, $slug, $post_type);

        if ($post_name_check
            || in_array($slug, $feeds, true) || $slug === 'embed'
            || $conflicts_with_date_archive
            || $is_bad_flat_slug
        ) {
            $suffix = 2;
            do {
                $alt_post_name = _truncate_post_slug($slug, 200 - (strlen($suffix) + 1))."-$suffix";
                $post_name_check = app(PostRepository::class)->slugTaken($alt_post_name, (int) $post_id, [$post_type]);
                $suffix++;
            } while ($post_name_check);
            $slug = $alt_post_name;
        }
    }

    /**
     * Filters the unique post slug.
     *
     * @since WP-3.3.0
     *
     * @param  string  $slug  The post slug.
     * @param  int  $post_id  Post ID.
     * @param  string  $post_status  The post status.
     * @param  string  $post_type  Post type.
     * @param  int  $post_parent  Post parent ID
     * @param  string  $original_slug  The original post slug.
     */
    return apply_filters('wp_unique_post_slug', $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug);
}

/**
 * Truncates a post slug.
 *
 * @since WM-0.5.0
 * @since WP-3.6.0
 * @see utf8_uri_encode()
 *
 * @param  string  $slug  The slug to truncate.
 * @param  int  $length  Optional. Max length of the slug. Default 200 (characters).
 * @return string The truncated slug.
 */
function _truncate_post_slug($slug, $length = 200)
{
    if (strlen($slug) > $length) {
        $decoded_slug = urldecode($slug);
        if ($decoded_slug === $slug) {
            $slug = substr($slug, 0, $length);
        } else {
            $slug = utf8_uri_encode($decoded_slug, $length, true);
        }
    }

    return rtrim($slug, '-');
}

/**
 * Fires actions related to the transitioning of a post's status.
 *
 * When a post is saved, the post status is "transitioned" from one status to another,
 * though this does not always mean the status has actually changed before and after
 * the save. This function fires a number of action hooks related to that transition:
 * the generic {@see 'transition_post_status'} action, as well as the dynamic hooks
 * {@see '$old_status_to_$new_status'} and {@see '$new_status_$post->post_type'}. Note
 * that the function does not transition the post object in the database.
 *
 * For instance: When publishing a post for the first time, the post status may transition
 * from 'draft' – or some other status – to 'publish'. However, if a post is already
 * published and is simply being updated, the "old" and "new" statuses may both be 'publish'
 * before and after the transition.
 *
 * @since WM-0.5.0
 * @since WP-2.3.0
 *
 * @param  string  $new_status  Transition to this post status.
 * @param  string  $old_status  Previous post status.
 * @param  WP_Post  $post  Post data.
 */
function wp_transition_post_status($new_status, $old_status, $post)
{
    /**
     * Fires when a post is transitioned from one status to another.
     *
     * @since WP-2.3.0
     *
     * @param  string  $new_status  New post status.
     * @param  string  $old_status  Old post status.
     * @param  WP_Post  $post  Post object.
     */
    do_action('transition_post_status', $new_status, $old_status, $post);

    $new_status_object = get_post_status_object($new_status);
    $old_status_valid = get_post_status_object($old_status) || $old_status === 'new';
    $post_type_object = get_post_type_object($post->post_type);

    if ($new_status_object && $old_status_valid) {
        /**
         * Fires when a post is transitioned from one status to another.
         *
         * The dynamic portions of the hook name, `$new_status` and `$old_status`,
         * refer to the old and new post statuses, respectively.
         *
         * Possible hook names include:
         *
         *  - `draft_to_publish`
         *  - `publish_to_trash`
         *  - `pending_to_draft`
         *
         * @since WP-2.3.0
         *
         * @param  WP_Post  $post  Post object.
         */
        do_action("{$old_status}_to_{$new_status}", $post);
    }

    if ($new_status_object && $post_type_object) {
        /**
         * Fires when a post is transitioned from one status to another.
         *
         * The dynamic portions of the hook name, `$new_status` and `$post->post_type`,
         * refer to the new post status and post type, respectively.
         *
         * Possible hook names include:
         *
         *  - `draft_post`
         *  - `future_post`
         *  - `pending_post`
         *  - `private_post`
         *  - `publish_post`
         *  - `trash_post`
         *  - `draft_page`
         *  - `future_page`
         *  - `pending_page`
         *  - `private_page`
         *  - `publish_page`
         *  - `trash_page`
         *  - `publish_attachment`
         *  - `trash_attachment`
         *
         * Please note: When this action is hooked using a particular post status (like
         * 'publish', as `publish_{$post->post_type}`), it will fire both when a post is
         * first transitioned to that status from something else, as well as upon
         * subsequent post updates (old and new status are both the same).
         *
         * Therefore, if you are looking to only fire a callback when a post is first
         * transitioned to a status, use the {@see 'transition_post_status'} hook instead.
         *
         * @since WP-2.3.0
         * @since WP-5.9.0 Added `$old_status` parameter.
         *
         * @param  int  $post_id  Post ID.
         * @param  WP_Post  $post  Post object.
         * @param  string  $old_status  Old post status.
         */
        do_action("{$new_status}_{$post->post_type}", $post->ID, $post, $old_status);
    }
}

/**
 * Fires actions after a post, its terms and meta data has been saved.
 *
 * @since WM-0.5.0
 * @since WP-5.6.0
 *
 * @param  int|WP_Post  $post  The post ID or object that has been saved.
 * @param  bool  $update  Whether this is an existing post being updated.
 * @param  null|WP_Post  $post_before  Null for new posts, the WP_Post object prior
 *                                     to the update for updated posts.
 */
function wp_after_insert_post($post, $update, $post_before)
{
    $post = get_post($post);

    if (! $post) {
        return;
    }

    $post_id = $post->ID;

    /**
     * Fires once a post, its terms and meta data has been saved.
     *
     * @since WP-5.6.0
     *
     * @param  int  $post_id  Post ID.
     * @param  WP_Post  $post  Post object.
     * @param  bool  $update  Whether this is an existing post being updated.
     * @param  null|WP_Post  $post_before  Null for new posts, the WP_Post object prior
     *                                     to the update for updated posts.
     */
    do_action('wp_after_insert_post', $post_id, $post, $update, $post_before);
}

/**
 * Will clean the post in the cache.
 *
 * Cleaning means delete from the cache of the post. Will call to clean the term
 * object cache associated with the post ID.
 *
 * This function not run if $_wp_suspend_cache_invalidation is not empty. See
 * wp_suspend_cache_invalidation().
 *
 * @since WM-0.5.0
 * @since WP-2.0.0
 *
 * @global bool $_wp_suspend_cache_invalidation
 *
 * @param  int|WP_Post  $post  Post ID or post object to remove from the cache.
 */
function clean_post_cache($post)
{
    global $_wp_suspend_cache_invalidation;

    if (! empty($_wp_suspend_cache_invalidation)) {
        return;
    }

    $post = get_post($post);

    if (! $post) {
        return;
    }

    wp_cache_delete($post->ID, 'posts');
    wp_cache_delete('post_parent:'.(string) $post->ID, 'posts');
    wp_cache_delete($post->ID, 'post_meta');

    clean_object_term_cache($post->ID, $post->post_type);

    wp_cache_delete('wp_get_archives', 'general');

    /**
     * Fires immediately after the given post's cache is cleaned.
     *
     * @since WP-2.5.0
     *
     * @param  int  $post_id  Post ID.
     * @param  WP_Post  $post  Post object.
     */
    do_action('clean_post_cache', $post->ID, $post);

    if ($post->post_type === 'page') {
        wp_cache_delete('all_page_ids', 'posts');

        /**
         * Fires immediately after the given page's cache is cleaned.
         *
         * @since WP-2.5.0
         *
         * @param  int  $post_id  Post ID.
         */
        do_action('clean_page_cache', $post->ID);
    }

    wp_cache_set_posts_last_changed();
}

/**
 * Updates posts in cache.
 *
 * @since WM-0.5.0
 * @since WP-1.5.1
 *
 * @param  WP_Post[]  $posts  Array of post objects (passed by reference).
 */
function update_post_cache(&$posts)
{
    if (! $posts) {
        return;
    }

    $data = [];
    foreach ($posts as $post) {
        if (empty($post->filter) || $post->filter !== 'raw') {
            $post = sanitize_post($post, 'raw');
        }
        $data[$post->ID] = $post;
    }
    wp_cache_add_multiple($data, 'posts');
}

/**
 * Updates post, term, and metadata caches for a list of post objects.
 *
 * @since WM-0.5.0
 * @since WP-1.5.0
 *
 * @param  WP_Post[]  $posts  Array of post objects (passed by reference).
 * @param  string|string[]  $post_type  Optional. Single post type, 'any', or an array of post types.
 *                                      Default 'post'.
 * @param  bool  $update_term_cache  Optional. Whether to update the term cache. Default true.
 * @param  bool  $update_meta_cache  Optional. Whether to update the meta cache. Default true.
 */
function update_post_caches(&$posts, $post_type = 'post', $update_term_cache = true, $update_meta_cache = true)
{
    // No point in doing all this work if we didn't match any posts.
    if (! $posts) {
        return;
    }

    update_post_cache($posts);

    $post_ids = [];
    foreach ($posts as $post) {
        $post_ids[] = $post->ID;
    }

    if (! $post_type) {
        $post_type = 'any';
    }

    if ($update_term_cache) {
        if (is_array($post_type)) {
            $ptypes = $post_type;
        } elseif ($post_type === 'any') {
            $ptypes = [];
            // Just use the post_types in the supplied posts.
            foreach ($posts as $post) {
                $ptypes[] = $post->post_type;
            }
            $ptypes = array_unique($ptypes);
        } else {
            $ptypes = [$post_type];
        }

        if (! empty($ptypes)) {
            update_object_term_cache($post_ids, $ptypes);
        }
    }

    if ($update_meta_cache) {
        update_postmeta_cache($post_ids);
    }
}

/**
 * Adds any posts from the given IDs to the cache that do not already exist in cache.
 *
 * @since WM-0.5.0
 * @since WP-3.4.0
 * @since WP-6.1.0 This function is no longer marked as "private".
 * @see update_post_cache()
 * @see update_postmeta_cache()
 * @see update_object_term_cache()
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param  int[]  $ids  ID list.
 * @param  bool  $update_term_cache  Optional. Whether to update the term cache. Default true.
 * @param  bool  $update_meta_cache  Optional. Whether to update the meta cache. Default true.
 */
function _prime_post_caches($ids, $update_term_cache = true, $update_meta_cache = true)
{

    $non_cached_ids = _get_non_cached_ids($ids, 'posts');
    if (! empty($non_cached_ids)) {
        $fresh_posts = app(PostRepository::class)->findMany(array_map('intval', $non_cached_ids));

        if ($fresh_posts) {
            // Despite the name, update_post_cache() expects an array rather than a single post.
            update_post_cache($fresh_posts);
        }
    }

    if ($update_meta_cache) {
        update_postmeta_cache($ids);
    }

    if ($update_term_cache) {
        $post_types = array_map('get_post_type', $ids);
        $post_types = array_unique($post_types);
        update_object_term_cache($ids, $post_types);
    }
}

/**
 * Updates metadata cache for a list of post IDs.
 *
 * Performs SQL query to retrieve the metadata for the post IDs and updates the
 * metadata cache for the posts. Therefore, the functions, which call this
 * function, do not need to perform SQL queries on their own.
 *
 * @since WM-0.5.0
 * @since WP-2.1.0
 *
 * @param  int[]  $post_ids  Array of post IDs.
 * @return array|false An array of metadata on success, false if there is nothing to update.
 */
function update_postmeta_cache($post_ids)
{
    return update_meta_cache('post', $post_ids);
}

/**
 * Adds a meta field to the given post.
 *
 * Post meta data is called "Custom Fields" on the Administration Screen.
 *
 * For historical reasons both the meta key and the meta value are expected to be "slashed" (slashes escaped) on input.
 *
 * @since WM-0.5.0
 * @since WP-1.5.0
 *
 * @param  int  $post_id  Post ID.
 * @param  string  $meta_key  Metadata name.
 * @param  mixed  $meta_value  Metadata value. Arrays and objects are stored as serialized data and
 *                             will be returned as the same type when retrieved. Other data types will
 *                             be stored as strings in the database:
 *                             - false is stored and retrieved as an empty string ('')
 *                             - true is stored and retrieved as '1'
 *                             - numbers (both integer and float) are stored and retrieved as strings
 *                             Must be serializable if non-scalar.
 * @param  bool  $unique  Optional. Whether the same key should not be added.
 *                        Default false.
 * @return int|false Meta ID on success, false on failure.
 */
function add_post_meta($post_id, $meta_key, $meta_value, $unique = false)
{
    // Make sure meta is added to the post, not a revision.
    $the_post = wp_is_post_revision($post_id);
    if ($the_post) {
        $post_id = $the_post;
    }

    return add_metadata('post', $post_id, $meta_key, $meta_value, $unique);
}

/**
 * Updates a post meta field based on the given post ID.
 *
 * Use the `$prev_value` parameter to differentiate between meta fields with the
 * same key and post ID.
 *
 * If the meta field for the post does not exist, it will be added and its ID returned.
 *
 * Can be used in place of add_post_meta().
 *
 * For historical reasons both the meta key and the meta value are expected to be "slashed" (slashes escaped) on input.
 *
 * @since WM-0.5.0
 * @since WP-1.5.0
 *
 * @param  int  $post_id  Post ID.
 * @param  string  $meta_key  Metadata key.
 * @param  mixed  $meta_value  Metadata value. Must be serializable if non-scalar.
 * @param  mixed  $prev_value  Optional. Previous value to check before updating.
 *                             If specified, only update existing metadata entries with
 *                             this value. Otherwise, update all entries. Default empty.
 * @return int|bool Meta ID if the key didn't exist, true on successful update,
 *                  false on failure or if the value passed to the function
 *                  is the same as the one that is already in the database.
 */
function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '')
{
    // Make sure meta is updated for the post, not for a revision.
    $the_post = wp_is_post_revision($post_id);
    if ($the_post) {
        $post_id = $the_post;
    }

    return update_metadata('post', $post_id, $meta_key, $meta_value, $prev_value);
}

/**
 * Deletes a post meta field for the given post ID.
 *
 * You can match based on the key, or key and value. Removing based on key and
 * value, will keep from removing duplicate metadata with the same key. It also
 * allows removing all metadata matching the key, if needed.
 *
 * For historical reasons both the meta key and the meta value are expected to be "slashed" (slashes escaped) on input.
 *
 * @since WM-0.5.0
 * @since WP-1.5.0
 *
 * @param  int  $post_id  Post ID.
 * @param  string  $meta_key  Metadata name.
 * @param  mixed  $meta_value  Optional. Metadata value. If provided,
 *                             rows will only be removed that match the value.
 *                             Must be serializable if non-scalar. Default empty.
 * @return bool True on success, false on failure.
 */
function delete_post_meta($post_id, $meta_key, $meta_value = '')
{
    // Make sure meta is deleted from the post, not from a revision.
    $the_post = wp_is_post_revision($post_id);
    if ($the_post) {
        $post_id = $the_post;
    }

    return delete_metadata('post', $post_id, $meta_key, $meta_value);
}

/**
 * Retrieves a post meta field for the given post ID.
 *
 * @since WM-0.5.0
 * @since WP-1.5.0
 *
 * @param  int  $post_id  Post ID.
 * @param  string  $key  Optional. The meta key to retrieve. By default,
 *                       returns data for all keys. Default empty.
 * @param  bool  $single  Optional. Whether to return a single value.
 *                        This parameter has no effect if `$key` is not specified.
 *                        Default false.
 * @return mixed An array of values if `$single` is false.
 *               The value of the meta field if `$single` is true.
 *               False for an invalid `$post_id` (non-numeric, zero, or negative value).
 *               An empty array if a valid but non-existing post ID is passed and `$single` is false.
 *               An empty string if a valid but non-existing post ID is passed and `$single` is true.
 *               Note: Non-serialized values are returned as strings:
 *               - false values are returned as empty strings ('')
 *               - true values are returned as '1'
 *               - numbers (both integer and float) are returned as strings
 *               Arrays and objects retain their original type.
 */
function get_post_meta($post_id, $key = '', $single = false)
{
    return get_metadata('post', $post_id, $key, $single);
}

/**
 * Retrieves post meta fields, based on post ID.
 *
 * The post meta fields are retrieved from the cache where possible,
 * so the function is optimized to be called more than once.
 *
 * @since WM-0.5.0
 * @since WP-1.2.0
 *
 * @param  int  $post_id  Optional. Post ID. Default is the ID of the global `$post`.
 * @return array<string, array<int, string>>|false Array of post meta values keyed by meta key, or false on failure.
 *                                                 Post meta values will always be strings, even for values which would
 *                                                 otherwise be retrieved individually as arrays or objects via
 *                                                 {@see get_post_meta()}. An empty array is returned if the post has
 *                                                 no post meta.
 */
function get_post_custom($post_id = 0)
{
    $post_id = absint($post_id);

    if (! $post_id) {
        $post_id = get_the_ID();
    }

    return get_post_meta($post_id);
}

/**
 * Retrieves meta field names for a post.
 *
 * If there are no meta fields, then nothing (null) will be returned.
 *
 * @since WM-0.5.0
 * @since WP-1.2.0
 *
 * @param  int  $post_id  Optional. Post ID. Default is the ID of the global `$post`.
 * @return array|null Array of the keys, if retrieved.
 */
function get_post_custom_keys($post_id = 0)
{
    $custom = get_post_custom($post_id);

    if (! is_array($custom)) {
        return null;
    }

    $keys = array_keys($custom);
    if ($keys) {
        return $keys;
    }

    return null;
}

/**
 * Retrieves values for a custom post field.
 *
 * The parameters must not be considered optional. All of the post meta fields
 * will be retrieved and only the meta field key values returned.
 *
 * @since WM-0.5.0
 * @since WP-1.2.0
 *
 * @param  string  $key  Optional. Meta field key. Default empty.
 * @param  int  $post_id  Optional. Post ID. Default is the ID of the global `$post`.
 * @return array|null Meta field values.
 */
function get_post_custom_values($key = '', $post_id = 0)
{
    if (! $key) {
        return null;
    }

    $custom = get_post_custom($post_id);

    return $custom[$key] ?? null;
}

/**
 * Registers a meta key for posts.
 *
 * @since WM-0.5.0
 * @since WP-4.9.8
 *
 * @param  string  $post_type  Post type to register a meta key for. Pass an empty string
 *                             to register the meta key across all existing post types.
 * @param  string  $meta_key  The meta key to register.
 * @param  array  $args  Data used to describe the meta key when registered. See
 *                       {@see register_meta()} for a list of supported arguments.
 * @return bool True if the meta key was successfully registered, false if not.
 */
function register_post_meta($post_type, $meta_key, array $args)
{
    $args['object_subtype'] = $post_type;

    return register_meta('post', $meta_key, $args);
}

/**
 * Unregisters a meta key for posts.
 *
 * @since WM-0.5.0
 * @since WP-4.9.8
 *
 * @param  string  $post_type  Post type the meta key is currently registered for. Pass
 *                             an empty string if the meta key is registered across all
 *                             existing post types.
 * @param  string  $meta_key  The meta key to unregister.
 * @return bool True on success, false if the meta key was not previously registered.
 */
function unregister_post_meta($post_type, $meta_key)
{
    return unregister_meta_key('post', $meta_key, $post_type);
}

/**
 * Deletes everything from post meta matching the given meta key.
 *
 * @since WM-0.5.0
 * @since WP-2.3.0
 *
 * @param  string  $post_meta_key  Key to search for when deleting.
 * @return bool Whether the post meta key was deleted from the database.
 */
function delete_post_meta_by_key($post_meta_key)
{
    return delete_metadata('post', null, $post_meta_key, '', true);
}

/**
 * Retrieves the parent post object for the given post.
 *
 * @since WM-0.5.0
 * @since WP-5.7.0
 *
 * @param  int|WP_Post|null  $post  Optional. Post ID or WP_Post object. Default is global $post.
 * @return WP_Post|null Parent post object, or null if there isn't one.
 */
function get_post_parent($post = null)
{
    $wp_post = get_post($post);

    return ! empty($wp_post->post_parent) ? get_post($wp_post->post_parent) : null;
}

/**
 * Returns whether the given post has a parent post.
 *
 * @since WM-0.5.0
 * @since WP-5.7.0
 *
 * @param  int|WP_Post|null  $post  Optional. Post ID or WP_Post object. Default is global $post.
 * @return bool Whether the post has a parent post.
 */
function has_post_parent($post = null)
{
    return (bool) get_post_parent($post);
}

/**
 * Sets the last changed time for the 'posts' cache group.
 *
 * @since WM-0.5.0
 * @since WP-5.0.0
 */
function wp_cache_set_posts_last_changed()
{
    wp_cache_set_last_changed('posts');
}

/**
 * Checks the given subset of the post hierarchy for hierarchy loops.
 *
 * Prevents loops from forming and breaks those that it finds. Attached
 * to the {@see 'wp_insert_post_parent'} filter.
 *
 * @since WM-0.5.0
 * @since WP-3.1.0
 * @see wp_find_hierarchy_loop()
 *
 * @param  int  $post_parent  ID of the parent for the post we're checking.
 * @param  int  $post_id  ID of the post we're checking.
 * @return int The new post_parent for the post, 0 otherwise.
 */
function wp_check_post_hierarchy_for_loops($post_parent, $post_id)
{
    // Nothing fancy here - bail.
    if (! $post_parent) {
        return 0;
    }

    // New post can't cause a loop.
    if (! $post_id) {
        return $post_parent;
    }

    // Can't be its own parent.
    if ($post_parent === $post_id) {
        return 0;
    }

    // Now look for larger loops.
    $loop = wp_find_hierarchy_loop('wp_get_post_parent_id', $post_id, $post_parent);
    if (! $loop) {
        return $post_parent; // No loop.
    }

    // Setting $post_parent to the given value causes a loop.
    if (isset($loop[$post_id])) {
        return 0;
    }

    // There's a loop, but it doesn't contain $post_id. Break the loop.
    foreach (array_keys($loop) as $loop_member) {
        wp_update_post(
            [
                'ID' => $loop_member,
                'post_parent' => 0,
            ]
        );
    }

    return $post_parent;
}

/**
 * Retrieves the ID of the current item in the WordPress Loop.
 *
 * @since WM-0.5.0
 * @since WP-2.1.0
 *
 * @return int|false The ID of the current item in the WordPress Loop. False if $post is not set.
 */
function get_the_ID() // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
{
    $post = get_post();

    return ! empty($post) ? $post->ID : false;
}

/**
 * Determines if the specified post is a revision.
 *
 * @since WM-0.5.0
 * @since WP-2.6.0
 *
 * @param  int|WP_Post  $post  Post ID or post object.
 * @return int|false ID of revision's parent on success, false if not a revision.
 */
function wp_is_post_revision($post)
{
    $post = wp_get_post_revision($post);

    if (! $post) {
        return false;
    }

    return (int) $post->post_parent;
}

/**
 * Gets a post revision.
 *
 * @since WM-0.5.0
 * @since WP-2.6.0
 *
 * @param  int|WP_Post  $post  Post ID or post object.
 * @param  string  $output  Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which
 *                          correspond to a WP_Post object, an associative array, or a numeric array,
 *                          respectively. Default OBJECT.
 * @param  string  $filter  Optional sanitization filter. See sanitize_post(). Default 'raw'.
 *
 * @phpstan-param int|WP_Post $post
 * @phpstan-param 'OBJECT'|'ARRAY_A'|'ARRAY_N' $output
 * @phpstan-param 'raw'|'edit'|'db'|'display' $filter
 *
 * @return WP_Post|array|null WP_Post (or array) on success, or null on failure.
 *
 * @phpstan-return (
 *     $output is 'ARRAY_A' ? non-empty-array<string, mixed>|null : (
 *         $output is 'ARRAY_N' ? non-empty-array<int, mixed>|null : (
 *             WP_Post|null
 *         )
 *     )
 * )
 */
function wp_get_post_revision(&$post, $output = OBJECT, $filter = 'raw')
{
    $revision = get_post($post, OBJECT, $filter);

    if (! $revision) {
        return $revision;
    }

    if ($revision->post_type !== 'revision') {
        return null;
    }

    if ($output === OBJECT) {
        return $revision;
    } elseif ($output === ARRAY_A) {
        /** @var non-empty-array<string, mixed> $_revision */
        $_revision = get_object_vars($revision);

        return $_revision;
    } elseif ($output === ARRAY_N) {
        /** @var non-empty-array<string, mixed> $vars */
        $vars = get_object_vars($revision);
        $_revision = array_values($vars);

        return $_revision;
    }

    return $revision;
}

/**
 * Hook used to schedule publication for a post marked for the future.
 *
 * The $post properties used and must exist are 'ID' and 'post_date_gmt'.
 *
 * @since WM-0.5.0
 * @since WP-2.3.0
 *
 * @param  int  $deprecated  Not used. Can be set to null. Never implemented. Not marked
 *                           as deprecated with _deprecated_argument() as it conflicts with
 *                           wp_transition_post_status() and the default filter for _future_post_hook().
 * @param  WP_Post  $post  Post object.
 */
function _future_post_hook($deprecated, $post)
{
    wp_clear_scheduled_hook('publish_future_post', [$post->ID]);
    wp_schedule_single_event(strtotime(get_gmt_from_date($post->post_date).' GMT'), 'publish_future_post', [$post->ID]);
}
