<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use stdClass;
use Throwable;

/**
 * PostgreSQL's equivalents of WordPress's MySQL behaviour, for $wpdb's own
 * methods. See docs/divergences.md, "$wpdb on PostgreSQL".
 */
final class PostgresDialect implements Dialect
{
    /** WordPress's date columns: NULL on PostgreSQL, 0000-00-00 00:00:00 to plugins. */
    private const DATE_COLUMNS = [
        'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt',
        'comment_date', 'comment_date_gmt', 'user_registered', 'link_updated',
    ];

    /** WordPress's mixed-case columns, stored lowercase on PostgreSQL. */
    private const CASED_COLUMNS = [
        'id' => 'ID', 'comment_id' => 'comment_ID', 'comment_post_id' => 'comment_post_ID',
        'comment_author_ip' => 'comment_author_IP',
    ];

    /** @var array<string, string|null> Identity sequence per table. */
    private array $sequences = [];

    public function __construct(private readonly string $tablePrefix) {}

    public function quoteIdentifier(string $name): string
    {
        // Lowercase: WordPress's SQL leaves names unquoted, and PostgreSQL folds
        // unquoted names to lowercase, so tables and columns are stored lowercase.
        return '"'.str_replace('"', '""', strtolower($name)).'"';
    }

    public function escape(PDO $pdo, string $text): string
    {
        // Standard strings: only the quote is doubled; backslash is an ordinary
        // character. PostgreSQL text cannot hold NUL bytes, so they are removed.
        return str_replace("'", "''", str_replace("\0", '', $text));
    }

    public function allowsEmulatedPrepares(): bool
    {
        return false;
    }

    public function stringifiesFetches(): bool
    {
        return false;
    }

    public function lastInsertId(PDO $pdo, ?string $table): int
    {
        if ($table === null) {
            return 0;
        }

        $table = strtolower($table);

        if (! array_key_exists($table, $this->sequences)) {
            $this->sequences[$table] = $this->identitySequence($pdo, $table);
        }

        if ($this->sequences[$table] === null) {
            return 0;
        }

        try {
            return (int) $pdo->query('SELECT currval('.$pdo->quote($this->sequences[$table]).')')->fetchColumn();
        } catch (Throwable) {
            return 0; // No insert into this table yet in this session.
        }
    }

    public function mapRow(stdClass $row, array $columns): stdClass
    {
        $mapped = new stdClass;

        foreach (get_object_vars($row) as $name => $value) {
            $table = $this->tableOf($columns, $name);
            $isWordPressTable = $table !== null && str_starts_with($table, strtolower($this->tablePrefix));

            if ($isWordPressTable && $value === null && in_array($name, self::DATE_COLUMNS, true)) {
                $value = '0000-00-00 00:00:00';
            }

            if ($isWordPressTable && isset(self::CASED_COLUMNS[$name])) {
                $name = self::CASED_COLUMNS[$name];
            }

            $mapped->$name = $value;
        }

        return $mapped;
    }

    public function mapColumns(array $columns): array
    {
        return array_map(function (stdClass $column): stdClass {
            $table = $column->table !== '' ? strtolower($column->table) : null;

            if ($table !== null && str_starts_with($table, strtolower($this->tablePrefix)) && isset(self::CASED_COLUMNS[$column->name])) {
                $column = clone $column;
                $column->name = $column->orgname = self::CASED_COLUMNS[$column->name];
            }

            return $column;
        }, $columns);
    }

    public function error(array $errorInfo, string $fallbackMessage): array
    {
        $state = (string) ($errorInfo[0] ?? '');
        $message = (string) ($errorInfo[2] ?? $fallbackMessage);

        // A lost connection: WordPress's "server has gone away" (2006) makes $wpdb
        // reconnect. PostgreSQL may report it as a general error (HY000), so its message counts too.
        $lost = str_starts_with($state, '08') || $state === '57P01'
            || str_contains($message, 'server closed the connection unexpectedly')
            || str_contains($message, 'terminating connection')
            || str_contains($message, 'no connection to the server');

        if ($lost) {
            return [2006, $message];
        }

        // Syntax errors and unknown functions: most likely a plugin's MySQL-only SQL.
        if ($state === '42601' || $state === '42883') {
            $message .= ' — This query uses MySQL-only syntax; plugins\' own SQL is not translated for PostgreSQL (AGENTS.md rule 3).';
        }

        return [1, $message];
    }

    /**
     * The table a result column came from (lowercase), if PostgreSQL says.
     *
     * @param  list<stdClass>  $columns
     */
    private function tableOf(array $columns, string $name): ?string
    {
        foreach ($columns as $column) {
            if ($column->name === $name) {
                return $column->table !== '' ? strtolower($column->table) : null;
            }
        }

        return null;
    }

    private function identitySequence(PDO $pdo, string $table): ?string
    {
        $statement = $pdo->prepare(
            "SELECT pg_get_serial_sequence(quote_ident(c.relname), a.attname)
             FROM pg_class c JOIN pg_attribute a ON a.attrelid = c.oid
             WHERE c.relname = ? AND c.relkind = 'r' AND a.attidentity <> '' LIMIT 1"
        );
        $statement->execute([$table]);
        $sequence = $statement->fetchColumn();

        return is_string($sequence) && $sequence !== '' ? $sequence : null;
    }
}
