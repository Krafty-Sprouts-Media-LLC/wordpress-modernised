<?php

/**
 * Post types and post statuses: the kinds of content (post, page, and any a
 * plugin registers) and their states (draft, published, ...).
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/post.php.
 */

/**
 * Creates the initial post types when 'init' action is fired.
 *
 * See {@see 'init'}.
 *
 * @since WM-0.5.0
 * @since WP-2.9.0
 */
function create_initial_post_types()
{
    WP_Post_Type::reset_default_labels();

    register_post_type(
        'post',
        [
            'labels' => [
                'name_admin_bar' => _x('Post', 'add new from admin bar'),
            ],
            'public' => true,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            '_edit_link' => 'post.php?post=%d', /* internal use only. don't use this when registering your own post type. */
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-admin-post',
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'delete_with_user' => true,
            'supports' => [
                'title',
                'editor' => ['notes' => true],
                'author',
                'thumbnail',
                'excerpt',
                'trackbacks',
                'custom-fields',
                'comments',
                'revisions',
                'post-formats',
            ],
            'show_in_rest' => true,
            'rest_base' => 'posts',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ]
    );

    register_post_type(
        'page',
        [
            'labels' => [
                'name_admin_bar' => _x('Page', 'add new from admin bar'),
            ],
            'public' => true,
            'publicly_queryable' => false,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            '_edit_link' => 'post.php?post=%d', /* internal use only. don't use this when registering your own post type. */
            'capability_type' => 'page',
            'map_meta_cap' => true,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-admin-page',
            'hierarchical' => true,
            'rewrite' => false,
            'query_var' => false,
            'delete_with_user' => true,
            'supports' => [
                'title',
                'editor' => ['notes' => true],
                'author',
                'thumbnail',
                'page-attributes',
                'custom-fields',
                'comments',
                'revisions',
            ],
            'show_in_rest' => true,
            'rest_base' => 'pages',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ]
    );

    register_post_type(
        'attachment',
        [
            'labels' => [
                'name' => _x('Media', 'post type general name'),
                'name_admin_bar' => _x('Media', 'add new from admin bar'),
                'add_new' => __('Add Media File'),
                'add_new_item' => __('Add Media File'),
                'edit_item' => __('Edit Media'),
                'view_item' => (get_option('wp_attachment_pages_enabled') === '1') ? __('View Attachment Page') : __('View Media File'),
                'attributes' => __('Attachment Attributes'),
            ],
            'public' => true,
            'show_ui' => true,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            '_edit_link' => 'post.php?post=%d', /* internal use only. don't use this when registering your own post type. */
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'upload_files',
            ],
            'map_meta_cap' => true,
            'menu_icon' => 'dashicons-admin-media',
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'show_in_nav_menus' => false,
            'delete_with_user' => true,
            'supports' => ['title', 'author', 'comments'],
            'show_in_rest' => true,
            'rest_base' => 'media',
            'rest_controller_class' => 'WP_REST_Attachments_Controller',
        ]
    );
    add_post_type_support('attachment:audio', 'thumbnail');
    add_post_type_support('attachment:video', 'thumbnail');

    register_post_type(
        'revision',
        [
            'labels' => [
                'name' => __('Revisions'),
                'singular_name' => __('Revision'),
            ],
            'public' => false,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            '_edit_link' => 'revision.php?revision=%d', /* internal use only. don't use this when registering your own post type. */
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'can_export' => false,
            'delete_with_user' => true,
            'supports' => ['author'],
        ]
    );

    register_post_type(
        'nav_menu_item',
        [
            'labels' => [
                'name' => __('Navigation Menu Items'),
                'singular_name' => __('Navigation Menu Item'),
            ],
            'public' => false,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            'hierarchical' => false,
            'rewrite' => false,
            'delete_with_user' => false,
            'query_var' => false,
            'map_meta_cap' => true,
            'capability_type' => ['edit_theme_options', 'edit_theme_options'],
            'capabilities' => [
                // Meta Capabilities.
                'edit_post' => 'edit_post',
                'read_post' => 'read_post',
                'delete_post' => 'delete_post',
                // Primitive Capabilities.
                'edit_posts' => 'edit_theme_options',
                'edit_others_posts' => 'edit_theme_options',
                'delete_posts' => 'edit_theme_options',
                'publish_posts' => 'edit_theme_options',
                'read_private_posts' => 'edit_theme_options',
                'read' => 'read',
                'delete_private_posts' => 'edit_theme_options',
                'delete_published_posts' => 'edit_theme_options',
                'delete_others_posts' => 'edit_theme_options',
                'edit_private_posts' => 'edit_theme_options',
                'edit_published_posts' => 'edit_theme_options',
            ],
            'show_in_rest' => true,
            'rest_base' => 'menu-items',
            'rest_controller_class' => 'WP_REST_Menu_Items_Controller',
        ]
    );

    register_post_type(
        'custom_css',
        [
            'labels' => [
                'name' => __('Custom CSS'),
                'singular_name' => __('Custom CSS'),
            ],
            'public' => false,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'delete_with_user' => false,
            'can_export' => true,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            'supports' => ['title', 'revisions'],
            'capabilities' => [
                'delete_posts' => 'edit_theme_options',
                'delete_post' => 'edit_theme_options',
                'delete_published_posts' => 'edit_theme_options',
                'delete_private_posts' => 'edit_theme_options',
                'delete_others_posts' => 'edit_theme_options',
                'edit_post' => 'edit_css',
                'edit_posts' => 'edit_css',
                'edit_others_posts' => 'edit_css',
                'edit_published_posts' => 'edit_css',
                'read_post' => 'read',
                'read_private_posts' => 'read',
                'publish_posts' => 'edit_theme_options',
            ],
        ]
    );

    register_post_type(
        'customize_changeset',
        [
            'labels' => [
                'name' => _x('Changesets', 'post type general name'),
                'singular_name' => _x('Changeset', 'post type singular name'),
                'add_new' => __('Add Changeset'),
                'add_new_item' => __('Add Changeset'),
                'new_item' => __('New Changeset'),
                'edit_item' => __('Edit Changeset'),
                'view_item' => __('View Changeset'),
                'all_items' => __('All Changesets'),
                'search_items' => __('Search Changesets'),
                'not_found' => __('No changesets found.'),
                'not_found_in_trash' => __('No changesets found in Trash.'),
            ],
            'public' => false,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            'map_meta_cap' => true,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'can_export' => false,
            'delete_with_user' => false,
            'supports' => ['title', 'author'],
            'capability_type' => 'customize_changeset',
            'capabilities' => [
                'create_posts' => 'customize',
                'delete_others_posts' => 'customize',
                'delete_post' => 'customize',
                'delete_posts' => 'customize',
                'delete_private_posts' => 'customize',
                'delete_published_posts' => 'customize',
                'edit_others_posts' => 'customize',
                'edit_post' => 'customize',
                'edit_posts' => 'customize',
                'edit_private_posts' => 'customize',
                'edit_published_posts' => 'do_not_allow',
                'publish_posts' => 'customize',
                'read' => 'read',
                'read_post' => 'customize',
                'read_private_posts' => 'customize',
            ],
        ]
    );

    register_post_type(
        'oembed_cache',
        [
            'labels' => [
                'name' => __('oEmbed Responses'),
                'singular_name' => __('oEmbed Response'),
            ],
            'public' => false,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'delete_with_user' => false,
            'can_export' => false,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            'supports' => [],
        ]
    );

    register_post_type(
        'user_request',
        [
            'labels' => [
                'name' => __('User Requests'),
                'singular_name' => __('User Request'),
            ],
            'public' => false,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'can_export' => false,
            'delete_with_user' => false,
            'supports' => [],
        ]
    );

    register_post_type(
        'wp_block',
        [
            'labels' => [
                'name' => _x('Patterns', 'post type general name'),
                'singular_name' => _x('Pattern', 'post type singular name'),
                'add_new' => __('Add Pattern'),
                'add_new_item' => __('Add Pattern'),
                'new_item' => __('New Pattern'),
                'edit_item' => __('Edit Block Pattern'),
                'view_item' => __('View Pattern'),
                'view_items' => __('View Patterns'),
                'all_items' => __('All Patterns'),
                'search_items' => __('Search Patterns'),
                'not_found' => __('No patterns found.'),
                'not_found_in_trash' => __('No patterns found in Trash.'),
                'filter_items_list' => __('Filter patterns list'),
                'items_list_navigation' => __('Patterns list navigation'),
                'items_list' => __('Patterns list'),
                'item_published' => __('Pattern published.'),
                'item_published_privately' => __('Pattern published privately.'),
                'item_reverted_to_draft' => __('Pattern reverted to draft.'),
                'item_scheduled' => __('Pattern scheduled.'),
                'item_updated' => __('Pattern updated.'),
            ],
            'public' => false,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            'show_ui' => true,
            'show_in_menu' => false,
            'rewrite' => false,
            'show_in_rest' => true,
            'rest_base' => 'blocks',
            'rest_controller_class' => 'WP_REST_Blocks_Controller',
            'capability_type' => 'block',
            'capabilities' => [
                // You need to be able to edit posts, in order to read blocks in their raw form.
                'read' => 'edit_posts',
                // You need to be able to publish posts, in order to create blocks.
                'create_posts' => 'publish_posts',
                'edit_posts' => 'edit_posts',
                'edit_published_posts' => 'edit_published_posts',
                'delete_published_posts' => 'delete_published_posts',
                // Enables trashing draft posts as well.
                'delete_posts' => 'delete_posts',
                'edit_others_posts' => 'edit_others_posts',
                'delete_others_posts' => 'delete_others_posts',
            ],
            'map_meta_cap' => true,
            'supports' => [
                'title',
                'excerpt',
                'editor',
                'revisions',
                'custom-fields',
            ],
        ]
    );

    $template_edit_link = 'site-editor.php?'.build_query(
        [
            'p' => '/%s/%s',
            'canvas' => 'edit',
        ]
    );

    register_post_type(
        'wp_template',
        [
            'labels' => [
                'name' => _x('Templates', 'post type general name'),
                'singular_name' => _x('Template', 'post type singular name'),
                'add_new' => __('Add Template'),
                'add_new_item' => __('Add Template'),
                'new_item' => __('New Template'),
                'edit_item' => __('Edit Template'),
                'view_item' => __('View Template'),
                'all_items' => __('Templates'),
                'search_items' => __('Search Templates'),
                'parent_item_colon' => __('Parent Template:'),
                'not_found' => __('No templates found.'),
                'not_found_in_trash' => __('No templates found in Trash.'),
                'archives' => __('Template archives'),
                'insert_into_item' => __('Insert into template'),
                'uploaded_to_this_item' => __('Uploaded to this template'),
                'filter_items_list' => __('Filter templates list'),
                'items_list_navigation' => __('Templates list navigation'),
                'items_list' => __('Templates list'),
                'item_updated' => __('Template updated.'),
            ],
            'description' => __('Templates to include in your theme.'),
            'public' => false,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            '_edit_link' => $template_edit_link, /* internal use only. don't use this when registering your own post type. */
            'has_archive' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'rewrite' => false,
            'rest_base' => 'templates',
            'rest_controller_class' => 'WP_REST_Templates_Controller',
            'autosave_rest_controller_class' => 'WP_REST_Template_Autosaves_Controller',
            'revisions_rest_controller_class' => 'WP_REST_Template_Revisions_Controller',
            'late_route_registration' => true,
            'capability_type' => ['template', 'templates'],
            'capabilities' => [
                'create_posts' => 'edit_theme_options',
                'delete_posts' => 'edit_theme_options',
                'delete_others_posts' => 'edit_theme_options',
                'delete_private_posts' => 'edit_theme_options',
                'delete_published_posts' => 'edit_theme_options',
                'edit_posts' => 'edit_theme_options',
                'edit_others_posts' => 'edit_theme_options',
                'edit_private_posts' => 'edit_theme_options',
                'edit_published_posts' => 'edit_theme_options',
                'publish_posts' => 'edit_theme_options',
                'read' => 'edit_theme_options',
                'read_private_posts' => 'edit_theme_options',
            ],
            'map_meta_cap' => true,
            'supports' => [
                'title',
                'slug',
                'excerpt',
                'editor',
                'revisions',
                'author',
            ],
        ]
    );

    register_post_type(
        'wp_template_part',
        [
            'labels' => [
                'name' => _x('Template Parts', 'post type general name'),
                'singular_name' => _x('Template Part', 'post type singular name'),
                'add_new' => __('Add Template Part'),
                'add_new_item' => __('Add Template Part'),
                'new_item' => __('New Template Part'),
                'edit_item' => __('Edit Template Part'),
                'view_item' => __('View Template Part'),
                'all_items' => __('Template Parts'),
                'search_items' => __('Search Template Parts'),
                'parent_item_colon' => __('Parent Template Part:'),
                'not_found' => __('No template parts found.'),
                'not_found_in_trash' => __('No template parts found in Trash.'),
                'archives' => __('Template part archives'),
                'insert_into_item' => __('Insert into template part'),
                'uploaded_to_this_item' => __('Uploaded to this template part'),
                'filter_items_list' => __('Filter template parts list'),
                'items_list_navigation' => __('Template parts list navigation'),
                'items_list' => __('Template parts list'),
                'item_updated' => __('Template part updated.'),
            ],
            'description' => __('Template parts to include in your templates.'),
            'public' => false,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            '_edit_link' => $template_edit_link, /* internal use only. don't use this when registering your own post type. */
            'has_archive' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'rewrite' => false,
            'rest_base' => 'template-parts',
            'rest_controller_class' => 'WP_REST_Templates_Controller',
            'autosave_rest_controller_class' => 'WP_REST_Template_Autosaves_Controller',
            'revisions_rest_controller_class' => 'WP_REST_Template_Revisions_Controller',
            'late_route_registration' => true,
            'map_meta_cap' => true,
            'capabilities' => [
                'create_posts' => 'edit_theme_options',
                'delete_posts' => 'edit_theme_options',
                'delete_others_posts' => 'edit_theme_options',
                'delete_private_posts' => 'edit_theme_options',
                'delete_published_posts' => 'edit_theme_options',
                'edit_posts' => 'edit_theme_options',
                'edit_others_posts' => 'edit_theme_options',
                'edit_private_posts' => 'edit_theme_options',
                'edit_published_posts' => 'edit_theme_options',
                'publish_posts' => 'edit_theme_options',
                'read' => 'edit_theme_options',
                'read_private_posts' => 'edit_theme_options',
            ],
            'supports' => [
                'title',
                'slug',
                'excerpt',
                'editor',
                'revisions',
                'author',
            ],
        ]
    );

    register_post_type(
        'wp_global_styles',
        [
            'label' => _x('Global Styles', 'post type general name'),
            'description' => __('Global styles to include in themes.'),
            'public' => false,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            '_edit_link' => '/site-editor.php?canvas=edit', /* internal use only. don't use this when registering your own post type. */
            'show_ui' => false,
            'show_in_rest' => true,
            'rewrite' => false,
            'rest_base' => 'global-styles',
            'rest_controller_class' => 'WP_REST_Global_Styles_Controller',
            'revisions_rest_controller_class' => 'WP_REST_Global_Styles_Revisions_Controller',
            'late_route_registration' => true,
            'capabilities' => [
                'read' => 'edit_posts',
                'create_posts' => 'edit_theme_options',
                'edit_posts' => 'edit_theme_options',
                'edit_published_posts' => 'edit_theme_options',
                'delete_published_posts' => 'edit_theme_options',
                'edit_others_posts' => 'edit_theme_options',
                'delete_others_posts' => 'edit_theme_options',
            ],
            'map_meta_cap' => true,
            'supports' => [
                'title',
                'editor',
                'revisions',
            ],
        ]
    );
    // Disable autosave endpoints for global styles.
    remove_post_type_support('wp_global_styles', 'autosave');

    $navigation_post_edit_link = 'site-editor.php?'.build_query(
        [
            'p' => '/wp_navigation/%s',
            'canvas' => 'edit',
        ]
    );

    register_post_type(
        'wp_navigation',
        [
            'labels' => [
                'name' => _x('Navigation Menus', 'post type general name'),
                'singular_name' => _x('Navigation Menu', 'post type singular name'),
                'add_new' => __('Add Navigation Menu'),
                'add_new_item' => __('Add Navigation Menu'),
                'new_item' => __('New Navigation Menu'),
                'edit_item' => __('Edit Navigation Menu'),
                'view_item' => __('View Navigation Menu'),
                'all_items' => __('Navigation Menus'),
                'search_items' => __('Search Navigation Menus'),
                'parent_item_colon' => __('Parent Navigation Menu:'),
                'not_found' => __('No Navigation Menu found.'),
                'not_found_in_trash' => __('No Navigation Menu found in Trash.'),
                'archives' => __('Navigation Menu archives'),
                'insert_into_item' => __('Insert into Navigation Menu'),
                'uploaded_to_this_item' => __('Uploaded to this Navigation Menu'),
                'filter_items_list' => __('Filter Navigation Menu list'),
                'items_list_navigation' => __('Navigation Menus list navigation'),
                'items_list' => __('Navigation Menus list'),
                'item_updated' => __('Navigation Menu updated.'),
            ],
            'description' => __('Navigation menus that can be inserted into your site.'),
            'public' => false,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            '_edit_link' => $navigation_post_edit_link, /* internal use only. don't use this when registering your own post type. */
            'has_archive' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'show_in_rest' => true,
            'rewrite' => false,
            'map_meta_cap' => true,
            'capabilities' => [
                'edit_others_posts' => 'edit_theme_options',
                'delete_posts' => 'edit_theme_options',
                'publish_posts' => 'edit_theme_options',
                'create_posts' => 'edit_theme_options',
                'read_private_posts' => 'edit_theme_options',
                'delete_private_posts' => 'edit_theme_options',
                'delete_published_posts' => 'edit_theme_options',
                'delete_others_posts' => 'edit_theme_options',
                'edit_private_posts' => 'edit_theme_options',
                'edit_published_posts' => 'edit_theme_options',
                'edit_posts' => 'edit_theme_options',
            ],
            'rest_base' => 'navigation',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'supports' => [
                'title',
                'editor',
                'revisions',
            ],
        ]
    );

    register_post_type(
        'wp_font_family',
        [
            'labels' => [
                'name' => __('Font Families'),
                'singular_name' => __('Font Family'),
            ],
            'public' => false,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            'hierarchical' => false,
            'capabilities' => [
                'read' => 'edit_theme_options',
                'read_private_posts' => 'edit_theme_options',
                'create_posts' => 'edit_theme_options',
                'publish_posts' => 'edit_theme_options',
                'edit_posts' => 'edit_theme_options',
                'edit_others_posts' => 'edit_theme_options',
                'edit_published_posts' => 'edit_theme_options',
                'delete_posts' => 'edit_theme_options',
                'delete_others_posts' => 'edit_theme_options',
                'delete_published_posts' => 'edit_theme_options',
            ],
            'map_meta_cap' => true,
            'query_var' => false,
            'rewrite' => false,
            'show_in_rest' => true,
            'rest_base' => 'font-families',
            'rest_controller_class' => 'WP_REST_Font_Families_Controller',
            'supports' => ['title'],
        ]
    );

    register_post_type(
        'wp_font_face',
        [
            'labels' => [
                'name' => __('Font Faces'),
                'singular_name' => __('Font Face'),
            ],
            'public' => false,
            '_builtin' => true, /* internal use only. don't use this when registering your own post type. */
            'hierarchical' => false,
            'capabilities' => [
                'read' => 'edit_theme_options',
                'read_private_posts' => 'edit_theme_options',
                'create_posts' => 'edit_theme_options',
                'publish_posts' => 'edit_theme_options',
                'edit_posts' => 'edit_theme_options',
                'edit_others_posts' => 'edit_theme_options',
                'edit_published_posts' => 'edit_theme_options',
                'delete_posts' => 'edit_theme_options',
                'delete_others_posts' => 'edit_theme_options',
                'delete_published_posts' => 'edit_theme_options',
            ],
            'map_meta_cap' => true,
            'query_var' => false,
            'rewrite' => false,
            'show_in_rest' => true,
            'rest_base' => 'font-families/(?P<font_family_id>[\d]+)/font-faces',
            'rest_controller_class' => 'WP_REST_Font_Faces_Controller',
            'supports' => ['title'],
        ]
    );

    register_post_status(
        'publish',
        [
            'label' => _x('Published', 'post status'),
            'public' => true,
            '_builtin' => true, /* internal use only. */
            /* translators: %s: Number of published posts. */
            'label_count' => _n_noop(
                'Published <span class="count">(%s)</span>',
                'Published <span class="count">(%s)</span>'
            ),
        ]
    );

    register_post_status(
        'future',
        [
            'label' => _x('Scheduled', 'post status'),
            'protected' => true,
            '_builtin' => true, /* internal use only. */
            /* translators: %s: Number of scheduled posts. */
            'label_count' => _n_noop(
                'Scheduled <span class="count">(%s)</span>',
                'Scheduled <span class="count">(%s)</span>'
            ),
        ]
    );

    register_post_status(
        'draft',
        [
            'label' => _x('Draft', 'post status'),
            'protected' => true,
            '_builtin' => true, /* internal use only. */
            /* translators: %s: Number of draft posts. */
            'label_count' => _n_noop(
                'Draft <span class="count">(%s)</span>',
                'Drafts <span class="count">(%s)</span>'
            ),
            'date_floating' => true,
        ]
    );

    register_post_status(
        'pending',
        [
            'label' => _x('Pending', 'post status'),
            'protected' => true,
            '_builtin' => true, /* internal use only. */
            /* translators: %s: Number of pending posts. */
            'label_count' => _n_noop(
                'Pending <span class="count">(%s)</span>',
                'Pending <span class="count">(%s)</span>'
            ),
            'date_floating' => true,
        ]
    );

    register_post_status(
        'private',
        [
            'label' => _x('Private', 'post status'),
            'private' => true,
            '_builtin' => true, /* internal use only. */
            /* translators: %s: Number of private posts. */
            'label_count' => _n_noop(
                'Private <span class="count">(%s)</span>',
                'Private <span class="count">(%s)</span>'
            ),
        ]
    );

    register_post_status(
        'trash',
        [
            'label' => _x('Trash', 'post status'),
            'internal' => true,
            '_builtin' => true, /* internal use only. */
            /* translators: %s: Number of trashed posts. */
            'label_count' => _n_noop(
                'Trash <span class="count">(%s)</span>',
                'Trash <span class="count">(%s)</span>'
            ),
            'show_in_admin_status_list' => true,
        ]
    );

    register_post_status(
        'auto-draft',
        [
            'label' => 'auto-draft',
            'internal' => true,
            '_builtin' => true, /* internal use only. */
            'date_floating' => true,
        ]
    );

    register_post_status(
        'inherit',
        [
            'label' => 'inherit',
            'internal' => true,
            '_builtin' => true, /* internal use only. */
            'exclude_from_search' => false,
        ]
    );

    register_post_status(
        'request-pending',
        [
            'label' => _x('Pending', 'request status'),
            'internal' => true,
            '_builtin' => true, /* internal use only. */
            /* translators: %s: Number of pending requests. */
            'label_count' => _n_noop(
                'Pending <span class="count">(%s)</span>',
                'Pending <span class="count">(%s)</span>'
            ),
            'exclude_from_search' => false,
        ]
    );

    register_post_status(
        'request-confirmed',
        [
            'label' => _x('Confirmed', 'request status'),
            'internal' => true,
            '_builtin' => true, /* internal use only. */
            /* translators: %s: Number of confirmed requests. */
            'label_count' => _n_noop(
                'Confirmed <span class="count">(%s)</span>',
                'Confirmed <span class="count">(%s)</span>'
            ),
            'exclude_from_search' => false,
        ]
    );

    register_post_status(
        'request-failed',
        [
            'label' => _x('Failed', 'request status'),
            'internal' => true,
            '_builtin' => true, /* internal use only. */
            /* translators: %s: Number of failed requests. */
            'label_count' => _n_noop(
                'Failed <span class="count">(%s)</span>',
                'Failed <span class="count">(%s)</span>'
            ),
            'exclude_from_search' => false,
        ]
    );

    register_post_status(
        'request-completed',
        [
            'label' => _x('Completed', 'request status'),
            'internal' => true,
            '_builtin' => true, /* internal use only. */
            /* translators: %s: Number of completed requests. */
            'label_count' => _n_noop(
                'Completed <span class="count">(%s)</span>',
                'Completed <span class="count">(%s)</span>'
            ),
            'exclude_from_search' => false,
        ]
    );
}

/**
 * Registers a post status. Do not use before init.
 *
 * A simple function for creating or modifying a post status based on the
 * parameters given. The function will accept an array (second optional
 * parameter), along with a string for the post status name.
 *
 * Arguments prefixed with an _underscore shouldn't be used by plugins and themes.
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 *
 * @global stdClass[] $wp_post_statuses Inserts new post status object into the list
 *
 * @param  string  $post_status  Name of the post status.
 * @param  array|string  $args  {
 *                              Optional. Array or string of post status arguments.
 *
 * @type bool|string $label                     A descriptive name for the post status marked
 *                   for translation. Defaults to value of $post_status.
 * @type array|false $label_count               Nooped plural text from _n_noop() to provide the singular
 *                   and plural forms of the label for counts. Default false
 *                   which means the `$label` argument will be used for both
 *                   the singular and plural forms of this label.
 * @type bool $exclude_from_search       Whether to exclude posts with this post status
 *            from search results. Default is value of $internal.
 * @type bool $_builtin                  Whether the status is built-in. Core-use only.
 *            Default false.
 * @type bool $public                    Whether posts of this status should be shown
 *            in the front end of the site. Default false.
 * @type bool $internal                  Whether the status is for internal use only.
 *            Default false.
 * @type bool $protected                 Whether posts with this status should be protected.
 *            Default false.
 * @type bool $private                   Whether posts with this status should be private.
 *            Default false.
 * @type bool $publicly_queryable        Whether posts with this status should be publicly-
 *            queryable. Default is value of $public.
 * @type bool $show_in_admin_all_list    Whether to include posts in the edit listing for
 *            their post type. Default is the opposite value
 *            of $internal.
 * @type bool $show_in_admin_status_list Show in the list of statuses with post counts at
 *            the top of the edit listings,
 *            e.g. All (12) | Published (9) | My Custom Status (2)
 *            Default is the opposite value of $internal.
 * @type bool $date_floating             Whether the post has a floating creation date.
 *            Default to false.
 *            }
 *
 * @return object
 */
function register_post_status($post_status, $args = [])
{
    global $wp_post_statuses;

    if (! is_array($wp_post_statuses)) {
        $wp_post_statuses = [];
    }

    // Args prefixed with an underscore are reserved for internal use.
    $defaults = [
        'label' => false,
        'label_count' => false,
        'exclude_from_search' => null,
        '_builtin' => false,
        'public' => null,
        'internal' => null,
        'protected' => null,
        'private' => null,
        'publicly_queryable' => null,
        'show_in_admin_status_list' => null,
        'show_in_admin_all_list' => null,
        'date_floating' => null,
    ];
    $args = wp_parse_args($args, $defaults);
    $args = (object) $args;

    $post_status = sanitize_key($post_status);
    $args->name = $post_status;

    // Set various defaults.
    if ($args->public === null && $args->internal === null && $args->protected === null && $args->private === null) {
        $args->internal = true;
    }

    if ($args->public === null) {
        $args->public = false;
    }

    if ($args->private === null) {
        $args->private = false;
    }

    if ($args->protected === null) {
        $args->protected = false;
    }

    if ($args->internal === null) {
        $args->internal = false;
    }

    if ($args->publicly_queryable === null) {
        $args->publicly_queryable = $args->public;
    }

    if ($args->exclude_from_search === null) {
        $args->exclude_from_search = $args->internal;
    }

    if ($args->show_in_admin_all_list === null) {
        $args->show_in_admin_all_list = ! $args->internal;
    }

    if ($args->show_in_admin_status_list === null) {
        $args->show_in_admin_status_list = ! $args->internal;
    }

    if ($args->date_floating === null) {
        $args->date_floating = false;
    }

    if ($args->label === false) {
        $args->label = $post_status;
    }

    if ($args->label_count === false) {
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingular,WordPress.WP.I18n.NonSingularStringLiteralPlural
        $args->label_count = _n_noop($args->label, $args->label);
    }

    $wp_post_statuses[$post_status] = $args;

    return $args;
}

/**
 * Retrieves a post status object by name.
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 * @see register_post_status()
 *
 * @global stdClass[] $wp_post_statuses List of post statuses.
 *
 * @param  string  $post_status  The name of a registered post status.
 * @return stdClass|null A post status object.
 */
function get_post_status_object($post_status)
{
    global $wp_post_statuses;

    if (! is_string($post_status) || empty($wp_post_statuses[$post_status])) {
        return null;
    }

    return $wp_post_statuses[$post_status];
}

/**
 * Gets a list of post statuses.
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 * @see register_post_status()
 *
 * @global stdClass[] $wp_post_statuses List of post statuses.
 *
 * @param  array|string  $args  Optional. Array or string of post status arguments to compare against
 *                              properties of the global `$wp_post_statuses objects`. Default empty array.
 * @param  string  $output  Optional. The type of output to return, either 'names' or 'objects'. Default 'names'.
 * @param  string  $operator  Optional. The logical operation to perform. 'or' means only one element
 *                            from the array needs to match; 'and' means all elements must match.
 *                            Default 'and'.
 * @return string[]|stdClass[] A list of post status names or objects.
 */
function get_post_stati($args = [], $output = 'names', $operator = 'and')
{
    global $wp_post_statuses;

    $field = ($output === 'names') ? 'name' : false;

    return wp_filter_object_list($wp_post_statuses, $args, $operator, $field);
}

/**
 * Determines whether the post type is hierarchical.
 *
 * A false return value might also mean that the post type does not exist.
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 * @see get_post_type_object()
 *
 * @param  string  $post_type  Post type name
 * @return bool Whether post type is hierarchical.
 */
function is_post_type_hierarchical($post_type)
{
    if (! post_type_exists($post_type)) {
        return false;
    }

    $post_type = get_post_type_object($post_type);

    return $post_type->hierarchical;
}

/**
 * Determines whether a post type is registered.
 *
 * For more information on this and similar theme functions, check out
 * the {@link https://developer.wordpress.org/themes/basics/conditional-tags/
 * Conditional Tags} article in the Theme Developer Handbook.
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 * @see get_post_type_object()
 *
 * @param  string  $post_type  Post type name.
 * @return bool Whether post type is registered.
 */
function post_type_exists($post_type)
{
    return (bool) get_post_type_object($post_type);
}

/**
 * Retrieves a post type object by name.
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 * @since WP-4.6.0 Object returned is now an instance of `WP_Post_Type`.
 * @see register_post_type()
 *
 * @global array $wp_post_types List of post types.
 *
 * @param  string  $post_type  The name of a registered post type.
 * @return WP_Post_Type|null WP_Post_Type object if it exists, null otherwise.
 */
function get_post_type_object($post_type)
{
    global $wp_post_types;

    if (! is_scalar($post_type) || empty($wp_post_types[$post_type])) {
        return null;
    }

    return $wp_post_types[$post_type];
}

/**
 * Gets a list of all registered post type objects.
 *
 * @since WM-0.5.0
 * @since WP-2.9.0
 * @see register_post_type() for accepted arguments.
 *
 * @global array $wp_post_types List of post types.
 *
 * @param  array|string  $args  Optional. An array of key => value arguments to match against
 *                              the post type objects. Default empty array.
 * @param  string  $output  Optional. The type of output to return. Either 'names'
 *                          or 'objects'. Default 'names'.
 * @param  string  $operator  Optional. The logical operation to perform. 'or' means only one
 *                            element from the array needs to match; 'and' means all elements
 *                            must match; 'not' means no elements may match. Default 'and'.
 * @return string[]|WP_Post_Type[] An array of post type names or objects.
 *
 * @phpstan-return ( $output is 'names' ? string[] : WP_Post_Type[] )
 */
function get_post_types($args = [], $output = 'names', $operator = 'and')
{
    global $wp_post_types;

    $field = ($output === 'names') ? 'name' : false;

    return wp_filter_object_list($wp_post_types, $args, $operator, $field);
}

/**
 * Registers a post type.
 *
 * Note: Post type registrations should not be hooked before the
 * {@see 'init'} action. Also, any taxonomy connections should be
 * registered via the `$taxonomies` argument to ensure consistency
 * when hooks such as {@see 'parse_query'} or {@see 'pre_get_posts'}
 * are used.
 *
 * Post types can support any number of built-in core features such
 * as meta boxes, custom fields, post thumbnails, post statuses,
 * comments, and more. See the `$supports` argument for a complete
 * list of supported features.
 *
 * @since WM-0.5.0
 * @since WP-2.9.0
 * @since WP-3.0.0 The `show_ui` argument is now enforced on the new post screen.
 * @since WP-4.4.0 The `show_ui` argument is now enforced on the post type listing
 *              screen and post editing screen.
 * @since WP-4.6.0 Post type object returned is now an instance of `WP_Post_Type`.
 * @since WP-4.7.0 Introduced `show_in_rest`, `rest_base` and `rest_controller_class`
 *              arguments to register the post type in REST API.
 * @since WP-5.0.0 The `template` and `template_lock` arguments were added.
 * @since WP-5.3.0 The `supports` argument will now accept an array of arguments for a feature.
 * @since WP-5.9.0 The `rest_namespace` argument was added.
 *
 * @global array $wp_post_types List of post types.
 *
 * @param  string  $post_type  Post type key. Must not exceed 20 characters and may only contain
 *                             lowercase alphanumeric characters, dashes, and underscores. See sanitize_key().
 * @param  array|string  $args  {
 *                              Array or string of arguments for registering a post type.
 *
 * @type string $label                           Name of the post type shown in the menu. Usually plural.
 *              Default is value of $labels['name'].
 * @type string[] $labels                          An array of labels for this post type. If not set, post
 *                labels are inherited for non-hierarchical types and page
 *                labels for hierarchical ones. See get_post_type_labels() for a full
 *                list of supported labels.
 * @type string $description                     A short descriptive summary of what the post type is.
 *              Default empty.
 * @type bool $public                          Whether a post type is intended for use publicly either via
 *            the admin interface or by front-end users. While the default
 *            settings of $exclude_from_search, $publicly_queryable, $show_ui,
 *            and $show_in_nav_menus are inherited from $public, each does not
 *            rely on this relationship and controls a very specific intention.
 *            Default false.
 * @type bool $hierarchical                    Whether the post type is hierarchical (e.g. page). Default false.
 * @type bool $exclude_from_search             Whether to exclude posts with this post type from front end search
 *            results. Default is the opposite value of $public.
 * @type bool $publicly_queryable              Whether queries can be performed on the front end for the post type
 *            as part of parse_request(). Endpoints would include:
 *            * ?post_type={post_type_key}
 *            * ?{post_type_key}={single_post_slug}
 *            * ?{post_type_query_var}={single_post_slug}
 *            If not set, the default is inherited from $public.
 * @type bool $show_ui                         Whether to generate and allow a UI for managing this post type in the
 *            admin. Default is value of $public.
 * @type bool|string $show_in_menu                    Where to show the post type in the admin menu. To work, $show_ui
 *                   must be true. If true, the post type is shown in its own top level
 *                   menu. If false, no menu is shown. If a string of an existing top
 *                   level menu ('tools.php' or 'edit.php?post_type=page', for example), the
 *                   post type will be placed as a sub-menu of that.
 *                   Default is value of $show_ui.
 * @type bool $show_in_nav_menus               Makes this post type available for selection in navigation menus.
 *            Default is value of $public.
 * @type bool $show_in_admin_bar               Makes this post type available via the admin bar. Default is value
 *            of $show_in_menu.
 * @type bool $show_in_rest                    Whether to include the post type in the REST API. Set this to true
 *            for the post type to be available in the block editor.
 * @type string $rest_base                       To change the base URL of REST API route. Default is $post_type.
 * @type string $rest_namespace                  To change the namespace URL of REST API route. Default is wp/v2.
 * @type string $rest_controller_class           REST API controller class name. Default is 'WP_REST_Posts_Controller'.
 * @type string|bool $autosave_rest_controller_class  REST API controller class name. Default is 'WP_REST_Autosaves_Controller'.
 * @type string|bool $revisions_rest_controller_class REST API controller class name. Default is 'WP_REST_Revisions_Controller'.
 * @type bool $late_route_registration         A flag to direct the REST API controllers for autosave / revisions
 *            should be registered before/after the post type controller.
 * @type int $menu_position                   The position in the menu order the post type should appear. To work,
 *           $show_in_menu must be true. Default null (at the bottom).
 * @type string $menu_icon                       The URL to the icon to be used for this menu. Pass a base64-encoded
 *              SVG using a data URI, which will be colored to match the color scheme
 *              -- this should begin with 'data:image/svg+xml;base64,'. Pass the name
 *              of a Dashicons helper class to use a font icon, e.g.
 *              'dashicons-chart-pie'. Pass 'none' to leave div.wp-menu-image empty
 *              so an icon can be added via CSS. Defaults to use the posts icon.
 * @type string|array $capability_type                 The string to use to build the read, edit, and delete capabilities.
 *                    May be passed as an array to allow for alternative plurals when using
 *                    this argument as a base to construct the capabilities, e.g.
 *                    array('story', 'stories'). Default 'post'.
 * @type string[] $capabilities                    Array of capabilities for this post type. $capability_type is used
 *                as a base to construct capabilities by default.
 *                See get_post_type_capabilities().
 * @type bool $map_meta_cap                    Whether to use the internal default meta capability handling.
 *            Default false.
 * @type array|false $supports                        Core feature(s) the post type supports. Serves as an alias for calling
 *                   add_post_type_support() directly. Core features include 'title',
 *                   'editor', 'comments', 'revisions', 'trackbacks', 'author', 'excerpt',
 *                   'page-attributes', 'thumbnail', 'custom-fields', and 'post-formats'.
 *                   Additionally, the 'revisions' feature dictates whether the post type
 *                   will store revisions, the 'autosave' feature dictates whether the post type
 *                   will be autosaved, and the 'comments' feature dictates whether the
 *                   comments count will show on the edit screen. For backward compatibility reasons,
 *                   adding 'editor' support implies 'autosave' support too. A feature can also be
 *                   specified as an array of arguments to provide additional information
 *                   about supporting that feature.
 *                   Example: `array( 'my_feature', array( 'field' => 'value' ) )`.
 *                   If false, no features will be added.
 *                   Default is an array containing 'title' and 'editor'.
 * @type callable $register_meta_box_cb            Provide a callback function that sets up the meta boxes for the
 *                edit form. Do remove_meta_box() and add_meta_box() calls in the
 *                callback. Default null.
 * @type string[] $taxonomies                      An array of taxonomy identifiers that will be registered for the
 *                post type. Taxonomies can be registered later with register_taxonomy()
 *                or register_taxonomy_for_object_type().
 *                Default empty array.
 * @type bool|string $has_archive                     Whether there should be post type archives, or if a string, the
 *                   archive slug to use. Will generate the proper rewrite rules if
 *                   $rewrite is enabled. Default false.
 * @type bool|array $rewrite                         {
 *                  Triggers the handling of rewrites for this post type. To prevent rewrite, set to false.
 *                  Defaults to true, using $post_type as slug. To specify rewrite rules, an array can be
 *                  passed with any of these keys:
 * @type string $slug       Customize the permastruct slug. Defaults to $post_type key.
 * @type bool $with_front Whether the permastruct should be prepended with WP_Rewrite::$front.
 *            Default true.
 * @type bool $feeds      Whether the feed permastruct should be built for this post type.
 *            Default is value of $has_archive.
 * @type bool $pages      Whether the permastruct should provide for pagination. Default true.
 * @type int $ep_mask    Endpoint mask to assign. If not specified and permalink_epmask is set,
 *           inherits from $permalink_epmask. If not specified and permalink_epmask
 *           is not set, defaults to EP_PERMALINK.
 *           }
 * @type string|bool $query_var                      Sets the query_var key for this post type. Defaults to $post_type
 *                   key. If false, a post type cannot be loaded at
 *                   ?{query_var}={post_slug}. If specified as a string, the query
 *                   ?{query_var_string}={post_slug} will be valid.
 * @type bool $can_export                     Whether to allow this post type to be exported. Default true.
 * @type bool $delete_with_user               Whether to delete posts of this type when deleting a user.
 *            * If true, posts of this type belonging to the user will be moved
 *            to Trash when the user is deleted.
 *            * If false, posts of this type belonging to the user will *not*
 *            be trashed or deleted.
 *            * If not set (the default), posts are trashed if post type supports
 *            the 'author' feature. Otherwise posts are not trashed or deleted.
 *            Default null.
 * @type array $template                       Array of blocks to use as the default initial state for an editor
 *             session. Each item should be an array containing block name and
 *             optional attributes. Default empty array.
 * @type string|false $template_lock                  Whether the block template should be locked if $template is set.
 *                    * If set to 'all', the user is unable to insert new blocks,
 *                    move existing blocks and delete blocks.
 *                    * If set to 'insert', the user is able to move existing blocks
 *                    but is unable to insert new blocks and delete blocks.
 *                    * If set to 'contentOnly', the user is only able to edit the content
 *                    of existing blocks.
 *                    Default false.
 * @type bool $_builtin                       FOR INTERNAL USE ONLY! True if this post type is a native or
 *            "built-in" post_type. Default false.
 * @type string $_edit_link                     FOR INTERNAL USE ONLY! URL segment to use for edit link of
 *              this post type. Default 'post.php?post=%d'.
 *              }
 *
 * @return WP_Post_Type|WP_Error The registered post type object on success,
 *                               WP_Error object on failure.
 */
function register_post_type($post_type, $args = [])
{
    global $wp_post_types;

    if (! is_array($wp_post_types)) {
        $wp_post_types = [];
    }

    // Sanitize post type name.
    $post_type = sanitize_key($post_type);

    if (empty($post_type) || strlen($post_type) > 20) {
        _doing_it_wrong(__FUNCTION__, __('Post type names must be between 1 and 20 characters in length.'), '4.2.0');

        return new WP_Error('post_type_length_invalid', __('Post type names must be between 1 and 20 characters in length.'));
    }

    $post_type_object = new WP_Post_Type($post_type, $args);
    $post_type_object->add_supports();
    $post_type_object->add_rewrite_rules();
    $post_type_object->register_meta_boxes();

    $wp_post_types[$post_type] = $post_type_object;

    $post_type_object->add_hooks();
    $post_type_object->register_taxonomies();

    /**
     * Fires after a post type is registered.
     *
     * @since WP-3.3.0
     * @since WP-4.6.0 Converted the `$post_type` parameter to accept a WP_Post_Type object.
     *
     * @param  string  $post_type  Post type.
     * @param  WP_Post_Type  $post_type_object  Arguments used to register the post type.
     */
    do_action('registered_post_type', $post_type, $post_type_object);

    /**
     * Fires after a specific post type is registered.
     *
     * The dynamic portion of the filter name, `$post_type`, refers to the post type key.
     *
     * Possible hook names include:
     *
     *  - `registered_post_type_post`
     *  - `registered_post_type_page`
     *
     * @since WP-6.0.0
     *
     * @param  string  $post_type  Post type.
     * @param  WP_Post_Type  $post_type_object  Arguments used to register the post type.
     */
    do_action("registered_post_type_{$post_type}", $post_type, $post_type_object);

    return $post_type_object;
}

/**
 * Unregisters a post type.
 *
 * Cannot be used to unregister built-in post types.
 *
 * @since WM-0.5.0
 * @since WP-4.5.0
 *
 * @global array $wp_post_types List of post types.
 *
 * @param  string  $post_type  Post type to unregister.
 * @return true|WP_Error True on success, WP_Error on failure or if the post type doesn't exist.
 */
function unregister_post_type($post_type)
{
    global $wp_post_types;

    if (! post_type_exists($post_type)) {
        return new WP_Error('invalid_post_type', __('Invalid post type.'));
    }

    $post_type_object = get_post_type_object($post_type);

    // Do not allow unregistering internal post types.
    if ($post_type_object->_builtin) {
        return new WP_Error('invalid_post_type', __('Unregistering a built-in post type is not allowed'));
    }

    $post_type_object->remove_supports();
    $post_type_object->remove_rewrite_rules();
    $post_type_object->unregister_meta_boxes();
    $post_type_object->remove_hooks();
    $post_type_object->unregister_taxonomies();

    unset($wp_post_types[$post_type]);

    /**
     * Fires after a post type was unregistered.
     *
     * @since WP-4.5.0
     *
     * @param  string  $post_type  Post type key.
     */
    do_action('unregistered_post_type', $post_type);

    return true;
}

/**
 * Builds an object with all post type capabilities out of a post type object
 *
 * Post type capabilities use the 'capability_type' argument as a base, if the
 * capability is not set in the 'capabilities' argument array or if the
 * 'capabilities' argument is not supplied.
 *
 * The capability_type argument can optionally be registered as an array, with
 * the first value being singular and the second plural, e.g. array('story, 'stories')
 * Otherwise, an 's' will be added to the value for the plural form. After
 * registration, capability_type will always be a string of the singular value.
 *
 * By default, the following keys are accepted as part of the capabilities array:
 *
 * - edit_post, read_post, and delete_post are meta capabilities, which are then
 *   generally mapped to corresponding primitive capabilities depending on the
 *   context, which would be the post being edited/read/deleted and the user or
 *   role being checked. Thus these capabilities would generally not be granted
 *   directly to users or roles.
 *
 * - edit_posts - Controls whether objects of this post type can be edited.
 * - edit_others_posts - Controls whether objects of this type owned by other users
 *   can be edited. If the post type does not support an author, then this will
 *   behave like edit_posts.
 * - delete_posts - Controls whether objects of this post type can be deleted.
 * - publish_posts - Controls publishing objects of this post type.
 * - read_private_posts - Controls whether private objects can be read.
 * - create_posts - Controls whether objects of this post type can be created.
 *
 * These primitive capabilities are checked in core in various locations.
 * There are also six other primitive capabilities which are not referenced
 * directly in core, except in map_meta_cap(), which takes the three aforementioned
 * meta capabilities and translates them into one or more primitive capabilities
 * that must then be checked against the user or role, depending on the context.
 *
 * - read - Controls whether objects of this post type can be read.
 * - delete_private_posts - Controls whether private objects can be deleted.
 * - delete_published_posts - Controls whether published objects can be deleted.
 * - delete_others_posts - Controls whether objects owned by other users can be
 *   can be deleted. If the post type does not support an author, then this will
 *   behave like delete_posts.
 * - edit_private_posts - Controls whether private objects can be edited.
 * - edit_published_posts - Controls whether published objects can be edited.
 *
 * These additional capabilities are only used in map_meta_cap(). Thus, they are
 * only assigned by default if the post type is registered with the 'map_meta_cap'
 * argument set to true (default is false).
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 * @since WP-5.4.0 'delete_posts' is included in default capabilities.
 * @see register_post_type()
 * @see map_meta_cap()
 *
 * @param  object  $args  Post type registration arguments.
 * @return object {
 *                Object with all the capabilities as member variables.
 *
 * @type string $edit_post              Capability to edit a post.
 * @type string $read_post              Capability to read a post.
 * @type string $delete_post            Capability to delete a post.
 * @type string $edit_posts             Capability to edit posts.
 * @type string $edit_others_posts      Capability to edit others' posts.
 * @type string $delete_posts           Capability to delete posts.
 * @type string $publish_posts          Capability to publish posts.
 * @type string $read_private_posts     Capability to read private posts.
 * @type string $create_posts           Capability to create posts.
 * @type string $read                   Optional. Capability to read a post.
 * @type string $delete_private_posts   Optional. Capability to delete private posts.
 * @type string $delete_published_posts Optional. Capability to delete published posts.
 * @type string $delete_others_posts    Optional. Capability to delete others' posts.
 * @type string $edit_private_posts     Optional. Capability to edit private posts.
 * @type string $edit_published_posts   Optional. Capability to edit published posts.
 *              }
 */
function get_post_type_capabilities($args)
{
    if (! is_array($args->capability_type)) {
        $args->capability_type = [$args->capability_type, $args->capability_type.'s'];
    }

    // Singular base for meta capabilities, plural base for primitive capabilities.
    [$singular_base, $plural_base] = $args->capability_type;

    $default_capabilities = [
        // Meta capabilities.
        'edit_post' => 'edit_'.$singular_base,
        'read_post' => 'read_'.$singular_base,
        'delete_post' => 'delete_'.$singular_base,
        // Primitive capabilities used outside of map_meta_cap():
        'edit_posts' => 'edit_'.$plural_base,
        'edit_others_posts' => 'edit_others_'.$plural_base,
        'delete_posts' => 'delete_'.$plural_base,
        'publish_posts' => 'publish_'.$plural_base,
        'read_private_posts' => 'read_private_'.$plural_base,
    ];

    // Primitive capabilities used within map_meta_cap():
    if ($args->map_meta_cap) {
        $default_capabilities_for_mapping = [
            'read' => 'read',
            'delete_private_posts' => 'delete_private_'.$plural_base,
            'delete_published_posts' => 'delete_published_'.$plural_base,
            'delete_others_posts' => 'delete_others_'.$plural_base,
            'edit_private_posts' => 'edit_private_'.$plural_base,
            'edit_published_posts' => 'edit_published_'.$plural_base,
        ];
        $default_capabilities = array_merge($default_capabilities, $default_capabilities_for_mapping);
    }

    $capabilities = array_merge($default_capabilities, $args->capabilities);

    // Post creation capability simply maps to edit_posts by default:
    if (! isset($capabilities['create_posts'])) {
        $capabilities['create_posts'] = $capabilities['edit_posts'];
    }

    // Remember meta capabilities for future reference.
    if ($args->map_meta_cap) {
        _post_type_meta_capabilities($capabilities);
    }

    return (object) $capabilities;
}

/**
 * Stores or returns a list of post type meta caps for map_meta_cap().
 *
 * @since WM-0.5.0
 * @since WP-3.1.0
 *
 * @global array $post_type_meta_caps Used to store meta capabilities.
 *
 * @param  string[]  $capabilities  Post type meta capabilities.
 */
function _post_type_meta_capabilities($capabilities = null)
{
    global $post_type_meta_caps;

    foreach ($capabilities as $core => $custom) {
        if (in_array($core, ['read_post', 'delete_post', 'edit_post'], true)) {
            $post_type_meta_caps[$custom] = $core;
        }
    }
}

/**
 * Builds an object with all post type labels out of a post type object.
 *
 * Accepted keys of the label array in the post type object:
 *
 * - `name` - General name for the post type, usually plural. The same and overridden
 *          by `$post_type_object->label`. Default is 'Posts' / 'Pages'.
 * - `singular_name` - Name for one object of this post type. Default is 'Post' / 'Page'.
 * - `add_new` - Label for adding a new item. Default is 'Add Post' / 'Add Page'.
 * - `add_new_item` - Label for adding a new singular item. Default is 'Add Post' / 'Add Page'.
 * - `edit_item` - Label for editing a singular item. Default is 'Edit Post' / 'Edit Page'.
 * - `new_item` - Label for the new item page title. Default is 'New Post' / 'New Page'.
 * - `view_item` - Label for viewing a singular item. Default is 'View Post' / 'View Page'.
 * - `view_items` - Label for viewing post type archives. Default is 'View Posts' / 'View Pages'.
 * - `search_items` - Label for searching plural items. Default is 'Search Posts' / 'Search Pages'.
 * - `not_found` - Label used when no items are found. Default is 'No posts found' / 'No pages found'.
 * - `not_found_in_trash` - Label used when no items are in the Trash. Default is 'No posts found in Trash' /
 *                        'No pages found in Trash'.
 * - `parent_item_colon` - Label used to prefix parents of hierarchical items. Not used on non-hierarchical
 *                       post types. Default is 'Parent Page:'.
 * - `all_items` - Label to signify all items in a submenu link. Default is 'All Posts' / 'All Pages'.
 * - `archives` - Label for archives in nav menus. Default is 'Post Archives' / 'Page Archives'.
 * - `attributes` - Label for the attributes meta box. Default is 'Post Attributes' / 'Page Attributes'.
 * - `insert_into_item` - Label for the media frame button. Default is 'Insert into post' / 'Insert into page'.
 * - `uploaded_to_this_item` - Label for the media frame filter. Default is 'Uploaded to this post' /
 *                           'Uploaded to this page'.
 * - `featured_image` - Label for the featured image meta box title. Default is 'Featured image'.
 * - `set_featured_image` - Label for setting the featured image. Default is 'Set featured image'.
 * - `remove_featured_image` - Label for removing the featured image. Default is 'Remove featured image'.
 * - `use_featured_image` - Label in the media frame for using a featured image. Default is 'Use as featured image'.
 * - `menu_name` - Label for the menu name. Default is the same as `name`.
 * - `filter_items_list` - Label for the table views hidden heading. Default is 'Filter posts list' /
 *                       'Filter pages list'.
 * - `filter_by_date` - Label for the date filter in list tables. Default is 'Filter by date'.
 * - `items_list_navigation` - Label for the table pagination hidden heading. Default is 'Posts list navigation' /
 *                           'Pages list navigation'.
 * - `items_list` - Label for the table hidden heading. Default is 'Posts list' / 'Pages list'.
 * - `item_published` - Label used when an item is published. Default is 'Post published.' / 'Page published.'
 * - `item_published_privately` - Label used when an item is published with private visibility.
 *                              Default is 'Post published privately.' / 'Page published privately.'
 * - `item_reverted_to_draft` - Label used when an item is switched to a draft.
 *                            Default is 'Post reverted to draft.' / 'Page reverted to draft.'
 * - `item_trashed` - Label used when an item is moved to Trash. Default is 'Post trashed.' / 'Page trashed.'
 * - `item_scheduled` - Label used when an item is scheduled for publishing. Default is 'Post scheduled.' /
 *                    'Page scheduled.'
 * - `item_updated` - Label used when an item is updated. Default is 'Post updated.' / 'Page updated.'
 * - `item_link` - Title for a navigation link block variation. Default is 'Post Link' / 'Page Link'.
 * - `item_link_description` - Description for a navigation link block variation. Default is 'A link to a post.' /
 *                             'A link to a page.'
 *
 * Above, the first default value is for non-hierarchical post types (like posts)
 * and the second one is for hierarchical post types (like pages).
 *
 * Note: To set labels used in post type admin notices, see the {@see 'post_updated_messages'} filter.
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 * @since WP-4.3.0 Added the `featured_image`, `set_featured_image`, `remove_featured_image`,
 *              and `use_featured_image` labels.
 * @since WP-4.4.0 Added the `archives`, `insert_into_item`, `uploaded_to_this_item`, `filter_items_list`,
 *              `items_list_navigation`, and `items_list` labels.
 * @since WP-4.6.0 Converted the `$post_type` parameter to accept a `WP_Post_Type` object.
 * @since WP-4.7.0 Added the `view_items` and `attributes` labels.
 * @since WP-5.0.0 Added the `item_published`, `item_published_privately`, `item_reverted_to_draft`,
 *              `item_scheduled`, and `item_updated` labels.
 * @since WP-5.7.0 Added the `filter_by_date` label.
 * @since WP-5.8.0 Added the `item_link` and `item_link_description` labels.
 * @since WP-6.3.0 Added the `item_trashed` label.
 * @since WP-6.4.0 Changed default values for the `add_new` label to include the type of content.
 *              This matches `add_new_item` and provides more context for better accessibility.
 * @since WP-6.6.0 Added the `template_name` label.
 * @since WP-6.7.0 Restored pre-6.4.0 defaults for the `add_new` label and updated documentation.
 *              Updated core usage to reference `add_new_item`.
 *
 * @param  object|WP_Post_Type  $post_type_object  Post type object.
 * @return object Object with all the labels as member variables.
 */
function get_post_type_labels($post_type_object)
{
    $nohier_vs_hier_defaults = WP_Post_Type::get_default_labels();

    $nohier_vs_hier_defaults['menu_name'] = $nohier_vs_hier_defaults['name'];

    $labels = _get_custom_object_labels($post_type_object, $nohier_vs_hier_defaults);

    if (! isset($post_type_object->labels->template_name) && isset($post_type_object->labels->singular_name)) {
        /* translators: %s: Post type name. */
        $labels->template_name = sprintf(__('Single item: %s'), $post_type_object->labels->singular_name);
    }

    $post_type = $post_type_object->name;

    $default_labels = clone $labels;

    /**
     * Filters the labels of a specific post type.
     *
     * The dynamic portion of the hook name, `$post_type`, refers to
     * the post type slug.
     *
     * Possible hook names include:
     *
     *  - `post_type_labels_post`
     *  - `post_type_labels_page`
     *  - `post_type_labels_attachment`
     *
     * @since WP-3.5.0
     * @see get_post_type_labels() for the full list of labels.
     *
     * @param  object  $labels  Object with labels for the post type as member variables.
     */
    $labels = apply_filters("post_type_labels_{$post_type}", $labels);

    // Ensure that the filtered labels contain all required default values.
    $labels = (object) array_merge((array) $default_labels, (array) $labels);

    return $labels;
}

/**
 * Builds an object with custom-something object (post type, taxonomy) labels
 * out of a custom-something object
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 *
 * @param  object  $data_object  A custom-something object.
 * @param  array  $nohier_vs_hier_defaults  Hierarchical vs non-hierarchical default labels.
 * @return object Object containing labels for the given custom-something object.
 */
function _get_custom_object_labels($data_object, $nohier_vs_hier_defaults)
{
    $data_object->labels = (array) $data_object->labels;

    if (isset($data_object->label) && empty($data_object->labels['name'])) {
        $data_object->labels['name'] = $data_object->label;
    }

    if (! isset($data_object->labels['singular_name']) && isset($data_object->labels['name'])) {
        $data_object->labels['singular_name'] = $data_object->labels['name'];
    }

    if (! isset($data_object->labels['name_admin_bar'])) {
        $data_object->labels['name_admin_bar'] = $data_object->labels['singular_name'] ?? $data_object->name;
    }

    if (! isset($data_object->labels['menu_name']) && isset($data_object->labels['name'])) {
        $data_object->labels['menu_name'] = $data_object->labels['name'];
    }

    if (! isset($data_object->labels['all_items']) && isset($data_object->labels['menu_name'])) {
        $data_object->labels['all_items'] = $data_object->labels['menu_name'];
    }

    if (! isset($data_object->labels['archives']) && isset($data_object->labels['all_items'])) {
        $data_object->labels['archives'] = $data_object->labels['all_items'];
    }

    $defaults = [];
    foreach ($nohier_vs_hier_defaults as $key => $value) {
        $defaults[$key] = $data_object->hierarchical ? $value[1] : $value[0];
    }

    $labels = array_merge($defaults, $data_object->labels);
    $data_object->labels = (object) $data_object->labels;

    return (object) $labels;
}

/**
 * Registers support of certain features for a post type.
 *
 * All core features are directly associated with a functional area of the edit
 * screen, such as the editor or a meta box. Features include: 'title', 'editor',
 * 'comments', 'revisions', 'trackbacks', 'author', 'excerpt', 'page-attributes',
 * 'thumbnail', 'custom-fields', and 'post-formats'.
 *
 * Additionally, the 'revisions' feature dictates whether the post type will
 * store revisions, the 'autosave' feature dictates whether the post type
 * will be autosaved, and the 'comments' feature dictates whether the comments
 * count will show on the edit screen.
 *
 * A third, optional parameter can also be passed along with a feature to provide
 * additional information about supporting that feature.
 *
 * Example usage:
 *
 *     add_post_type_support( 'my_post_type', 'comments' );
 *     add_post_type_support( 'my_post_type', array(
 *         'author', 'excerpt',
 *     ) );
 *     add_post_type_support( 'my_post_type', 'my_feature', array(
 *         'field' => 'value',
 *     ) );
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 * @since WP-5.3.0 Formalized the existing and already documented `...$args` parameter
 *              by adding it to the function signature.
 *
 * @global array $_wp_post_type_features
 *
 * @param  string  $post_type  The post type for which to add the feature.
 * @param  string|array  $feature  The feature being added, accepts an array of
 *                                 feature strings or a single string.
 * @param  mixed  ...$args  Optional extra arguments to pass along with certain features.
 */
function add_post_type_support($post_type, $feature, ...$args)
{
    global $_wp_post_type_features;

    $features = (array) $feature;
    foreach ($features as $feature) {
        if ($args) {
            $_wp_post_type_features[$post_type][$feature] = $args;
        } else {
            $_wp_post_type_features[$post_type][$feature] = true;
        }
    }
}

/**
 * Removes support for a feature from a post type.
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 *
 * @global array $_wp_post_type_features
 *
 * @param  string  $post_type  The post type for which to remove the feature.
 * @param  string  $feature  The feature being removed.
 */
function remove_post_type_support($post_type, $feature)
{
    global $_wp_post_type_features;

    unset($_wp_post_type_features[$post_type][$feature]);
}

/**
 * Gets all the post type features
 *
 * @since WM-0.5.0
 * @since WP-3.4.0
 *
 * @global array $_wp_post_type_features
 *
 * @param  string  $post_type  The post type.
 * @return array Post type supports list.
 */
function get_all_post_type_supports($post_type)
{
    global $_wp_post_type_features;

    return $_wp_post_type_features[$post_type] ?? [];
}

/**
 * Checks a post type's support for a given feature.
 *
 * @since WM-0.5.0
 * @since WP-3.0.0
 *
 * @global array $_wp_post_type_features
 *
 * @param  string  $post_type  The post type being checked.
 * @param  string  $feature  The feature being checked.
 * @return bool Whether the post type supports the given feature.
 */
function post_type_supports($post_type, $feature)
{
    global $_wp_post_type_features;

    return  isset($_wp_post_type_features[$post_type][$feature]);
}

/**
 * Retrieves a list of post type names that support a specific feature.
 *
 * @since WM-0.5.0
 * @since WP-4.5.0
 *
 * @global array $_wp_post_type_features Post type features
 *
 * @param  array|string  $feature  Single feature or an array of features the post types should support.
 * @param  string  $operator  Optional. The logical operation to perform. 'or' means
 *                            only one element from the array needs to match; 'and'
 *                            means all elements must match; 'not' means no elements may
 *                            match. Default 'and'.
 * @return string[] A list of post type names.
 */
function get_post_types_by_support($feature, $operator = 'and')
{
    global $_wp_post_type_features;

    $features = array_fill_keys((array) $feature, true);

    return array_keys(wp_filter_object_list($_wp_post_type_features, $features, $operator));
}

/**
 * Determines whether a post type is considered "viewable".
 *
 * For built-in post types such as posts and pages, the 'public' value will be evaluated.
 * For all others, the 'publicly_queryable' value will be used.
 *
 * @since WM-0.5.0
 * @since WP-4.4.0
 * @since WP-4.5.0 Added the ability to pass a post type name in addition to object.
 * @since WP-4.6.0 Converted the `$post_type` parameter to accept a `WP_Post_Type` object.
 * @since WP-5.9.0 Added `is_post_type_viewable` hook to filter the result.
 *
 * @param  string|WP_Post_Type  $post_type  Post type name or object.
 * @return bool Whether the post type should be considered viewable.
 */
function is_post_type_viewable($post_type)
{
    if (is_scalar($post_type)) {
        $post_type = get_post_type_object($post_type);

        if (! $post_type) {
            return false;
        }
    }

    if (! is_object($post_type)) {
        return false;
    }

    $is_viewable = $post_type->publicly_queryable || ($post_type->_builtin && $post_type->public);

    /**
     * Filters whether a post type is considered "viewable".
     *
     * The returned filtered value must be a boolean type to ensure
     * `is_post_type_viewable()` only returns a boolean. This strictness
     * is by design to maintain backwards-compatibility and guard against
     * potential type errors in PHP 8.1+. Non-boolean values (even falsey
     * and truthy values) will result in the function returning false.
     *
     * @since WP-5.9.0
     *
     * @param  bool  $is_viewable  Whether the post type is "viewable" (strict type).
     * @param  WP_Post_Type  $post_type  Post type object.
     */
    return apply_filters('is_post_type_viewable', $is_viewable, $post_type) === true;
}

/**
 * Determines whether a post status is considered "viewable".
 *
 * For built-in post statuses such as publish and private, the 'public' value will be evaluated.
 * For all others, the 'publicly_queryable' value will be used.
 *
 * @since WM-0.5.0
 * @since WP-5.7.0
 * @since WP-5.9.0 Added `is_post_status_viewable` hook to filter the result.
 *
 * @param  string|stdClass  $post_status  Post status name or object.
 * @return bool Whether the post status should be considered viewable.
 */
function is_post_status_viewable($post_status)
{
    if (is_scalar($post_status)) {
        if (! is_string($post_status)) {
            return false;
        }

        $post_status = get_post_status_object($post_status);

        if (! $post_status) {
            return false;
        }
    }

    if (
        ! is_object($post_status) ||
        $post_status->internal ||
        $post_status->protected
    ) {
        return false;
    }

    $is_viewable = $post_status->publicly_queryable || ($post_status->_builtin && $post_status->public);

    /**
     * Filters whether a post status is considered "viewable".
     *
     * The returned filtered value must be a boolean type to ensure
     * `is_post_status_viewable()` only returns a boolean. This strictness
     * is by design to maintain backwards-compatibility and guard against
     * potential type errors in PHP 8.1+. Non-boolean values (even falsey
     * and truthy values) will result in the function returning false.
     *
     * @since WP-5.9.0
     *
     * @param  bool  $is_viewable  Whether the post status is "viewable" (strict type).
     * @param  stdClass  $post_status  Post status object.
     */
    return apply_filters('is_post_status_viewable', $is_viewable, $post_status) === true;
}
