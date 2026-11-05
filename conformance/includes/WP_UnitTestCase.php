<?php

/**
 * The base class WordPress's own tests extend, cut down to what the areas
 * we have rebuilt need. Members are added as more WordPress test areas join
 * the checker.
 *
 * Contains code from WordPress 7.1.4: tests/phpunit/includes/abstract-testcase.php.
 */

use PHPUnit\Util\Test;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Saves and restores hook state around every test, and checks the
 * `@expectedDeprecated` and `@expectedIncorrectUsage` annotations the way
 * WordPress's own base test case does.
 */
abstract class WP_UnitTestCase extends TestCase
{
    /** @var list<string> */
    protected $expected_deprecated = [];

    /** @var array<string, string> */
    protected $caught_deprecated = [];

    /** @var list<string> */
    protected $expected_doing_it_wrong = [];

    /** @var array<string, string> */
    protected $caught_doing_it_wrong = [];

    /** @var array<string, mixed> */
    protected static $hooks_saved = [];

    public function set_up()
    {
        static $waiting = null;
        $waiting ??= require dirname(__DIR__).'/waiting.php';

        $key = static::class.'::'.$this->getName(false);

        if (isset($waiting[$key])) {
            $this->markTestSkipped('Waiting: '.$waiting[$key]);
        }

        if (! self::$hooks_saved) {
            $this->_backup_hooks();
        }

        $this->expectDeprecated();
    }

    public function tear_down()
    {
        $this->expected_deprecated = [];
        $this->caught_deprecated = [];
        $this->expected_doing_it_wrong = [];
        $this->caught_doing_it_wrong = [];

        $this->_restore_hooks();
    }

    protected function assert_post_conditions()
    {
        $this->expectedDeprecated();
    }

    /**
     * Saves the hook globals so each test starts from the same state.
     */
    protected function _backup_hooks()
    {
        self::$hooks_saved['wp_filter'] = [];

        foreach ($GLOBALS['wp_filter'] as $hook_name => $hook_object) {
            self::$hooks_saved['wp_filter'][$hook_name] = clone $hook_object;
        }

        foreach (['wp_actions', 'wp_filters', 'wp_current_filter'] as $key) {
            self::$hooks_saved[$key] = $GLOBALS[$key];
        }
    }

    /**
     * Puts the hook globals back as they were saved.
     */
    protected function _restore_hooks()
    {
        if (isset(self::$hooks_saved['wp_filter'])) {
            $GLOBALS['wp_filter'] = [];

            foreach (self::$hooks_saved['wp_filter'] as $hook_name => $hook_object) {
                $GLOBALS['wp_filter'][$hook_name] = clone $hook_object;
            }
        }

        foreach (['wp_actions', 'wp_filters', 'wp_current_filter'] as $key) {
            if (isset(self::$hooks_saved[$key])) {
                $GLOBALS[$key] = self::$hooks_saved[$key];
            }
        }
    }

    /**
     * Reads the test's expected notices and starts catching notices.
     */
    public function expectDeprecated()
    {
        $annotations = Test::parseTestMethodAnnotations(static::class, $this->getName(false));

        foreach (['class', 'method'] as $depth) {
            if (! empty($annotations[$depth]['expectedDeprecated'])) {
                $this->expected_deprecated = array_merge($this->expected_deprecated, $annotations[$depth]['expectedDeprecated']);
            }

            if (! empty($annotations[$depth]['expectedIncorrectUsage'])) {
                $this->expected_doing_it_wrong = array_merge($this->expected_doing_it_wrong, $annotations[$depth]['expectedIncorrectUsage']);
            }
        }

        add_action('deprecated_function_run', [$this, 'deprecated_function_run'], 10, 3);
        add_action('deprecated_argument_run', [$this, 'deprecated_function_run'], 10, 3);
        add_action('deprecated_class_run', [$this, 'deprecated_function_run'], 10, 3);
        add_action('deprecated_file_included', [$this, 'deprecated_function_run'], 10, 4);
        add_action('deprecated_hook_run', [$this, 'deprecated_function_run'], 10, 4);
        add_action('doing_it_wrong_run', [$this, 'doing_it_wrong_run'], 10, 3);

        add_action('deprecated_function_trigger_error', '__return_false');
        add_action('deprecated_argument_trigger_error', '__return_false');
        add_action('deprecated_class_trigger_error', '__return_false');
        add_action('deprecated_file_trigger_error', '__return_false');
        add_action('deprecated_hook_trigger_error', '__return_false');
        add_action('doing_it_wrong_trigger_error', '__return_false');
    }

    /**
     * Fails the test if an expected notice was not raised, or an unexpected one was.
     */
    public function expectedDeprecated()
    {
        $errors = [];

        foreach (array_diff($this->expected_deprecated, array_keys($this->caught_deprecated)) as $not_caught) {
            $errors[] = "Failed to assert that $not_caught triggered a deprecation notice.";
        }

        foreach (array_diff(array_keys($this->caught_deprecated), $this->expected_deprecated) as $unexpected) {
            $errors[] = "Unexpected deprecation notice for $unexpected.";
            $errors[] = $this->caught_deprecated[$unexpected];
        }

        foreach (array_diff($this->expected_doing_it_wrong, array_keys($this->caught_doing_it_wrong)) as $not_caught) {
            $errors[] = "Failed to assert that $not_caught triggered an incorrect usage notice.";
        }

        foreach (array_diff(array_keys($this->caught_doing_it_wrong), $this->expected_doing_it_wrong) as $unexpected) {
            $errors[] = "Unexpected incorrect usage notice for $unexpected.";
            $errors[] = $this->caught_doing_it_wrong[$unexpected];
        }

        if ($this->expected_deprecated || $this->expected_doing_it_wrong || $this->caught_deprecated || $this->caught_doing_it_wrong) {
            $this->assertEmpty($errors, implode("\n", $errors));
        }
    }

    /**
     * Records a deprecation notice raised during the test.
     */
    public function deprecated_function_run($function_name, $replacement, $version, $message = '')
    {
        if (isset($this->caught_deprecated[$function_name])) {
            return;
        }

        $kind = [
            'deprecated_function_run' => 'Function %1$s is deprecated',
            'deprecated_argument_run' => 'Function %1$s was called with an argument that is deprecated',
            'deprecated_class_run' => 'Class %1$s is deprecated',
            'deprecated_file_included' => 'File %1$s is deprecated',
            'deprecated_hook_run' => 'Hook %1$s is deprecated',
        ][current_action()] ?? '%1$s is deprecated';

        $text = $replacement
            ? sprintf($kind.' since version %2$s! Use %3$s instead.', $function_name, $version, $replacement)
            : sprintf($kind.' since version %2$s with no alternative available.', $function_name, $version);

        if (in_array(current_action(), ['deprecated_file_included', 'deprecated_hook_run'], true)) {
            $text .= ' '.$message;
        }

        $this->caught_deprecated[$function_name] = $text;
    }

    /**
     * Records an incorrect-usage notice raised during the test.
     */
    public function doing_it_wrong_run($function_name, $message, $version)
    {
        if (isset($this->caught_doing_it_wrong[$function_name])) {
            return;
        }

        if ($version) {
            $message .= ' '.sprintf('(This message was added in version %s.)', $version);
        }

        $this->caught_doing_it_wrong[$function_name] = $message;
    }

    public function setExpectedDeprecated($deprecated)
    {
        $this->expected_deprecated[] = $deprecated;
    }

    public function setExpectedIncorrectUsage($doing_it_wrong)
    {
        $this->expected_doing_it_wrong[] = $doing_it_wrong;
    }

    /**
     * Asserts two arrays hold the same values, in any order.
     */
    public function assertSameSets($expected, $actual, $message = '')
    {
        $this->assertIsArray($expected, $message.' Expected value must be an array.');
        $this->assertIsArray($actual, $message.' Value under test is not an array.');

        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual, $message);
    }

    /**
     * Asserts two arrays hold the same keys and values, in any order.
     */
    public function assertSameSetsWithIndex($expected, $actual, $message = '')
    {
        $this->assertIsArray($expected, $message.' Expected value must be an array.');
        $this->assertIsArray($actual, $message.' Value under test is not an array.');

        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual, $message);
    }
}
