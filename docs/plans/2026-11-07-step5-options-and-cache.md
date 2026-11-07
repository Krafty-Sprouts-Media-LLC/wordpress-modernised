# Step 5: Options and the cache. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

## In plain words

**The goal:** the first room of the house. Options are the site's settings (`get_option( 'blogname' )`, `update_option()`); every plugin uses them. They need WordPress's cache, so the cache comes in the same step. Transients (settings that expire) come with them.

**What we build:**
1. **One query counter.** WordPress counts every database query (`get_num_queries()`), and its tests check those counts. From now on the house talks to the database with Laravel's query builder, so Laravel's queries and `$wpdb`'s go into one count.
2. **WordPress's cache on Laravel's cache.** `wp_cache_get()`, `wp_cache_set()` and the rest. During a request they keep a fast copy in memory. If you switch on a persistent store (Redis, say) in `.env`, WordPress and Laravel share it.
3. **Small helpers options need:** turning values into stored text and back (`maybe_serialize()`), and slash handling.
4. **Options:** `get_option()`, `add_option()`, `update_option()`, `delete_option()`, autoloading, the site-option doors on a single site, and a typed Laravel face (`Options`) for our own code, on the same cache and hooks.
5. **Transients**, with WordPress's daily clean-up of expired ones run by Laravel's scheduler.

**Decisions for you (my recommendation first):**

| # | Question | Recommendation | Why |
|---|---|---|---|
| A | Where does WordPress's cache keep things between requests? | **Memory only by default, as WordPress does. Set `WP_OBJECT_CACHE_STORE=redis` (any Laravel cache store) and it becomes persistent, shared with Laravel.** | Rule 6: WordPress keeps the cache in memory because many hosts have no Redis. That reason is still alive. Laravel's own default store is the database, and caching database reads in the database gains nothing. With Redis, transients also move out of the options table into the cache, as they do in WordPress. |
| B | Stored format of option values? | **Keep PHP's serialized format for now.** Switching to JSON comes later as its own recorded change. | You decided WordPress's tables stay as they are for now. Changing the format breaks plugins that read `wp_options` with their own SQL, so it needs its own plan with its plugin cost. |
| C | How does the house talk to the database? | **Laravel's query builder,** with `$wpdb`'s query count and query log fed from Laravel's query events: one counter, one log. | It's the Laravel way, and the builder writes SQL that works on MySQL, MariaDB and PostgreSQL alike, which solves backlog item 19 for everything we rebuild. Cost, recorded: WordPress's `query` filter only sees queries plugins send through `$wpdb`, not the core's own. |
| D | How does our own Laravel code read options? | **A small typed `Options` service over the same doors** (same cache, same hooks). No Eloquent model for options. | An Eloquent model would skip the cache and the filters, so it would give different answers from `get_option()` (rule 2). |
| E | Backlog item 18: we promise PHP 8.3, but Laravel's Symfony 8 components need PHP 8.4.1. | **Raise the minimum to PHP 8.4.** | PostgreSQL already needs 8.4. Staying on 8.3 would mean pinning older Symfony 7 packages. WordPress's minimum stays a recorded difference. |

---

**Goal:** WordPress 7.1.4's cache and option tests pass on MySQL, MariaDB and PostgreSQL, except recorded waits and differences. Options and transients are stored through Laravel's query builder. WordPress's cache runs on Laravel's cache system. Laravel code reads options through the same cache and hooks.

**Architecture:**
- **Cache.** The house class `App\Core\Cache\ObjectCache` holds a per-request memory layer, plus an optional Laravel cache store, chosen by `config('wordpress.object_cache')` (`null` means memory only). The door class `WP_Object_Cache` keeps WordPress's shape and hands each call to it. `wp_cache_*()` call the global `$wp_object_cache`, as in WordPress.
- **Options.** The doors in `wp-api/functions/option.php` carry WordPress's flow:
  - filters and actions;
  - the `alloptions` and `notoptions` cache entries, which plugins read directly;
  - validation and `_doing_it_wrong()` notices.

  They read and write rows only through the house class `App\Core\Options\OptionRepository`, which uses Laravel's query builder.
- **Queries.** `App\Core\Database\QueryCounter` listens to Laravel's `QueryExecuted` and adds each house query to `$wpdb->num_queries`, and to `$wpdb->queries` when `SAVEQUERIES` is on. Queries `$wpdb` runs itself are counted once, as now.
- **Events.** `added_option`, `updated_option` and `deleted_option` are announced once: the doors dispatch the Laravel events `OptionAdded`, `OptionUpdated` and `OptionDeleted`, and `HookBus::bridge()` turns each into its WordPress action.

**Tech Stack:** PHP 8.4+ (if E is approved), Laravel 13.40 (cache, query builder, events, scheduler), MySQL 9.7 / MariaDB 12.3 / PostgreSQL 18. No new packages.

**Spec:** `AGENTS.md` (decisions; rules 2–8 and 11), `docs/coding-standards.md`, and WordPress 7.1.4's `wp-includes/option.php`, `cache.php`, `cache-compat.php`, `class-wp-object-cache.php` and `functions.php` (serialization helpers), read from `conformance/wordpress-develop/src/`. Decisions A–E become binding when the owner approves this plan.

## Global Constraints

- New doors are tagged `@since WM-0.4.0` first, then their `@since WP-x.y.z` history. Files with carried-over code name the WordPress file and `7.1.4` in their file docblock (rule 8).
- Doors keep WordPress's exact names, parameters, defaults and return shapes, with no added types (rule 7). House classes are `final`, typed, and use `declare(strict_types=1)`.
- House SQL goes through Laravel's query builder only, never string-built SQL, so it is portable to all three databases.
- `wp_cache_flush()` never empties Laravel's own cache entries. A persistent store is flushed by moving a version key, not by `Cache::flush()`.
- WordPress test files run unmodified. A test that can't pass yet is listed in `conformance/waiting.php` (area not built) or `conformance/diverged.php` (recorded difference), with a reason.
- The checker excludes the test groups WordPress's own `phpunit.xml.dist` excludes for single sites: `ajax`, `ms-files`, `ms-required`, `external-http`.
- Everything passes on all three databases: `php artisan test` and `composer conformance`, each with `DB_CONNECTION=mysql`, `mariadb` and `pgsql`.
- Run `vendor/bin/pint` with no path arguments. Nothing private goes in tracked files. Nothing is pushed until the owner says so.

## Review Focus

1. **A persistent store is shared between requests.** Expect a value set through one `WP_Object_Cache` to be found by a fresh `WP_Object_Cache` (a new request) when `wordpress.object_cache` is set, and not found when it is `null`. Test: Task 2, `test_a_persistent_store_is_shared_between_requests`.
2. **Flushing WordPress's cache keeps Laravel's entries.** Expect `Cache::store($store)->get('laravel-key')` to survive `wp_cache_flush()` and `wp_cache_flush_group('posts')`, while every WordPress key is gone. Test: Task 2, `test_flushing_keeps_laravels_own_entries`.
3. **Values a store can't serialize.** WordPress's memory cache accepts closures. Expect `wp_cache_set( 'k', fn () => 1 )` with a persistent store to return `true`, keep the value for this request, and not throw. Test: Task 2, `test_unserializable_values_stay_in_memory`.
4. **Odd keys on a persistent store.** Expect keys with spaces, slashes and 300 characters to round-trip through a fresh instance, since Memcached rejects such raw keys. Test: Task 2, `test_odd_keys_work_with_a_persistent_store`.
5. **Expiry on a persistent store.** Expect `wp_cache_set( 'k', 'v', 'g', 1 )`, then travelling 2 seconds, to miss in a fresh instance. Test: Task 2, `test_expiry_applies_in_a_persistent_store`.

---

### Task 1: PHP 8.4 minimum and one query counter

**Files:**
- Modify: `composer.json` (`"php": "^8.4"`), `.github/workflows/tests.yml` (drop the PHP 8.3 `include` row; add one `php: '8.4'` row on `mysql:9.7`)
- Modify: `app/Providers/CoreServiceProvider.php` (remove `supportsPostgres()` and its guard; Composer's platform check now refuses PHP 8.3), `tests/Feature/Core/CoreServiceProviderTest.php` (remove `test_postgresql_needs_php_8_4`)
- Create: `app/Core/Database/QueryCounter.php`
- Modify: `app/Core/Database/WpdbConnection.php` (mark its own `logQuery()` call so the counter skips it)
- Test: `tests/Feature/Core/Database/QueryCounterTest.php`
- Docs: `AGENTS.md` (decisions table: PHP row and Defaults row say 8.4), `docs/divergences.md` (the minimum-PHP entry says 8.4 and why; a new entry: "the `query` filter sees plugins' `$wpdb` queries, not the core's own", with its plugin cost: query-rewriting plugins see fewer queries; Query Monitor-style tools still see every query through `$wpdb->queries`), `docs/backlog.md` (delete items 18 and 19; item 19 is answered by decision C)

**Interfaces:**
- Produces: `QueryCounter::register(Dispatcher $events): void`, called from `CoreServiceProvider::boot()`. `WpdbConnection` exposes nothing new to other tasks.

- [ ] **Step 1: Write the failing tests**

```php
public function test_laravel_queries_count_as_wordpress_queries(): void
{
    global $wpdb;
    $before = get_num_queries();
    DB::table($wpdb->options)->count();
    $this->assertSame($before + 1, get_num_queries());
}

public function test_wpdb_queries_count_once(): void
{
    global $wpdb;
    $before = get_num_queries();
    $wpdb->query('SELECT 1');
    $this->assertSame($before + 1, get_num_queries());
}

#[RunInSeparateProcess]
public function test_saved_queries_include_laravel_queries(): void
{
    define('SAVEQUERIES', true);
    $this->refreshApplication();
    global $wpdb;
    DB::table($wpdb->options)->where('option_name', 'wm_probe')->count();
    $this->assertStringContainsString('wm_probe', end($wpdb->queries)[0] ?? '');
}
```

(`wm_probe` is a binding, so the logged SQL must show bindings inlined, as `$wpdb->queries` holds runnable SQL. Use `$event->toRawSql()`.)

- [ ] **Step 2: Run them and see the first and third fail**

Run: `php artisan test --filter=QueryCounterTest`. Expected: the first fails (`+0`), the second passes, the third fails.

- [ ] **Step 3: Implement `QueryCounter` and the `WpdbConnection` mark**

The counter listens for `QueryExecuted` on the default connection only. It skips events raised inside `WpdbConnection::query()`, using a flag set around its `logQuery()` call. For every other event it increments `$GLOBALS['wpdb']->num_queries`, and when `SAVEQUERIES` is defined and true, it calls `$wpdb->log_query($event->toRawSql(), $event->time / 1000, $wpdb->get_caller(), microtime(true) - $event->time / 1000, [])`.

- [ ] **Step 4: Apply the PHP 8.4 changes and docs listed above; run everything**

Run: `composer update --lock` (the lock's hash changes; no package versions change), then `php artisan test` and `composer conformance` on all three databases. Expected: all pass, with the counts from step 4's merge minus the removed PHP 8.3 test.

- [ ] **Step 5: Commit** — `Raise the minimum to PHP 8.4; count Laravel's queries as WordPress's`

---

### Task 2: WordPress's cache on Laravel's cache

**Files:**
- Create: `app/Core/Cache/ObjectCache.php` (house)
- Create: `wp-api/classes/WP_Object_Cache.php` (door; carried over from `class-wp-object-cache.php` 7.1.4 for its shape, key checks and notices)
- Create: `wp-api/functions/cache.php` (every function in `cache.php` and `cache-compat.php` 7.1.4, the salted ones included, plus `wp_using_ext_object_cache()`, `wp_suspend_cache_addition()` and `wp_suspend_cache_invalidation()`)
- Modify: `wp-api/load.php`, `config/wordpress.php` (`'object_cache' => env('WP_OBJECT_CACHE_STORE')`), `.env.example` (a commented `WP_OBJECT_CACHE_STORE=redis` with one line on what it does), `app/Providers/CoreServiceProvider.php` (`bootCache()`: `wp_cache_init()`, then `wp_using_ext_object_cache($cache->isPersistent())`; it runs before `bootDatabase()`)
- Modify: `conformance/phpunit.xml.dist` (add `tests/cache.php`, and the `<groups><exclude>` list from Global Constraints)
- Test: `tests/Feature/Core/Cache/ObjectCacheTest.php`
- Docs: `docs/divergences.md` (the object cache: memory by default, as WordPress; any Laravel store when configured; flush by version key; `switch_to_blog` keeps one blog, since there is no multisite)

**Interfaces:**
- Produces, in `App\Core\Cache\ObjectCache` (`final`):
  - `__construct(?Illuminate\Contracts\Cache\Repository $store)`;
  - `static fromConfig(): self`, which reads `wordpress.object_cache`;
  - `get(int|string $key, string $group, bool $force, ?bool &$found): mixed`;
  - `add`, `set` and `replace(int|string $key, mixed $data, string $group, int $expire): bool`;
  - `delete(int|string $key, string $group): bool`;
  - `incr` and `decr(int|string $key, int $offset, string $group): int|false`;
  - `flush(): bool`, `flushRuntime(): bool`, `flushGroup(string $group): bool`;
  - `addNonPersistentGroups(array $groups): void`, `addGlobalGroups(array $groups): void`;
  - `isPersistent(): bool`;
  - `hits(): int`, `misses(): int`, `runtime(): array`.
- `WP_Object_Cache` builds its own house instance in its constructor (`ObjectCache::fromConfig()`), because WordPress's tests do `new WP_Object_Cache()`. `cache`, `cache_hits` and `cache_misses` are read live from the house, with the virtual-property pattern `wpdb::$dbh` uses.

- [ ] **Step 1: Write the failing app tests**

Write the five Review Focus tests. Each sets `config(['wordpress.object_cache' => 'array'])` and uses a fresh `new WP_Object_Cache()` as "the next request". Laravel's `array` store is one per process, so it stands in for Redis. Add:

```php
public function test_memory_only_by_default(): void
{
    (new WP_Object_Cache)->set('k', 'v', 'g');
    $this->assertFalse((new WP_Object_Cache)->get('k', 'g'));
    $this->assertFalse(wp_using_ext_object_cache());
}

public function test_non_persistent_groups_never_reach_the_store(): void
{
    config(['wordpress.object_cache' => 'array']);
    $first = new WP_Object_Cache;
    $first->add_non_persistent_groups(['counts']);
    $first->set('k', 'v', 'counts');
    $this->assertFalse((new WP_Object_Cache)->get('k', 'counts'));
}
```

- [ ] **Step 2: Run them and see them fail** (`WP_Object_Cache` is not defined)

Run: `php artisan test --filter=ObjectCacheTest`

- [ ] **Step 3: Implement the house, the door and the functions**

House rules:
- **Memory first.** Reads check memory first, then the store, then copy the store's answer into memory. Writes go to both, except for non-persistent groups.
- **Copies, not references.** Objects are cloned on set and on get, as WordPress does, so a caller can't change the cached copy by accident.
- **Store keys.** A store key is `'wm:'.hash('xxh128', "$version|$group|$groupVersion|$key")`. `flush()` bumps `$version` and `flushGroup()` bumps that group's version; both versions live in the store under fixed keys.
- **Expiry.** `$expire = 0` means `forever()`; otherwise `put($key, $value, $expire)`.
- **Unserializable values.** A store write that throws (a closure, say) is caught: the value stays in memory, and the method still returns `true`.
- **incr and decr.** These follow WordPress: a non-numeric value counts as 0, and a result below 0 becomes 0. They write through to the store, so they are not atomic across processes; that is recorded in the divergence entry.

- [ ] **Step 4: Run the app tests, then WordPress's cache tests**

Run: `php artisan test --filter=ObjectCacheTest`, then `composer conformance -- --filter Tests_Cache` on all three databases. Expected: all pass. The `switch_to_blog` tests either pass on one blog or are listed in `diverged.php` with the reason "no multisite (owner's decision B, step 3)".

- [ ] **Step 5: Commit** — `Run WordPress's object cache on Laravel's cache`

---

### Task 3: Value helpers options rely on

**Files:**
- Modify: `wp-api/functions/values.php` (carried over from `functions.php` and `formatting.php` 7.1.4):
  - serialization: `maybe_serialize()`, `maybe_unserialize()`, `is_serialized()`, `is_serialized_string()`;
  - slashes: `wp_slash()`, `wp_unslash()`, `stripslashes_deep()`, `stripslashes_from_strings_only()`, `map_deep()`.
- Modify: `wp-api/functions/environment.php` (`wp_installing()`)
- Modify: `wp-api/interim/multisite.php` (`get_current_network_id()` returns `1`; listed in `docs/stand-ins.md`)
- Create: `wp-api/interim/sanitize-option.php`, a stand-in `sanitize_option()` that only runs the `sanitize_option_{$option}` filter (WordPress's default branch). Core-option cleaning comes with the formatting step. Add it to `wp-api/load.php` and list it in `docs/stand-ins.md`.
- Modify: `conformance/phpunit.xml.dist`: add `functions/isSerialized.php`, `functions/isSerializedString.php`, `functions/maybeSerialize.php`, `formatting/wpSlash.php`, `formatting/stripslashesDeep.php` and `formatting/mapDeep.php`

**Interfaces:**
- Produces: the doors above, with WordPress's signatures. Task 4 calls them.

- [ ] **Step 1: Add the checker files and run them; see them fail** (functions undefined)

Run: `composer conformance -- --filter "Tests_Functions_(IsSerialized|IsSerializedString|MaybeSerialize)|Tests_Formatting_(wpSlash|StripslashesDeep|MapDeep)"`

- [ ] **Step 2: Implement the doors** (WordPress's bodies, in Laravel's style via Pint)

- [ ] **Step 3: Run the same command on all three databases; expect all to pass**

- [ ] **Step 4: Commit** — `Add the value helpers options rely on`

---

### Task 4: Options

**Files:**
- Create: `app/Core/Options/OptionRepository.php` (house, query builder only)
- Create: `app/Core/Options/Options.php` (the typed face for Laravel code)
- Create: `app/Core/Options/Events/OptionAdded.php`, `OptionUpdated.php`, `OptionDeleted.php` (readonly classes holding WordPress's action arguments)
- Create: `wp-api/functions/option.php` (doors, carried over from `option.php` 7.1.4):
  - everything except user settings, transients (Task 5), the Settings API (`register_setting()` and its family), and the multisite-only `wp_load_core_site_options()`;
  - `form_option()` is included.
- Modify: `wp-api/load.php`; `app/Providers/CoreServiceProvider.php` (bridge the three events; register WordPress 7.1.4's `default-filters.php` lines for options, such as `wp_default_autoload_value` → `wp_filter_default_autoload_value_via_option_size`, as strings like the existing hooks)
- Modify: `conformance/phpunit.xml.dist`: add these `option/` files:
  - `option.php`, `getOptions.php`, `updateOption.php`, `slashes.php`;
  - `siteOption.php`, `networkOption.php`;
  - `wpAutoloadValuesToAutoload.php`, `wpDetermineOptionAutoloadValue.php`, `wpLoadAlloptions.php`;
  - `wpPrimeOptionCaches.php`, `wpPrimeOptionCachesByGroup.php`, `wpPrimeNetworkOptionCaches.php`;
  - `wpSetOptionAutoload.php`, `wpSetOptionAutoloadValues.php`, `wpSetOptionsAutoload.php`.
- Modify: `conformance/waiting.php`: `Tests_Option_Registration::*` and `Tests_Option_SanitizeOption::*` (Settings API and formatting step), `Tests_Option_ThemeMods::*` (themes step), `Tests_Option_UserSettings::*` and `Tests_Option_wpUserSettings::*` (logins step). Add these five files to `phpunit.xml.dist` too, so every run shows them as waiting, with the reason.
- Test: `tests/Feature/Core/Options/OptionsTest.php`
- Docs: `docs/divergences.md` (option rows are written through Laravel's query builder; `add_option()`'s "insert or update" uses `upsert()`; values stay serialized: decision B)

**Interfaces:**
- Consumes: Task 1 (counting), Task 2 (`wp_cache_*`), Task 3 (helpers).
- Produces, in `OptionRepository` (`final`):
  - `autoloaded(): array<string, string>` (name ⇒ raw value);
  - `find(string $name): ?string`;
  - `findMany(array $names): array<string, string>`;
  - `insert(string $name, string $value, string $autoload): bool`;
  - `update(string $name, string $value, ?string $autoload): int` (rows changed);
  - `delete(string $name): int`;
  - `autoloadOf(array $names): array<string, string>`;
  - `setAutoload(array $autoloadByName): int`.
- Produces, in `Options` (`final`, container-resolved):
  - `get(string $name, mixed $default = false): mixed`;
  - `set(string $name, mixed $value, ?bool $autoload = null): bool`;
  - `delete(string $name): bool`.

  Each calls the matching door, so the cache and hooks are the same.

- [ ] **Step 1: Write the failing app tests**

```php
public function test_options_round_trip_on_this_database(): void
{
    $value = ['a' => 1, 'quote' => "it's", 'nested' => [true, null, 1.5]];
    $this->assertTrue(add_option('wm_test', $value));
    wp_cache_flush();
    $this->assertSame($value, get_option('wm_test'));
    $this->assertTrue(update_option('wm_test', 'plain'));
    $this->assertSame('plain', get_option('wm_test'));
    $this->assertTrue(delete_option('wm_test'));
    $this->assertFalse(get_option('wm_test'));
}

public function test_laravel_code_reads_the_same_option_from_the_same_cache(): void
{
    update_option('wm_shared', 'x');
    $before = get_num_queries();
    $this->assertSame('x', app(Options::class)->get('wm_shared'));
    $this->assertSame($before, get_num_queries());
}

public function test_an_update_is_announced_once_to_both_sides(): void
{
    $laravel = 0; $wordpress = 0;
    Event::listen(OptionUpdated::class, function () use (&$laravel) { $laravel++; });
    add_action('updated_option', function () use (&$wordpress) { $wordpress++; });
    add_option('wm_once', 'a');
    update_option('wm_once', 'b');
    $this->assertSame([1, 1], [$laravel, $wordpress]);
}
```

- [ ] **Step 2: Run them and see them fail** (`add_option` undefined)

Run: `php artisan test --filter=OptionsTest`

- [ ] **Step 3: Implement the house, the doors and the bridge**

The doors keep WordPress's order of filters, actions and cache steps exactly; WordPress's query-count tests depend on it. Where WordPress dispatches `do_action( 'added_option' | 'updated_option' | 'deleted_option', … )`, the door dispatches the Laravel event instead, and the bridge fires the action with the same arguments.

- [ ] **Step 4: Run the app tests, then the checker, on all three databases**

Run: `php artisan test`, then `composer conformance`. Expected: all pass. Any remaining failure is fixed or listed with a reason. Report the counts per database.

- [ ] **Step 5: Commit** — `Rebuild options on Laravel's query builder and cache`

---

### Task 5: Transients and their daily clean-up

**Files:**
- Modify: `wp-api/functions/option.php`:
  - `get_transient()`, `set_transient()`, `delete_transient()`;
  - `get_site_transient()`, `set_site_transient()`, `delete_site_transient()`;
  - `delete_expired_transients()`: WordPress's multi-table `DELETE a, b FROM …` is MySQL-only, so it is rebuilt through `OptionRepository::deleteExpiredTransients(int $now): int`.
- Modify: `app/Core/Options/OptionRepository.php` (`deleteExpiredTransients()`), `routes/console.php` (`Schedule::call(fn () => do_action('delete_expired_transients'))->daily()`), `app/Providers/CoreServiceProvider.php` (`add_action('delete_expired_transients', 'delete_expired_transients')`, from `default-filters.php`)
- Modify: `conformance/phpunit.xml.dist` (add `option/transient.php` and `option/siteTransient.php`)
- Test: `tests/Feature/Core/Options/TransientsTest.php`
- Docs: `docs/divergences.md`:
  - the daily clean-up runs on Laravel's scheduler, not WP-Cron (WP-Cron comes later);
  - a persistent cache keeps transients in Laravel's cache store, as WordPress does with any persistent object cache.

  Also update the `AGENTS.md` status line to say options and the cache are built.

**Interfaces:**
- Consumes: Task 4's `OptionRepository` and doors.
- Produces: `OptionRepository::deleteExpiredTransients(int $now): int` (rows deleted).

- [ ] **Step 1: Write the failing app tests**

```php
public function test_expired_transients_are_cleaned_on_every_database(): void
{
    set_transient('wm_old', 'x', 60);
    set_transient('wm_new', 'y', 3600);
    $this->travel(2)->minutes();
    delete_expired_transients();
    wp_cache_flush();
    $this->assertNull(app(OptionRepository::class)->find('_transient_wm_old'));
    $this->assertSame('y', get_transient('wm_new'));
}

public function test_the_scheduler_runs_the_clean_up_daily(): void
{
    $events = collect(app(Schedule::class)->events());
    $this->assertTrue($events->contains(fn ($e) => $e->expression === '0 0 * * *'
        && str_contains((string) $e->description, 'delete_expired_transients')));
}
```

(Give the scheduled call `->description('delete_expired_transients')` so the test can find it. `set_transient()` uses PHP's `time()`, so the first test's travel must reach it: add `$this->travelTo(now())` before `set_transient()` and check that `time()` follows Carbon. If it doesn't, write the expired row's timeout directly instead.)

- [ ] **Step 2: Run them and see them fail**

Run: `php artisan test --filter=TransientsTest`

- [ ] **Step 3: Implement the transient doors, the repository method and the schedule**

`deleteExpiredTransients()` runs two deletes per prefix (`_transient_` and `_site_transient_`). Each deletes the value rows whose matching `…timeout_…` row is below `$now`, then the expired timeout rows, using a query-builder subquery, never a MySQL multi-table delete.

- [ ] **Step 4: Run everything on all three databases**

Run: `php artisan test`, `composer conformance`, and `vendor/bin/pint --test`. Expected: all pass. Record the per-database checker counts in `docs/divergences.md`, as step 4 did.

- [ ] **Step 5: Commit** — `Add transients, cleaned daily by Laravel's scheduler`
