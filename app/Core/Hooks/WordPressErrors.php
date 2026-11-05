<?php

declare(strict_types=1);

namespace App\Core\Hooks;

use Illuminate\Support\Facades\Log;

/**
 * Sends WordPress's notices, warnings and deprecations to Laravel's log instead
 * of raising PHP errors, which Laravel would turn into a crashed page. Fatal
 * errors (E_USER_ERROR) still throw WP_Exception, as WordPress does.
 * See docs/divergences.md, "wp_trigger_error() notices".
 */
final class WordPressErrors
{
    /**
     * Filter on wp_trigger_error_trigger_error: lets only E_USER_ERROR through
     * to WordPress's own handling (which throws WP_Exception). Static, so
     * re-booting the app never adds it twice.
     */
    public static function shouldTrigger(mixed $trigger, mixed $function_name = '', mixed $message = '', mixed $error_level = E_USER_NOTICE): bool
    {
        return (bool) $trigger && (int) $error_level === E_USER_ERROR;
    }

    /**
     * Action on wp_trigger_error_always_run: logs everything except E_USER_ERROR.
     * Parameters are loose because plugins call wp_trigger_error() with loose input.
     */
    public static function log(mixed $function_name = '', mixed $message = '', mixed $error_level = E_USER_NOTICE): void
    {
        $error_level = (int) $error_level;

        if (! WP_DEBUG || $error_level === E_USER_ERROR) {
            return;
        }

        $function_name = (string) $function_name;
        $text = $function_name === '' ? (string) $message : sprintf('%s(): %s', $function_name, (string) $message);
        $context = ['wordpress_function' => $function_name];

        match ($error_level) {
            E_USER_DEPRECATED => Log::channel(config('logging.deprecations.channel') ?? 'null')->warning($text, $context),
            E_USER_WARNING => Log::warning($text, $context),
            default => Log::notice($text, $context),
        };
    }
}
