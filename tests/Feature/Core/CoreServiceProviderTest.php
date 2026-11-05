<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * How the core is wired into Laravel: WP_DEBUG follows the app's debug
 * setting, and WordPress's notices go to Laravel's log instead of crashing.
 */
final class CoreServiceProviderTest extends TestCase
{
    public function test_wp_debug_follows_app_debug(): void
    {
        $this->assertSame((bool) config('app.debug'), WP_DEBUG);
    }

    public function test_doing_it_wrong_logs_instead_of_throwing(): void
    {
        if (! WP_DEBUG) {
            $this->markTestSkipped('WordPress only raises notices when WP_DEBUG is on.');
        }

        Log::spy();
        $runs = 0;
        add_action('doing_it_wrong_run', function () use (&$runs) {
            $runs++;
        });

        _doing_it_wrong('some_function', 'Bad call.', '1.0.0');

        $this->assertSame(1, $runs);
        Log::shouldHaveReceived('notice')->once();
    }
}
