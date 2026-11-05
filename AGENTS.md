# WordPress, modernised on Laravel

Instructions for every coding agent and contributor working in this repository.

> **Working name.** "wordpress-modernised" is a placeholder until the project goes to production. The WordPress Foundation's trademark policy restricts "WordPress" and "WP" in product names, so the final name will be original. Keep the name out of namespaces and package prefixes so renaming stays cheap. Our version tag prefix `WM-` is replaced at the rename with one search-and-replace.

**If `private/CONTEXT.md` exists, read it first.** It holds the owner's private context and is not in the public repository.

## What this is, in plain words

This is our own product: WordPress rebuilt on Laravel.

- **Laravel is the house.** Everything really runs on Laravel: the database, cache, queues, scheduled jobs, logins, routing and pages.
- **WordPress's function and class names are the doors.** `get_post()`, `add_filter()`, `WP_Query` and the rest keep their names, arguments and return shapes, because WordPress plugins and themes call them by name. Behind each door, Laravel does the work.
- **The hooks are a shared noticeboard.** When something happens, such as a post being saved, it is announced once. WordPress plugins (through `add_action()`) and our Laravel code (through typed Laravel events) both hear it.
- **WordPress's own test suite is the checker.** It tells us whether each door behaves the way plugins expect.

We take things from WordPress only when we choose to: mainly security fixes for code we carried over, and new functions that plugins start relying on. We **write our own version** of what we take; we do not merge WordPress's code in.

**Status:** nothing of the core is built yet. Plans live in `docs/plans/`.

## Decisions already made

| Topic | Decision |
|---|---|
| Licence | **GPL-2.0-or-later** (required for WordPress-derived code; Laravel's MIT licence is compatible) |
| Runtime | **Laravel owns the runtime.** The WordPress API is a layer on top of it |
| Compatibility | **API-level** (rule 3): WordPress's functions, hooks, classes, REST API and `$wpdb`'s methods work unchanged. The tables and stored formats behind them can be redesigned, one recorded change at a time. Plugins' own MySQL-flavoured SQL is not translated for other databases |
| Database | One database layer on Laravel's connection. **MySQL/MariaDB first** (what plugins expect), **PostgreSQL proven early**. `$wpdb`'s methods work on both; plugins' own MySQL-flavoured SQL is not translated, so those plugins get fixed (rule 3) |
| Database connection | **One connection, Laravel's.** `$wpdb->dbh` is Laravel's PDO. WordPress's 12 single-site tables are kept as they are; table design changes come later, one at a time. No multisite for now. No Laravel `users` table: `wp_users` is the one user table |
| SQL modes | **WordPress's relaxed MySQL modes for the whole connection** (Laravel's code included), until WordPress's `0000-00-00` empty dates are replaced; then strict mode returns |
| Defaults | `WP_DEBUG_DISPLAY` **off** by default (WordPress: on); minimum PHP **8.3** (WordPress: 7.4). Both recorded in `docs/divergences.md` |
| WordPress baseline | **WordPress 7.1.4** (tag `7.1.4` of `wordpress-develop`). Its PHPUnit suite is the checker |
| PHP | **8.3 or newer** (Laravel 13's minimum); **8.4 or newer with PostgreSQL** (PHP 8.3's PDO can corrupt some PostgreSQL queries) |
| Framework | Laravel 13. App tests on PHPUnit 12; WordPress's tests on PHPUnit 9.6 in `conformance/` (they need it) |
| Code style | **Laravel's style everywhere**, enforced by Pint. See `docs/coding-standards.md` |
| Versions | Our own semver, starting at 0.1.0, tagged `@since WM-x.y.z`. WordPress's history is kept, labelled `@since WP-x.y.z` |
| Editor | **Gutenberg kept**, improved rather than replaced. It talks to the core only through the REST API |
| Admin | **WordPress's admin, refreshed**: same structure and the same ways for plugins to plug in, rebuilt in Blade, responsive and accessible. Designs are shown to the owner before building |
| Plugin APIs | The WordPress API for existing plugins and themes. A native Laravel API (packages, service providers, typed events) for new plugins. Both use the same engine |
| Name | Decided before production, not now |

## Rules

1. **This repository is public.** Nothing internal goes into a tracked file: the owner's words, sites, plugin lists, client names, local paths, other private projects, credentials. Internal material lives in `private/`, which is gitignored. Before every commit, check new and changed files for internal content.

2. **Laravel owns the runtime; the WordPress API is a layer.** There is one of everything: one container, one database connection, one cache, one queue, one auth system, one request lifecycle, one hook engine. A WordPress function never keeps its own separate copy of state that Laravel also holds. If `get_option()` and Eloquent can both read an option, they go through the same connection and the same cache.

3. **The compatibility promise is API-level.** Plugins and themes that use WordPress's documented API run unchanged: its functions, hooks, classes, REST API and `$wpdb`'s methods. **The database layout behind the API is not promised.** Tables, columns, stored formats (such as PHP-serialized values) and other internals are ours to redesign the Laravel way, each change recorded with its plugin cost (rule 4). Plugins that write their own SQL against WordPress's tables, or depend on internals or on a fault we fix, get fixed copies. Owner's decision, 2026-11-05.

4. **Every deliberate difference from WordPress is recorded.** `docs/divergences.md` says what WordPress does, what we do instead, why, which plugins it breaks, and how they are fixed. No silent differences.

5. **WordPress's test suite is the checker.** Each rebuilt area runs WordPress's own tests for it. A failing test is either a bug in our code or a recorded difference (rule 4), whose test is then adapted to describe our behaviour. "Passes" plus "differs on purpose, recorded" must account for every test.

6. **Before rebuilding any WordPress behaviour, state why it exists.** If the reason is a dead constraint (shared hosting, old PHP or MySQL, backwards compatibility with nothing that still uses it), rebuild it properly and record the difference. If plugins or editors genuinely rely on it, keep the behaviour, built the Laravel way.

7. **The doors keep WordPress's shape.** Plugin-facing functions and classes keep WordPress's exact names, parameters, defaults and return shapes, with no added parameter or return types. Everything behind the doors is typed, Laravel-style code.

8. **Code taken from WordPress says where it came from.** A file containing code carried over from WordPress names the WordPress file and version in its file docblock. The project stays GPL-2.0-or-later.

9. **Documentation is adapted, not invented.** WordPress's manuals and handbooks are the starting point, edited to describe this project. WordPress.org documentation is GPLv2 by default (some is CC0); confirm the licence of each source before copying it in.

10. **The owner decides.** Present options with a recommendation, in plain words; assume nothing. Write a plan just before building it, and build nothing until the owner approves it. Nothing is pushed to GitHub until the owner says so.

11. **Verify versions from source.** Check the current release of any package, WordPress, PHP or tool before choosing or pinning it. Never rely on remembered versions.

## Where things go

| Path | What |
|---|---|
| `app/` | The house: Laravel code. Services behind the doors live in `app/Core/<Area>/` |
| `wp-api/` | The doors: WordPress's global functions and `WP_` classes. Loaded by `wp-api/load.php` |
| `conformance/` | The checker: runs WordPress's own tests against our code |
| `docs/coding-standards.md` | Code style, version tags, docblocks and naming |
| `docs/plans/` | One plan per build step, `<date>-<step>-<name>.md`, written just before it is built |
| `docs/divergences.md` | Every deliberate difference from WordPress (rule 4) |
| `docs/backlog.md` | Review findings not fixed yet, and decisions waiting for the owner. Delete an item in the commit that does it |
| `private/` | Internal briefs, references and notes. Gitignored; never published |

## Commands

The local toolchain is Laravel Herd on Windows (PHP and Composer on `PATH` in PowerShell).

```bash
docker compose up -d --wait
composer install
php artisan test
vendor/bin/pint
composer conformance:fetch
composer conformance
```

`docker compose up -d --wait` starts the local databases: MySQL 9.7 on port 3306, MariaDB 12.3 on 3307, and PostgreSQL 18 on 5437. Tests always run on a real database: MySQL by default; set `DB_CONNECTION=mariadb DB_PORT=3307` or `DB_CONNECTION=pgsql DB_PORT=5437` to run the same tests (and the checker) on the others.

There are two test runners. `php artisan test` runs our own tests on PHPUnit 12. `composer conformance` is the checker: it runs WordPress 7.1.4's own tests, which need PHPUnit 9.6, against the booted app. `composer conformance:fetch` downloads those tests once. Pass PHPUnit options after `--`, for example `composer conformance -- --testsuite harness`.
