<?php

declare(strict_types=1);

namespace App\Core\Posts\Events;

use WP_Post;

/**
 * A post was deleted for good (WordPress's deleted_post action).
 *
 * Announced once: HookBus bridges it to the WordPress action, so plugins
 * and Laravel listeners both hear it.
 */
final readonly class PostDeleted
{
    public function __construct(
        public int $postId,
        public WP_Post $post,
    ) {}
}
