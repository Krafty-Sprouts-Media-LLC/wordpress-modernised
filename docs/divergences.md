# Divergences from WordPress

**In plain words:** every place where we deliberately behave differently from WordPress is listed here, with which plugins it affects and how they get fixed (AGENTS.md rule 4). WordPress's tests for these behaviours are skipped as "diverged on purpose" (`conformance/diverged.php`), and tests describing our behaviour live in `conformance/adapted/`.

## WordPress's tables on PostgreSQL

**Since:** WM-0.3.0

| | |
|---|---|
| **What WordPress does** | Runs on MySQL or MariaDB only. Its tables use MySQL types, `_ci` collations (text compared without case), `0000-00-00 00:00:00` as an empty date, unsigned integers, and mixed-case column names (`ID`, `comment_ID`, `comment_post_ID`, `comment_author_IP`). |
| **What we do instead** | On PostgreSQL 18, the same 12 tables and columns, converted from WordPress's own definitions (`App\Core\Database\WordPressSchema::postgresStatements()`): <br>- identity columns for `auto_increment`;<br>- text in the ICU collation `wp_ci`, which ignores case **and accents**, as MySQL's `utf8mb4_unicode_520_ci` does (`'cafe' = 'café'`). One MySQL behaviour can't be matched: MySQL ignores trailing spaces in comparisons (`'a' = 'a '`), PostgreSQL doesn't;<br>- **empty dates stored as NULL**;<br>- **column names lowercase** (`id`, `comment_id`, …), because PostgreSQL folds unquoted names and WordPress's SQL doesn't quote them;<br>- unsigned not enforced;<br>- index names prefixed with their table (PostgreSQL index names are unique per schema). |
| **Why** | PostgreSQL support (owner's decisions A–C, 2026-11-05). PostgreSQL rejects `0000-00-00`, has no unsigned integers, and folds unquoted names to lowercase. |
| **Plugins it breaks** | On PostgreSQL only: plugins writing their own SQL that inserts `0000-00-00` dates, relies on unsigned overflow behaviour, quotes WordPress's mixed-case columns (`"ID"`), or uses MySQL-only syntax (API-level compatibility, AGENTS.md rule 3). MySQL and MariaDB are unchanged. |
| **How they are fixed** | Use `$wpdb`'s methods, which handle the differences; write portable SQL. |

## `$wpdb` on PostgreSQL

**Since:** WM-0.3.0

| | |
|---|---|
| **What WordPress does** | `$wpdb` speaks MySQL only. |
| **What we do instead** | On PostgreSQL, `$wpdb`'s **methods** work: `insert()`, `update()`, `delete()`, `replace()`, `get_var()`, `get_row()`, `get_col()`, `get_results()`, `prepare()`, `esc_sql()`, `insert_id`, `rows_affected`. A small dialect seam (`App\Core\Database\Dialect`) handles the differences:<br>- names are double-quoted and lowercased;<br>- escaping doubles quotes, as PostgreSQL's standard strings expect (backslash is ordinary text);<br>- `REPLACE` keeps MySQL's meaning (delete the clashing row, then insert; 2 rows affected);<br>- the insert ID comes from the table's identity sequence;<br>- in **date columns**, the empty date `0000-00-00 00:00:00` is stored as NULL and read back as `0000-00-00 00:00:00` (other columns keep that text as given);<br>- results from **WordPress's own tables** (`posts`, `users`, `comments`, `links`, matched exactly) get WordPress's column casing (`ID`, `comment_ID`) and empty dates; plugin tables come back as stored;<br>- an insert that gives its own ID sets `insert_id` to it and moves the table's counter past it, as MySQL's AUTO_INCREMENT does (identity and serial columns alike);<br>- `%i` identifiers use double quotes; the old `$wpdb->escape()` / `_weak_escape()` escape the PostgreSQL way (WordPress's `addslashes()` would allow injection there);<br>- WordPress's charset and length checks get MySQL-shaped column details from PostgreSQL's catalog (all text is UTF-8, `utf8mb4`). |
| **What stays different** | **Plugins' own SQL is passed through as written, never translated** (AGENTS.md rule 3): MySQL-only syntax (backticks, `LIMIT 0, 10`, MySQL functions) fails, with a note saying so. **NUL bytes are removed from text** (PostgreSQL text can't hold them). **Inside a transaction, each `$wpdb` query runs in its own savepoint**, so a failed query fails alone, as in MySQL (PostgreSQL would otherwise abort the whole transaction); this costs two small extra round trips per query inside transactions. `$wpdb->select()` can't switch to another database (PostgreSQL connections can't). `dbDelta()` is not supported (owner's decision D). A plugin's own `START TRANSACTION` / `COMMIT` / `SAVEPOINT` through `$wpdb->query()` run as written. Text columns report MySQL's `longtext`, so WordPress's smaller byte limits for `text`/`tinytext` don't apply. Expressions and aliases (`SELECT post_date_gmt AS d`, `MAX(post_modified)`) don't get the empty-date or casing mapping. **PostgreSQL needs PHP 8.4 or newer**, now the minimum everywhere (see *Minimum PHP is 8.4*). Lost connections are recognised from PostgreSQL's messages, so `$wpdb` reconnects as on MySQL. |
| **Why** | PostgreSQL support with API-level compatibility (owner's decisions, 2026-11-05). |
| **Plugins it breaks** | On PostgreSQL only: plugins that write MySQL-only SQL themselves, store NUL bytes in text, switch databases with `$wpdb->select()`, or create tables with `dbDelta()`. MySQL and MariaDB are unchanged (pinned by `WpdbMySqlDialectTest`). |
| **How they are fixed** | Use `$wpdb`'s methods and portable SQL; native plugins create tables with Laravel migrations. |
| **WordPress tests** | On PostgreSQL the checker runs all 994 tests: 683 pass, and the rest are listed in `conformance/diverged.php` (`'pgsql'`) or `waiting.php` with reasons. Diverged on PostgreSQL: all of `Tests_DB_dbDelta` (decision D) and `Tests_DB_Charset` (MySQL's character sets); `Tests_DB`'s three SQL-mode tests; three `prepare()` tests and one update test that check for MySQL's escaping or backticks in the SQL text. MySQL and MariaDB: unchanged (994 tests, 30 listed). |

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

## Minimum PHP is 8.4

**Since:** WM-0.2.0 (8.3); WM-0.4.0 (8.4)

| | |
|---|---|
| **What WordPress does** | `$required_php_version` is `7.4`. |
| **What we do instead** | `8.4`. |
| **Why** | Laravel owns the runtime (AGENTS.md rule 2). Laravel 13 itself allows 8.3, but its current Symfony 8 components need PHP 8.4.1, and PHP 8.3's PDO can silently corrupt some PostgreSQL queries (a value ending in a backslash followed by one containing `?` or `:name`). Owner's decision E, 2026-11-07. |
| **Plugins it breaks** | Plugins that only run on PHP older than 8.4. WordPress itself supports 8.4, so plugins maintained for current WordPress are unaffected. |
| **How they are fixed** | Update the plugin to PHP 8.4, or use a maintained version. |

## The object cache runs on Laravel's cache

**Since:** WM-0.4.0

| | |
|---|---|
| **What WordPress does** | `WP_Object_Cache` keeps values in memory for one request. A persistent cache (Redis, Memcached) needs a drop-in file, `wp-content/object-cache.php`, that replaces the class. `wp_cache_flush()` empties everything. |
| **What we do instead** | `WP_Object_Cache` hands its work to `App\Core\Cache\ObjectCache`: memory for the request, as in WordPress, plus any Laravel cache store named by `WP_OBJECT_CACHE_STORE` (owner's decision A, 2026-11-07). No drop-in: the store is a setting. With a store:<br>- `wp_using_ext_object_cache()` is true, so transients live in the store, as WordPress does with any persistent cache;<br>- `$expire` is honoured;<br>- `wp_cache_add_non_persistent_groups()` keeps those groups in memory;<br>- `wp_cache_flush()` and `wp_cache_flush_group()` move a version number instead of emptying the store, so Laravel's own entries survive;<br>- a value PHP can't serialize (a closure), or one holding a class the store may not rebuild, stays in memory for the request. The store may rebuild plain objects, WordPress's own value classes (`WP_Post`, `WP_Term`, `WP_User`, …), Laravel's `cache.serializable_classes`, and classes listed in `wordpress.object_cache_classes`. This keeps Laravel's protection against objects planted in the store;<br>- `wp_cache_incr()` and `wp_cache_decr()` read, change and write back, so they are not atomic across simultaneous requests, and the item loses any expiry.<br>`$wp_object_cache->cache`, `cache_hits` and `cache_misses` are read-only. `switch_to_blog()` keeps one site (no multisite). |
| **Why** | Laravel owns the cache (AGENTS.md rule 2). WordPress keeps the cache in memory by default because many hosts have no Redis, and that reason still holds (rule 6), so memory stays the default. |
| **Plugins it breaks** | Object-cache drop-ins (`object-cache.php`, such as Redis Object Cache): not loaded; set `WP_OBJECT_CACHE_STORE` instead. Plugins that write to `$wp_object_cache->cache` directly. With a persistent store, plugins caching their own classes get them from memory only, so each request rebuilds them. |
| **How they are fixed** | Use a Laravel cache store. Use the `wp_cache_*()` functions instead of the cache's internals. List a plugin's own cached classes in `wordpress.object_cache_classes`. |

## The `query` filter sees plugins' queries, not the core's

**Since:** WM-0.4.0

| | |
|---|---|
| **What WordPress does** | Every query WordPress runs goes through `$wpdb->query()`, so the `query` filter sees (and can rewrite) all of them, core and plugin alike. |
| **What we do instead** | The house runs its queries through Laravel's query builder (owner's decision C, 2026-11-07). The `query` filter sees the queries plugins send through `$wpdb`; the core's own queries don't pass through it. They are still counted in `get_num_queries()` and, with `SAVEQUERIES` on, logged in `$wpdb->queries` (`App\Core\Database\QueryCounter`). |
| **Why** | Laravel's query builder writes SQL that works on MySQL, MariaDB and PostgreSQL alike, so the core needs no translator. String-built SQL through `$wpdb` would have to be written three ways. |
| **Plugins it breaks** | Plugins that rewrite core queries through the `query` filter (rare: query-routing and sharding tools, which replace `$wpdb` in WordPress anyway). Debugging tools that read `$wpdb->queries` or `get_num_queries()` keep working. |
| **How they are fixed** | Rewrite against the area's own filters (such as `posts_request`), or listen to Laravel's `QueryExecuted` event. |

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
