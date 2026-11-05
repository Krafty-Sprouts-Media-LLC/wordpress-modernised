<?php

/**
 * Notices for deprecated hooks and incorrect usage, and how they are raised.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/functions.php.
 */

/**
 * Marks a deprecated action or filter hook as deprecated and throws a notice.
 *
 * Use the {@see 'deprecated_hook_run'} action to get the backtrace describing where
 * the deprecated hook was called.
 *
 * Default behavior is to trigger a user error if `WP_DEBUG` is true.
 *
 * This function is called by the do_action_deprecated() and apply_filters_deprecated()
 * functions, and so generally does not need to be called directly.
 *
 * @since WM-0.1.0
 * @since WP-4.6.0
 * @since WP-5.4.0 The error type is now classified as E_USER_DEPRECATED (used to default to E_USER_NOTICE).
 *
 * @param  string  $hook  The hook that was used.
 * @param  string  $version  The version of WordPress that deprecated the hook.
 * @param  string  $replacement  Optional. The hook that should have been used. Default empty string.
 * @param  string  $message  Optional. A message regarding the change. Default empty.
 */
function _deprecated_hook($hook, $version, $replacement = '', $message = '')
{
    /**
     * Fires when a deprecated hook is called.
     *
     * @since WM-0.1.0
     * @since WP-4.6.0
     *
     * @param  string  $hook  The hook that was called.
     * @param  string  $replacement  The hook that should be used as a replacement.
     * @param  string  $version  The version of WordPress that deprecated the argument used.
     * @param  string  $message  A message regarding the change.
     */
    do_action('deprecated_hook_run', $hook, $replacement, $version, $message);

    /**
     * Filters whether to trigger deprecated hook errors.
     *
     * @since WM-0.1.0
     * @since WP-4.6.0
     *
     * @param  bool  $trigger  Whether to trigger deprecated hook errors. Requires
     *                         `WP_DEBUG` to be defined true.
     */
    if (WP_DEBUG && apply_filters('deprecated_hook_trigger_error', true)) {
        $message = empty($message) ? '' : ' '.$message;

        if ($replacement) {
            $message = sprintf(
                /* translators: 1: WordPress hook name, 2: Version number, 3: Alternative hook name. */
                __('Hook %1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.'),
                $hook,
                $version,
                $replacement
            ).$message;
        } else {
            $message = sprintf(
                /* translators: 1: WordPress hook name, 2: Version number. */
                __('Hook %1$s is <strong>deprecated</strong> since version %2$s with no alternative available.'),
                $hook,
                $version
            ).$message;
        }

        wp_trigger_error('', $message, E_USER_DEPRECATED);
    }
}

/**
 * Marks something as being incorrectly called.
 *
 * There is a {@see 'doing_it_wrong_run'} hook that will be called that can be used
 * to get the backtrace up to what file and function called the deprecated function.
 *
 * The current behavior is to trigger a user error if `WP_DEBUG` is true.
 *
 * @since WM-0.1.0
 * @since WP-3.1.0
 * @since WP-5.4.0 This function is no longer marked as "private".
 *
 * @param  string  $function_name  The function that was called.
 * @param  string  $message  A message explaining what has been done incorrectly.
 * @param  string  $version  The version of WordPress where the message was added.
 */
function _doing_it_wrong($function_name, $message, $version)
{

    /**
     * Fires when the given function is being used incorrectly.
     *
     * @since WM-0.1.0
     * @since WP-3.1.0
     *
     * @param  string  $function_name  The function that was called.
     * @param  string  $message  A message explaining what has been done incorrectly.
     * @param  string  $version  The version of WordPress where the message was added.
     */
    do_action('doing_it_wrong_run', $function_name, $message, $version);

    /**
     * Filters whether to trigger an error for _doing_it_wrong() calls.
     *
     * @since WM-0.1.0
     * @since WP-3.1.0
     * @since WP-5.1.0 Added the `$function_name`, `$message`, and `$version` parameters.
     *
     * @param  bool  $trigger  Whether to trigger the error for _doing_it_wrong() calls. Default true.
     * @param  string  $function_name  The function that was called.
     * @param  string  $message  A message explaining what has been done incorrectly.
     * @param  string  $version  The version of WordPress where the message was added.
     */
    if (WP_DEBUG && apply_filters('doing_it_wrong_trigger_error', true, $function_name, $message, $version)) {
        if (function_exists('__')) {
            if ($version) {
                /* translators: %s: Version number. */
                $version = sprintf(__('(This message was added in version %s.)'), $version);
            }

            $message .= ' '.sprintf(
                /* translators: %s: Documentation URL. */
                __('Please see <a href="%s">Debugging in WordPress</a> for more information.'),
                __('https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/')
            );

            $message = sprintf(
                /* translators: Developer debugging message. 1: PHP function name, 2: Explanatory message, 3: WordPress version number. */
                __('Function %1$s was called <strong>incorrectly</strong>. %2$s %3$s'),
                $function_name,
                $message,
                $version
            );
        } else {
            if ($version) {
                $version = sprintf('(This message was added in version %s.)', $version);
            }

            $message .= sprintf(
                ' Please see <a href="%s">Debugging in WordPress</a> for more information.',
                'https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/'
            );

            $message = sprintf(
                'Function %1$s was called <strong>incorrectly</strong>. %2$s %3$s',
                $function_name,
                $message,
                $version
            );
        }

        wp_trigger_error('', $message);
    }
}

/**
 * Generates a user-level error/warning/notice/deprecation message.
 *
 * Generates the message when `WP_DEBUG` is true.
 *
 * @since WM-0.1.0
 * @since WP-6.4.0
 *
 * @param  string  $function_name  The function that triggered the error.
 * @param  string  $message  The message explaining the error.
 *                           The message can contain allowed HTML 'a' (with href), 'code',
 *                           'br', 'em', and 'strong' tags and http or https protocols.
 *                           If it contains other HTML tags or protocols, the message should be escaped
 *                           before passing to this function to avoid being stripped {@see wp_kses()}.
 * @param  int  $error_level  Optional. The designated error type for this error.
 *                            Only works with E_USER family of constants. Default E_USER_NOTICE.
 */
function wp_trigger_error($function_name, $message, $error_level = E_USER_NOTICE)
{
    /**
     * Always fires when the given function triggers a user-level error/warning/notice/deprecation message.
     *
     * Can be used to attach custom error handlers even if WP_DEBUG is not truthy.
     *
     * @since WM-0.1.0
     * @since WP-7.0.0
     *
     * @param  string  $function_name  The function that triggered the error.
     * @param  string  $message  The message explaining the error.
     * @param  int  $error_level  The designated error type for this error.
     */
    do_action('wp_trigger_error_always_run', $function_name, $message, $error_level);

    /**
     * Filters whether to trigger an error.
     *
     * @since WM-0.1.0
     * @since WP-7.0.0
     *
     * @param  bool  $trigger  Whether to trigger the error. Default true.
     * @param  string  $function_name  The function that triggered the error.
     * @param  string  $message  The message explaining the error.
     * @param  int  $error_level  The designated error type for this error.
     */
    if (! apply_filters('wp_trigger_error_trigger_error', true, $function_name, $message, $error_level)) {
        return;
    }

    // Bail out if WP_DEBUG is not turned on.
    if (! WP_DEBUG) {
        return;
    }

    /**
     * Fires when the given function triggers a user-level error/warning/notice/deprecation message.
     *
     * Can be used for debug backtracking.
     *
     * @since WM-0.1.0
     * @since WP-6.4.0
     *
     * @param  string  $function_name  The function that triggered the error.
     * @param  string  $message  The message explaining the error.
     * @param  int  $error_level  The designated error type for this error.
     */
    do_action('wp_trigger_error_run', $function_name, $message, $error_level);

    if (! empty($function_name)) {
        $message = sprintf('%s(): %s', $function_name, $message);
    }

    $message = wp_kses(
        $message,
        [
            'a' => ['href' => true],
            'br' => [],
            'code' => [],
            'em' => [],
            'strong' => [],
        ],
        ['http', 'https']
    );

    if ($error_level === E_USER_ERROR) {
        throw new WP_Exception($message);
    }

    trigger_error($message, $error_level);
}

/**
 * Marks a function as deprecated and inform when it has been used.
 *
 * There is a {@see 'deprecated_function_run'} hook that will be called that can be used
 * to get the backtrace up to what file and function called the deprecated function.
 *
 * The current behavior is to trigger a user error if `WP_DEBUG` is true.
 *
 * This function is to be used in every function that is deprecated.
 *
 * @since WM-0.1.0
 * @since WP-2.5.0
 * @since WP-5.4.0 This function is no longer marked as "private".
 * @since WP-5.4.0 The error type is now classified as E_USER_DEPRECATED (used to default to E_USER_NOTICE).
 *
 * @param  string  $function_name  The function that was called.
 * @param  string  $version  The version of WordPress that deprecated the function.
 * @param  string  $replacement  Optional. The function that should have been called. Default empty string.
 */
function _deprecated_function($function_name, $version, $replacement = '')
{

    /**
     * Fires when a deprecated function is called.
     *
     * @since WM-0.1.0
     * @since WP-2.5.0
     *
     * @param  string  $function_name  The function that was called.
     * @param  string  $replacement  The function that should have been called.
     * @param  string  $version  The version of WordPress that deprecated the function.
     */
    do_action('deprecated_function_run', $function_name, $replacement, $version);

    /**
     * Filters whether to trigger an error for deprecated functions.
     *
     * @since WM-0.1.0
     * @since WP-2.5.0
     *
     * @param  bool  $trigger  Whether to trigger the error for deprecated functions. Default true.
     */
    if (WP_DEBUG && apply_filters('deprecated_function_trigger_error', true)) {
        if (function_exists('__')) {
            if ($replacement) {
                $message = sprintf(
                    /* translators: 1: PHP function name, 2: Version number, 3: Alternative function name. */
                    __('Function %1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.'),
                    $function_name,
                    $version,
                    $replacement
                );
            } else {
                $message = sprintf(
                    /* translators: 1: PHP function name, 2: Version number. */
                    __('Function %1$s is <strong>deprecated</strong> since version %2$s with no alternative available.'),
                    $function_name,
                    $version
                );
            }
        } else {
            if ($replacement) {
                $message = sprintf(
                    'Function %1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.',
                    $function_name,
                    $version,
                    $replacement
                );
            } else {
                $message = sprintf(
                    'Function %1$s is <strong>deprecated</strong> since version %2$s with no alternative available.',
                    $function_name,
                    $version
                );
            }
        }

        wp_trigger_error('', $message, E_USER_DEPRECATED);
    }
}

/**
 * Returns a comma-separated string or array of functions that have been called to get
 * to the current point in code.
 *
 * @since WM-0.1.0
 * @since WP-3.4.0
 * @see https://core.trac.wordpress.org/ticket/19589
 *
 * @param  string  $ignore_class  Optional. A class to ignore all function calls within - useful
 *                                when you want to just give info about the callee. Default null.
 * @param  int  $skip_frames  Optional. A number of stack frames to skip - useful for unwinding
 *                            back to the source of the issue. Default 0.
 * @param  bool  $pretty  Optional. Whether you want a comma separated string instead of
 *                        the raw array returned. Default true.
 * @return string|array Either a string containing a reversed comma separated trace or an array
 *                      of individual calls.
 */
function wp_debug_backtrace_summary($ignore_class = null, $skip_frames = 0, $pretty = true)
{
    static $truncate_paths;

    $trace = debug_backtrace(false);
    $caller = [];
    $check_class = ! is_null($ignore_class);
    $skip_frames++; // Skip this function.

    if (! isset($truncate_paths)) {
        // WM: only the install paths that are defined yet (no ABSPATH or WP_CONTENT_DIR before the plugin-loading step).
        $truncate_paths = array_map('wp_normalize_path', array_filter([
            defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : null,
            defined('ABSPATH') ? ABSPATH : null,
        ]));
    }

    foreach ($trace as $call) {
        if ($skip_frames > 0) {
            $skip_frames--;
        } elseif (isset($call['class'])) {
            if ($check_class && $ignore_class === $call['class']) {
                continue; // Filter out calls.
            }

            $caller[] = "{$call['class']}{$call['type']}{$call['function']}";
        } else {
            if (in_array($call['function'], ['do_action', 'apply_filters', 'do_action_ref_array', 'apply_filters_ref_array'], true)) {
                $caller[] = "{$call['function']}('{$call['args'][0]}')";
            } elseif (in_array($call['function'], ['include', 'include_once', 'require', 'require_once'], true)) {
                $filename = $call['args'][0] ?? '';
                $caller[] = $call['function']."('".str_replace($truncate_paths, '', wp_normalize_path($filename))."')";
            } else {
                $caller[] = $call['function'];
            }
        }
    }
    if ($pretty) {
        return implode(', ', array_reverse($caller));
    } else {
        return $caller;
    }
}

/**
 * Checks whether the given variable is a WordPress Error.
 *
 * Returns whether `$thing` is an instance of the `WP_Error` class.
 *
 * @since WM-0.1.0
 * @since WP-2.1.0
 *
 * @param  mixed  $thing  The variable to check.
 * @return bool Whether the variable is an instance of WP_Error.
 *
 * @phpstan-assert-if-true WP_Error $thing
 */
function is_wp_error($thing)
{
    $is_wp_error = ($thing instanceof WP_Error);

    if ($is_wp_error) {
        /**
         * Fires when `is_wp_error()` is called and its parameter is an instance of WP_Error.
         *
         * @since WM-0.1.0
         * @since WP-5.6.0
         *
         * @param  WP_Error  $thing  The error object passed to `is_wp_error()`.
         */
        do_action('is_wp_error_instance', $thing);
    }

    return $is_wp_error;
}
