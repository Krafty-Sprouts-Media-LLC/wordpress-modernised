# Coding standards

**In plain words:** one code style everywhere (Laravel's). The "doors" (WordPress's function and class names) keep WordPress's shape so plugins can call them. Every door is documented, with our own version first and WordPress's history labelled. The Laravel code behind the doors is typed and documented briefly.

## 1. One style: Laravel's

- **Pint, with Laravel's preset, formats every PHP file** in `app/`, `wp-api/`, `config/`, `database/`, `routes/`, `tests/` and `conformance/` (our harness files).
- Pint never touches the fetched WordPress checkout (`conformance/wordpress-develop/`) or `vendor/`.
- Run `vendor/bin/pint` before every commit, **with no path arguments**. CI runs `vendor/bin/pint --test`. Given explicit paths, Pint ignores the exclusions in `pint.json`, and the checker's `conformance/vendor/` links back into the project, so Pint would loop.
- We do **not** follow the WordPress Coding Standards (tabs, spaces inside parentheses, Yoda conditions). We don't merge WordPress code, so there's no reason to match its formatting.

## 2. The doors (`wp-api/`) vs the house (`app/`)

| | `wp-api/` (the doors) | `app/` (the house) |
|---|---|---|
| What | WordPress's global functions and `WP_` classes, for plugins and themes | Laravel code: services, models, events, controllers |
| Names | **WordPress's exact names**: `add_filter()`, `WP_Hook`, `snake_case` functions and methods | Laravel's: `StudlyCase` classes, `camelCase` methods, `App\` namespace |
| Types | **None on parameters or returns** (rule 7). Plugins pass loose types that WordPress accepts | Typed parameters, returns and properties |
| `declare(strict_types=1)` | **Never.** Strict types would make the doors reject calls that WordPress accepts, such as `"10"` passed where a number is expected | **Always** |
| Docblocks | Full: see section 3 | Short: see section 4 |

A door does as little as it can: it checks and shapes its input the way WordPress does, then hands the work to a service in `app/Core/<Area>/`. **Doors call the house, never Laravel directly:** code in `wp-api/` may use `App\Core\...` classes, but never `Illuminate\...`. Laravel stays behind the house services.

## 3. Docblocks on the doors

Every function, class, method, property, constant **and hook** in `wp-api/` has a docblock with the following:

1. **A summary line**, written for plugin authors.
2. **`@since WM-x.y.z`**: the version of *our* project that added it. This always comes first.
3. **`@since WP-x.y.z`**: WordPress's version history, if the door comes from WordPress. Copy WordPress's own `@since` lines, adding the `WP-` prefix.
4. **`@param`**, with type and description, for every parameter; **`@return`**, with type and description.

When we change a door's behaviour, add a line in the same style and record the change in `docs/divergences.md`. (The `WM-0.4.0` line below is an invented example.)

```php
/**
 * Adds a callback function to a filter hook.
 *
 * @since WM-0.1.0
 * @since WP-0.71
 * @since WM-0.4.0 Changed: <what changed>. See docs/divergences.md.
 *
 * @param string   $hook_name     The name of the filter to add the callback to.
 * @param callable $callback      The callback to run when the filter is applied.
 * @param int      $priority      Optional. Order of execution. Default 10.
 * @param int      $accepted_args Optional. Number of arguments the callback accepts. Default 1.
 * @return true Always returns true.
 */
function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
```

**Hooks are documented where they fire,** in WordPress's hook style: a docblock immediately before `do_action()` or `apply_filters()`, with a summary, both `@since` tags, and `@param` for each argument.

**Rules for version tags:**

- `WM-` means our project; `WP-` means WordPress. A bare number never appears.
- Our versions are semver, starting at `WM-0.1.0`.
- An existing `@since` line is never edited. A change adds a new line.
- `@deprecated` names the replacement and the version in which the old form is removed.
- At the rename, `WM-` is replaced across the codebase with one search-and-replace.

**File docblocks:** a file in `wp-api/` that contains code carried over from WordPress names its source on one line:

```php
/**
 * Hook functions: add_filter(), apply_filters(), do_action() and the rest.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/plugin.php.
 */
```

## 4. Docblocks in the house (`app/`)

- **Every class, interface, trait and enum opens with one to three sentences saying what it is for.** For example: "Owns the hook registry and gives Laravel code a typed way to add and fire hooks."
- **Write a tag only where PHP's types cannot say it:** array shapes (`@param array<string, mixed> $fields`), generics, and `@throws` for exceptions a caller should handle.
- **No docblocks that repeat the signature.** No `@since`, because git records when internal code changed. No `@author`.

## 5. Naming files and folders

| What | Where and how | Example |
|---|---|---|
| A door class | `wp-api/classes/<ClassName>.php`, named exactly as the class | `wp-api/classes/WP_Hook.php` |
| Door functions | `wp-api/functions/<topic>.php`, kebab-case, grouped by topic | `wp-api/functions/hooks.php` |
| Temporary stand-ins | `wp-api/interim/<topic>.php`, each listed in `docs/stand-ins.md` | `wp-api/interim/l10n.php` |
| Loading the doors | `wp-api/load.php` requires every door file, in order. No Composer autoloading inside `wp-api/` | |
| A service behind a door | `app/Core/<Area>/<Name>.php` | `app/Core/Hooks/HookBus.php` |
| Laravel events | `app/Core/<Area>/Events/<WhatHappened>.php`, past tense | `app/Core/Posts/Events/PostSaved.php` |
| App tests | `tests/Unit/...` or `tests/Feature/...`, named `<Subject>Test.php` | `tests/Unit/Core/HookBusTest.php` |
| Our conformance harness | `conformance/` (harness code), `conformance/adapted/` (WordPress tests adapted after a recorded difference, at WordPress's test paths) | |
| Docs | `docs/`, kebab-case | `docs/divergences.md` |
| Plans | `docs/plans/<date>-<step>-<name>.md` | `docs/plans/2026-11-05-step2-hooks-and-conformance.md` |
