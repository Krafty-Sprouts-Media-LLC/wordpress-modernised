# Divergences from WordPress

**In plain words:** every place where we deliberately behave differently from WordPress is listed here, with which plugins it affects and how they get fixed (AGENTS.md rule 4). WordPress's tests for these behaviours are skipped as "diverged on purpose" (`conformance/diverged.php`), and tests describing our behaviour live in `conformance/adapted/`.

## `$wpdb->dbh` is a PDO connection, not mysqli

**Since:** WM-0.2.0

| | |
|---|---|
| **What WordPress does** | `$wpdb` connects with PHP's `mysqli` extension. `$wpdb->dbh` is a `mysqli` object. |
| **What we do instead** | `$wpdb` uses Laravel's database connection, which is PDO. `$wpdb->dbh` is that `PDO` object, the same one Laravel uses, so WordPress and Laravel share one connection and one transaction. `$wpdb`'s own methods behave as in WordPress. |
| **Why** | One of everything (AGENTS.md rule 2). With two connections, a save through `$wpdb` and a save through Laravel could half-succeed. Owner's decision A, 2026-11-05. |
| **Plugins it breaks** | Plugins that call `mysqli_*()` functions directly on `$wpdb->dbh` (for example `mysqli_real_escape_string( $wpdb->dbh, … )`, or `mysqli_insert_id( $wpdb->dbh )`). Rare: WordPress's documented API is `$wpdb`'s methods. |
| **How they are fixed** | Use `$wpdb`'s methods instead: `$wpdb->prepare()` or `esc_sql()` for escaping, `$wpdb->insert_id`, `$wpdb->last_error`, `$wpdb->get_results()`. |
| **Implemented in** | `wp-api/classes/wpdb.php` (the `mysqli` calls replaced), `app/Core/Database/WpdbConnection.php`. |
| **WordPress tests** | None diverge: WordPress's database tests only check that `$wpdb->dbh` exists and behaves as a property. Our behaviour is pinned by `tests/Feature/Core/Database/WpdbTest.php`. |

## `WP_DEBUG_DISPLAY` is off by default

**Since:** WM-0.2.0

| | |
|---|---|
| **What WordPress does** | `WP_DEBUG_DISPLAY` defaults to `true`: with `WP_DEBUG` on, errors (including database errors from `$wpdb`) are printed into the page. |
| **What we do instead** | It defaults to `false`; set `WP_DEBUG_DISPLAY=true` in `.env` (`config/wordpress.php`) to get WordPress's behaviour. Database errors still go to the error log. |
| **Why** | Laravel shows errors through its own error pages. Printed database errors leak query details into pages and API responses. |
| **Plugins it breaks** | None in behaviour. Developers who relied on seeing database errors in the page set it to `true`. |
| **How they are fixed** | `WP_DEBUG_DISPLAY=true` in `.env`. |
| **WordPress tests** | None diverge: the checker runs with WordPress's test configuration (`WP_DEBUG_DISPLAY` on). |

## Minimum PHP is 8.3

**Since:** WM-0.2.0

| | |
|---|---|
| **What WordPress does** | `$required_php_version` is `7.4`. |
| **What we do instead** | `8.3`, Laravel 13's minimum. |
| **Why** | Laravel owns the runtime (AGENTS.md rule 2). |
| **Plugins it breaks** | Plugins that only run on PHP older than 8.3. WordPress itself supports 8.3+, so plugins maintained for current WordPress are unaffected. |
| **How they are fixed** | Update the plugin to PHP 8.3, or use a maintained version. |

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
