<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Hooks\HookBus;
use PHPUnit\Framework\Attributes\Depends;
use Tests\TestCase;
use WP_Hook;

/**
 * The noticeboard seen from both sides: Laravel code through HookBus,
 * WordPress plugins through the hook functions, one shared registry.
 */
final class HookBusTest extends TestCase
{
    public function test_hooks_live_in_globals_after_boot(): void
    {
        app(HookBus::class)->addFilter('hookbus_test', fn ($value) => $value.'!');

        $this->assertSame('a!', apply_filters('hookbus_test', 'a'));
        $this->assertInstanceOf(WP_Hook::class, $GLOBALS['wp_filter']['hookbus_test']);
    }

    public function test_plugin_filters_reach_laravel_code(): void
    {
        add_filter('hookbus_test2', fn ($value) => $value * 2);

        $this->assertSame(42, app(HookBus::class)->applyFilters('hookbus_test2', 21));
    }

    public function test_bridge_fires_wordpress_action_for_laravel_event(): void
    {
        $received = [];
        app(HookBus::class)->bridge(HookBusTestEvent::class, 'test_event_fired', fn (HookBusTestEvent $event) => [$event->id]);
        add_action('test_event_fired', function ($id) use (&$received) {
            $received[] = $id;
        });

        event(new HookBusTestEvent(7));

        $this->assertSame([7], $received);
    }

    public function test_hooks_do_not_leak_between_tests_a(): void
    {
        add_action('core_leak_probe', '__return_true');

        $this->assertTrue((bool) has_action('core_leak_probe'));
    }

    #[Depends('test_hooks_do_not_leak_between_tests_a')]
    public function test_hooks_do_not_leak_between_tests_b(): void
    {
        $this->assertFalse(has_action('core_leak_probe'));
    }
}

/**
 * A Laravel event used only to prove the bridge.
 */
final class HookBusTestEvent
{
    public function __construct(public readonly int $id) {}
}
