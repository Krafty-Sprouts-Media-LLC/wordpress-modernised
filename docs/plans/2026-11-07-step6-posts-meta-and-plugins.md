# Step 6: Posts, post meta and a plugin loader. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

## In plain words

**The goal:** the cheap test of the whole project. Build the room most plugins use (posts and the extra data plugins attach to them, "post meta"), add a way to switch a plugin on, then run one of your own plugins unchanged. If it runs, this approach works; if it struggles, you'll know before spending more.

**What we build:**
1. **Small helpers posts need:** turning a title into a web address ("Hello World" → `hello-world`), dates and time zones, and WordPress's argument handling.
2. **Post types and statuses:** the list of kinds of content (post, page, …) and states (draft, published, …).
3. **Post meta:** the extra data plugins attach to posts (`get_post_meta()`, `update_post_meta()`). It's built so users', comments' and categories' extra data can reuse it later.
4. **Posts:** create, read, change, trash and delete posts (`wp_insert_post()`, `get_post()`, …), with WordPress's announcements (`save_post` and friends) heard by plugins and Laravel alike.
5. **A plugin loader:** a `wp-content/plugins` folder, a command to switch plugins on and off (`php artisan wp:plugin activate …`), and WordPress's start-up moments (`plugins_loaded`, `init`, `wp_loaded`) at the right points in Laravel's start-up.
6. **The test:** we load **your** plugin and record what works, what breaks, and why.

**What is not in this step:** users and logins (step 7, straight after), categories and tags, post search (`WP_Query` and `get_posts()`), revisions and autosaves, the admin screens and themes. A plugin feature that needs them is recorded as "waits for step N", not counted as a failure of the idea.

**Decisions for you (my recommendation first):**

| # | Question | Recommendation | Why |
|---|---|---|---|
| A | Where do plugins live? | **`wp-content/plugins/` in the project folder, kept out of the public repository** (gitignored), except our own small sample plugin used by the tests. | Plugins expect WordPress's folder layout. Your own plugins may be private or paid, so they never go into the public repository (rule 1). |
| B | When do plugins' start-up hooks run? | **On every web request and every `artisan` command**, right after Laravel has started: active plugins load, then `muplugins_loaded`, `plugins_loaded`, `init` and `wp_loaded` fire, in WordPress's order. | Plugins do their setup on these hooks; WordPress runs them on every page load, and also in WP-CLI. Queue workers run them once per worker, like any Laravel boot. |
| C | How are plugins switched on before the admin exists? | **An `artisan` command:** `wp:plugin list`, `wp:plugin activate <plugin>`, `wp:plugin deactivate <plugin>`. It runs WordPress's activation steps (the plugin's `register_activation_hook()` included) and refuses a plugin that crashes while loading. | Same steps as the admin's "Activate" link, available now; the admin will call the same code later. |
| D | Which of your plugins do we test with? | **You choose one of your own simple plugins** that saves settings and adds post meta. Its code stays private; the public docs say only "one of the owner's plugins" and what kind of features it used. | It exercises exactly what this step builds. |
| E | Revisions, autosaves, the 30-day trash clean-up and default categories on new posts? | **Not in this step;** each recorded as waiting. | Each needs a room not built yet (users or categories) or the scheduler work of a later step. |

---

**Goal:** WordPress 7.1.4's tests for post meta, post types, statuses and the post functions built pass on MySQL, MariaDB and PostgreSQL, except recorded waits and differences. A sample plugin activates and runs through `artisan`. Then one of the owner's own plugins is run and the results are recorded.

**Architecture:**
- **Same split as step 5.** Doors in `wp-api/functions/` carry WordPress's flow (filters, actions, caches) from `wp-includes/post.php`, `meta.php`, `class-wp-post.php`, `class-wp-post-type.php` and `plugin.php`. They read and write rows only through house repositories on Laravel's query builder, one call per query WordPress runs:
  - `App\Core\Meta\MetaRepository`, generic over meta tables (`postmeta` now; `usermeta`, `commentmeta` and `termmeta` later);
  - `App\Core\Posts\PostRepository`.
- **Announcements.** `save_post` fires as `save_post_{$post->post_type}`, then `save_post`, then `wp_insert_post`, in WordPress's order. Only the generic `save_post` and `deleted_post` actions are bridged from the Laravel events `PostSaved` and `PostDeleted` (the step-5 pattern). All other post actions stay plain `do_action()` calls.
- **Plugins.** `App\Core\Plugins\PluginLoader` (house) reads `active_plugins`, checks each file exists under `WP_PLUGIN_DIR`, includes it, and fires WordPress's start-up actions. It runs from a `$app->booted()` callback in `CoreServiceProvider`, skipped when the options table doesn't exist yet (fresh install, `migrate`).
- **Stand-ins.** Categories and tags are not built, so a taxonomy stand-in answers "no taxonomies" (`get_object_taxonomies()` returns `[]`, `is_object_in_taxonomy()` returns `false`). `wp_insert_post()` therefore skips categories and tags, as WordPress does for a post type without them. With no users yet, `get_current_user_id()` returns `0` and `current_user_can()` returns `false`.

**Tech Stack:** PHP 8.4+, Laravel 13.40 (query builder, events, console commands), MySQL 9.7 / MariaDB 12.3 / PostgreSQL 18. No new packages.

**Spec:** `AGENTS.md` (decisions; rules 1–11), `docs/coding-standards.md`, WordPress 7.1.4's sources in `conformance/wordpress-develop/src/` (`wp-includes/post.php`, `meta.php`, `class-wp-post.php`, `class-wp-post-type.php`, `post-formats.php` as needed, `plugin.php`, `load.php`, `formatting.php`, `functions.php`, `link-template.php`, `wp-admin/includes/plugin.php`, `wp-settings.php`). Decisions A–E become binding when the owner approves this plan.

## Global Constraints

- New doors are tagged `@since WM-0.5.0` first, then their `@since WP-x.y.z` history. Use the step-5 extraction approach: copy WordPress's functions with their docblocks, retag them, then replace each `$wpdb` call with one repository call. Files with carried-over code name the WordPress file and `7.1.4` (rule 8).
- Doors keep WordPress's exact names, parameters, defaults and return shapes (rule 7). House classes are `final`, typed, `declare(strict_types=1)`, and use Laravel's query builder only.
- Multisite branches are left out, as in step 5.
- Query counts match WordPress's on the paths WordPress's tests count.
- The checker loads WordPress's own test factories (`tests/phpunit/includes/factory/`) unmodified. Tests needing users, terms, `WP_Query`, themes, comments or attachments go in `conformance/waiting.php` with the step they wait for. A data provider that fails before a skip can apply keeps its file out of `phpunit.xml.dist` until its step, with a note in `waiting.php`'s header.
- Nothing of the owner's plugin goes into a tracked file: not its code, name, options or output. The test results go in `private/`; the public record says "one of the owner's plugins".
- Everything passes on all three databases: `php artisan test` and `composer conformance`. Run `vendor/bin/pint`. Nothing is pushed.

## Review Focus

1. **A plugin that crashes while loading.** Expect `wp:plugin activate` to fail with the plugin's error message and leave `active_plugins` unchanged; the app keeps booting. Test: Task 5, `test_a_plugin_that_crashes_is_not_activated`.
2. **An active plugin whose file has gone.** Expect boot to skip it and log a notice; WordPress would also deactivate it in the admin later. Test: Task 5, `test_a_missing_active_plugin_is_skipped`.
3. **A fresh install.** Expect `php artisan migrate` and the app to boot when the options table doesn't exist yet; no plugin loads. Test: Task 5, `test_boot_without_an_options_table_loads_no_plugins`.
4. **Windows paths.** Expect `plugin_basename()` on a path with backslashes, and on one with mixed slashes, to give `wm-sample/wm-sample.php`. Test: Task 5, `test_plugin_basename_handles_windows_paths`.
5. **Post IDs and meta on PostgreSQL.** Expect `wp_insert_post()` to return the new integer ID, `get_post()->ID` to match it, and post meta holding `"it's"`, an array and a serialized-looking string to round-trip. Test: Task 4, `test_posts_and_meta_round_trip_on_this_database`.

---

### Task 1: Helpers posts need

**Files:**
- Modify: `wp-api/functions/values.php` (from `functions.php` and `formatting.php` 7.1.4): `wp_parse_args()`, `wp_parse_list()`, `wp_parse_id_list()`, `sanitize_key()`, `sanitize_title()`, `sanitize_title_with_dashes()`, `remove_accents()`, `wp_check_invalid_utf8()`, `seems_utf8()`/`wp_is_valid_utf8()` (whichever 7.1.4 uses), `wp_encode_emoji()`, `utf8_uri_encode()`, `_wp_specialchars()`, `esc_html()`, `esc_attr()`, `wp_basename()`.
- Create: `wp-api/functions/dates.php` (from `functions.php` and `formatting.php`): `current_time()`, `current_datetime()`, `wp_timezone()`, `wp_timezone_string()`, `get_gmt_from_date()`, `get_date_from_gmt()`, `wp_checkdate()`, `mysql2date()`, `wp_date()` (only as far as `mysql2date()` needs; translation of month names via the existing l10n stand-in).
- Modify: `wp-api/interim/l10n.php`: stand-ins for `_x()`, `_n()`, `_nx()`, `_n_noop()`, `_nx_noop()`, `esc_html__()`, `esc_attr__()`, returning the English text, and a note on the existing `__()` clash (`docs/stand-ins.md`).
- Modify: `conformance/phpunit.xml.dist`: add the 7.1.4 tests for these functions that exist under `tests/formatting/` and `tests/functions/`. Find them with `grep -l` on each function name, and list in this task's commit message the files added. Tests needing areas not built go to `waiting.php`.

**Interfaces:**
- Produces: the doors above with WordPress's signatures. Tasks 2–5 call them.

- [ ] **Step 1:** Add the test files to the checker; run them; see them fail (functions undefined).
- [ ] **Step 2:** Carry the functions over (extraction script, retag, Pint).
- [ ] **Step 3:** Run them on all three databases; expect all to pass or be listed with reasons.
- [ ] **Step 4: Commit** — `Add the formatting and date helpers posts need`

---

### Task 2: Post types, statuses and the taxonomy stand-in

**Files:**
- Create: `wp-api/classes/WP_Post_Type.php` (from `class-wp-post-type.php` 7.1.4; the parts touching rewrite rules, REST controllers and meta boxes keep their methods, with bodies that wait for their steps listed in `docs/stand-ins.md`).
- Create: `wp-api/functions/post-types.php` (from `post.php`):
  - `register_post_type()`, `unregister_post_type()`, `get_post_type_object()`, `get_post_types()`;
  - `post_type_exists()`, `post_type_supports()`, `add_post_type_support()`, `remove_post_type_support()`, `get_post_types_by_support()`, `is_post_type_hierarchical()`, `get_post_type_labels()`, `_get_custom_object_labels()`;
  - `register_post_status()`, `get_post_status_object()`, `get_post_stati()`;
  - `create_initial_post_types()`, `_add_post_type_submenus()` only if `register_post_type()` needs it.
- Create: `wp-api/interim/taxonomy.php`: `get_object_taxonomies()` returns `[]`, `is_object_in_taxonomy()` and `taxonomy_exists()` return `false`, `get_taxonomy()` returns `false`.
- Create: `wp-api/interim/users.php`: `get_current_user_id()` returns `0`, `current_user_can()` returns `false`, `is_user_logged_in()` returns `false`.
- Both interim files are listed in `docs/stand-ins.md`, replaced by the terms and logins steps.
- Modify: `app/Providers/CoreServiceProvider.php`: call `create_initial_post_types()` at boot (WordPress hooks it on `init` priority 0; we run it in the `init` sequence Task 5 builds; until then, at boot).
- Modify: `conformance/includes/WP_UnitTestCase.php`: `reset_post_types()` and `reset_post_statuses()` from `abstract-testcase.php` 7.1.4, called in `set_up()` as WordPress does. This replaces step 5's direct `wp_load_alloptions()` stand-in, since `create_initial_post_types()` reads the option itself.
- Modify: `conformance/phpunit.xml.dist`: `tests/post/types.php`, `tests/post/wpPostType.php`, `tests/post/getPostStatus.php`, `tests/post/getPostTypeLabels.php`, `tests/post/isPostTypeViewable.php`, `tests/post/isPostStatusViewable.php`.

**Interfaces:**
- Produces: the doors above. Task 4 relies on `get_post_type_object()`, `post_type_supports()`, `get_post_stati()` and `is_object_in_taxonomy()`.

- [ ] **Step 1:** Add the test files; run; see them fail.
- [ ] **Step 2:** Carry the code over; add the stand-ins; wire `create_initial_post_types()`.
- [ ] **Step 3:** Run all suites on all three databases; list waits with reasons.
- [ ] **Step 4: Commit** — `Add post types, post statuses and stand-ins for terms and users`

---

### Task 3: The metadata API

**Files:**
- Create: `app/Core/Meta/MetaRepository.php` (house).
- Create: `wp-api/functions/meta.php` (from `meta.php` 7.1.4):
  - `add_metadata()`, `update_metadata()`, `delete_metadata()`, `get_metadata()`, `get_metadata_raw()`, `get_metadata_default()`, `metadata_exists()`;
  - `get_metadata_by_mid()`, `update_metadata_by_mid()`, `delete_metadata_by_mid()`, `update_meta_cache()`, `wp_metadata_lazyloader()` only if needed;
  - `_get_meta_table()`, `is_protected_meta()`, `sanitize_meta()`;
  - `register_meta()`, `unregister_meta_key()`, `get_registered_meta_keys()`, `get_registered_metadata()`, `_wp_register_meta_args_allowed_list()`, `get_object_subtype()`.
  - Leave out `WP_Meta_Query` and the meta-query functions; they wait for the search step.
- Modify: `conformance/phpunit.xml.dist`: `tests/meta.php`, `tests/meta/deleteMetadata.php`, `tests/meta/isProtectedMeta.php`, `tests/meta/registerMeta.php`, `tests/meta/slashes.php`, `tests/meta/updateMetadata.php`. `tests/meta/query.php` waits for search. Tests here that create posts or users through factories wait for Task 4 or the logins step; re-check them in Task 4.
- Docs: `docs/divergences.md`: meta rows through Laravel's query builder; the `add_{$meta_type}_metadata`-style filters and the `{$meta_type}_meta` cache group are unchanged.

**Interfaces:**
- Produces, in `MetaRepository` (`final`), each taking `string $metaType` (`'post'`, …) and mapping it to its table and ID column (`post_id`, `user_id`, …, `umeta_id` for users) as `_get_meta_table()` and WordPress's column rules do:
  - `forObjects(string $metaType, array $objectIds): array<int, array<string, list<string>>>` (WordPress's `update_meta_cache()` shape);
  - `add(string $metaType, int $objectId, string $key, string $value): int` (new meta ID);
  - `idsFor(string $metaType, int $objectId, string $key, ?string $value): list<int>`;
  - `updateValue(string $metaType, int $objectId, string $key, string $value, ?string $previous): int`;
  - `deleteIds(string $metaType, array $metaIds): int`;
  - `byId(string $metaType, int $metaId): ?stdClass`;
  - `updateById(string $metaType, int $metaId, string $key, string $value): int`.
- Each method is one query. If WordPress's code needs a different split to keep its query counts, follow WordPress and record the ruling.

- [ ] **Step 1:** Add the test files; run; see them fail.
- [ ] **Step 2:** Write `MetaRepository` and the doors (extraction script; one repository call per `$wpdb` call).
- [ ] **Step 3:** Run all suites on all three databases.
- [ ] **Step 4: Commit** — `Rebuild the metadata API on Laravel's query builder`

---

### Task 4: Posts

**Files:**
- Create: `app/Core/Posts/PostRepository.php` (house); `app/Core/Posts/Events/PostSaved.php`, `PostDeleted.php`.
- Create: `wp-api/classes/WP_Post.php` (from `class-wp-post.php` 7.1.4). Remove the checker's `WP_Post` stand-in in `conformance/includes/stand-ins.php`.
- Create: `wp-api/functions/post.php` (from `post.php` 7.1.4):
  - reading: `get_post()`, `get_post_field()`, `get_post_status()`, `get_post_type()`, `get_post_parent()`, `has_post_parent()`, `wp_get_post_parent_id()`, `get_post_ancestors()`, `get_children()` (only if it doesn't need `get_posts()`);
  - sanitizing: `sanitize_post()`, `sanitize_post_field()`;
  - writing: `wp_insert_post()`, `wp_update_post()`, `wp_publish_post()`, `wp_trash_post()`, `wp_untrash_post()`, `wp_delete_post()`, `wp_add_trashed_suffix_to_post_name_for_trashed_posts()`, `wp_add_trashed_suffix_to_post_name_for_post()`, `wp_resolve_post_date()`, `wp_unique_post_slug()`, `wp_transition_post_status()`, `wp_after_insert_post()`;
  - caches: `clean_post_cache()`, `update_post_cache()`, `update_post_caches()` (meta part only), `_prime_post_caches()`, `update_postmeta_cache()`;
  - meta wrappers: `add_post_meta()`, `update_post_meta()`, `delete_post_meta()`, `get_post_meta()`, `get_post_custom()`, `get_post_custom_keys()`, `get_post_custom_values()`, `register_post_meta()`, `unregister_post_meta()`, `delete_post_meta_by_key()`.
  - Branches that call terms, users, attachments, thumbnails or page templates stay as WordPress wrote them; they answer through the Task 2 stand-ins. A branch that would fatal (an attachment-only function, say) is left out with a `// WM:` note and its tests listed in `waiting.php`.
- Modify: `conformance/bootstrap.php`: load WordPress's test factories (`tests/phpunit/includes/factory.php` and `factory/`). Modify `WP_UnitTestCase`: `factory()`, `$this->factory`, as in `abstract-testcase.php` 7.1.4.
- Modify: `conformance/phpunit.xml.dist`, adding these `tests/post/` files:
  - `getPost.php`, `wpPost.php`, `wpInsertPost.php`, `wpDeletePost.php`, `wpTrashPost.php`, `wpUntrashPost.php`, `wpPublishPost.php`;
  - `wpUniquePostSlug.php`, `truncatePostSlug.php`, `getPostParent.php`, `wpGetPostParentId.php`;
  - `meta.php`, `slashes.php`, `updatePostCache.php`, `primePostCaches.php`, `wpAfterInsertPost.php`;
  - and `tests/post.php`.

  Re-run Task 3's meta tests that waited for posts. Every test that needs users, terms, `WP_Query`/`get_posts()`, revisions, attachments, comments or themes goes in `waiting.php` with its step.
- Test: `tests/Feature/Core/Posts/PostsTest.php`.
- Docs: `docs/divergences.md`: posts through Laravel's query builder; `save_post` and `deleted_post` bridged from Laravel events; no revisions, autosaves or default category yet; trash is kept until deleted (no 30-day clean-up yet).

**Interfaces:**
- Consumes: Tasks 1–3.
- Produces, in `PostRepository` (`final`):
  - `find(int $id): ?stdClass` (a row with WordPress's casing via the step-4 dialect: `ID`);
  - `findMany(array $ids): list<stdClass>`;
  - `insert(array $columns): int` (new ID; `insertGetId()`, which returns the ID on PostgreSQL too);
  - `update(int $id, array $columns): int`;
  - `delete(int $id): int`;
  - `slugTaken(string $slug, string $postType, int $exceptId, int $parent, bool $hierarchical): bool`;
  - `childrenOf(int $id): list<int>`.
- Produces: `PostSaved(int $postId, WP_Post $post, bool $update)` and `PostDeleted(int $postId, WP_Post $post)`, bridged to `save_post` and `deleted_post`.

- [ ] **Step 1: Write the failing app tests**

```php
public function test_posts_and_meta_round_trip_on_this_database(): void
{
    $id = wp_insert_post(['post_title' => 'Hello World', 'post_status' => 'publish', 'post_content' => "it's"]);

    $this->assertIsInt($id);
    $this->assertGreaterThan(0, $id);
    $this->assertSame($id, get_post($id)->ID);
    $this->assertSame('hello-world', get_post($id)->post_name);

    foreach (["it's", ['a' => [1, 2]], 'a:1:{i:0;s:1:"x";}'] as $value) {
        update_post_meta($id, 'wm_key', $value);
        wp_cache_flush();
        $this->assertSame($value, get_post_meta($id, 'wm_key', true));
    }
}

public function test_saving_is_announced_once_to_both_sides(): void
{
    $laravel = 0;
    $wordpress = 0;
    Event::listen(PostSaved::class, function () use (&$laravel) { $laravel++; });
    add_action('save_post', function () use (&$wordpress) { $wordpress++; });

    wp_insert_post(['post_title' => 'x']);

    $this->assertSame([1, 1], [$laravel, $wordpress]);
}
```

- [ ] **Step 2:** Run them; see them fail.
- [ ] **Step 3:** Write `PostRepository`, `WP_Post`, the doors, the events and their bridges, and the factories in the checker.
- [ ] **Step 4:** Run all suites on all three databases.
- [ ] **Step 5: Commit** — `Rebuild posts and post meta on Laravel's query builder`

---

### Task 5: The plugin loader

**Files:**
- Create: `app/Core/Plugins/PluginLoader.php` (house: finds, validates and includes active plugins, fires the start-up actions).
- Create: `app/Console/Commands/WpPluginCommand.php`: `wp:plugin {action : list|activate|deactivate} {plugin?}`.
- Create: `wp-api/functions/plugin.php` (from `plugin.php`, `load.php` and `wp-admin/includes/plugin.php` 7.1.4):
  - paths and hooks: `plugin_basename()`, `wp_register_plugin_realpath()`, `plugin_dir_path()`, `plugin_dir_url()`, `plugins_url()`, `register_activation_hook()`, `register_deactivation_hook()`, `register_uninstall_hook()`;
  - activation: `wp_get_active_and_valid_plugins()`, `is_plugin_active()`, `validate_plugin()`, `activate_plugin()`, `deactivate_plugins()`, `get_plugin_data()` (header parsing only), `get_file_data()`.
- Create: `wp-api/functions/link-template.php`, only what `plugins_url()` needs: `site_url()`, `get_site_url()`, `home_url()`, `get_home_url()`, `set_url_scheme()`, `is_ssl()`, `content_url()`.
- Modify: `config/wordpress.php`: `'content_dir' => env('WP_CONTENT_DIR', base_path('wp-content'))`. The provider defines `WP_CONTENT_DIR`, `WP_PLUGIN_DIR` and `WP_CONTENT_URL`/`WP_PLUGIN_URL` (from `siteurl`) before plugins load. `ABSPATH` points at the app's `wp-api/abspath/`, which holds the empty `wp-admin/includes/upgrade.php` (moved from `conformance/abspath/`, listed in `docs/stand-ins.md`).
- Modify: `app/Providers/CoreServiceProvider.php`: `$this->app->booted(fn () => app(PluginLoader::class)->boot())`. It is skipped when `Schema::hasTable(prefix.'options')` is false. In order it:
  1. fires `muplugins_loaded`;
  2. includes the active plugins;
  3. fires `plugins_loaded`, then `setup_theme` and `after_setup_theme` (no themes yet, but plugins hook them);
  4. calls `create_initial_post_types()` through `init` at priority 0, as WordPress hooks it;
  5. fires `init`, then `wp_loaded`.
- Create: `tests/Fixtures/wp-content/plugins/wm-sample/wm-sample.php`, a plugin in WordPress's style. It has a header, saves an option on activation, registers a post type on `init`, adds post meta on `save_post`, and adds a filter. Also `tests/Fixtures/wp-content/plugins/wm-broken/wm-broken.php`, which throws on load.
- Modify: `.gitignore`: `/wp-content/` (decision A).
- Test: `tests/Feature/Core/Plugins/PluginLoaderTest.php` (uses `config(['wordpress.content_dir' => base_path('tests/Fixtures/wp-content')])`).
- Docs:
  - `docs/divergences.md`: plugins are switched on with `artisan` until the admin exists; the start-up actions fire on every request and command; there are no must-use plugins and no `wp-config.php`.
  - `docs/stand-ins.md`: the `abspath/` move.
  - `AGENTS.md` "Where things go": a `wp-content/` row.

**Interfaces:**
- Consumes: Tasks 1–4.
- Produces: `PluginLoader::boot(): void`; `PluginLoader::activate(string $plugin): true|WP_Error`; `PluginLoader::deactivate(string $plugin): void`. The command and, later, the admin call these.

- [ ] **Step 1: Write the failing tests:** the four Review Focus tests for this task (1–4), plus:

```php
public function test_an_activated_plugin_runs_on_the_next_boot(): void
{
    $this->artisan('wp:plugin activate wm-sample/wm-sample.php')->assertSuccessful();
    $this->assertSame('activated', get_option('wm_sample_status'));

    $this->refreshApplication();

    $this->assertTrue(post_type_exists('wm_sample_type'));
    $id = wp_insert_post(['post_title' => 'x', 'post_type' => 'wm_sample_type']);
    $this->assertSame('seen', get_post_meta($id, 'wm_sample_seen', true));
}
```

- [ ] **Step 2:** Run them; see them fail.
- [ ] **Step 3:** Write the loader, the command, the doors, the fixtures and the wiring.
- [ ] **Step 4:** Run all suites on all three databases.
- [ ] **Step 5: Commit** — `Load WordPress plugins: a wp-content folder, wp:plugin and start-up hooks`

---

### Task 6: Run one of the owner's plugins

This task is done with the owner, after Tasks 1–5 are merged or on the branch, as the owner prefers.

- [ ] **Step 1:** The owner names the plugin and puts it in `wp-content/plugins/` (gitignored).
- [ ] **Step 2:** `php artisan wp:plugin activate <plugin>`, then exercise what it does without the admin: its hooks, its options, its post meta, any front-end output a test request can reach. Read the plugin's code to list its features first.
- [ ] **Step 3:** Record, in `private/notes/plugin-test-<date>.md`, each feature with one of:
  - **works**;
  - **waits for step N**, the room it needs (users, terms, search, admin, themes);
  - **breaks because of us**: a bug, or a recorded difference that hits it;
  - **breaks because of the plugin**: its own SQL against WordPress's tables, or internals (rule 3).
- [ ] **Step 4:** Fix the "breaks because of us" items that belong to Tasks 1–5's areas, each test-first; list the rest.
- [ ] **Step 5:** Report to the owner in plain words: did the engine part run? The owner then decides between continuing (step 7, users and logins) and parking the project.
