<?php

declare(strict_types=1);

namespace App\Core\Hooks;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * The noticeboard for Laravel code: a typed way to add and fire WordPress hooks,
 * and a bridge that announces Laravel events as WordPress actions. It keeps no
 * state of its own; WordPress's $wp_filter global stays the single registry.
 */
final class HookBus
{
    public function __construct(private readonly Dispatcher $events) {}

    public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_action($hook, $callback, $priority, $acceptedArgs);
    }

    public function removeFilter(string $hook, callable $callback, int $priority = 10): bool
    {
        return remove_filter($hook, $callback, $priority);
    }

    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return apply_filters($hook, $value, ...$args);
    }

    public function doAction(string $hook, mixed ...$args): void
    {
        do_action($hook, ...$args);
    }

    /**
     * When a Laravel event of $eventClass is dispatched, fire the WordPress
     * action $action with the arguments $arguments returns for that event.
     *
     * @param  class-string  $eventClass
     * @param  Closure(object): list<mixed>  $arguments
     */
    public function bridge(string $eventClass, string $action, Closure $arguments): void
    {
        $this->events->listen($eventClass, function (object $event) use ($action, $arguments): void {
            do_action($action, ...$arguments($event));
        });
    }
}
