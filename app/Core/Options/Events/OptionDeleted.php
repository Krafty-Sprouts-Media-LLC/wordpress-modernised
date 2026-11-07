<?php

declare(strict_types=1);

namespace App\Core\Options\Events;

/**
 * An option was deleted (WordPress's deleted_option action).
 *
 * Announced once: HookBus bridges it to the WordPress action, so plugins
 * and Laravel listeners both hear it.
 */
final readonly class OptionDeleted
{
    public function __construct(
        public string $option,
    ) {}
}
