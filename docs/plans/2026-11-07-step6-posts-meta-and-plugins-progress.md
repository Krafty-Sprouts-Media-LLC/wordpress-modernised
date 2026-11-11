# Step 6 progress: parked

**Status (2026-11-11): parked by the owner.** The project is paused, not abandoned. Everything below is committed; every suite was green on MySQL 9.7, MariaDB 12.3 and PostgreSQL 18 at the last run (app tests 77/21 skipped, 77/21, 93/5; WordPress's checker 2,502 tests each).

## Where things stand

- `main` holds steps 2–5: hooks, the database layer (MySQL, MariaDB, PostgreSQL), the object cache, options and transients.
- Branch `step6-posts-meta-and-plugins` holds step 6, Tasks 1–4 of [the plan](2026-11-07-step6-posts-meta-and-plugins.md): formatting and date helpers, post types and statuses, the metadata API, posts. It is not merged into `main`.

## To resume

1. Check out `step6-posts-meta-and-plugins`; run `docker compose up -d --wait`, then the suites on all three databases.
2. Finish Task 4's docs (rule 4):
   - `docs/divergences.md`:
     - posts and post meta go through Laravel's query builder;
     - database errors become `WP_Error` from Laravel exceptions;
     - posts scheduled for the future don't publish themselves until WP-Cron is built;
     - `_transition_post_status()` isn't hooked until the cron step;
     - the trashed-slug lookup is answered directly, not through `get_posts()`;
     - on PostgreSQL, `NOT NULL` text columns default to `''`, as MySQL's relaxed mode stores.
   - `docs/stand-ins.md`:
     - taxonomy additions (`wp_delete_object_term_relationships()`, `clean_object_term_cache()`, `update_object_term_cache()`);
     - users;
     - comments (`wp_defer_comment_counting()`, trash and untrash);
     - cron (`wp_clear_scheduled_hook()`, `wp_schedule_single_event()`);
     - `get_permalink()` (plain permalinks);
     - the `$wp_rewrite` fallbacks (feeds, `pagination_base`);
     - the checker rule that a fixture needing an unbuilt area makes its class wait;
     - `post/wpInsertPost.php` kept out of the checker until the logins step.
3. Task 5 (the plugin loader), then the final review, then Task 6: run one of the owner's plugins.

## Notes and rulings made while building (Tasks 1–4)

Task 1: Ruling: helpers split into wp-api/functions/formatting.php, dates.php, utf8.php and kses.php (entity rules only) instead of all in values.php — mirrors WordPress's files, keeps each focused — cost if wrong: none
Task 1: Ruling: mysql2date(), wp_date() and wp_encode_emoji() left out — they need WP_Locale or the emoji tables; posts reach wp_encode_emoji() only when a column's charset is utf8 (ours are utf8mb4) — cost if wrong: a fatal if that branch is ever reached
Task 1: Ruling: utf8.php carries only WordPress's mbstring branches — Laravel requires mbstring and PHP ≥ 8.1.6 — cost if wrong: none
Task 1: Ruling: added WordPress's unit constants (wp-api/constants.php) and get_locale() stand-in — date and accent tests need them — cost if wrong: none
Task 1: Ruling: WordPress's default filters for built areas moved into wp-api/default-filters.php (wm_add_default_filters()); step 5's two provider lines moved there; added sanitize_title→sanitize_title_with_dashes and pre_option_gmt_offset→wp_timezone_override_offset (both needed by the tests) — cost if wrong: none
Task 1: complete (commits 204c64c..bdd2510, tests: powershell.exe -NoProfile -ExecutionPolicy Bypass -File .superpowers/sdd/2026-11-07-step6-posts-meta-and-plugins/all.ps1 → pgsql app: Tests:    5 skipped, 84 passed (203 assertions) | checker: Tests: 1849, Assertions: 3361, Warnings: 8, Skipped: 396.)
Task 2: Ruling: WP_Post_Type::remove_rewrite_rules() guards $wp/$wp_rewrite (null until the request and rewrite steps) — unregistering a post type with a query var would fatal otherwise; nothing was added to remove — cost if wrong: none
Task 2: Ruling: EP_* constants added (wp-api/constants.php) — WP_Post_Type::set_props() reads EP_PERMALINK for every post type with rewrites, i.e. most plugin post types — cost if wrong: none
Task 2: Ruling: getPostStatus.php moved to Task 4 (needs get_post(), wp_trash_post()) — cost if wrong: none
Task 2: Ruling: checker gains assertWPError/assertNotWPError, reset_post_types()/reset_post_statuses() in set_up (replacing step 5's direct wp_load_alloptions(), as planned) — cost if wrong: none
Task 2: Ruling: start-up registration uses a new App\Core\Database\WordPressTables::ready() (Schema::hasTable inside try/catch) — a bare hasTable broke WpdbConnectionLifecycleTest::test_app_boots_without_a_reachable_database (RED→GREEN); Task 5's loader reuses it — cost if wrong: one extra query per boot
Task 2: Ruling: 13 post-type tests wait (7 rewrite, 3 REST, 3 terms) — cost if wrong: none
Task 2: complete (commits bdd2510..c2b12c0, tests: powershell.exe -NoProfile -ExecutionPolicy Bypass -File .superpowers/sdd/2026-11-07-step6-posts-meta-and-plugins/all.ps1 → pgsql app: Tests:    5 skipped, 84 passed (203 assertions) | checker: Tests: 1958, Assertions: 3571, Warnings: 8, Skipped: 416.)
Task 3: Ruling: MetaRepository takes the table and its columns (not a meta type) — WordPress resolves meta tables by name via $wpdb->{type}meta, which plugins extend; ids()/objectIds() return text and row() text values, as $wpdb does — cost if wrong: none
Task 3: Ruling: wp_metadata_lazyloader() left out (needs WP_Metadata_Lazyloader, used by comments/terms) — cost if wrong: a plugin calling it fatals until the comments/terms steps
Task 3: Ruling: meta can't be verified before posts exist — get_object_subtype() calls get_post_type() on every post meta call — so Task 3's commit waits for Task 4's posts; both land together (separate commits) — cost if wrong: none
Task 3: Ruling: added wp_is_numeric_array(), default filter register_meta_args→_wp_register_meta_args_allowed_list, checker unregister_all_meta_keys() in tear_down — cost if wrong: none
Task 3: note for review: register_meta() with a 'default' and a schema reaches rest_validate_value_from_schema() (REST step) — a plugin registering meta defaults may fatal until then
