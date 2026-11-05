<?php

declare(strict_types=1);

namespace App\Core\Database;

use stdClass;

/**
 * One query's outcome in the shapes WordPress's wpdb expects from mysqli:
 * rows as objects with every value a string (or null), and column details
 * like mysqli_fetch_field() returns.
 */
final readonly class WpdbResult
{
    /**
     * @param  list<stdClass>  $rows
     * @param  list<stdClass>  $columns
     */
    public function __construct(
        public bool $isResultSet,
        public array $rows = [],
        public array $columns = [],
        public int $affectedRows = 0,
    ) {}
}
