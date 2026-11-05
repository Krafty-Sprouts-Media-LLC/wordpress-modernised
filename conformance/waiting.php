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
];
