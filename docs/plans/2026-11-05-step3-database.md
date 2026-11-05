# Step 3: The database. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

## In plain words

**What we build:** WordPress's database, running on Laravel.

1. **Test databases.** Real MySQL and MariaDB servers run in Docker on your computer, and on GitHub later, so every test talks to a real database.
2. **WordPress's 12 tables**, exactly as WordPress has them (same names, same columns), created the Laravel way. We do **not** split or change them in this step.
3. **The checker gets a database.** Each WordPress test runs inside a database "undo" (a transaction), so tests never leave a mess.
4. **`$wpdb`, the door every plugin uses for the database,** keeps WordPress's shape. Underneath, it uses **Laravel's one database connection**.
5. **`dbDelta()`**: the door plugins use to create their own tables when they're switched on.
6. **Tests on GitHub** across MySQL and MariaDB versions, once pushing is allowed.

**Decisions for you (my recommendation first):**

| # | Question | Recommendation | Why |
|---|---|---|---|
| A | One database connection or two? | **One: Laravel's.** `$wpdb` uses Laravel's connection. | Saves from WordPress and from Laravel then succeed or fail together. The cost: the few plugins that call PHP's `mysqli_*()` functions directly on `$wpdb->dbh` break. It gets recorded, with how to fix them. |
| B | Multisite (many sites in one install)? | **Not now.** Single-site tables only; multisite later if your sites need it. | It adds 6 tables and a lot of special cases. |
| C | Laravel's own `users` table? | **Remove it.** WordPress's `wp_users` is the one user table, and logins come in their own step. | Two user tables means two login systems (AGENTS.md rule 2: one of everything). |
| D | Which database versions? | **MySQL 9.7 for daily work.** GitHub tests MySQL 8.4 and 9.7, plus MariaDB 11.8 and 12.3 (all long-term-support versions). | Covers what real hosts run. WordPress recommends MySQL 8.0+ or MariaDB 10.11+. |
| E | Table name prefix? | **`wp_`, changeable in settings,** as in WordPress. | Plugins expect `$wpdb->prefix`. |

**Not in this step:** PostgreSQL (the next plan, with a choice of how to translate plugins' MySQL queries), settings (`get_option()`), and any change to WordPress's table design.

---

**Goal:** `$wpdb`, WordPress's 12 single-site tables and `dbDelta()` work on Laravel's database connection with MySQL and MariaDB, and pass WordPress 7.1.4's own database tests (`db.php`, `db/realEscape.php`, `db/charset.php`, `db/dbDelta.php`), with every failure either fixed or recorded.

**Architecture:**
- **The tables** are created by a Laravel migration that matches WordPress 7.1.4's `wp_get_db_schema()` column for column and index for index, using the table prefix from `config('wordpress.table_prefix')`.
- **The `wpdb` door** (`wp-api/classes/wpdb.php`) is carried over from 7.1.4. Its roughly 40 lines that call `mysqli_*` are replaced by calls to a typed house service, `App\Core\Database\WpdbConnection`, which uses the PDO of Laravel's connection. `$wpdb->dbh` is that PDO.
- **Plugin connections to another database** (`new wpdb( $user, $pass, $name, $host )` for a different database) get their own Laravel on-demand connection (`DB::build()`).
- **The global `$wpdb`** is created by `CoreServiceProvider`, which also defines WordPress's `DB_*` constants from Laravel's config.

**Tech Stack:** PHP 8.3+, Laravel 13.40, PDO MySQL, Docker Compose, MySQL 9.7 / 8.4, MariaDB 12.3 / 11.8. PHPUnit 12 for app tests, PHPUnit 9.6 for the checker.

**Spec:** `AGENTS.md` (decisions; rules 2–8) and `docs/coding-standards.md`. Decisions A–E above become binding once the owner approves this plan.

## Global Constraints

- WordPress baseline is 7.1.4. Read `wpdb`, `schema.php` and `upgrade.php` from the 7.1.4 tag. The checker's checkout has `src/wp-includes`. Add `src/wp-admin/includes` to the fetch script's sparse checkout, because `schema.php` and `upgrade.php` live there.
- Tables keep WordPress 7.1.4's exact names, columns, types, defaults, indexes and index prefix lengths. **No table design changes in this step.**
- Charset `utf8mb4`, collation `utf8mb4_unicode_520_ci`: what `wpdb::determine_charset()` picks on MySQL 5.6+ and MariaDB. Set it on the Laravel `mysql` and `mariadb` connections.
- **One connection (decision A).** The global `$wpdb` never opens a connection of its own when its credentials match Laravel's default connection.
- **The doors call the house, never Laravel directly.** Code in `wp-api/` may call `App\Core\...` services, but never `Illuminate\...`. Update `docs/coding-standards.md` §2 to say this, replacing "no Laravel inside wp-api/".
- Every difference from WordPress is recorded in `docs/divergences.md` (rule 4), skipped in `conformance/diverged.php`, and adapted in `conformance/adapted/` (rule 5). Tests that depend on later steps go in `conformance/waiting.php`.
- The usual rules apply:
  - WordPress test files run unmodified.
  - Run `vendor/bin/pint` with no path arguments.
  - Nothing from `private/` goes in tracked files.
  - Nothing is pushed until the owner says so.

## Review Focus

1. **Two connections by accident.** If `$wpdb` opens its own PDO, a save made through `$wpdb` inside `DB::transaction()` is not rolled back with it. Expect `$wpdb->dbh === DB::connection()->getPdo()`, and expect a `$wpdb->insert()` inside a rolled-back Laravel transaction to be gone afterwards. Test: Task 4, `test_wpdb_shares_laravels_transaction`.
2. **The table prefix out of step.** Expect the tables, `$wpdb->prefix`, and `$wpdb->posts` and the other table names to follow `config('wordpress.table_prefix')` together. Test: Task 2, `test_tables_follow_configured_prefix`.
3. **Text mangled on the way in or out.** Emoji and other 4-byte characters need `utf8mb4` everywhere. Expect text saved through `$wpdb` to read back identically through Eloquent and through `$wpdb`. Test: Task 4, `test_four_byte_text_round_trips`.
4. **MariaDB and MySQL behave differently.**
   - Version strings look like `11.8.9-MariaDB`.
   - Laravel has a separate `mariadb` driver.
   - `has_cap( 'utf8mb4_520' )` must be true on both.

   Expect `$wpdb->db_version()` to be a plain number and the capabilities to match WordPress's on every server in the CI matrix. Test: Task 4, `test_reports_version_and_capabilities`, run on all four servers in Task 6.
5. **Docker isn't running.** Expect a clear message, "Start the test databases: docker compose up -d", instead of a wall of connection errors. Test: Task 1, `test_missing_database_gives_a_clear_hint`.

---

### Task 1: Test databases

**Files:**
- Create: `compose.yaml` with two services. Each has a health check and a named volume, and the test database credentials are in `.env.example`.
  - `mysql` (`mysql:9.7`, port 3306);
  - `mariadb` (`mariadb:12.3`, port 3307).
- Modify: `.env.example`, setting `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=wordpress`, `DB_USERNAME=wordpress` and `DB_PASSWORD=wordpress`. Add a comment for MariaDB on port 3307, with `DB_CONNECTION=mariadb`.
- Modify: `config/database.php`, setting `collation` on `mysql` and `mariadb` to `env('DB_COLLATION', 'utf8mb4_unicode_520_ci')`.
- Modify: `phpunit.xml`. Replace the SQLite lines with `DB_CONNECTION=mysql` and `DB_DATABASE=wordpress_testing`, keeping the other DB values from `.env`.
- Create: `app/Core/Database/DatabaseAvailability.php`, with `public static function hint(Throwable $e): ?string`. It returns `Start the test databases: docker compose up -d` when `$e` is a connection-refused error, and `null` otherwise.
- Modify: `tests/TestCase.php`. When the first connection fails, fail with `DatabaseAvailability::hint()`.
- Create: `compose/mysql-init.sql`, which creates the databases `wordpress_testing` and `wordpress_conformance`, both granted to `wordpress`.
- Test: `tests/Unit/Core/Database/DatabaseAvailabilityTest.php` and `tests/Feature/Core/Database/ConnectionTest.php`.

**Interfaces:**
- Produces: the databases `wordpress` (local development), `wordpress_testing` (app tests) and `wordpress_conformance` (the checker), on MySQL and MariaDB.

**Steps:**

- [ ] **Step 1: Write the failing tests**
  - `test_missing_database_gives_a_clear_hint()`: `hint()` of a `PDOException` whose message contains `[2002]` returns the Docker hint; `hint( new RuntimeException( 'x' ) )` returns `null`.
  - `test_app_tests_run_on_mysql_or_mariadb()`: `DB::connection()->getDriverName()` is in `['mysql', 'mariadb']`, and `DB::scalar( 'select 1' )` is `1`.
- [ ] **Step 2: Run them.** Run `php artisan test --filter=Database`. Expected: the hint test errors (class not found), and the connection test fails, because the driver is `sqlite`.
- [ ] **Step 3: Implement the files above.** Run `docker compose up -d --wait`.
- [ ] **Step 4: Run all app tests.** Run `php artisan test`. Expected: all pass, on MySQL.
- [ ] **Step 5: Docs, format and commit.** In the Commands section of `AGENTS.md`, add `docker compose up -d --wait` as the first step before running tests. Commit with the message `Run tests on real MySQL and MariaDB (Docker)`.

### Task 2: WordPress's tables

**Files:**
- Create: `config/wordpress.php` with `'table_prefix' => env('WP_TABLE_PREFIX', 'wp_')`.
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`. Remove `users` and `password_reset_tokens`, and keep `sessions` (decision C). Delete `app/Models/User.php` and `database/factories/UserFactory.php`, and empty `DatabaseSeeder::run()`. Point `config/auth.php`'s provider at the `database` driver with table `null` and a comment that logins arrive in their own step. List "no login system yet" in `docs/stand-ins.md`.
- Create: `database/migrations/0001_01_02_000000_create_wordpress_tables.php`. It creates the 12 single-site tables of WordPress 7.1.4's `wp_get_db_schema( 'all' )` for a single site:
  - `terms`, `termmeta`, `term_taxonomy` and `term_relationships`;
  - `comments` and `commentmeta`;
  - `links` and `options`;
  - `posts` and `postmeta`;
  - `users` and `usermeta`.

  All are prefixed with `config( 'wordpress.table_prefix' )`. Use Laravel's schema builder for columns and plain indexes. Use one `DB::statement()` per table for indexes with prefix lengths (for example `meta_key(191)`, `post_name(191)`), which the builder can't express. The file docblock says `Contains code from WordPress 7.1.4: src/wp-admin/includes/schema.php.`
- Create: `tests/Fixtures/wordpress-7.1.4-schema.sql`, WordPress's own `CREATE TABLE` statements for those 12 tables with the `wp_` prefix, generated once from `wp_get_db_schema()` by a scratch script. Its header comment says where it came from.
- Test: `tests/Feature/Core/Database/WordPressTablesTest.php`

**Interfaces:**
- Produces: the 12 WordPress tables, and `config('wordpress.table_prefix')`.

**Steps:**

- [ ] **Step 1: Write the failing tests** (they use `RefreshDatabase`):
  - `test_tables_match_wordpress_7_1_4()`:
    1. Create the scratch schema `wm_schema_reference` and run the fixture SQL in it.
    2. Compare `information_schema.COLUMNS` (name, ordinal position, column type, nullability, default, extra) and `information_schema.STATISTICS` (index name, uniqueness, column order, sub-part length) for the 12 tables between the reference schema and the migrated one.
    3. Assert they are identical, then drop the scratch schema.
  - `test_tables_follow_configured_prefix()`: with the config set to `wmtest_`, run the WordPress-tables migration's `up()`, then assert that `Schema::hasTable( 'wmtest_posts' )` is true and `wp_posts` is unaffected. Then `down()`.
  - `test_laravel_has_no_users_table_of_its_own()`: `Schema::hasTable( 'users' )` is false.
- [ ] **Step 2: Run them.** Expected: they fail, because no WordPress tables exist and `users` does.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run all app tests.** Expected: all pass.
- [ ] **Step 5: Format and commit** with the message `Create WordPress 7.1.4's tables with Laravel migrations`.

### Task 3: The checker gets a database

**Files:**
- Modify: `conformance/fetch-wordpress-tests.php`. Add `src/wp-admin/includes` to the sparse checkout. An existing checkout is updated in place with `sparse-checkout add`; the pin and pristine checks are unchanged.
- Modify: `conformance/bootstrap.php`:
  - Set `DB_DATABASE=wordpress_conformance`, and `LOG_CHANNEL=null` (this also settles deferred minor #9).
  - After boot, run `migrate:fresh --force` through the console kernel once per run.
  - Then call `WP_UnitTestCase::commit_transaction()`, so that nothing created during setup is held in a transaction.
- Modify: `conformance/includes/WP_UnitTestCase.php`. Add these, from 7.1.4:
  - `start_transaction()`, called in `set_up()`;
  - a `ROLLBACK` through `$wpdb` in `tear_down()`;
  - `commit_transaction()`, `_create_temporary_tables()` and `_drop_temporary_tables()`.

  Until Task 4 provides `$wpdb`, use a stand-in, recorded as a ruling. `start_transaction()` and the rollback go through `DB::connection()->getPdo()` behind a private helper, `run_sql( string $sql )`. Task 4 switches that helper to `$wpdb->query()`.
- Test: add to `conformance/harness/HarnessTest.php`.

**Interfaces:**
- Consumes: the tables (Task 2) and the test databases (Task 1).
- Produces: a migrated `wordpress_conformance` database, with each WordPress test wrapped in a transaction.

**Steps:**

- [ ] **Step 1: Write the failing harness tests**
  - `test_checker_database_is_migrated()`: `Schema::hasTable( config( 'wordpress.table_prefix' ).'posts' )`.
  - `test_each_test_runs_in_a_transaction()`: in a `WP_UnitTestCase` subclass in `conformance/harness/`, after `set_up()`, `DB::connection()->getPdo()->inTransaction()` is true.
- [ ] **Step 2: Run them.** Run `composer conformance -- --testsuite harness`. Expected: both fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run the whole checker.** Run `composer conformance`. Expected: harness green, and the hooks results unchanged (318 pass, 1 waiting, 18 diverged).
- [ ] **Step 5: Format and commit** with the message `Give the checker a migrated database and per-test transactions`.

### Task 4: The `$wpdb` door on Laravel's connection

**Files:**
- Create: `wp-api/classes/wpdb.php`, carried over from 7.1.4's `class-wpdb.php` by the scratchpad carry-over script, with these changes:
  - Every `mysqli_*` call goes through `$this->connection`, an `App\Core\Database\WpdbConnection`.
  - `db_connect()` gets that connection from `WpdbConnection::for( $dbhost, $dbuser, $dbpassword, $dbname )`.
  - `$this->dbh` is the PDO.

  The public methods, properties and return shapes are unchanged (rule 7). Note each changed method in its docblock with `@since WM-0.2.0 Changed: runs on Laravel's database connection (PDO).`
- Create: `app/Core/Database/WpdbConnection.php`, a `final` class:
  - `static for(string $host, string $user, string $password, string $name): self`. When these match Laravel's default connection, it wraps that connection; otherwise it uses `DB::build()` with the same driver.
  - `pdo(): PDO`
  - `query(string $sql): WpdbResult|false`, which records the error on failure.
  - `escape(string $text): string`, which is `PDO::quote()` without its outer quotes.
  - `lastError(): string`
  - `lastInsertId(): int`
  - `serverVersion(): string`, numeric only, so MariaDB's suffix is stripped.
  - `serverInfo(): string`, the full version string.
  - `ping(): bool`
  - `setCharset(string $charset, string $collate): bool`
- Create: `app/Core/Database/WpdbResult.php`, a `final readonly` class:
  - `rows`: list of `stdClass`;
  - `columns`: list of `stdClass` in mysqli's `fetch_fields()` shape (`name`, `orgname`, `table`, `orgtable`, `def`, `db`, `catalog`, `max_length`, `length`, `charsetnr`, `flags`, `type`, `decimals`), built from `PDOStatement::getColumnMeta()`;
  - `affectedRows`: int;
  - `isResultSet`: bool.
- Modify: `wp-api/load.php` to load `wpdb.php`. Modify `CoreServiceProvider::boot()`:
  - define `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST`, `DB_CHARSET` (`utf8mb4`) and `DB_COLLATE` (`''`) from the default connection's config, if not already defined;
  - create `$GLOBALS['wpdb']` once, as `new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST )`, then `set_prefix( config( 'wordpress.table_prefix' ) )`;
  - re-point it at the new PDO when the app re-boots in tests.
- Modify: `conformance/includes/WP_UnitTestCase.php`, switching `run_sql()` to `$wpdb->query()`.
- Modify: `conformance/phpunit.xml.dist`, adding `db.php`, `db/realEscape.php` and `db/charset.php`.
- Modify: `docs/divergences.md` and `conformance/diverged.php`. Add the entry "`$wpdb->dbh` is PDO, not mysqli" (decision A), with the plugin cost and the fix (`$wpdb->_real_escape()` / `$wpdb->prepare()` instead of `mysqli_*()` on `$wpdb->dbh`). Diverge exactly the WordPress tests that touch `mysqli` or `->dbh` directly, and add adapted tests for what we do instead in `conformance/adapted/db.php`.
- Test: `tests/Feature/Core/Database/WpdbTest.php`

**Interfaces:**
- Consumes: `CoreServiceProvider` (from step 2), the tables (Task 2), and the checker's transactions (Task 3).
- Produces: the global `$wpdb` with WordPress 7.1.4's public API; `App\Core\Database\WpdbConnection` and `WpdbResult`.

**Steps:**

- [ ] **Step 1: Write the failing app tests** (`RefreshDatabase`):
  - `test_wpdb_shares_laravels_transaction()`: `$wpdb->dbh === DB::connection()->getPdo()`. Then:
    1. `DB::beginTransaction()`;
    2. `$wpdb->insert( $wpdb->options, ['option_name' => 'wm_probe', 'option_value' => '1', 'autoload' => 'off'] )`;
    3. `DB::rollBack()`;
    4. `$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", 'wm_probe' ) )` is `'0'`.
  - `test_four_byte_text_round_trips()`: insert `'Café 🎉 日本'` into `post_title` through `$wpdb->insert()`; read it back through `DB::table( $wpdb->posts )->value( 'post_title' )` and through `$wpdb->get_var()`; both are identical.
  - `test_reports_version_and_capabilities()`: `$wpdb->db_version()` matches `/^\d+\.\d+(\.\d+)?$/`; `$wpdb->has_cap( 'utf8mb4' )` and `$wpdb->has_cap( 'utf8mb4_520' )` are true; `$wpdb->db_server_info()` contains `MariaDB` exactly when the driver is `mariadb`.
  - `test_query_results_have_wordpress_shapes()`: after one insert, `get_results( ..., OBJECT )[0]` is a `stdClass`; `ARRAY_A` gives an associative array; `get_col()` gives a list of strings; `$wpdb->insert_id` is a positive int; `$wpdb->num_rows` is `1`.
  - `test_a_second_database_gets_its_own_connection()`: `new wpdb( user, password, 'wordpress_conformance', host )->dbh !== DB::connection()->getPdo()`, and it can `get_var( 'SELECT 1' )`.
- [ ] **Step 2: Run them.** Expected: errors, because `$wpdb` is undefined.
- [ ] **Step 3: Implement.** Carry `class-wpdb.php` over, then replace the `mysqli` layer as listed.
- [ ] **Step 4: Run the app tests, then the checker.** Expected: app tests all pass. In the checker, the database tests pass except those recorded as diverged (mysqli/dbh) or waiting (needing options, posts or multisite, each listed with its reason). The hooks results are unchanged.
- [ ] **Step 5: Docs, format and commit.** Update `docs/coding-standards.md` §2 (doors call the house, never `Illuminate`). Commit with the message `Run $wpdb on Laravel's connection`, followed by the checker counts.

### Task 5: `dbDelta()` for plugins' own tables

**Files:**
- Create: `wp-api/functions/db-delta.php`, containing `dbDelta()` from 7.1.4's `src/wp-admin/includes/upgrade.php` (and any private helper it calls from that file).
- Create: `wp-api/functions/schema.php`, containing `wp_get_db_schema()` from 7.1.4's `src/wp-admin/includes/schema.php`. Its multisite branch is listed in `conformance/waiting.php` until multisite is decided (decision B).
- Modify: `wp-api/load.php`.
- Modify: `conformance/phpunit.xml.dist`, adding `db/dbDelta.php`.
- Test: `tests/Feature/Core/Database/DbDeltaTest.php`

**Interfaces:**
- Consumes: `$wpdb` (Task 4).
- Produces: `dbDelta()` and `wp_get_db_schema()` with 7.1.4's signatures.

**Steps:**

- [ ] **Step 1: Write the failing app test.** `test_migrations_and_wordpress_schema_agree()`: `dbDelta( wp_get_db_schema( 'all' ), false )` against the migrated database returns `[]`, so WordPress itself sees nothing to change.
- [ ] **Step 2: Run it.** Expected: `Call to undefined function dbDelta()`.
- [ ] **Step 3: Implement** by carrying over the two functions with the script.
- [ ] **Step 4: Run the app tests and the checker.** Expected: app tests pass, and the `dbDelta` tests pass except recorded ones.
- [ ] **Step 5: Format and commit** with the message `Add dbDelta() and wp_get_db_schema()`.

### Task 6: Database matrix on GitHub (CI)

**Files:**
- Modify: `.github/workflows/tests.yml`:
  - Add a `database` matrix: `mysql:8.4`, `mysql:9.7`, `mariadb:11.8` and `mariadb:12.3`, each as a GitHub service container with a health check and an init step creating `wordpress_testing` and `wordpress_conformance`.
  - Run PHP 8.5 against all four servers, and PHP 8.3 against `mysql:9.7` only.
  - Set `DB_CONNECTION` to `mariadb` for the MariaDB rows.

**Steps:**

- [ ] **Step 1: Write the matrix.** Check the current majors of the actions again (rule 11).
- [ ] **Step 2: Run locally against MariaDB.** With `DB_CONNECTION=mariadb DB_PORT=3307`, run `php artisan test` and `composer conformance`. Expected: the same results as on MySQL.
- [ ] **Step 3: Commit** with the message `Test against MySQL 8.4/9.7 and MariaDB 11.8/12.3 in CI`.

## After this plan (not in scope)

- **PostgreSQL (next plan).** Plugins write MySQL-flavoured SQL, so PostgreSQL needs a translator. Options to put to the owner:
  - adapt WordPress Playground's MySQL parser (GPL, maintained by WordPress contributors), which today translates to SQLite;
  - the older regex-based PG4WP;
  - fix plugins instead.

  Laravel's own queries already work on PostgreSQL.
- **Settings:** `get_option()` and friends on `wp_options`, through Laravel's cache (rule 2).
- **Table design improvements** (for example, a faster replacement for `wp_postmeta`). Owner decisions, one at a time, each recorded with its plugin cost.
- **Multisite**, if decision B changes.
