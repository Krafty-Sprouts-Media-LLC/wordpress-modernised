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
