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
    /**
     * Per WordPress table (unprefixed): its mixed-case columns, stored lowercase
     * on PostgreSQL, and its date columns, NULL on PostgreSQL but 0000-00-00 00:00:00 to plugins.
     */
    private const TABLE_RULES = [
        'posts' => ['cased' => ['id' => 'ID'], 'dates' => ['post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt']],
        'users' => ['cased' => ['id' => 'ID'], 'dates' => ['user_registered']],
        'comments' => [
            'cased' => ['comment_id' => 'comment_ID', 'comment_post_id' => 'comment_post_ID', 'comment_author_ip' => 'comment_author_IP'],
            'dates' => ['comment_date', 'comment_date_gmt'],
        ],
        'links' => ['cased' => [], 'dates' => ['link_updated']],
    ];

    /** @var array<string, array{0: string, 1: string}|null> Auto-numbered column and its sequence, per table. */
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
        // libpq's own escaping (PQescapeStringConn) follows the connection's
        // standard_conforming_strings, whichever way it is set. PostgreSQL text
        // cannot hold NUL bytes, so they are removed first.
        return substr($pdo->quote(str_replace("\0", '', $text)), 1, -1);
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
        $sequence = $table === null ? null : ($this->autoNumbered($pdo, $table)[1] ?? null);

        if ($sequence === null) {
            return 0;
        }

        // currval() fails when this session hasn't used the sequence yet, and a
        // failure would abort a surrounding transaction: ask inside a savepoint.
        $nested = $pdo->inTransaction();

        try {
            if ($nested) {
                $pdo->exec('SAVEPOINT wm_currval');
            }

            $id = (int) $pdo->query('SELECT currval('.$pdo->quote($sequence).')')->fetchColumn();

            if ($nested) {
                $pdo->exec('RELEASE SAVEPOINT wm_currval');
            }

            return $id;
        } catch (Throwable) {
            if ($nested) {
                $pdo->exec('ROLLBACK TO SAVEPOINT wm_currval');
            }

            return 0;
        }
    }

    /**
     * The auto-numbered (identity or serial) column of $table and its sequence, if any.
     *
     * @return array{0: string, 1: string}|null
     */
    public function autoNumbered(PDO $pdo, string $table): ?array
    {
        $table = strtolower($table);

        if (! array_key_exists($table, $this->sequences)) {
            $statement = $pdo->prepare(
                "SELECT a.attname, pg_get_serial_sequence(quote_ident(n.nspname) || '.' || quote_ident(c.relname), a.attname)
                 FROM pg_class c
                 JOIN pg_namespace n ON n.oid = c.relnamespace
                 JOIN pg_attribute a ON a.attrelid = c.oid
                 WHERE c.relname = ? AND n.nspname = current_schema() AND c.relkind = 'r'
                   AND a.attnum > 0 AND NOT a.attisdropped
                   AND pg_get_serial_sequence(quote_ident(n.nspname) || '.' || quote_ident(c.relname), a.attname) IS NOT NULL
                 LIMIT 1"
            );
            $statement->execute([$table]);
            $row = $statement->fetch(PDO::FETCH_NUM);

            $this->sequences[$table] = $row ? [(string) $row[0], (string) $row[1]] : null;
        }

        return $this->sequences[$table];
    }

    public function columnsNeedingTable(): array
    {
        $names = [];

        foreach (self::TABLE_RULES as $rules) {
            $names = [...$names, ...array_keys($rules['cased']), ...$rules['dates']];
        }

        return array_values(array_unique($names));
    }

    public function mapRow(stdClass $row, array $tablesByColumn): stdClass
    {
        $mapped = new stdClass;

        foreach (get_object_vars($row) as $name => $value) {
            $rules = $this->rulesFor($tablesByColumn[$name] ?? null);

            if ($rules !== null && $value === null && in_array($name, $rules['dates'], true)) {
                $value = '0000-00-00 00:00:00';
            }

            $mapped->{$rules['cased'][$name] ?? $name} = $value;
        }

        return $mapped;
    }

    public function mapColumns(array $columns): array
    {
        return array_map(function (stdClass $column): stdClass {
            $rules = $this->rulesFor($column->table !== '' ? $column->table : null);

            if ($rules !== null && isset($rules['cased'][$column->name])) {
                $column = clone $column;
                $column->name = $column->orgname = $rules['cased'][$column->name];
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
     * The shaping rules for a result column's table: only WordPress's own tables, matched exactly.
     *
     * @return array{cased: array<string, string>, dates: list<string>}|null
     */
    private function rulesFor(?string $table): ?array
    {
        if ($table === null) {
            return null;
        }

        $prefix = strtolower($this->tablePrefix);
        $table = strtolower($table);

        if (! str_starts_with($table, $prefix)) {
            return null;
        }

        return self::TABLE_RULES[substr($table, strlen($prefix))] ?? null;
    }
}
