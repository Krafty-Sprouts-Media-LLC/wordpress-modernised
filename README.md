# WordPress, modernised on Laravel

WordPress rebuilt on Laravel, part by part: WordPress's code rewritten as Laravel code, its faults fixed, and every difference from WordPress recorded with its cost to plugins.

**Status:** early. Built so far: the hooks, the database layer, the object cache, options and transients.

"wordpress-modernised" is a working name. The project will take an original name before any release.

## Requirements

- PHP 8.4 or newer
- Composer

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan test
```

## Licence

GPL-2.0-or-later. See [LICENSE](LICENSE). Code derived from WordPress keeps WordPress's GPL licence; Laravel is MIT-licensed, which is compatible.
