<?php

/**
 * WordPress tests for behaviour we changed on purpose. Each is skipped with
 * the divergence it belongs to, and each divergence is recorded in
 * docs/divergences.md and has adapted tests in conformance/adapted/.
 *
 * Keyed by "TestClass::test_method" ("TestClass::*" for a whole class).
 * Entries under a driver name ('pgsql' => [...]) apply to that database only.
 *
 * @return array<string, string|array<string, string>>
 */
$notices = 'wp_trigger_error() notices: logged to Laravel instead of raised as PHP errors (see conformance/adapted/functions/wpTriggerError.php).';

return [
    'Tests_Functions_WpTriggerError::test_should_trigger_warning' => $notices,
    'Tests_Functions_WpTriggerError::test_should_trigger_notice' => $notices,
    'Tests_Functions_WpTriggerError::test_should_trigger_deprecation' => $notices,

    // The core's own queries run through Laravel's query builder (docs/divergences.md, "The `query` filter sees plugins' queries, not the core's").
    'Tests_Option_WpSetOptionAutoloadValues::test_wp_set_option_autoload_values_with_sql_query_failure' => "Forces the core's UPDATE to fail through the `query` filter, which only sees plugins' \$wpdb queries (OptionsTest::test_the_query_filter_sees_plugin_queries_not_core_option_queries).",
    'Tests_Option_Transient::test_get_transient_with_timeout_makes_a_single_database_call' => "Reads the core's SQL text through the `query` filter, which only sees plugins' \$wpdb queries; the query count it also checks matches (OptionsTest::test_the_query_filter_sees_plugin_queries_not_core_option_queries).",
    'Tests_Option_Transient::test_set_transient_primes_option_cache' => "Reads the core's SQL text through the `query` filter, which only sees plugins' \$wpdb queries; the query count it also checks matches (OptionsTest::test_the_query_filter_sees_plugin_queries_not_core_option_queries).",

    // PostgreSQL: MySQL's own features, and MySQL's exact SQL text (docs/divergences.md, "$wpdb on PostgreSQL").
    'pgsql' => [
        'Tests_DB_dbDelta::*' => 'dbDelta() reads MySQL table definitions; not supported on PostgreSQL (owner decision D).',
        'Tests_DB_Charset::*' => "Tests MySQL's character sets and collations; PostgreSQL is all UTF-8.",
        'Tests_DB::test_set_sql_mode' => "MySQL's SQL modes; PostgreSQL has none.",
        'Tests_DB::test_set_incompatible_sql_mode' => "MySQL's SQL modes; PostgreSQL has none.",
        'Tests_DB::test_set_allowed_incompatible_sql_mode' => "MySQL's SQL modes; PostgreSQL has none.",
        'Tests_DB::test_prepare_with_placeholders_and_individual_args' => "Checks MySQL's backslash escaping in the SQL text; PostgreSQL doubles the quote instead (tests/Feature/Core/Database/WpdbPostgresTest).",
        'Tests_DB::test_prepare_with_placeholders_and_array_args' => "Checks MySQL's backslash escaping in the SQL text; PostgreSQL doubles the quote instead (tests/Feature/Core/Database/WpdbPostgresTest).",
        'Tests_DB::test_empty_where_on_update' => 'Checks the SQL text for MySQL backticks; PostgreSQL quotes names with double quotes.',
        'Tests_DB::test_prepare_should_respect_the_allow_unsafe_unquoted_parameters_property' => "Checks %i's MySQL backticks in the SQL text; on PostgreSQL %i uses double quotes (WpdbPostgresTest::test_identifier_placeholders_work).",
    ],
];
