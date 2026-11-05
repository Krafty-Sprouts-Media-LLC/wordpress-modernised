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
];
