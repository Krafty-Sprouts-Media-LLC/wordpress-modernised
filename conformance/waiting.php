<?php

/**
 * WordPress tests that cannot pass yet because they depend on an area we
 * have not rebuilt. Each is skipped with its reason, so it shows in every run.
 * Remove an entry when its area lands; the test must then pass.
 *
 * Keyed by "TestClass::test_method".
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
];
