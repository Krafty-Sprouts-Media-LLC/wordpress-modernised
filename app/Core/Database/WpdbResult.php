<?php

declare(strict_types=1);

namespace App\Core\Database;

use Closure;
use stdClass;

/**
 * One query's outcome in the shapes WordPress's wpdb expects from mysqli:
 * rows as objects with every value a string (or null), and column details
 * like mysqli_fetch_field() returns. Column details are built only when
 * asked for (WordPress's get_col_info()), because on PostgreSQL they cost a
 * catalog lookup per column.
 */
final class WpdbResult
{
    /** @var list<stdClass>|null */
    private ?array $columns = null;

    /**
     * @param  list<stdClass>  $rows
     * @param  (Closure(): list<stdClass>)|null  $columnsLoader
     */
    public function __construct(
        public readonly bool $isResultSet,
        public readonly array $rows = [],
        private readonly ?Closure $columnsLoader = null,
        public readonly int $affectedRows = 0,
    ) {}

    /**
     * The column details, built on first use.
     *
     * @return list<stdClass>
     */
    public function columns(): array
    {
        return $this->columns ??= $this->columnsLoader ? ($this->columnsLoader)() : [];
    }
}
