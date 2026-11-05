<?php

/**
 * Stand-in for WordPress's wp-admin/includes/upgrade.php, for code that does
 * `require_once ABSPATH . 'wp-admin/includes/upgrade.php'` before calling
 * dbDelta() (WordPress's own tests, and many plugins). dbDelta() is already
 * loaded from wp-api/functions/db-delta.php, so there is nothing to load.
 * Listed in docs/stand-ins.md.
 */
