# Divergences from WordPress

**In plain words:** every place where we deliberately behave differently from WordPress is listed here, with which plugins it affects and how they get fixed (AGENTS.md rule 4). WordPress's tests for these behaviours are skipped as "diverged on purpose" (`conformance/diverged.php`), and tests describing our behaviour live in `conformance/adapted/`.

## wp_trigger_error() notices

**Since:** WM-0.1.0

| | |
|---|---|
| **What WordPress does** | With `WP_DEBUG` on, `wp_trigger_error()` (and so `_doing_it_wrong()`, `_deprecated_hook()` and the other deprecation helpers) raises `E_USER_NOTICE`, `E_USER_WARNING` or `E_USER_DEPRECATED` as a PHP error, after firing `wp_trigger_error_run`. `E_USER_ERROR` throws `WP_Exception`. |
| **What we do instead** | Notices and warnings go to Laravel's log (at notice and warning level); deprecations go to Laravel's deprecations log channel. No PHP error is raised, so `wp_trigger_error_run` does not fire for those levels. `wp_trigger_error_always_run` still fires for every level. `E_USER_ERROR` still throws `WP_Exception`, exactly as in WordPress. |
| **Why** | Laravel turns every PHP notice and warning into an exception. Raising them would take a page down whenever a plugin misuses a function, which WordPress only shows as a debug message. |
| **Plugins it breaks** | Plugins that listen on `wp_trigger_error_run` to collect debug messages (for example, debugging or query-monitor style tools) no longer see notices, warnings or deprecations through that action. |
| **How they are fixed** | Listen on `wp_trigger_error_always_run` instead, which fires for every level. Messages are also in Laravel's log. |
| **Implemented in** | `app/Core/Hooks/WordPressErrors.php`, wired by `app/Providers/CoreServiceProvider.php` through WordPress's own `wp_trigger_error_trigger_error` filter and `wp_trigger_error_always_run` action. |
| **WordPress tests** | `Tests_Functions_WpTriggerError`: `test_should_throw_exception` passes unchanged; the warning, notice and deprecation tests are diverged and adapted in `conformance/adapted/functions/wpTriggerError.php`. |

**Still open (owner's decision):** the wider policy for plugins' own raw PHP notices and warnings (not raised through WordPress), before real plugins are loaded.
