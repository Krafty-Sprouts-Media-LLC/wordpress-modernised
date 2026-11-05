<?php

/**
 * Adapted from WordPress 7.1.4: tests/phpunit/tests/functions/wpTriggerError.php.
 *
 * WordPress raises E_USER_WARNING, E_USER_NOTICE and E_USER_DEPRECATED as PHP
 * errors. We log them to Laravel instead (docs/divergences.md, "wp_trigger_error()
 * notices"). These tests describe our behaviour, with WordPress's own data.
 */

use Monolog\Handler\TestHandler;
use Monolog\Level;

/**
 * wp_trigger_error() inside the app: notices, warnings and deprecations are
 * logged with the same message WordPress would raise, and nothing is raised.
 */
final class Tests_Adapted_Functions_WpTriggerError extends WP_UnitTestCase
{
    private TestHandler $log;

    private TestHandler $deprecations;

    public function set_up()
    {
        parent::set_up();

        $this->log = $this->memoryChannel('checker_log');
        $this->deprecations = $this->memoryChannel('checker_deprecations');

        config([
            'logging.default' => 'checker_log',
            'logging.deprecations.channel' => 'checker_deprecations',
        ]);
    }

    /**
     * @dataProvider data_should_log
     */
    public function test_warning_is_logged_not_raised($function_name, $message, $expected_message)
    {
        wp_trigger_error($function_name, $message, E_USER_WARNING);

        $this->assertTrue($this->log->hasRecordThatContains($expected_message, Level::Warning));
    }

    /**
     * @dataProvider data_should_log
     */
    public function test_notice_is_logged_not_raised($function_name, $message, $expected_message)
    {
        wp_trigger_error($function_name, $message, E_USER_NOTICE);

        $this->assertTrue($this->log->hasRecordThatContains($expected_message, Level::Notice));
    }

    /**
     * @dataProvider data_should_log
     */
    public function test_deprecation_is_logged_not_raised($function_name, $message, $expected_message)
    {
        wp_trigger_error($function_name, $message, E_USER_DEPRECATED);

        $this->assertTrue($this->deprecations->hasRecordThatContains($expected_message, Level::Warning));
    }

    /**
     * WordPress's data, except that logs keep the message's HTML as given:
     * WordPress strips disallowed tags because it prints the message as HTML.
     */
    public function data_should_log()
    {
        return [
            'function name and message are given' => ['some_function', 'expected the function name and message', 'some_function(): expected the function name and message'],
            'message is given' => ['', 'expect only the message', 'expect only the message'],
            'function name is given' => ['some_function', '', 'some_function(): '],
            'allowed HTML elements are present in message' => ['some_function', '<strong>expected</strong> the function name and message', 'some_function(): <strong>expected</strong> the function name and message'],
            'HTML links are present in message' => ['some_function', '<a href="https://example.com">expected the function name and message</a>', 'some_function(): <a href="https://example.com">expected the function name and message</a>'],
            'disallowed HTML elements are present in message' => ['some_function', '<script>alert("expected the function name and message")</script>', 'some_function(): <script>alert("expected the function name and message")</script>'],
        ];
    }

    /**
     * Defines a log channel that records into memory, and returns its handler.
     */
    private function memoryChannel(string $name): TestHandler
    {
        $handler = new TestHandler;

        config(["logging.channels.{$name}" => ['driver' => 'monolog', 'handler' => TestHandler::class]]);
        app('log')->forgetChannel($name);
        app('log')->channel($name)->getLogger()->setHandlers([$handler]);

        return $handler;
    }
}
