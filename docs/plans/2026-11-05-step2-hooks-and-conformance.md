# Step 2: Hooks and the WordPress conformance harness. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** WordPress's plugin API (`add_action`, `add_filter`, `do_action`, `apply_filters` and the rest of the hook functions) runs inside the Laravel app, and passes WordPress 7.1.4's own PHPUnit tests for it, run unmodified.

**Architecture:** Two test runners side by side. The Laravel app keeps PHPUnit 12. WordPress's tests need PHPUnit 9.6 with Yoast's polyfills, because they use docblock annotations that PHPUnit 12 no longer reads. So they run in a separate `conformance/` Composer project against a pinned checkout of `wordpress-develop` at tag 7.1.4. The ported core lives in `core/`, mirrors WordPress's file paths, has no Composer or Laravel dependency, and is loaded by one file, `core/load.php`. That file is required by both the conformance bootstrap and the app's Composer `autoload.files`.

**Tech Stack:** PHP 8.3+, Laravel 13.40, PHPUnit 12.5 (app), PHPUnit 9.6.38 + yoast/phpunit-polyfills 1.1.5 (conformance), git.

**Spec:** `CLAUDE.md` (decisions table and rules 2–7), and the starting brief §5 steps 1–2 (in `private/`; not needed to execute this plan).

## Global Constraints

- WordPress baseline: tag `7.1.4` of `https://github.com/WordPress/wordpress-develop.git`, commit `0afb74de8c3fe4c1869e44b62b0418d67b11aa4c`. Port from this tag's `src/`, never from trunk.
- Licence GPL-2.0-or-later. Every file ported from WordPress starts with a file docblock line: `Ported from WordPress 7.1.4: src/wp-includes/<file>.` (CLAUDE.md rule 7).
- Plugin-facing functions and classes keep WordPress's exact names, signatures, defaults and return shapes. Do not add parameter or return types to them (rule 6).
- **This step is a faithful port.** Change WordPress code only to remove branches for PHP versions below 8.3. Fault fixes come later, one at a time, each recorded in `docs/divergences.md` (rules 3 and 5).
- `core/` must not use Composer, Laravel, or anything under `vendor/`.
- The conformance runner must never load the root `vendor/autoload.php`. It would register PHPUnit 12 classes over PHPUnit 9.6.
- WordPress's test files are run unmodified. Fetched files are gitignored and never edited.
- The repo is public (rule 1): nothing under `private/` is referenced in tracked code.

## Review Focus

1. **Composer `files` are included inside a function scope.** Without `global $wp_filter;` statements in the included file, hook state becomes a local variable and silently disappears. Expect `$GLOBALS['wp_filter']` to hold the hook registered by `add_filter()` after the app boots. Test: Task 4, `test_hooks_live_in_globals_after_boot`.
2. **Two PHPUnit versions in one process.** If the conformance bootstrap loads the root autoloader, tests fail in confusing ways. Expect the conformance run to report PHPUnit 9.6. Test: Task 1, `test_runs_on_phpunit_9_6`.
3. **A stale or wrong WordPress checkout.** If the fetch is re-run, partially failed, or points at another ref, conformance numbers are measured against the wrong code. Expect the fetch script to be idempotent and to refuse any checkout whose HEAD is not the pinned SHA. Test: Task 1, `test_checkout_is_pinned_tag`.
4. **Hook state leaking between Laravel tests.** All Laravel tests share one PHP process, so a hook added in one test would fire in the next. Expect each Laravel test to start with the hook globals as they were after boot. Test: Task 4, `test_hooks_do_not_leak_between_tests` (a pair of ordered tests).
5. **`WP_DEBUG` undefined when core code runs.** `_deprecated_hook()` and `_doing_it_wrong()` read the constant, so an undefined `WP_DEBUG` is a fatal error. Expect the app to define it from `config('app.debug')` during provider registration, and the conformance bootstrap to define it as `true`. Test: Task 4, `test_wp_debug_follows_app_debug`.

---

### Task 1: Conformance harness

**Files:**
- Create: `conformance/composer.json`, which requires `phpunit/phpunit: ^9.6.38` and `yoast/phpunit-polyfills: ^1.1.5`. It has no autoload of the root project.
- Create: `conformance/fetch-wordpress-tests.php`
- Create: `conformance/bootstrap.php`
- Create: `conformance/phpunit.xml.dist`
- Create: `conformance/includes/class-wp-unittestcase.php`
- Create: `conformance/includes/stand-ins.php`
- Test: `conformance/harness/HarnessTest.php`
- Modify: `.gitignore`, adding `/conformance/vendor` and `/conformance/wordpress-develop`
- Modify: `composer.json` scripts, adding `"conformance:fetch": "@php conformance/fetch-wordpress-tests.php"` and `"conformance": ["@composer --working-dir=conformance install --no-interaction --quiet", "@php conformance/vendor/bin/phpunit -c conformance/phpunit.xml.dist"]`

**Interfaces:**
- Produces: the class `WP_UnitTestCase`, extending `Yoast\PHPUnitPolyfills\TestCases\TestCase`. It is adapted from WordPress 7.1.4's `tests/phpunit/includes/abstract-testcase.php` and keeps only these members, copied with provenance:
  - `set_up()`: back up the hooks once, then call `expectDeprecated()`.
  - `tear_down()`: reset the expected and caught arrays, then `_restore_hooks()`.
  - `assert_post_conditions()`: calls `expectedDeprecated()`.
  - `_backup_hooks()` and `_restore_hooks()`.
  - `expectDeprecated()`, `expectedDeprecated()`, `deprecated_function_run()` and `doing_it_wrong_run()`.
  - `setExpectedDeprecated()` and `setExpectedIncorrectUsage()`.
  - `assertSameSets()`, `assertSameSetsWithIndex()` and `assertIsNonDecimalIntString()`.
- Produces: the constant `WP_DEBUG === true` and the constant `WP_TESTS_CHECKOUT` (the absolute path of `conformance/wordpress-develop`), both defined in `bootstrap.php`.
- Produces: the stand-in `WP_Post`, declared only `if ( ! class_exists( 'WP_Post' ) )`. It is `#[AllowDynamicProperties]` and its constructor copies `get_object_vars( $post )` onto `$this`. This is all the hooks tests need. The real `WP_Post` arrives with the core-data step.

**Steps:**

- [ ] **Step 1: Write `HarnessTest`**

`conformance/harness/HarnessTest.php` extends `Yoast\PHPUnitPolyfills\TestCases\TestCase`, not `WP_UnitTestCase`. It contains:
- `test_runs_on_phpunit_9_6()`: `assertStringStartsWith( '9.6.', \PHPUnit\Runner\Version::id() )`.
- `test_checkout_is_pinned_tag()`: `assertSame( '0afb74de8c3fe4c1869e44b62b0418d67b11aa4c', trim( shell_exec( 'git -C ' . escapeshellarg( WP_TESTS_CHECKOUT ) . ' rev-parse HEAD' ) ) )`.
- `test_wordpress_test_utilities_are_loaded()`: `assertTrue( class_exists( 'MockAction' ) )`, `class_exists( 'Mock_Invokable' )` and `class_exists( 'WP_UnitTestCase' )`.

- [ ] **Step 2: Write `fetch-wordpress-tests.php`**

Constants: `TAG = '7.1.4'`, `SHA = '0afb74de8c3fe4c1869e44b62b0418d67b11aa4c'`, `TARGET = __DIR__ . '/wordpress-develop'`.
- If `TARGET` exists and its HEAD is `SHA`, print `already at 7.1.4` and exit 0.
- If `TARGET` exists at any other HEAD, exit 1 with `wordpress-develop is not at 7.1.4; delete conformance/wordpress-develop and re-run`.
- Otherwise run `git clone --depth 1 --branch 7.1.4 --filter=blob:none --sparse https://github.com/WordPress/wordpress-develop.git TARGET`, then `git -C TARGET sparse-checkout set tests/phpunit/includes tests/phpunit/tests src/wp-includes`, then verify HEAD equals `SHA` (exit 1 if not).
- Exit non-zero on any git failure.

- [ ] **Step 3: Write the bootstrap, the config and the base test case**

`bootstrap.php`, in this order:
1. Define `WP_DEBUG` (true) and `WP_TESTS_CHECKOUT`.
2. Require `conformance/vendor/autoload.php`.
3. Require `core/load.php` if it exists (it arrives in Task 2).
4. Require `WP_TESTS_CHECKOUT . '/tests/phpunit/includes/utils.php'` and `mock-invokable.php`.
5. Require `includes/class-wp-unittestcase.php` and `includes/stand-ins.php`.

`phpunit.xml.dist`: a `harness` suite with `conformance/harness`, and a `wordpress` suite with an explicit `<file>` list that starts empty.

- [ ] **Step 4: Run the harness**

Run `composer conformance:fetch`, then `composer conformance -- --testsuite harness`.
Expected: `OK (3 tests, …)`.
Run `composer conformance:fetch` again. Expected: `already at 7.1.4`.

- [ ] **Step 5: Commit** with the message `Add WordPress 7.1.4 conformance harness (PHPUnit 9.6)`.

### Task 2: Port the hooks

**Files:**
- Create: `core/load.php`. It requires, in order:
  - `wp-includes/interim/l10n.php`
  - `wp-includes/interim/kses.php`
  - `wp-includes/class-wp-hook.php`
  - `wp-includes/class-wp-filter-sentinel.php`
  - `wp-includes/plugin.php`
  - `wp-includes/functions.php`
- Create: `core/wp-includes/class-wp-hook.php`, `class-wp-filter-sentinel.php` and `plugin.php`. These are ported from 7.1.4. Port `plugin.php` up to and including `do_action_deprecated()`, plus `_wp_call_all_hook()` and `_wp_filter_build_unique_id()`. Its plugin-path functions (`plugin_basename()` through `register_uninstall_hook()`) are left for the plugin-loader step: say so in the file docblock.
- Create: `core/wp-includes/functions.php`, which ports only these functions from 7.1.4's `functions.php`:
  - `_deprecated_hook()`, `_doing_it_wrong()` and `wp_trigger_error()`;
  - `__return_true()`, `__return_false()`, `__return_zero()`, `__return_null()`, `__return_empty_array()` and `__return_empty_string()`.
- Create: `core/wp-includes/interim/l10n.php` with `__( $text, $domain = 'default' ): string`, which returns `$text`. Declare it only if no `__` function exists.
- Create: `core/wp-includes/interim/kses.php` with `wp_kses( $content, $allowed_html, $allowed_protocols = array() )`. It returns `strip_tags( $content, array_keys( $allowed_html ) )`. Declare it only if no `wp_kses` function exists.
- `wp_trigger_error()` throws `WP_Exception` for `E_USER_ERROR`. Port `class-wp-exception.php` (7.1.4: an empty `class WP_Exception extends Exception {}`) and add it to `load.php` before `functions.php`.
- Create: `docs/stand-ins.md`, a table of every interim stand-in: `__()`, `wp_kses()` and the conformance `WP_Post`. For each it gives what is missing compared with WordPress and which step replaces it.
- Create: `docs/divergences.md`. It has a heading, the column list from CLAUDE.md rule 3 (what WordPress does, what we do, why, plugins broken, how fixed) and the line `None yet.`
- Modify: `conformance/phpunit.xml.dist`. Add these to the `wordpress` suite, each under `wordpress-develop/tests/phpunit/tests/`: `actions.php`, `filters.php` and every file in `hooks/`.

**Interfaces:**
- Consumes: `WP_UnitTestCase` and the bootstrap from Task 1.
- Produces:
  - the globals `$wp_filter`, `$wp_actions`, `$wp_filters` and `$wp_current_filter`, initialised exactly as in 7.1.4's `plugin.php`, including its top-level `global` statements;
  - every hook function, with 7.1.4's signatures;
  - `WP_Hook`, `WP_Filter_Sentinel`, `WP_Exception`, `_deprecated_hook()`, `_doing_it_wrong()`, `wp_trigger_error()` and the `__return_*()` functions.

**Steps:**

- [ ] **Step 1: Add the WordPress hooks tests to the `wordpress` suite and run them**

Run `composer conformance -- --testsuite wordpress`.
Expected: errors `Call to undefined function add_action()` from `WP_UnitTestCase::set_up()`.

- [ ] **Step 2: Port the files listed above from `conformance/wordpress-develop/src/wp-includes/`**

Each file gets the provenance docblock. Drop only code guarded for PHP below 8.3.

- [ ] **Step 3: Run the suite**

Run `composer conformance -- --testsuite wordpress`.
Expected: every test passes **except** the data sets of `test_priority_callback_order_with_integers` and `test_priority_callback_order_with_unhappy_path_nonintegers` in `Tests_Actions`, `Tests_Filters`, `Tests_Hooks_ApplyFilters` and `Tests_Hooks_DoAction`. Those error with `Call to undefined function wp_list_pluck()`, which Task 3 fixes.
Record the pass/error counts in the commit message.

- [ ] **Step 4: Commit** with the message `Port WordPress 7.1.4 hooks (WP_Hook, plugin.php hook API)`, followed by the counts.

### Task 3: Port the list utilities

**Files:**
- Create: `core/wp-includes/class-wp-list-util.php`, ported from 7.1.4.
- Modify: `core/wp-includes/functions.php`. Add `wp_filter_object_list()`, `wp_list_filter()`, `wp_list_pluck()` and `wp_list_sort()` from 7.1.4.
- Modify: `core/load.php`. Require `class-wp-list-util.php` before `functions.php`.
- Modify: `conformance/phpunit.xml.dist`. Add `functions/wpListPluck.php`, `functions/wpListUtil.php`, `functions/wpListFilter.php`, `functions/wpListSort.php` and `functions/wpFilterObjectList.php`.

**Interfaces:**
- Consumes: `_doing_it_wrong()` and `__()` from Task 2.
- Produces: `WP_List_Util` and the four `wp_list_*` / `wp_filter_object_list` functions, with 7.1.4's signatures.

**Steps:**

- [ ] **Step 1: Add the five list-utility test files and run the `wordpress` suite**

Expected: `Call to undefined function wp_list_pluck()` and `Class "WP_List_Util" not found`.

- [ ] **Step 2: Port `class-wp-list-util.php` and the four functions**

- [ ] **Step 3: Run the suite**

Run `composer conformance`.
Expected: no errors or failures in either suite. Any skipped or risky test is listed in the commit message with WordPress's own reason for it. The two `@expectedIncorrectUsage WP_List_Util::pluck` tests pass through `doing_it_wrong_run`.

- [ ] **Step 4: Commit** with the message `Port WP_List_Util and wp_list_* helpers; hooks conformance at 100%`.

### Task 4: Load the core into the Laravel app

**Files:**
- Modify: `composer.json`. Add `"files": ["core/load.php"]` under `autoload`, then run `composer dump-autoload`.
- Create: `app/Providers/CoreServiceProvider.php`. Its `register()` runs `if ( ! defined( 'WP_DEBUG' ) ) define( 'WP_DEBUG', (bool) config( 'app.debug' ) );`.
- Modify: `bootstrap/providers.php`. Register `CoreServiceProvider`.
- Modify: `tests/TestCase.php`. Snapshot the four hook globals once, on the first `setUp()` (cloning each `WP_Hook`, as `WP_UnitTestCase::_backup_hooks()` does), and restore them in `tearDown()`.
- Test: `tests/Unit/CoreHooksTest.php`. This one extends `Tests\TestCase`, so the app boots.

**Interfaces:**
- Consumes: `core/load.php` from Tasks 2–3.
- Produces: hook functions available everywhere in the app, and `WP_DEBUG` defined after provider registration.

**Steps:**

- [ ] **Step 1: Write the failing tests**

`tests/Unit/CoreHooksTest.php`:
- `test_hooks_live_in_globals_after_boot()`: calls `add_filter( 'core_hooks_test', fn ( $v ) => $v . '!' )`. It asserts `apply_filters( 'core_hooks_test', 'a' ) === 'a!'`, and that `$GLOBALS['wp_filter']['core_hooks_test']` is an instance of `WP_Hook`.
- `test_wp_debug_follows_app_debug()`: `assertSame( (bool) config( 'app.debug' ), WP_DEBUG )`.
- `test_hooks_do_not_leak_between_tests_a()`: `add_action( 'core_leak_probe', '__return_true' )`, then `assertTrue( has_action( 'core_leak_probe' ) )`.
- `test_hooks_do_not_leak_between_tests_b()`, marked `#[Depends('test_hooks_do_not_leak_between_tests_a')]`: `assertFalse( has_action( 'core_leak_probe' ) )`.

- [ ] **Step 2: Run them**

Run `php artisan test --filter=CoreHooksTest`.
Expected: errors, `Call to undefined function add_filter()`.

- [ ] **Step 3: Implement the four modifications above**

- [ ] **Step 4: Run all of the app's tests**

Run `php artisan test`.
Expected: all pass (the 2 example tests plus 4 new ones).

- [ ] **Step 5: Run both suites together**

Run `composer conformance` and `php artisan test`. Expected: both green.

- [ ] **Step 6: Update the docs and commit**

Add `composer conformance:fetch` and `composer conformance` to the Commands block in `CLAUDE.md`, with one line explaining why there are two runners. Commit with the message `Load the ported core into Laravel; isolate hook state per test`.

### Task 5: CI on GitHub Actions

**Files:**
- Create: `.github/workflows/tests.yml`

**Steps:**

- [ ] **Step 1: Write the workflow**

It runs on push and on pull requests to `main`, with a PHP matrix of `8.3` and `8.5` using `shivammathur/setup-php` (check the current major release before pinning: rule 10). Steps:
1. `composer install`
2. `cp .env.example .env && php artisan key:generate`
3. `php artisan test`
4. `composer conformance:fetch`
5. `composer conformance`

- [ ] **Step 2: Lint it locally**

Run `gh workflow view` after the push, or use `actionlint` if it is installed. Expected: the workflow parses, and both jobs go green on the first push.

- [ ] **Step 3: Commit** with the message `Run app tests and WordPress conformance in CI`.

## After this plan (not in scope)

- **Fault review of the hooks.** With conformance at 100%, list the hook behaviours that exist only for dead constraints. Present them to the owner as fix candidates, each with its plugin cost, before changing anything.
- **Next plan:** the database layer and WordPress's schema (brief §5 step 1 / §3.3: MySQL/MariaDB first, PostgreSQL proven early).
