<?php

declare(strict_types=1);

namespace App\Core\Hooks;

use Illuminate\Support\Facades\Log;

/**
 * Sends WordPress's notices (incorrect usage, deprecated hooks) to Laravel's log.
 * Laravel turns raw PHP notices into exceptions, which would take a page down
 * whenever a plugin misuses a function, so the PHP error is never raised.
 */
final class WordPressErrors
{
    /**
     * Listens on WordPress's wp_trigger_error_always_run action. A static
     * callback, so re-booting the app never adds it twice.
     */
    public static function log(string $function_name, string $message, int $error_level): void
    {
        if (! WP_DEBUG) {
            return;
        }

        $context = ['wordpress_function' => $function_name];

        if ($error_level === E_USER_DEPRECATED) {
            Log::channel('deprecations')->warning($message, $context);

            return;
        }

        Log::notice($message, $context);
    }
}
