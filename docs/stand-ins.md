# Stand-ins and waiting tests

**In plain words:** some pieces are temporary placeholders until the real part is built. Some WordPress tests have to wait for a part we haven't built yet. Both are listed here so nothing temporary is forgotten.

## Stand-ins

| Stand-in | Where | What it lacks compared with WordPress | Replaced by |
|---|---|---|---|
| `wp_kses()` | `wp-api/interim/kses.php` | Keeps allowed tags but does not filter attributes or URL protocols. Only `wp_trigger_error()` uses it, on its own messages | The HTML-filtering (kses) step |
| `sanitize_option()` | `wp-api/interim/sanitize-option.php` | Runs only the `sanitize_option_{$option}` filter; none of WordPress's rules for its own options (`admin_email`, `siteurl`, `posts_per_page`, …), and no settings errors | The formatting step |
| The Requests library (`WpOrg\Requests\…`) | `conformance/bootstrap.php` (checker only) | WordPress's own copy, autoloaded from the fetched sources, because some tests build its objects | The HTTP step |
| A WordPress install's options | `conformance/bootstrap.php` (checker only) | Seven of the options `populate_options()` writes (`siteurl`, `home`, `blogname`, `blogdescription`, `admin_email`, `timezone_string`, `wp_attachment_pages_enabled`), with WordPress's test-install values | The install step |
| Loading options before each test | `conformance/includes/WP_UnitTestCase.php` (checker only) | WordPress's `set_up()` registers the default post types, which reads an option and loads every autoloaded one; ours calls `wp_load_alloptions()` directly | The posts step |
| `WP_Post` | `conformance/includes/stand-ins.php` (checker only) | Copies fields onto itself; no behaviour | The posts step |
| `wpdb` | `conformance/includes/stand-ins.php` (checker only) | Empty class. WordPress's test utilities declare a class extending it | The database step |
| `wp_die()`, `_default_wp_die_handler()`, `dead_db()` | `wp-api/interim/die.php` | `wp_die()` runs the `wp_die_handler` filter, but doesn't pick AJAX, JSON or XML-RPC handlers; the default handler throws `WP_Exception` (Laravel shows an error page) instead of printing WordPress's HTML page; no `db-error.php` template | WordPress's error pages, with the request step |
| `wp_load_translations_early()`, `get_locale()`, `_x()`, `_n()`, `_nx()`, `_n_noop()`, `_nx_noop()`, `esc_html__()`, `esc_attr__()` | `wp-api/interim/l10n.php` | English only: no translations are loaded. `get_locale()` is always `en_US` (through the `locale` filter); the others return the English text through WordPress's gettext filters | The translation step |
| `is_main_site()`, `is_main_network()`, `get_current_blog_id()`, `get_current_network_id()` | `wp-api/interim/multisite.php` | Always `true`, and IDs always `1`: single site only (owner's decision B) | Multisite, if it is ever decided |
| `ABSPATH` and `wp-admin/includes/upgrade.php` | `conformance/abspath/` (checker only) | An empty `upgrade.php`, because `dbDelta()` is already loaded. Plugins also `require_once ABSPATH . 'wp-admin/includes/upgrade.php'` before `dbDelta()`, so the app will need the same | The plugin-loading step (what `ABSPATH` means in the app) |
| No login system | `config/auth.php` | Laravel's own `users` table, `User` model and password resets are removed; WordPress's `wp_users` is the one user table, but nothing logs in yet | The logins step |

## Known clash: `__()`

Laravel defines a global `__()` translation helper, and Composer loads it before our code, so in this app `__()` is Laravel's.

- **One-argument calls** like `__( 'Hello' )` work: Laravel returns the text unchanged when it has no translation.
- **WordPress's two-argument form** `__( 'Hello', 'my-plugin' )` does **not** work yet. Laravel's second parameter expects an array of replacements, not a text domain.

Plugins call the two-argument form constantly, so the translation step must give the app one `__()` that understands both forms.

## Waiting tests

WordPress tests skipped because they depend on an area not rebuilt yet. The list lives in `conformance/waiting.php`; each shows as skipped, with its reason, in every checker run.

| Test | Waiting for |
|---|---|
| `Tests_Filters::test_other_priority` | WordPress's default filters (it reads the `the_content` hook) |
| `Tests_DB::test_get_col`, `Tests_DB::test_db_reconnect` | The default admin user a WordPress install creates (logins step) |
| `Tests_DB::test_mysqli_flush_sync` | WordPress's post factory and `wp_delete_post()` (posts step) |
