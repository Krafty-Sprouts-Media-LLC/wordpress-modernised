<?php

/**
 * WordPress tests for behaviour we changed on purpose. Each is skipped with
 * the divergence it belongs to, and each divergence is recorded in
 * docs/divergences.md and has adapted tests in conformance/adapted/.
 *
 * Keyed by "TestClass::test_method".
 *
 * @return array<string, string>
 */
$notices = 'wp_trigger_error() notices: logged to Laravel instead of raised as PHP errors (see conformance/adapted/functions/wpTriggerError.php).';

return [
    'Tests_Functions_WpTriggerError::test_should_trigger_warning' => $notices,
    'Tests_Functions_WpTriggerError::test_should_trigger_notice' => $notices,
    'Tests_Functions_WpTriggerError::test_should_trigger_deprecation' => $notices,
];
