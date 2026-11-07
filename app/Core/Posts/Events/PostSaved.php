<?php

declare(strict_types=1);

namespace App\Core\Posts\Events;

use WP_Post;

/**
 * A post was created or updated (WordPress's save_post action).
 *
 * Announced once: HookBus bridges it to the WordPress action, so plugins
 * and Laravel listeners both hear it.
 */
final readonly class PostSaved
{
    public function __construct(
        public int $postId,
        public WP_Post $post,
        public bool $update,
    ) {}
}
