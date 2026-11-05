# WordPress, modernised on Laravel

> **Working name.** "wordpress-modernised" is a placeholder until the project goes to production. The WordPress Foundation's trademark policy restricts "WordPress" and "WP" in product names, so the final name will be original. Keep the name out of code identifiers (no `WordPress\` namespaces, no name-bearing package prefixes) so renaming stays a one-place change.

## What this is

WordPress itself, rebuilt on Laravel: WordPress's code carried onto Laravel **part by part**, rewritten as Laravel code (not WordPress running inside Laravel), its faults fixed along the way, and WordPress's manuals adapted to match.

Once a part is rebuilt it belongs to this project. It takes what it chooses from WordPress's later releases (new Gutenberg versions, new APIs, security fixes worth porting) and never waits for them. Laravel supplies what WordPress builds by hand: queues and scheduling instead of WP-Cron, migrations instead of `dbDelta`, caching, auth, validation, testing.

**Status:** nothing of the core is built yet. Plans live in `docs/plans/`.

## Decisions already made

| Topic | Decision |
|---|---|
| Licence | **GPL-2.0-or-later** (required for WordPress-derived code; Laravel's MIT licence is compatible) |
| Database | One database layer. **MySQL/MariaDB first** (what plugins expect), **PostgreSQL proven early**. Plugins' raw MySQL queries go through a translator, or the plugin gets fixed |
| WordPress baseline | **WordPress 7.1.4** (the `7.1.4` tag of `wordpress-develop`). Its PHPUnit suite is the measure of "works like WordPress" |
| PHP | **8.3 or newer** (Laravel 13's minimum). WordPress itself supports 8.3+, so only plugins stuck on old PHP are lost |
| Framework | Laravel 13, PHPUnit (the same test runner WordPress uses) |
| Editor | **Gutenberg kept**, improved rather than replaced. It talks to the core only through the REST API |
| Admin | **WordPress's admin, refreshed**: same structure and the same ways for plugins to plug in, rebuilt in Blade, responsive and accessible. Designs are shown to the owner before building |
| Name | Decided before production, not now |

## Non-negotiable rules

1. **This repository is public.** Nothing internal goes into a tracked file: the owner's words, sites, plugin lists, client names, local paths, other private projects, credentials. Internal material lives in `private/` and `CLAUDE.local.md`, both gitignored. Before every commit, check new and changed docs for internal content.

2. **The compatibility promise.** Plugins and themes that use WordPress's documented functions, hooks and REST API run unchanged, as far as the rebuilt core behaves the same. Plugins that depend on undocumented behaviour, or on a fault we fix, get fixed copies.

3. **Every fault fix records its plugin cost.** A deliberate change from WordPress's behaviour is written down in `docs/divergences.md`: what WordPress does, what we do instead, why, which plugins it breaks, and how they are fixed. No silent divergence.

4. **WordPress's test suite is the specification.** Each rebuilt part runs WordPress's own tests for it. A failing test is either a bug in our code or a recorded divergence (rule 3), whose test is then adapted to describe the corrected behaviour. "Passes" plus "deliberately diverges, recorded" must account for every test.

5. **Before copying any WordPress behaviour, state why it exists.** If the reason is a dead constraint (shared hosting, PHP 4/5, MySQL 4, permanent backwards compatibility with nothing that still uses it), it is a fault candidate: fix it and record the cost. If plugins or editors genuinely rely on it, keep it.

6. **Plugins see WordPress's API; Laravel sits behind it.** WordPress's global functions (`add_filter()`, `get_post_meta()`, `WP_Query`…) keep their names, signatures and return shapes. Their implementations are Laravel services. Plugin-facing behaviour is never "improved" by changing a signature.

7. **WordPress-derived code keeps its provenance.** A file ported from WordPress says, in its file-level docblock, which WordPress file and version it comes from. The project stays GPL-2.0-or-later.

8. **Documentation is adapted, not invented.** WordPress's manuals and handbooks are the starting point, edited to describe this project. WordPress.org documentation is GPLv2 by default (some is CC0); confirm the licence of each source before copying it in.

9. **The owner decides.** Present options with a recommendation; assume nothing. Write a plan just before building it, and build nothing until the owner approves the design.

10. **Verify versions from source.** Check the current release of any package, WordPress, PHP or tool before choosing or pinning it. Never rely on remembered versions.

## Where things go

| Path | What |
|---|---|
| `docs/plans/` | One plan per build step, `<date>-<step>-<name>.md`, written just before it is built |
| `docs/divergences.md` | Every deliberate difference from WordPress, with its plugin cost (rule 3) |
| `private/` | Internal briefs, references and notes. Gitignored; never published |
| `CLAUDE.local.md` | Private context for coding agents. Gitignored |

## Commands

The local toolchain is Laravel Herd on Windows (PHP and Composer on `PATH` in PowerShell).

```bash
composer install
php artisan test
vendor/bin/pint
```
