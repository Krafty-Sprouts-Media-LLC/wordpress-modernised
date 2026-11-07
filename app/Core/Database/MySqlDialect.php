<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use stdClass;

/**
 * MySQL and MariaDB: exactly what WordPress's mysqli code does.
 */
final class MySqlDialect implements Dialect
{
    public function quoteIdentifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    public function escape(PDO $pdo, string $text): string
    {
        // PDO::quote() minus its outer quotes: byte-for-byte mysqli_real_escape_string().
        return substr($pdo->quote($text), 1, -1);
    }

    public function allowsEmulatedPrepares(): bool
    {
        // Safe here: the connection's MULTI_STATEMENTS option refuses stacked statements.
        return true;
    }

    public function stringifiesFetches(): bool
    {
        return true;
    }

    public function lastInsertId(PDO $pdo, ?string $table): int
    {
        return (int) $pdo->lastInsertId();
    }

    public function columnsNeedingTable(): array
    {
        return [];
    }

    public function mapRow(stdClass $row, array $tablesByColumn): stdClass
    {
        return $row;
    }

    public function toStorage(string $table, array $values): array
    {
        return $values;
    }

    public function fromStorage(string $table, stdClass $row): stdClass
    {
        foreach (get_object_vars($row) as $name => $value) {
            $row->{$name} = $value === null ? null : (string) $value;
        }

        return $row;
    }

    public function mapColumns(array $columns): array
    {
        return $columns;
    }

    public function error(array $errorInfo, string $fallbackMessage): array
    {
        return [(int) ($errorInfo[1] ?? 0), (string) ($errorInfo[2] ?? $fallbackMessage)];
    }
}
