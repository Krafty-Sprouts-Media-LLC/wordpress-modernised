# Divergences from WordPress

**In plain words:** every place where we deliberately behave differently from WordPress is listed here, with which plugins it affects and how they get fixed (AGENTS.md rule 4). WordPress's tests for these behaviours are skipped as "diverged on purpose" (`conformance/diverged.php`), and tests describing our behaviour live in `conformance/adapted/`.

## WordPress's tables on PostgreSQL

**Since:** WM-0.3.0

| | |
|---|---|
| **What WordPress does** | Runs on MySQL or MariaDB only. Its tables use MySQL types, `_ci` collations (text compared without case), `0000-00-00 00:00:00` as an empty date, unsigned integers, and mixed-case column names (`ID`, `comment_ID`, `comment_post_ID`, `comment_author_IP`). |
| **What we do instead** | On PostgreSQL 18, the same 12 tables and columns, converted from WordPress's own definitions (`App\Core\Database\WordPressSchema::postgresStatements()`): <br>- identity columns for `auto_increment`;<br>- text in the case-insensitive ICU collation `wp_ci`;<br>- **empty dates stored as NULL**;<br>- **column names lowercase** (`id`, `comment_id`, …), because PostgreSQL folds unquoted names and WordPress's SQL doesn't quote them;<br>- unsigned not enforced;<br>- index names prefixed with their table (PostgreSQL index names are unique per schema). |
| **Why** | PostgreSQL support (owner's decisions A–C, 2026-11-05). PostgreSQL rejects `0000-00-00`, has no unsigned integers, and folds unquoted names to lowercase. |
| **Plugins it breaks** | On PostgreSQL only: plugins writing their own SQL that inserts `0000-00-00` dates, relies on unsigned overflow behaviour, quotes WordPress's mixed-case columns (`"ID"`), or uses MySQL-only syntax (API-level compatibility, AGENTS.md rule 3). MySQL and MariaDB are unchanged. |
| **How they are fixed** | Use `$wpdb`'s methods, which handle the differences; write portable SQL. |

## `$wpdb` on PostgreSQL

**Since:** WM-0.3.0

| | |
|---|---|
| **What WordPress does** | `$wpdb` speaks MySQL only. |
| **What we do instead** | On PostgreSQL, `$wpdb`'s **methods** work: `insert()`, `update()`, `delete()`, `replace()`, `get_var()`, `get_row()`, `get_col()`, `get_results()`, `prepare()`, `esc_sql()`, `insert_id`, `rows_affected`. A small dialect seam (`App\Core\Database\Dialect`) handles the differences:<br>- names are double-quoted and lowercased;<br>- escaping doubles quotes, as PostgreSQL's standard strings expect (backslash is ordinary text);<br>- `REPLACE` keeps MySQL's meaning (delete the clashing row, then insert; 2 rows affected);<br>- the insert ID comes from the table's identity sequence;<br>- empty dates (`0000-00-00 00:00:00`) are stored as NULL and read back as `0000-00-00 00:00:00`;<br>- results give WordPress's column casing (`ID`, `comment_ID`) for WordPress's tables;<br>- every table counts as UTF-8 (`utf8mb4`), so MySQL's charset checks are skipped. |
| **What stays different** | **Plugins' own SQL is passed through as written, never translated** (AGENTS.md rule 3): MySQL-only syntax (backticks, `LIMIT 0, 10`, MySQL functions) fails, with a note saying so. **NUL bytes are removed from text** (PostgreSQL text can't hold them). **Inside a transaction, each `$wpdb` query runs in its own savepoint**, so a failed query fails alone, as in MySQL (PostgreSQL would otherwise abort the whole transaction); this costs two small extra round trips per query inside transactions. `$wpdb->select()` can't switch to another database (PostgreSQL connections can't). `dbDelta()` is not supported (owner's decision D). Lost connections are recognised from PostgreSQL's messages, so `$wpdb` reconnects as on MySQL. |
| **Why** | PostgreSQL support with API-level compatibility (owner's decisions, 2026-11-05). |
| **Plugins it breaks** | On PostgreSQL only: plugins that write MySQL-only SQL themselves, store NUL bytes in text, switch databases with `$wpdb->select()`, or create tables with `dbDelta()`. MySQL and MariaDB are unchanged (pinned by `WpdbMySqlDialectTest`). |
| **How they are fixed** | Use `$wpdb`'s methods and portable SQL; native plugins create tables with Laravel migrations. |

## `$wpdb->dbh` is a PDO connection, not mysqli

**Since:** WM-0.2.0

| | |
|---|---|
| **What WordPress does** | `$wpdb` connects with PHP's `mysqli` extension. `$wpdb->dbh` is a `mysqli` object. |
| **What we do instead** | `$wpdb` uses Laravel's database connection, which is PDO. For the site's database, `$wpdb->dbh` is always Laravel's current `PDO` object, opened on first use, so WordPress and Laravel share one connection and one transaction. `$wpdb`'s documented methods return what WordPress's do (values as strings, `null` for NULL, the same return values from `query()`), checked by WordPress's own database tests. Queries use the text protocol, as mysqli does. |
| **Why** | One of everything (AGENTS.md rule 2). With two connections, a save through `$wpdb` and a save through Laravel could half-succeed. Owner's decision A, 2026-11-05. |
| **What sharing one connection means** | **Transactions:** a plugin's `$wpdb->query( 'START TRANSACTION' )` inside Laravel's `DB::transaction()` makes MySQL commit Laravel's earlier writes, and Laravel's rollback then undoes nothing. The other way round, `DB::transaction()` while a plugin's raw transaction is open fails ("There is already an active transaction"). **Switching databases:** `$wpdb->select( 'other' )` switches Laravel's connection too, until switched back. **Charset:** `$wpdb->set_charset()` changes Laravel's connection charset too. **Closing:** `$wpdb->close()` detaches `$wpdb` but leaves Laravel's connection open. **Visibility:** `$wpdb`'s queries appear in `DB::listen()` and Laravel's query log, like Laravel's own. |
| **Plugins it breaks** | Plugins that call `mysqli_*()` functions directly on `$wpdb->dbh` or `$wpdb->result` (for example `mysqli_real_escape_string( $wpdb->dbh, … )`, `mysqli_insert_id( $wpdb->dbh )`, `mysqli_fetch_assoc( $wpdb->result )`). Rare: WordPress's documented API is `$wpdb`'s methods. Plugins that run their own `START TRANSACTION` while Laravel code is mid-transaction (see above). |
| **How they are fixed** | Use `$wpdb`'s methods instead: `$wpdb->prepare()` or `esc_sql()` for escaping, `$wpdb->insert_id`, `$wpdb->last_error`, `$wpdb->get_results()`. For transactions, a later step may route `$wpdb`'s `START TRANSACTION`/`COMMIT`/`ROLLBACK` through Laravel's transaction manager (owner's decision). |
| **Not identical yet** | `$wpdb->get_col_info()` returns the column name, table, length, type and the main flags, but not every mysqli flag bit, the character-set number, or the original name behind an alias. |
| **Implemented in** | `wp-api/classes/wpdb.php` (the `mysqli` calls replaced), `app/Core/Database/WpdbConnection.php`. |
| **WordPress tests** | None diverge: WordPress's database tests only check that `$wpdb->dbh` exists and behaves as a property. Our behaviour is pinned by `tests/Feature/Core/Database/WpdbTest.php` and `WpdbConnectionLifecycleTest.php`. |

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
