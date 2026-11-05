# Step 2: Hooks and the WordPress checker. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

## In plain words

1. **Set up the checker.** Download WordPress 7.1.4's own tests and run them against our app, with the app really running.
2. **Build the hook doors**: `add_action()`, `add_filter()`, `do_action()`, `apply_filters()` and the rest, so plugins can use the noticeboard.
3. **Build the small helpers those tests need**: the `wp_list_*` functions. After this, the checker passes 100% for hooks.
4. **Connect the noticeboard to Laravel.** Laravel code gets a typed way in (`HookBus`). Laravel events can announce on the noticeboard. WordPress's warnings go to Laravel's log instead of crashing pages.
5. **Run the tests automatically on GitHub** (CI), once pushing is allowed.

---

**Goal:** WordPress's hook API runs as part of the Laravel app, passes WordPress 7.1.4's own tests for it, and is joined to Laravel through a typed service and an event bridge.

**Architecture:**
- **The doors.** The hook functions and `WP_Hook` live in `wp-api/`, written in our style. The hook registry is the `$wp_filter` global, because plugins read and change it directly, and it is the single source of truth.
- **The Laravel side.** `App\Core\Hooks\HookBus` is a container singleton that gives Laravel code a typed API over that same registry and bridges Laravel events onto WordPress actions.
- **The checker.** WordPress's tests need PHPUnit 9.6, so they run in a separate Composer project, `conformance/`. It installs this app as a path dependency, which brings Laravel but not PHPUnit 12, and it boots the real Laravel application before the tests run.

**Tech Stack:** PHP 8.3+, Laravel 13.40, PHPUnit 12.5 (app tests), PHPUnit 9.6.38 + yoast/phpunit-polyfills 1.1.5 (checker), Pint 1.x, git.

**Spec:** `AGENTS.md` (decisions and rules 2, 3, 5, 7 and 8) and `docs/coding-standards.md`.

## Global Constraints

- WordPress baseline: tag `7.1.4` of `https://github.com/WordPress/wordpress-develop.git`, commit `0afb74de8c3fe4c1869e44b62b0418d67b11aa4c`. Read WordPress's source from that checkout, never from trunk.
- Doors keep WordPress's exact names, parameters, defaults and return shapes, with no parameter or return types (rule 7).
- No `declare(strict_types=1)` in `wp-api/`; always in `app/` (coding standards §2).
- Every door has a full docblock with `@since WM-0.1.0` first, then WordPress's `@since` lines prefixed `WP-`. Hooks are documented where they fire (coding standards §3).
- A file containing code from WordPress says `Contains code from WordPress 7.1.4: src/wp-includes/<file>.` in its file docblock (rule 8).
- Behaviour must match WordPress 7.1.4 unless a difference is recorded in `docs/divergences.md`. This step records none.
- `wp-api/` uses no Composer autoloading and no Laravel. `wp-api/load.php` requires its files.
- The checker never loads the root `vendor/autoload.php`; it would bring PHPUnit 12.
- WordPress's test files run unmodified. The fetched checkout is gitignored and never edited.
- The repo is public (rule 1): nothing from `private/` appears in tracked files.
- Nothing is pushed until the owner says so.

## Review Focus

1. **Composer loads `wp-api/load.php` inside a function.** Without `global $wp_filter;` (and the other three hook globals) at the top of the hooks file, hook state becomes a local variable and silently disappears. Expect `$GLOBALS['wp_filter']` to hold a hook added with `add_filter()` after the app boots. Test: Task 4, `test_hooks_live_in_globals_after_boot`.
2. **A WordPress notice crashes a Laravel page.** Laravel turns every PHP notice into an `ErrorException` (`HandleExceptions::handleError`). With `WP_DEBUG` on, `_doing_it_wrong()` raises `E_USER_NOTICE`, so any plugin misusing a function would take the page down. Expect the notice to go to Laravel's log exactly once (even after the app has been booted many times in one process), the request to carry on, and WordPress's `doing_it_wrong_run` action to still fire. Test: Task 4, `test_doing_it_wrong_logs_instead_of_throwing`.
3. **The checker boots the app with the wrong packages.** Laravel discovers service providers from `vendor/composer/installed.json`. Booted from `conformance/`, it would read the root `vendor/`, find dev-only providers such as Pail that aren't installed in `conformance/vendor/`, and fail. Expect the bootstrap to point `COMPOSER_VENDOR_DIR`, `APP_PACKAGES_CACHE` and `APP_SERVICES_CACHE` at `conformance/`. Test: Task 1, `test_laravel_app_is_booted`.
4. **A stale or wrong WordPress checkout.** Expect the fetch script to be idempotent and to refuse any checkout whose HEAD is not the pinned commit. Test: Task 1, `test_checkout_is_pinned_tag`.
5. **Hook state leaking between Laravel tests.** All app tests share one PHP process. Expect each test to start with the hooks as they were after boot. Test: Task 4, the ordered pair `test_hooks_do_not_leak_between_tests_a` / `_b`.

---

### Task 1: The checker (conformance harness)

**Files:**
- Create: `conformance/composer.json`
  - `repositories`: `[{"type": "path", "url": "..", "options": {"symlink": true}}]`
  - `require`: `{"krafty-sprouts-media/wordpress-modernised": "@dev"}`
  - `require-dev`: `{"phpunit/phpunit": "^9.6.38", "yoast/phpunit-polyfills": "^1.1.5"}`
  - `minimum-stability: dev` and `prefer-stable: true`
- Create: `conformance/fetch-wordpress-tests.php`
- Create: `conformance/bootstrap.php`
- Create: `conformance/phpunit.xml.dist`
- Create: `conformance/includes/WP_UnitTestCase.php`
- Create: `conformance/includes/stand-ins.php`
- Test: `conformance/harness/HarnessTest.php`
- Create: `pint.json`, with the `laravel` preset, excluding `conformance/wordpress-develop` and `conformance/vendor`
- Modify: `.gitignore`, adding `/conformance/vendor`, `/conformance/wordpress-develop`, `/conformance/cache` and `/conformance/composer.lock`
- Modify: `composer.json` scripts
  - `"conformance:fetch": "@php conformance/fetch-wordpress-tests.php"`
  - `"conformance": ["@composer --working-dir=conformance update --no-interaction --quiet", "@php conformance/vendor/bin/phpunit -c conformance/phpunit.xml.dist"]`
  - It's `update`, not `install`, so the path dependency always reflects the root's current `composer.json`.

**Interfaces:**
- Produces: the constants `WP_DEBUG === true` and `WP_TESTS_CHECKOUT` (absolute path to `conformance/wordpress-develop`), defined in `bootstrap.php`.
- Produces: the class `WP_UnitTestCase`, extending `Yoast\PHPUnitPolyfills\TestCases\TestCase`. It is adapted from WordPress 7.1.4's `tests/phpunit/includes/abstract-testcase.php` and keeps only these members:
  - `set_up()`: back up the hooks once, then call `expectDeprecated()`.
  - `tear_down()`: reset the expected and caught lists, then `_restore_hooks()`.
  - `assert_post_conditions()`: calls `expectedDeprecated()`.
  - `_backup_hooks()` and `_restore_hooks()`.
  - `expectDeprecated()`, `expectedDeprecated()`, `deprecated_function_run()` and `doing_it_wrong_run()`.
  - `setExpectedDeprecated()` and `setExpectedIncorrectUsage()`.
  - `assertSameSets()`, `assertSameSetsWithIndex()` and `assertIsNonDecimalIntString()`.
  - Its file docblock says `Contains code from WordPress 7.1.4: tests/phpunit/includes/abstract-testcase.php.`
- Produces: the stand-in `WP_Post`, declared only `if ( ! class_exists( 'WP_Post' ) )`. It is `#[AllowDynamicProperties]`, and its constructor copies `get_object_vars( $post )` onto `$this`. It is listed in `docs/stand-ins.md` (created in Task 2) and replaced when posts are built.

**Steps:**

- [ ] **Step 1: Write `HarnessTest`**

`conformance/harness/HarnessTest.php` extends `Yoast\PHPUnitPolyfills\TestCases\TestCase` and contains:
- `test_runs_on_phpunit_9_6()`: `assertStringStartsWith( '9.6.', \PHPUnit\Runner\Version::id() )`.
- `test_checkout_is_pinned_tag()`: asserts that `git -C WP_TESTS_CHECKOUT rev-parse HEAD`, trimmed, equals `0afb74de8c3fe4c1869e44b62b0418d67b11aa4c`.
- `test_laravel_app_is_booted()`: `assertTrue( app()->isBooted() )` and `assertSame( realpath( __DIR__ . '/../..' ), realpath( base_path() ) )`.
- `test_wordpress_test_utilities_are_loaded()`: `class_exists()` is true for `MockAction`, `Mock_Invokable` and `WP_UnitTestCase`.

- [ ] **Step 2: Write `fetch-wordpress-tests.php`**

- Constants: `TAG = '7.1.4'`, `SHA = '0afb74de8c3fe4c1869e44b62b0418d67b11aa4c'`, and the target `__DIR__ . '/wordpress-develop'`.
- If the target exists at `SHA`, print `already at 7.1.4` and exit 0.
- If it exists at any other HEAD, exit 1 with `wordpress-develop is not at 7.1.4; delete conformance/wordpress-develop and re-run`.
- Otherwise:
  1. run `git clone --depth 1 --branch 7.1.4 --filter=blob:none --sparse <url> <target>`;
  2. run `git -C <target> sparse-checkout set tests/phpunit/includes tests/phpunit/tests src/wp-includes`;
  3. verify HEAD equals `SHA`.
- Exit non-zero on any git failure.

- [ ] **Step 3: Write the bootstrap, the config and the base test case**

`bootstrap.php` does the following, in order:
1. Define `WP_DEBUG` and `WP_TESTS_CHECKOUT`.
2. `putenv()` and set `$_ENV` for `APP_ENV=testing`, `COMPOSER_VENDOR_DIR=<conformance>/vendor`, `APP_PACKAGES_CACHE=<conformance>/cache/packages.php` and `APP_SERVICES_CACHE=<conformance>/cache/services.php`.
3. Require `conformance/vendor/autoload.php`. This loads `wp-api/load.php` through the root package's `autoload.files` once Task 4 adds it; until then, require `wp-api/load.php` directly if it exists.
4. Boot Laravel: `$app = require <root>/bootstrap/app.php; $app->make( Illuminate\Contracts\Console\Kernel::class )->bootstrap();`
5. Require WordPress's `tests/phpunit/includes/utils.php` and `mock-invokable.php` from the checkout.
6. Require `includes/WP_UnitTestCase.php` and `includes/stand-ins.php`.

`phpunit.xml.dist` has two suites: `harness` (`conformance/harness`) and `wordpress`, an explicit `<file>` list that starts empty.

Do not turn on `optimize-autoloader` or classmap scanning in `conformance/composer.json`. The path link puts the project inside its own `conformance/vendor/`, so a directory scan would loop.

- [ ] **Step 4: Run the harness**

Run `composer conformance:fetch`, then `composer conformance -- --testsuite harness`.
Expected: `OK (4 tests, …)`. Run `composer conformance:fetch` again; expected: `already at 7.1.4`.

- [ ] **Step 5: Format and commit**

Run `vendor/bin/pint`, then commit with the message `Add the WordPress 7.1.4 checker (PHPUnit 9.6, boots the Laravel app)`.

### Task 2: The hook doors

**Files:**
- Create: `wp-api/load.php`. It requires, in order:
  - `interim/l10n.php` and `interim/kses.php`;
  - `classes/WP_Hook.php`, `classes/WP_Filter_Sentinel.php` and `classes/WP_Exception.php`;
  - `functions/hooks.php`, `functions/errors.php` and `functions/return-helpers.php`.
- Create: `wp-api/classes/WP_Hook.php`, `WP_Filter_Sentinel.php` and `WP_Exception.php`, from 7.1.4's `class-wp-hook.php`, `class-wp-filter-sentinel.php` and `class-wp-exception.php`.
- Create: `wp-api/functions/hooks.php`. It covers everything in 7.1.4's `plugin.php` from the top-level `global` statements through `do_action_deprecated()`, plus `_wp_call_all_hook()` and `_wp_filter_build_unique_id()`. The plugin-path functions (`plugin_basename()` … `register_uninstall_hook()`) come with the plugin-loading step; say so in the file docblock.
- Create: `wp-api/functions/errors.php` with `_deprecated_hook()`, `_doing_it_wrong()` and `wp_trigger_error()`, from 7.1.4's `functions.php`.
- Create: `wp-api/functions/return-helpers.php` with `__return_true()`, `__return_false()`, `__return_zero()`, `__return_null()`, `__return_empty_array()` and `__return_empty_string()`.
- Create: `wp-api/interim/l10n.php` with `__( $text, $domain = 'default' )`, which returns `$text`. Declare it only if there is no `__` function.
- Create: `wp-api/interim/kses.php` with `wp_kses( $content, $allowed_html, $allowed_protocols = array() )`, which returns `strip_tags( $content, array_keys( $allowed_html ) )`. Declare it only if there is no `wp_kses` function.
- Create: `docs/stand-ins.md`, a table with one row each for `__()`, `wp_kses()` and the checker's `WP_Post`. Each row says what is missing compared with WordPress and which step replaces it.
- Create: `docs/divergences.md`. It has a heading, the columns from rule 4 (what WordPress does, what we do, why, plugins broken, how fixed) and the line `None yet.`
- Modify: `conformance/phpunit.xml.dist`. Add these to the `wordpress` suite, under `wordpress-develop/tests/phpunit/tests/`: `actions.php`, `filters.php` and every file in `hooks/`.

**Interfaces:**
- Consumes: `WP_UnitTestCase` and the bootstrap from Task 1.
- Produces:
  - the globals `$wp_filter`, `$wp_actions`, `$wp_filters` and `$wp_current_filter`, initialised as in 7.1.4, including turning a pre-filled `$wp_filter` into hooks with `WP_Hook::build_preinitialized_hooks()`;
  - every hook function, with 7.1.4's signatures;
  - `WP_Hook`, `WP_Filter_Sentinel` and `WP_Exception`;
  - `_deprecated_hook()`, `_doing_it_wrong()` and `wp_trigger_error()`;
  - the `__return_*()` functions.

**Steps:**

- [ ] **Step 1: Add the hooks tests to the `wordpress` suite and run them**

Run `composer conformance -- --testsuite wordpress`.
Expected: errors, `Call to undefined function add_action()`, from `WP_UnitTestCase::set_up()`.

- [ ] **Step 2: Write the files listed above**

Follow WordPress 7.1.4's logic, in our style, with full door docblocks.

- [ ] **Step 3: Run the suite**

Run `composer conformance -- --testsuite wordpress`.
Expected: everything passes **except** the data sets of `test_priority_callback_order_with_integers` and `test_priority_callback_order_with_unhappy_path_nonintegers`, in `Tests_Actions`, `Tests_Filters`, `Tests_Hooks_ApplyFilters` and `Tests_Hooks_DoAction`. Those error with `Call to undefined function wp_list_pluck()`, which Task 3 fixes.

- [ ] **Step 4: Format and commit**

Run `vendor/bin/pint`, then commit with the message `Add the hook doors (WP_Hook and the hook functions)`, followed by the pass and error counts.

### Task 3: List helpers, and the checker at 100%

**Files:**
- Create: `wp-api/classes/WP_List_Util.php`, from 7.1.4.
- Create: `wp-api/functions/list-helpers.php` with `wp_filter_object_list()`, `wp_list_filter()`, `wp_list_pluck()` and `wp_list_sort()`, from 7.1.4.
- Modify: `wp-api/load.php`, requiring both new files.
- Modify: `conformance/phpunit.xml.dist`. Add `functions/wpListPluck.php`, `wpListUtil.php`, `wpListFilter.php`, `wpListSort.php` and `wpFilterObjectList.php`.

**Interfaces:**
- Consumes: `_doing_it_wrong()` and `__()` from Task 2.
- Produces: `WP_List_Util` and the four list functions, with 7.1.4's signatures.

**Steps:**

- [ ] **Step 1: Add the five test files and run the `wordpress` suite**

Expected: `Call to undefined function wp_list_pluck()` and `Class "WP_List_Util" not found`.

- [ ] **Step 2: Write the two files**

- [ ] **Step 3: Run the checker**

Run `composer conformance`.
Expected: no errors or failures in either suite. List any skipped or risky test in the commit message, with WordPress's reason for it.

- [ ] **Step 4: Format and commit**

Run `vendor/bin/pint`, then commit with the message `Add WP_List_Util and the wp_list_* helpers; hooks checker at 100%`.

### Task 4: Connect the noticeboard to Laravel

**Files:**
- Modify: `composer.json`. Add `"files": ["wp-api/load.php"]` under `autoload`, then run `composer dump-autoload`.
- Create: `app/Core/Hooks/HookBus.php`
- Create: `app/Providers/CoreServiceProvider.php`
- Modify: `bootstrap/providers.php`, registering `CoreServiceProvider`.
- Modify: `tests/TestCase.php`. On the first `setUp()`, snapshot the four hook globals (cloning each `WP_Hook`, as `WP_UnitTestCase::_backup_hooks()` does); restore them in `tearDown()`.
- Test: `tests/Unit/Core/HookBusTest.php` and `tests/Feature/Core/CoreServiceProviderTest.php`

**Interfaces:**
- Consumes: the hook doors from Task 2 and `_doing_it_wrong()`.
- Produces: `App\Core\Hooks\HookBus`, a `final` container singleton. It holds no state of its own; every method goes through the doors, so the `$wp_filter` global stays the single source of truth.
  - `addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void`
  - `addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void`
  - `removeFilter(string $hook, callable $callback, int $priority = 10): bool`
  - `applyFilters(string $hook, mixed $value, mixed ...$args): mixed`
  - `doAction(string $hook, mixed ...$args): void`
  - `bridge(string $eventClass, string $action, Closure $arguments): void`: when a Laravel event of `$eventClass` is dispatched, call `do_action( $action, ...$arguments( $event ) )`.
- Produces: `CoreServiceProvider`.
  - `register()` binds `HookBus` as a singleton and defines `WP_DEBUG` as `(bool) config( 'app.debug' )` if it isn't already defined.
  - `boot()` sends WordPress's warnings to Laravel's log. It adds `__return_false` to the filter `wp_trigger_error_trigger_error`, and the action callback `[App\Core\Hooks\WordPressErrors::class, 'log']` on `wp_trigger_error_always_run`. That callback, when `WP_DEBUG` is true, calls `Log::notice()` for `E_USER_NOTICE` and `E_USER_WARNING`, and `Log::channel( 'deprecations' )->warning()` for `E_USER_DEPRECATED`. Both use the message and the context `['wordpress_function' => $function_name]`. `E_USER_ERROR` still throws `WP_Exception`, as WordPress does. This uses WordPress's own filter and action, so it is not a divergence.
  - **Both callbacks are strings or static-method arrays, never closures.** Every Laravel test boots a fresh app, so `boot()` runs many times in one process. WordPress's hook engine treats an identical string or static callback as the same entry, so it is never added twice; a new closure each time would be.
- Create: `app/Core/Hooks/WordPressErrors.php`, with `public static function log(string $function_name, string $message, int $error_level): void`.

**Steps:**

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Core/HookBusTest.php`, extending `Tests\TestCase`:
- `test_hooks_live_in_globals_after_boot()`: after `app( HookBus::class )->addFilter( 'hookbus_test', fn ( $v ) => $v.'!' )`, `apply_filters( 'hookbus_test', 'a' )` is `'a!'`, and `$GLOBALS['wp_filter']['hookbus_test']` is an instance of `WP_Hook`.
- `test_plugin_filters_reach_laravel_code()`: after `add_filter( 'hookbus_test2', fn ( $v ) => $v * 2 )`, `app( HookBus::class )->applyFilters( 'hookbus_test2', 21 )` is `42`.
- `test_bridge_fires_wordpress_action_for_laravel_event()`: define a test event class with a public `$id`, call `bridge( TestEvent::class, 'test_event_fired', fn ( $e ) => [ $e->id ] )`, add `add_action( 'test_event_fired', $spy )`, then dispatch `event( new TestEvent( 7 ) )`. `$spy` received `7` exactly once.
- `test_hooks_do_not_leak_between_tests_a()`: `add_action( 'core_leak_probe', '__return_true' )`, then `assertTrue( (bool) has_action( 'core_leak_probe' ) )`.
- `test_hooks_do_not_leak_between_tests_b()`, marked `#[Depends('test_hooks_do_not_leak_between_tests_a')]`: `assertFalse( has_action( 'core_leak_probe' ) )`.

`tests/Feature/Core/CoreServiceProviderTest.php`:
- `test_wp_debug_follows_app_debug()`: `assertSame( (bool) config( 'app.debug' ), WP_DEBUG )`.
- `test_doing_it_wrong_logs_instead_of_throwing()`: skip unless `WP_DEBUG`. Call `Log::spy()`, add an action spy on `doing_it_wrong_run`, then call `_doing_it_wrong( 'some_function', 'Bad call.', '1.0.0' )`. No exception is thrown, the `doing_it_wrong_run` spy was called once, and `Log::shouldHaveReceived( 'notice' )->once()`.

- [ ] **Step 2: Run them**

Run `php artisan test --filter=Core`. Expected: errors, `Class "App\Core\Hooks\HookBus" not found`.

- [ ] **Step 3: Implement the files above**

- [ ] **Step 4: Run everything**

Run `php artisan test` and `composer conformance`. Expected: both green.

- [ ] **Step 5: Docs, format and commit**

Add `composer conformance:fetch` and `composer conformance` to the Commands block in `AGENTS.md`, with one line on why there are two test runners. Run `vendor/bin/pint`, then commit with the message `Connect the hook noticeboard to Laravel (HookBus, event bridge, warnings to the log)`.

### Task 5: Tests on GitHub (CI)

This task is built now but only runs once the owner allows pushing.

**Files:**
- Create: `.github/workflows/tests.yml`

**Steps:**

- [ ] **Step 1: Write the workflow**

It runs on push and on pull requests to `main`, with a PHP matrix of `8.3` and `8.5`, using `shivammathur/setup-php` (check its current major release first: rule 11). Steps:
1. `composer install`
2. `vendor/bin/pint --test`
3. `cp .env.example .env && php artisan key:generate`
4. `php artisan test`
5. `composer conformance:fetch`
6. `composer conformance`

- [ ] **Step 2: Commit** with the message `Run app tests, style check and the WordPress checker in CI`.

## After this plan (not in scope)

- **Loading plugins.** Map WordPress's startup stages (`muplugins_loaded`, `plugins_loaded`, `init`, `wp_loaded`) onto Laravel's providers and request lifecycle, and load plugins.
- **A plugin error policy.** Laravel turns any PHP notice into a crash. Plugins full of sloppy notices need a decided policy (log or crash, per environment) before real plugins are loaded. This is the owner's decision.
- **Hook fault review.** With the checker at 100%, list any hook behaviour that exists only for dead reasons, as candidates for the owner.
- **Next plan:** the database layer and WordPress's tables on Laravel's connection (MySQL/MariaDB first, PostgreSQL proven early).
