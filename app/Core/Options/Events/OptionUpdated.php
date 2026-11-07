<?php

declare(strict_types=1);

namespace App\Core\Options\Events;

/**
 * An option's value changed (WordPress's updated_option action).
 *
 * Announced once: HookBus bridges it to the WordPress action, so plugins
 * and Laravel listeners both hear it.
 */
final readonly class OptionUpdated
{
    public function __construct(
        public string $option,
        public mixed $oldValue,
        public mixed $value,
    ) {}
}
