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
| 12 | `AGENTS.md` still says "nothing of the core is built yet". The checker's package name repeats the working name (one more thing to rename). | `AGENTS.md`, `conformance/composer.json` | Hooks review #12 |

| 13 | Table and column names containing backticks: routing names through `quote_identifier()` now escapes them on MySQL too, where WordPress emits them raw. Safer, but unrecorded and not pinned by `WpdbMySqlDialectTest`. | `wp-api/classes/wpdb.php` (`_insert_replace_helper`, `update`, `delete`) | PostgreSQL review #14 |
| 14 | Floats on PostgreSQL print PHP's way (`3.0`, `1.0E+20`, `INF`), not MySQL's (`3`, `1e20`). `bytea` values come back as a resource string instead of their bytes. | `app/Core/Database/WpdbConnection.php` (`rows()`) | PostgreSQL review #15 |
| 15 | Schema-qualified inserts (`INSERT INTO public.wp_options`) give `insert_id` 0; the shaping takes the table prefix from config, not `$wpdb->prefix`; in a joined query with two columns of the same name, the column-detail lookup can pick the wrong one. | `wp-api/classes/wpdb.php`, `app/Core/Database/PostgresDialect.php`, `WpdbConnection::sourceTables()` | PostgreSQL review #17–#19 |
| 16 | `WpdbTest::test_errors_are_reported_like_wordpress` only checks the table name on MySQL too; it could assert MySQL's exact "doesn't exist" message there. | `tests/Feature/Core/Database/WpdbTest.php` | PostgreSQL review #20 |
| 17 | Notes for plugin authors writing raw SQL on PostgreSQL: jsonb's `?` operator must be written `??` (PDO treats `?` as a placeholder); regular expressions don't work on `wp_ci` columns. `conformance/diverged.php`'s header says adapted tests live in `conformance/adapted/`; the PostgreSQL entries point to app tests. The step-4 plan still says port 5432 (it's 5437). | docs | PostgreSQL review #21–#22 |

## Known and accepted

- Commit `e3f4b16` says "68 need wp_list_pluck()"; it was 60 errors plus 8 float-deprecation failures. History isn't rewritten for a message. (Hooks review #11.)
