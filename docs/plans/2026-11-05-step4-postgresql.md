# Step 4: PostgreSQL. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

## In plain words

**The goal:** the same app runs on PostgreSQL as well as MySQL and MariaDB, with the doors (WordPress's functions and `$wpdb`'s methods) working the same on all three.

**What changed:** you chose API-level compatibility. So there is **no translator**: plugins that write their own MySQL-style SQL are not promised to work on PostgreSQL. What must work is everything that goes through the doors.

**What we build:**
1. PostgreSQL 18 in Docker, beside MySQL and MariaDB.
2. WordPress's 12 tables on PostgreSQL: the same columns, with PostgreSQL types that behave the way the doors expect (text compared without caring about upper and lower case; empty dates stored as "no date").
3. `$wpdb`'s methods on PostgreSQL:
   - `insert()`, `update()`, `delete()`, `replace()`, `get_results()` and the others;
   - safe escaping;
   - the right quoting;
   - the right ID after an insert.

   A plugin's own MySQL-only SQL gets a clear error naming the problem.
4. WordPress's database tests run on PostgreSQL: the ones about the doors pass, and the ones that test MySQL itself are recorded as MySQL-only.
5. PostgreSQL in the GitHub tests.

**Decisions for you (my recommendation first):**

| # | Question | Recommendation | Why |
|---|---|---|---|
| A | Minimum PostgreSQL version? | **18** (current: 18.6). | The first version that can match text without caring about case, the way MySQL does, including in `LIKE` searches, which WordPress's doors rely on (for example, looking up a page by its name). |
| B | Empty dates on PostgreSQL? | **"No date" (NULL).** | PostgreSQL rejects MySQL's `0000-00-00`. NULL is the proper fix, and it's where MySQL will go too when we redesign dates (backlog D1). The doors turn NULL back into WordPress's `0000-00-00 00:00:00` for plugins until then. |
| C | Upper and lower case in text? | **Case-insensitive, like MySQL** (a PostgreSQL 18 collation on WordPress's text columns). | Doors like `get_option( 'Foo' )` and unique names behave the same on every database. |
| D | `dbDelta()` (plugins creating their own tables with MySQL-style table definitions) on PostgreSQL? | **Not supported yet; recorded.** Native plugins create tables with Laravel migrations, which work everywhere. | `dbDelta()` reads MySQL table definitions by design. Supporting it on PostgreSQL is a translator in miniature; it can come with the plugin-loading step if your plugins need it. |

---

**Goal:** On PostgreSQL 18, the app boots, WordPress's 12 tables exist with door-compatible behaviour, and `$wpdb`'s documented methods behave as on MySQL. WordPress 7.1.4's database tests pass on PostgreSQL except recorded MySQL-only ones. MySQL and MariaDB are unchanged.

**Architecture:**
- **No translator.** `$wpdb` gets a small dialect seam: `App\Core\Database\Dialect`, with implementations `MySqlDialect` and `PostgresDialect`, chosen by the connection's driver. The dialect decides:
  - how identifiers are quoted;
  - how text is escaped;
  - how `REPLACE` and the insert ID work;
  - how the character-set helpers answer (PostgreSQL is all UTF-8).
- **`$wpdb`'s builders use the seam.** `_insert_replace_helper()`, `update()`, `delete()` and `quote_identifier()` go through it instead of hard-coded backticks.
- **Raw SQL is passed to the database as written.** On PostgreSQL, a MySQL-only query fails with PostgreSQL's error plus our note: "This query uses MySQL-only syntax; plugins' own SQL is not translated (AGENTS.md rule 3)."
- **Empty dates.** The doors read PostgreSQL's NULL dates as `0000-00-00 00:00:00`. In this step, that is a result-mapping rule in `WpdbConnection` for WordPress's date columns.

**Tech Stack:** PHP 8.3+, Laravel 13.40, PostgreSQL 18.6 (`postgres:18`), PDO pgsql.

**Spec:** `AGENTS.md` (decisions, including Compatibility: API-level; rules 2–8 and 11) and `docs/coding-standards.md`. Decisions A–D become binding when the owner approves this plan.

## Global Constraints

- MySQL and MariaDB behaviour must not change. Every existing test passes on both, byte-for-byte, as before.
- **Escaping on PostgreSQL must be PostgreSQL-correct.** PostgreSQL's standard strings don't treat backslash as an escape. So `$wpdb->_real_escape()`, `prepare()` and `esc_sql()` must double quotes PostgreSQL's way, never with MySQL's backslash, and must refuse or strip NUL bytes. This is the security-critical part of the step.
- PostgreSQL 18 minimum. The ICU collation `wp_ci` (`provider = icu`, `locale = 'und-u-ks-level2'`, `deterministic = false`) is created by the migration and used on WordPress's text columns.
- No query is rewritten by text search-and-replace. `$wpdb` builds dialect-correct SQL in its own methods; raw SQL passes through untouched.
- The usual rules apply:
  - WordPress test files run unmodified.
  - Run `vendor/bin/pint` with no path arguments.
  - Nothing private goes in tracked files.
  - Nothing is pushed until the owner says so.

## Review Focus

1. **Escaping turned into injection on PostgreSQL.** Expect values containing `'`, `\`, `\'`, NUL and `'; DROP TABLE wp_options; --`, inserted through `$wpdb->insert()` and through `$wpdb->prepare()` plus `query()`, to round-trip exactly, with `wp_options` intact. Test: Task 3, `test_escaping_is_postgresql_safe`.
2. **Case-insensitive behaviour.** Expect a second option `foo` to be refused after `Foo` (unique key), and `post_name = 'Hello'` and `LIKE 'hel%'` to match `hello`. Test: Task 2, `test_text_compares_without_case_like_mysql`.
3. **Empty dates.** Expect `$wpdb->insert()` with `post_date_gmt = '0000-00-00 00:00:00'` to store NULL, and reading the row back through `$wpdb` to return `'0000-00-00 00:00:00'`. Test: Task 3, `test_empty_dates_read_back_as_wordpress_expects`.
4. **IDs and counts.** Expect `insert_id`, `rows_affected`, and `replace()`'s return value to match MySQL for the same sequence. Test: Task 3, `test_ids_and_counts_match_mysql`.
5. **MySQL and MariaDB untouched.** Expect the dialect seam on MySQL to produce byte-identical SQL to before (backticks, backslash escaping). Test: Task 3, `test_mysql_dialect_is_unchanged`, plus the whole existing suite on both.

---

### Task 1: PostgreSQL 18 beside MySQL and MariaDB

**Files:**
- Modify: `compose.yaml`. Add `postgres` (`postgres:18`, port 5432), with a health check, a named volume, and `compose/init-postgres.sql`. The script creates `wordpress`, `wordpress_testing` and `wordpress_conformance`, owned by `wordpress`.
- Modify: `.env.example`, adding a PostgreSQL comment (`DB_CONNECTION=pgsql`, `DB_PORT=5432`).
- Modify: `app/Providers/CoreServiceProvider.php`. `bootDatabase()` accepts `pgsql`.
- Modify: tests that are MySQL-specific by nature (`WordPressTablesTest::test_tables_match_wordpress_7_1_4`, the MySQL version and mysqli-parity checks in `WpdbTest`, `DbDeltaTest`). They skip on `pgsql` with a reason.
- Test: `tests/Feature/Core/Database/PostgresConnectionTest.php` (pgsql only).

**Steps:**

- [ ] **Step 1: Write the failing tests.**
  - `test_app_tests_run_on_postgresql_18()`: the driver is `pgsql`, and `server_version_num` is at least 180000.
  - `test_laravel_tables_work_on_postgresql()`: a cache item, a session row and a job row round-trip.
- [ ] **Step 2: Run them** with `DB_CONNECTION=pgsql DB_PORT=5432 php artisan test --filter=Postgres`. Expected: the connection is refused.
- [ ] **Step 3: Implement.** Run `docker compose up -d --wait`.
- [ ] **Step 4: Run all app tests on all three databases.** Expected: MySQL and MariaDB unchanged. On PostgreSQL, the new tests pass and the MySQL-specific ones are skipped with reasons. Note the remaining failures (Tasks 2–3).
- [ ] **Step 5: Commit** with the message `Add PostgreSQL 18 beside MySQL and MariaDB`.

### Task 2: WordPress's tables on PostgreSQL

**Files:**
- Modify: `app/Core/Database/WordPressSchema.php`. Add `postgresStatements(string $prefix): array<string, list<string>>`. It maps each of the 12 tables as follows:

  | MySQL | PostgreSQL |
  |---|---|
  | `bigint(20) unsigned NOT NULL auto_increment` | `bigint GENERATED BY DEFAULT AS IDENTITY` |
  | other `bigint` / `int` / `tinyint` | `bigint` / `integer` / `smallint` (unsigned not enforced; recorded) |
  | `varchar(n)` | `varchar(n) COLLATE wp_ci` |
  | any `text` type | `text COLLATE wp_ci` |
  | `datetime NOT NULL default '0000-00-00 00:00:00'` | `timestamp(0) NULL DEFAULT NULL` (decision B) |
  | `KEY k (col(191))` | `CREATE INDEX {table}_k ON {table} (col)` (index names are unique per schema in PostgreSQL) |
  | `PRIMARY KEY`, `UNIQUE KEY` | the same |

- Modify: `database/migrations/0001_01_02_000000_create_wordpress_tables.php`. On `pgsql`, `CREATE COLLATION IF NOT EXISTS wp_ci …`, then `postgresStatements()`; `down()` drops the tables.
- Modify: `docs/divergences.md`, adding "WordPress's tables on PostgreSQL" (empty dates are NULL; unsigned not enforced; index names prefixed).
- Test: `tests/Feature/Core/Database/WordPressTablesPostgresTest.php` (pgsql only).

**Steps:**

- [ ] **Step 1: Write the failing tests.**
  - `test_tables_have_wordpress_columns_on_postgresql()`: for each table, the column names and order equal the MySQL fixture's, and the types follow the mapping.
  - `test_text_compares_without_case_like_mysql()` (Review Focus 2).
  - `test_empty_dates_are_null()`: a post inserted without dates has NULL `post_date_gmt`.
- [ ] **Step 2: Run them.** Expected: they fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run all app tests on all three databases.**
- [ ] **Step 5: Commit** with the message `Create WordPress's tables on PostgreSQL`.

### Task 3: `$wpdb`'s methods on PostgreSQL

**Files:**
- Create: `app/Core/Database/Dialect.php` (interface) and `MySqlDialect.php` / `PostgresDialect.php`. Each provides:
  - `quoteIdentifier(string): string`;
  - `escape(PDO, string): string` (PostgreSQL: double single quotes, reject NUL);
  - `replaceSql(string $table, array $columns, string $values): string` (PostgreSQL: `INSERT … ON CONFLICT (primary key) DO UPDATE`, with the primary key read from the catalog);
  - `lastInsertId(PDO, string $table): int` (PostgreSQL: `currval(pg_get_serial_sequence(…))`);
  - `charsetFor(string $table, ?string $column): string|false` (PostgreSQL: `'utf8mb4'`);
  - `serverInfo(PDO): string` (PostgreSQL: `'18.x-PostgreSQL'`).
- Modify: `app/Core/Database/WpdbConnection.php`.
  - It exposes `dialect(): Dialect`.
  - `escape()` and `lastInsertId()` delegate to the dialect.
  - Results on PostgreSQL map NULL in WordPress's date columns (`*_date`, `*_date_gmt`, `*_modified`, `*_modified_gmt`, `user_registered`, `link_updated`) to `'0000-00-00 00:00:00'`.
  - On PostgreSQL, an error on raw SQL adds the "MySQL-only syntax" note.
- Modify: `wp-api/classes/wpdb.php`. Each change gets `@since WM-0.3.0 Changed: …`.
  - `quote_identifier()` uses the dialect;
  - `_insert_replace_helper()`, `update()` and `delete()` build identifiers through `quote_identifier()`, and `REPLACE` through the dialect;
  - on PostgreSQL, the charset helpers (`get_table_charset()`, `get_col_charset()`, `get_col_length()`, `check_safe_collation()`, `strip_invalid_text()`) answer from the dialect, or strip invalid UTF-8 in PHP, instead of running MySQL's `SHOW` commands;
  - `insert()` and `update()` turn `'0000-00-00 00:00:00'` into NULL for date columns on PostgreSQL.
- Modify: `docs/divergences.md`, adding "`$wpdb` on PostgreSQL" (no translation of raw SQL; charset helpers; empty-date mapping; `dbDelta()` not supported yet).
- Test: `tests/Feature/Core/Database/WpdbPostgresTest.php` (pgsql only), plus `tests/Unit/Core/Database/MySqlDialectTest.php`.

**Steps:**

- [ ] **Step 1: Write the failing tests.**
  - `test_escaping_is_postgresql_safe()` (Review Focus 1).
  - `test_empty_dates_read_back_as_wordpress_expects()` (Review Focus 3).
  - `test_ids_and_counts_match_mysql()` (Review Focus 4).
  - `test_mysql_only_raw_sql_fails_with_a_clear_note()`: `$wpdb->query( 'SELECT `ID` FROM wp_posts LIMIT 0, 1' )` returns false, and `last_error` contains "MySQL-only syntax".
  - Every non-MySQL-specific `WpdbTest` case also passes on PostgreSQL.
  - `MySqlDialectTest::test_mysql_dialect_is_unchanged()` (Review Focus 5): identifiers, escaping and `REPLACE` SQL are identical to WordPress's.
- [ ] **Step 2: Run them.** Expected: they fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run all app tests on all three databases.** Expected: all pass, apart from the recorded MySQL-only skips.
- [ ] **Step 5: Commit** with the message `Run $wpdb's methods on PostgreSQL`.

### Task 4: WordPress's database tests on PostgreSQL

**Files:**
- Modify: `conformance/bootstrap.php` and `conformance/includes/WP_UnitTestCase.php` so the checker runs with `DB_CONNECTION=pgsql`. The migrations create `wp_ci`, and the transactions are Laravel's (unchanged).
- Modify: `conformance/diverged.php` and `waiting.php`, accepting entries per driver: `'pgsql' => [ 'Tests_DB::…' => 'reason' ]`. Each WordPress database test that runs MySQL-only SQL, or tests MySQL's own charset behaviour, is listed with its reason, after running it.
- Modify: `docs/divergences.md` with the counts.

**Steps:**

- [ ] **Step 1: Run the checker on PostgreSQL** (`DB_CONNECTION=pgsql DB_PORT=5432 composer conformance`) and record every failure.
- [ ] **Step 2: Sort each failure.**
  - A door bug: fix it, with an app test first.
  - MySQL-only SQL or charset behaviour in the test itself: list it as diverged on PostgreSQL, with the reason.
  - Needs a later step: list it as waiting.
- [ ] **Step 3: Run the checker on all three databases.** Expected: exit 0 on each.
- [ ] **Step 4: Commit** with the message `Run WordPress's database tests on PostgreSQL`, with the counts per database.

### Task 5: PostgreSQL in CI

**Files:**
- Modify: `.github/workflows/tests.yml`. Add `postgres:18` (PHP 8.5) to the database matrix, with a health check, `DB_CONNECTION=pgsql`, and the init script run with `psql`.

**Steps:**

- [ ] **Step 1: Write the matrix row.** Check the current action majors again (rule 11).
- [ ] **Step 2: Run locally on all three databases.** Expected: the same results as Task 4.
- [ ] **Step 3: Commit** with the message `Test against PostgreSQL 18 in CI`.

## After this plan (not in scope)

- **`dbDelta()` on PostgreSQL:** only if the plugin-loading step shows your plugins need it (decision D).
- **Empty dates as NULL on MySQL too** (backlog D1), which brings strict mode back. A table redesign, with its plugin cost.
- **The other table redesigns API-level compatibility allows,** each its own step with your OK: faster meta storage, JSON instead of PHP-serialized values, links stored by ID.
