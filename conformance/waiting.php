<?php

/**
 * WordPress tests that cannot pass yet because they depend on an area we
 * have not rebuilt. Each is skipped with its reason, so it shows in every run.
 * Remove an entry when its area lands; the test must then pass.
 *
 * Keyed by "TestClass::test_method".
 *
 * Kept out of phpunit.xml.dist until their step, because they fail before
 * a skip can apply (a data provider or class clean-up needs the area):
 * option/themeMods.php (themes), post/wpInsertPost.php (logins: its class
 * clean-up edits user roles).
 *
 * @return array<string, string>
 */
return [
    'Tests_Filters::test_other_priority' => 'Needs WordPress default filters (the_content). Arrives with the default-filters step.',
    'Tests_DB::test_get_col' => 'Needs the default admin user a WordPress install creates. Arrives with the logins step.',
    'Tests_DB::test_db_reconnect' => 'Needs the default admin user a WordPress install creates. Arrives with the logins step.',
    'Tests_DB::test_mysqli_flush_sync' => "Needs WordPress's post factory and wp_delete_post(). Arrives with the posts step.",
    'Tests_Option_Registration::*' => "Needs the Settings API (register_setting()) and WordPress's rules in sanitize_option(). Arrive with the formatting step.",
    'Tests_Option_SanitizeOption::*' => "Needs WordPress's rules in sanitize_option() (stand-in runs only its filter). Arrives with the formatting step.",
    'Tests_Option_UserSettings::*' => 'Needs users and the current user. Arrives with the logins step.',
    'Tests_Option_wpUserSettings::*' => 'Needs users and the current user. Arrives with the logins step.',
    'Tests_Post_Types::test_post_type_with_no_feed' => "Needs permalinks and the request's query vars (\$wp_rewrite, \$wp). Arrives with the rewrite step.",
    'Tests_Post_Types::test_unregister_post_type_removes_query_vars' => "Needs permalinks and the request's query vars (\$wp_rewrite, \$wp). Arrives with the rewrite step.",
    'Tests_Post_Types::test_unregister_post_type_removes_rewrite_tags' => "Needs permalinks and the request's query vars (\$wp_rewrite, \$wp). Arrives with the rewrite step.",
    'Tests_Post_Types::test_unregister_post_type_removes_rewrite_rules' => "Needs permalinks and the request's query vars (\$wp_rewrite, \$wp). Arrives with the rewrite step.",
    'Tests_Post_WP_Post_Type::test_does_not_add_query_var_if_not_public' => "Needs permalinks and the request's query vars (\$wp_rewrite, \$wp). Arrives with the rewrite step.",
    'Tests_Post_WP_Post_Type::test_adds_query_var_if_public' => "Needs permalinks and the request's query vars (\$wp_rewrite, \$wp). Arrives with the rewrite step.",
    'Tests_Post_WP_Post_Type::test_adds_rewrite_rules' => "Needs permalinks and the request's query vars (\$wp_rewrite, \$wp). Arrives with the rewrite step.",
    'Tests_Post_Types::test_removing_autosave_support_removes_rest_api_controller' => "Needs the REST API's controllers. Arrives with the REST API step.",
    'Tests_Post_WP_Post_Type::test_get_revisions_rest_controller_should_return_correct_values' => "Needs the REST API's controllers. Arrives with the REST API step.",
    'Tests_Post_WP_Post_Type::test_get_autosave_rest_controller_should_return_correct_values' => "Needs the REST API's controllers. Arrives with the REST API step.",
    'Tests_Post_Types::test_register_taxonomy_for_object_type' => 'Needs taxonomies (categories and tags). Arrives with the terms step.',
    'Tests_Post_Types::test_unregister_post_type_removes_post_type_from_taxonomies' => 'Needs taxonomies (categories and tags). Arrives with the terms step.',
    'Tests_Post_WP_Post_Type::test_register_taxonomies' => 'Needs taxonomies (categories and tags). Arrives with the terms step.',
    'Tests_Meta_Register_Meta::test_register_meta_back_compat_with_auth_callback_and_no_sanitize_callback_has_old_style_auth_filter' => 'Needs taxonomies (categories and tags). Arrives with the terms step.',
    'Tests_Meta_Slashes::test_edit_post' => 'Needs users. Arrives with the logins step.',
    'Tests_Meta_UpdateMetadata::test_clear_user_metadata_caches' => 'Needs users. Arrives with the logins step.',
    'Tests_Post::test_parse_post_content_single_page' => 'Needs users. Arrives with the logins step.',
    'Tests_Post_GetPost::test_get_post_sanitization' => "Needs the editing context (format_to_edit(), the current user's editor). Arrives with the formatting and logins steps.",
    'Tests_Post_GetPostStatus::test_get_post_status_resolves' => 'Needs attachments. Arrives with the media step.',
    'Tests_Post_Meta::test_unique_postmeta' => 'Needs users. Arrives with the logins step.',
    'Tests_Post_PrimePostCaches::test_prime_post_caches' => 'Needs taxonomies (categories and tags). Arrives with the terms step.',
    'Tests_Post_Slashes::test_edit_post' => 'Needs users. Arrives with the logins step.',
    'Tests_Post_UpdatePostCache::test_get_posts_caches_post_filter_is_always_raw' => 'Needs taxonomies (categories and tags). Arrives with the terms step.',
    'Tests_Post_UpdatePostCache::test_query_caches_post_filter' => 'Needs post search (WP_Query, get_posts()). Arrives with the search step.',
    'Tests_Post_WpDeletePost::test_wp_delete_post_reassign_hierarchical_post_type' => 'Needs users. Arrives with the logins step.',
    'Tests_Post_WpTrashPost::test_trash_post' => 'Needs post search (WP_Query, get_posts()). Arrives with the search step.',
    'Tests_Post_WpUntrashPost::test_untrash_post' => 'Needs post search (WP_Query, get_posts()). Arrives with the search step.',
    'Tests_Post_wpAfterInsertPost::test_update_via_wp_update_post' => 'Needs users. Arrives with the logins step.',
    'Tests_Post_wpPublishPost::test_wp_publish_post_adds_default_category' => 'Needs taxonomies (categories and tags). Arrives with the terms step.',
    'Tests_Post_wpPublishPost::test_wp_publish_post_adds_default_category_when_tagged' => 'Needs taxonomies (categories and tags). Arrives with the terms step.',
    'Tests_Post_wpPublishPost::test_wp_publish_post_adds_default_term' => 'Needs users. Arrives with the logins step.',
    'Tests_Post_wpPublishPost::test_wp_publish_post_and_avoid_content_filtering' => 'Needs taxonomies (categories and tags). Arrives with the terms step.',
    'Tests_Post_wpPublishPost::test_wp_publish_post_respects_current_categories' => 'Needs taxonomies (categories and tags). Arrives with the terms step.',
    'Tests_Post_wpPublishPost::test_wp_publish_post_respects_current_terms' => 'Needs taxonomies (categories and tags). Arrives with the terms step.',
    'Tests_Post_wpPublishPost::test_wp_update_post_with_content_filtering' => 'Needs HTML filtering (kses). Arrives with the kses step.',
    'Tests_Post_wpUniquePostSlug::test_daylike_slugs_should_not_be_suffixed_for_invalid_day_numbers' => 'Needs permalinks ($wp_rewrite). Arrives with the rewrite step.',
    'Tests_Post_wpUniquePostSlug::test_daylike_slugs_should_not_be_suffixed_if_permalink_structure_does_not_result_in_a_clash_with_day_archives' => 'Needs permalinks ($wp_rewrite). Arrives with the rewrite step.',
    'Tests_Post_wpUniquePostSlug::test_embed_slug_should_be_suffixed_for_attachments' => 'Needs users. Arrives with the logins step.',
    'Tests_Post_wpUniquePostSlug::test_embed_slug_should_be_suffixed_for_pages' => 'Needs permalinks ($wp_rewrite). Arrives with the rewrite step.',
    'Tests_Post_wpUniquePostSlug::test_embed_slug_should_be_suffixed_for_posts' => 'Needs permalinks ($wp_rewrite). Arrives with the rewrite step.',
    'Tests_Post_wpUniquePostSlug::test_monthlike_slugs_should_not_be_suffixed_for_invalid_month_numbers' => 'Needs permalinks ($wp_rewrite). Arrives with the rewrite step.',
    'Tests_Post_wpUniquePostSlug::test_monthlike_slugs_should_not_be_suffixed_if_permalink_structure_does_not_result_in_a_clash_with_month_archives' => 'Needs permalinks ($wp_rewrite). Arrives with the rewrite step.',
    'Tests_Post_wpUniquePostSlug::test_non_latin_slugs' => 'Needs users. Arrives with the logins step.',
    'Tests_Post_wpUniquePostSlug::test_slugs_resulting_in_permalinks_that_resemble_day_archives_should_be_suffixed' => 'Needs permalinks ($wp_rewrite). Arrives with the rewrite step.',
    'Tests_Post_wpUniquePostSlug::test_slugs_resulting_in_permalinks_that_resemble_month_archives_should_be_suffixed' => 'Needs permalinks ($wp_rewrite). Arrives with the rewrite step.',
    'Tests_Post_wpUniquePostSlug::test_slugs_resulting_in_permalinks_that_resemble_year_archives_should_be_suffixed' => 'Needs permalinks ($wp_rewrite). Arrives with the rewrite step.',
    'Tests_Post_wpUniquePostSlug::test_slugs_resulting_in_permalinks_that_resemble_year_archives_should_not_be_suffixed_for_already_published_posts' => 'Needs permalinks ($wp_rewrite). Arrives with the rewrite step.',
    'Tests_Post_wpUniquePostSlug::test_wp_unique_post_slug_with_hierarchy_and_attachments' => 'Needs permalinks ($wp_rewrite). Arrives with the rewrite step.',
    'Tests_Post_wpUniquePostSlug::test_yearlike_slugs_should_not_be_suffixed_if_permalink_structure_does_not_result_in_a_clash_with_year_archives' => 'Needs permalinks ($wp_rewrite). Arrives with the rewrite step.',
    'Tests_Meta::*' => 'Its shared fixture creates users. Arrives with the logins step.',
];
