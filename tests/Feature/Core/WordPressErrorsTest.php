<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Tests\TestCase;
use WP_Exception;

/**
 * How WordPress's own errors behave inside the app: fatal ones still throw,
 * the rest go to Laravel's log instead of crashing the page.
 * See docs/divergences.md, "wp_trigger_error() notices".
 */
final class WordPressErrorsTest extends TestCase
{
    private TestHandler $log;

    private TestHandler $deprecations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->log = $this->memoryChannel('wm_test_log');
        $this->deprecations = $this->memoryChannel('wm_test_deprecations');

        config([
            'logging.default' => 'wm_test_log',
            'logging.deprecations.channel' => 'wm_test_deprecations',
        ]);
    }

    public function test_user_error_still_throws_wp_exception(): void
    {
        $this->expectException(WP_Exception::class);
        $this->expectExceptionMessage('some_function(): fatal thing');

        wp_trigger_error('some_function', 'fatal thing', E_USER_ERROR);
    }

    public function test_notice_is_logged_not_raised(): void
    {
        wp_trigger_error('some_function', 'a notice', E_USER_NOTICE);

        $this->assertTrue($this->log->hasRecordThatContains('some_function(): a notice', Level::Notice));
    }

    public function test_warning_is_logged_as_warning(): void
    {
        wp_trigger_error('some_function', 'a warning', E_USER_WARNING);

        $this->assertTrue($this->log->hasRecordThatContains('some_function(): a warning', Level::Warning));
    }

    public function test_deprecation_goes_to_the_configured_deprecations_channel(): void
    {
        _deprecated_hook('old_hook', '1.0.0', 'new_hook');

        $this->assertTrue($this->deprecations->hasRecordThatContains('old_hook', Level::Warning));
        $this->assertFalse($this->log->hasEmergencyRecords());
    }

    public function test_loose_input_from_plugins_does_not_crash(): void
    {
        wp_trigger_error(null, 'only a message');

        $this->assertTrue($this->log->hasRecordThatContains('only a message', Level::Notice));
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
