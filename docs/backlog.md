# Backlog

**In plain words:** small things found by reviews that were not fixed yet, and decisions still waiting for the owner. Each item says what it is, where it is, and where it came from. When an item is done, delete it here in the same commit. When the repository is pushed, these can become GitHub issues.

## Waiting for the owner's decision

None right now. Decided items move to the decisions table in `AGENTS.md`.

## Planned for a later step (decided)

| # | What | Where | From |
|---|---|---|---|
| P1 | Route `$wpdb`'s `START TRANSACTION` / `COMMIT` / `ROLLBACK` through Laravel's transaction manager. Today a plugin's own `START TRANSACTION` inside Laravel's `DB::transaction()` commits Laravel's earlier writes, and the reverse fails. | `wp-api/classes/wpdb.php`, `app/Core/Database/WpdbConnection.php`; recorded in `docs/divergences.md` | Database review #6; owner decision D2 |

## Fixes not done yet

| # | What | Where | From |
|---|---|---|---|
| 1 | Deciding whether a plugin's `new wpdb( … )` is "the site's database": the password is not compared; a host without a port matches the site's port (WordPress means 3306); WordPress's socket host forms (`host:/path.sock`, `host:port:/path.sock`) are not understood, because `parse_db_host()` is bypassed. | `app/Core/Database/WpdbConnection.php` (`for()`, `sameDatabase()`), `wp-api/classes/wpdb.php` (`db_connect()`) | Database review #8 |
| 2 | `$wpdb->get_col_info()` returns name, table, length, type and the main flags, but not every mysqli flag bit (NUM, AUTO_INCREMENT, PART_KEY, NO_DEFAULT), the character-set number, or the original name behind an alias. Recorded in `docs/divergences.md`. | `app/Core/Database/WpdbConnection.php` (`columns()`), `WpdbResult.php` docblock | Database review #9 |
| 3 | `$wpdb->set_charset()` on the site connection sends `SET NAMES` but PDO's own escaping charset doesn't follow. Only matters for gbk, big5 or sjis connections. | `app/Core/Database/WpdbConnection.php` (`setCharset()`) | Database review #13 |
| 4 | Each new `$wpdb` adds a `query` filter in `placeholder_escape()`; in tests the app boots many times, so these pile up. | `wp-api/classes/wpdb.php` (`placeholder_escape()`), `app/Providers/CoreServiceProvider.php` | Database review #15 |
| 5 | The `schema.php` file docblock names the wrong WordPress path: it should be `src/wp-admin/includes/schema.php` (AGENTS.md rule 8). | `wp-api/functions/schema.php` | Database review #11 |
| 7 | A leftover empty `wpdb` placeholder: the real `wpdb` now exists, so the checker's stand-in is dead code, and the stand-ins list still names it. | `conformance/includes/stand-ins.php`, `docs/stand-ins.md` | Database review #12 |
| 8 | The table-prefix test doesn't check that `$wpdb->prefix` and `$wpdb->posts` follow `config('wordpress.table_prefix')`. | `tests/Feature/Core/Database/WordPressTablesTest.php` | Database review #16 |
| 9 | The plugin-table `dbDelta()` test creates a real table inside a test transaction (MySQL commits it), and leaves the table behind if an assertion fails before the drop. | `tests/Feature/Core/Database/DbDeltaTest.php` | Database review #16 |
| 10 | The test for "hook globals must be declared global" can't fail for that bug: functions declare the globals themselves. It needs a test run in a separate PHP process that checks the globals before any hook call. | `tests/Unit/Core/HookBusTest.php` | Hooks review #6 |
| 11 | In the checker, Laravel's shutdown handler stays installed after its error handlers are removed; harmless except possibly a duplicate message on a fatal error. | `conformance/bootstrap.php` | Hooks review #10 |

| 13 | Table and column names containing backticks: routing names through `quote_identifier()` now escapes them on MySQL too, where WordPress emits them raw. Safer, but unrecorded and not pinned by `WpdbMySqlDialectTest`. | `wp-api/classes/wpdb.php` (`_insert_replace_helper`, `update`, `delete`) | PostgreSQL review #14 |
| 14 | Floats on PostgreSQL print PHP's way (`3.0`, `1.0E+20`, `INF`), not MySQL's (`3`, `1e20`). `bytea` values come back as a resource string instead of their bytes. | `app/Core/Database/WpdbConnection.php` (`rows()`) | PostgreSQL review #15 |
| 15 | Schema-qualified inserts (`INSERT INTO public.wp_options`) give `insert_id` 0; the shaping takes the table prefix from config, not `$wpdb->prefix`; in a joined query with two columns of the same name, the column-detail lookup can pick the wrong one. | `wp-api/classes/wpdb.php`, `app/Core/Database/PostgresDialect.php`, `WpdbConnection::sourceTables()` | PostgreSQL review #17–#19 |
| 16 | `WpdbTest::test_errors_are_reported_like_wordpress` only checks the table name on MySQL too; it could assert MySQL's exact "doesn't exist" message there. | `tests/Feature/Core/Database/WpdbTest.php` | PostgreSQL review #20 |
| 17 | Notes for plugin authors writing raw SQL on PostgreSQL: jsonb's `?` operator must be written `??` (PDO treats `?` as a placeholder); regular expressions don't work on `wp_ci` columns. `conformance/diverged.php`'s header says adapted tests live in `conformance/adapted/`; the PostgreSQL entries point to app tests. The step-4 plan still says port 5432 (it's 5437). | docs | PostgreSQL review #21–#22 |
| 18 | Options cached in a persistent store are serialized text that `get_option()` unserializes without limits, so someone who can write to the store (or the options table) can plant objects, as in WordPress. Closed by moving stored values to JSON (decision B). | `wp-api/functions/values.php` (`maybe_unserialize()`) | Step-5 review #5 |
| 19 | The checker always runs memory-only. Add a CI run with `WP_OBJECT_CACHE_STORE=array`; run by hand on 2026-11-07, only `Tests_Cache::test_wp_cache_flush_group` failed (it expects a persistent cache to refuse group flushes). The checker would need per-cache-mode entries in `diverged.php`. | `.github/workflows/tests.yml`, `conformance/diverged.php` | Step-5 review #6 |
| 20 | `wp_cache_incr()`/`decr()` on a value like `'1.5'`, or past `PHP_INT_MAX`, gives a float, which the engine's `int` return type rejects (TypeError); WordPress returns the float. | `app/Core/Cache/ObjectCache.php` (`adjust()`) | Step-5 review #7 |
| 21 | `composer.json` says `^8.4`, but Symfony 8 needs 8.4.1: make it `^8.4.1`. | `composer.json` | Step-5 review #8 |
| 22 | A Laravel listener on `OptionAdded`/`Updated`/`Deleted` that returns `false` stops later listeners, including the bridge, so plugins miss the WordPress action; `Event::fake()` silences them too. Document it, or make the bridge ignore listener return values. | `app/Core/Hooks/HookBus.php` (`bridge()`) | Step-5 review #9 |
| 23 | If the persistent cache store is down (Redis unreachable), every `wp_cache_*()` and so every `get_option()` throws. WordPress's popular drop-ins fall back to memory. Catch store errors and fall back, or record it. | `app/Core/Cache/ObjectCache.php` | Step-5 review #10 |
| 24 | Small fixes: `wp-api/interim/sanitize-option.php` lacks its "Contains code from WordPress 7.1.4: src/wp-includes/formatting.php" line (rule 8); on Laravel's database cache store `wp_cache_delete()` of a missing key returns true (its `forget()` always does); the transient clean-up's SELECT-then-DELETE can delete a transient refreshed in between (a cache miss only). | several | Step-5 review #11–#13 |

## Known and accepted

- Commit `e3f4b16` says "68 need wp_list_pluck()"; it was 60 errors plus 8 float-deprecation failures. History isn't rewritten for a message. (Hooks review #11.)
