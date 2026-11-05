<?php

declare(strict_types=1);

namespace App\Core\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use PDOStatement;
use stdClass;
use Throwable;

/**
 * The database connection behind WordPress's $wpdb door. For the site's own
 * database it is Laravel's default connection, so WordPress and Laravel share
 * one connection and one transaction. For any other database (a plugin
 * connecting elsewhere) it is a Laravel on-demand connection.
 */
final class WpdbConnection
{
    /** mysqli field type numbers, keyed by PDO's native_type names. */
    private const TYPES = [
        'DECIMAL' => 0, 'TINY' => 1, 'SHORT' => 2, 'LONG' => 3, 'FLOAT' => 4, 'DOUBLE' => 5,
        'NULL' => 6, 'TIMESTAMP' => 7, 'LONGLONG' => 8, 'INT24' => 9, 'DATE' => 10, 'TIME' => 11,
        'DATETIME' => 12, 'YEAR' => 13, 'NEWDATE' => 14, 'VARCHAR' => 15, 'BIT' => 16,
        'JSON' => 245, 'NEWDECIMAL' => 246, 'ENUM' => 247, 'SET' => 248, 'TINY_BLOB' => 249,
        'MEDIUM_BLOB' => 250, 'LONG_BLOB' => 251, 'BLOB' => 252, 'VAR_STRING' => 253, 'STRING' => 254,
        'GEOMETRY' => 255,
    ];

    /** mysqli field flag bits, keyed by PDO's flag names. */
    private const FLAGS = [
        'not_null' => 1, 'primary_key' => 2, 'unique_key' => 4, 'multiple_key' => 8,
        'blob' => 16, 'unsigned' => 32, 'zerofill' => 64, 'binary' => 128,
    ];

    private string $lastError = '';

    private int $lastErrorNumber = 0;

    /** @var array<string, Dialect> */
    private array $dialects = [];

    /**
     * @param  Connection|null  $own  A connection of its own; null means Laravel's default connection, looked up on every use.
     */
    private function __construct(private readonly ?Connection $own) {}

    /**
     * The connection for these credentials: Laravel's default connection when
     * they name the same server and database, otherwise a new one.
     */
    public static function for(string $host, string $user, string $password, string $name): self
    {
        $default = DB::connection();
        [$hostname, $port] = self::splitHost($host);

        if (self::sameDatabase($default->getConfig(), $hostname, $port, $user, $name)) {
            return new self(null);
        }

        $config = array_merge($default->getConfig(), [
            'host' => $hostname,
            'port' => $port ?? $default->getConfig('port'),
            'username' => $user,
            'password' => $password,
            'database' => $name,
            // A name of its own: DB::build() replaces (and disconnects) any
            // earlier connection of the same name, which would break a
            // plugin's first wpdb when it makes a second one.
            'name' => 'wpdb-'.bin2hex(random_bytes(8)),
        ]);

        return new self(DB::build($config));
    }

    /**
     * Whether this is Laravel's own connection to the site's database.
     */
    public function isShared(): bool
    {
        return $this->own === null;
    }

    /**
     * The connection's character set and collation, from Laravel's settings.
     *
     * @return array{0: string, 1: string}
     */
    public function charsetAndCollation(): array
    {
        // PostgreSQL's UTF8 is full Unicode: WordPress's name for that is utf8mb4.
        if ($this->isPostgres()) {
            return ['utf8mb4', 'utf8mb4_unicode_520_ci'];
        }

        $connection = $this->connection();

        return [(string) $connection->getConfig('charset'), (string) $connection->getConfig('collation')];
    }

    /**
     * Opens the connection again after it was lost. Laravel's own connection is
     * only reopened outside a Laravel transaction, as Laravel itself does: a lost
     * transaction cannot be continued.
     */
    public function reconnect(): bool
    {
        try {
            if ($this->own !== null) {
                $this->own->reconnect();
            } elseif (DB::connection()->transactionLevel() === 0) {
                DB::reconnect();
            }
        } catch (Throwable) {
            return false;
        }

        return $this->ping();
    }

    /**
     * The connection for an existing PDO handle: Laravel's default when it is
     * Laravel's own PDO, otherwise a connection wrapping that handle.
     */
    public static function forPdo(PDO $pdo): self
    {
        $default = DB::connection();

        if ($pdo === $default->getPdo()) {
            return new self(null);
        }

        $connection = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
            ? new PostgresConnection($pdo, '', '', $default->getConfig())
            : new MySqlConnection($pdo, '', '', $default->getConfig());

        return new self($connection);
    }

    /**
     * The live PDO. For the site's database this is always Laravel's current
     * one, even after Laravel reconnects.
     */
    public function pdo(): PDO
    {
        $connection = $this->connection();

        // After Laravel's disconnect() the PDO is gone; open it again, as Laravel does before a query.
        if ($connection->getRawPdo() === null) {
            $connection->reconnect();
        }

        return $connection->getPdo();
    }

    /**
     * Laravel's default connection as it is now (never a stored copy, which
     * would go stale when Laravel reconnects), or this wpdb's own connection.
     */
    private function connection(): Connection
    {
        return $this->own ?? DB::connection();
    }

    /**
     * Runs one statement. Returns false on failure; the error is then in lastError().
     */
    public function query(string $sql): WpdbResult|false
    {
        $this->lastError = '';
        $this->lastErrorNumber = 0;
        $started = microtime(true);

        try {
            $pdo = $this->pdo();
        } catch (Throwable $e) {
            // No connection at all: report it as MySQL's "can't connect" (2002).
            $this->lastErrorNumber = 2002;
            $this->lastError = $e->getMessage();

            return false;
        }

        // mysqli sends plain text queries and gets the server's own text back.
        // PDO's default (native prepares) uses the binary protocol: an extra
        // round trip per query, and DOUBLE values rounded by PHP. So on MySQL
        // wpdb's queries use emulated prepares (stacked statements stay refused
        // by the connection's MULTI_STATEMENTS option). PostgreSQL keeps native
        // prepares, which refuse stacked statements themselves. Values come back
        // as text either way; Laravel's settings are restored afterwards.
        $dialect = $this->dialect();
        $emulate = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $stringify = $pdo->getAttribute(PDO::ATTR_STRINGIFY_FETCHES);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $dialect->allowsEmulatedPrepares());
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, $dialect->stringifiesFetches());

        // PostgreSQL aborts the whole transaction when one statement fails; MySQL
        // only fails that statement. Inside a transaction, a savepoint per query
        // gives PostgreSQL MySQL's behaviour, so tolerated failures (common in
        // WordPress) don't break everything after them.
        // A plugin's own transaction control (START TRANSACTION, COMMIT,
        // SAVEPOINT …) runs as written: wrapping it would end or swallow it.
        $savepoint = $this->isPostgres() && $pdo->inTransaction() && ! self::isTransactionControl($sql);

        try {
            if ($savepoint) {
                $pdo->exec('SAVEPOINT wm_wpdb');
            }

            $statement = $pdo->query($sql);

            if ($savepoint && $pdo->inTransaction()) {
                $pdo->exec('RELEASE SAVEPOINT wm_wpdb');
            }

            if ($statement->columnCount() === 0) {
                $result = new WpdbResult(isResultSet: false, affectedRows: $statement->rowCount());
            } else {
                $rows = $this->rows($statement);
                $tables = $rows === [] ? [] : $this->sourceTables($sql, $statement, array_keys(get_object_vars($rows[0])), $dialect->columnsNeedingTable());
                $rows = array_map(fn (stdClass $row) => $dialect->mapRow($row, $tables), $rows);
                $result = new WpdbResult(
                    isResultSet: true,
                    rows: $rows,
                    columnsLoader: fn () => $dialect->mapColumns($this->columns($statement)),
                    affectedRows: $statement->rowCount(),
                );
            }
        } catch (PDOException $e) {
            if ($savepoint) {
                try {
                    $pdo->exec('ROLLBACK TO SAVEPOINT wm_wpdb');
                } catch (Throwable) {
                    // The connection itself is gone; the error below says so.
                }
            }

            $this->recordError($e);

            return false;
        } finally {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
            $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, $stringify);
        }

        // Visible to DB::listen(), the query log and debugging tools, like Laravel's own queries.
        $this->connection()->logQuery($sql, [], round((microtime(true) - $started) * 1000, 2));

        return $result;
    }

    /**
     * Whether $sql is transaction control (BEGIN, START TRANSACTION, COMMIT,
     * ROLLBACK, SAVEPOINT, RELEASE, …), which must not run inside a savepoint.
     */
    private static function isTransactionControl(string $sql): bool
    {
        return preg_match('/^\s*(BEGIN|START\s+TRANSACTION|COMMIT|END|ROLLBACK|ABORT|SAVEPOINT|RELEASE|PREPARE\s+TRANSACTION)\b/i', $sql) === 1;
    }

    /**
     * The table of a query that reads from exactly one table (one FROM, no
     * JOIN, UNION or sub-select), or null.
     */
    private static function singleTable(string $sql): ?string
    {
        if (preg_match_all('/FROM/i', $sql) !== 1 || preg_match('/JOIN|UNION|\(\s*SELECT/i', $sql) === 1) {
            return null;
        }

        $pattern = '/FROM\s+"?([A-Za-z0-9_]+)"?(?:\s+(?:AS\s+)?(?!WHERE|ORDER|GROUP|LIMIT|HAVING)[A-Za-z_]\w*)?\s*(?:WHERE|ORDER|GROUP|LIMIT|HAVING|;|$)/i';

        return preg_match($pattern, $sql, $m) === 1 ? $m[1] : null;
    }

    /**
     * The source table of each result column whose name is in $names, looked
     * up only for those columns (column details cost a catalog lookup each on
     * PostgreSQL). $columnNames are the result's names, in order.
     *
     * @param  list<string>  $columnNames
     * @param  list<string>  $names
     * @return array<string, string>
     */
    private function sourceTables(string $sql, PDOStatement $statement, array $columnNames, array $names): array
    {
        $tables = [];
        $single = self::singleTable($sql);

        foreach ($columnNames as $i => $name) {
            if (in_array($name, $names, true)) {
                // A one-table query names its table; otherwise ask PostgreSQL (costly).
                $table = $single ?? (string) (($statement->getColumnMeta($i) ?: [])['table'] ?? '');

                if ($table !== '') {
                    $tables[$name] = $table;
                }
            }
        }

        return $tables;
    }

    /**
     * Escapes text for use inside quotes in SQL, as mysqli_real_escape_string() does.
     */
    public function escape(string $text): string
    {
        return $this->dialect()->escape($this->pdo(), $text);
    }

    /**
     * The SQL dialect of this connection's database.
     */
    public function dialect(): Dialect
    {
        $driver = $this->connection()->getDriverName();

        return $this->dialects[$driver] ??= $driver === 'pgsql'
            ? new PostgresDialect((string) config('wordpress.table_prefix'))
            : new MySqlDialect;
    }

    /**
     * Whether this connection's database is PostgreSQL.
     */
    public function isPostgres(): bool
    {
        return $this->connection()->getDriverName() === 'pgsql';
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function lastErrorNumber(): int
    {
        return $this->lastErrorNumber;
    }

    /**
     * The ID generated by the last insert (on PostgreSQL, into $table).
     */
    public function lastInsertId(?string $table = null): int
    {
        return $this->dialect()->lastInsertId($this->pdo(), $table);
    }

    /**
     * The server's version string, as mysqli_get_server_info() returns it (for example "11.8.9-MariaDB").
     */
    public function serverInfo(): string
    {
        return (string) $this->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    public function characterSetName(): string
    {
        if ($this->isPostgres()) {
            return 'utf8mb4'; // PostgreSQL connections are always UTF-8.
        }

        return (string) $this->scalar('SELECT @@character_set_client');
    }

    /**
     * Sets the connection's character set and collation, as WordPress does on
     * connecting. On PostgreSQL everything is UTF-8 already, so there is nothing to set.
     */
    public function setCharset(string $charset, string $collate = ''): bool
    {
        if ($this->isPostgres()) {
            return true;
        }

        $sql = 'SET NAMES '.$this->pdo()->quote($charset);

        if ($collate !== '') {
            $sql .= ' COLLATE '.$this->pdo()->quote($collate);
        }

        return $this->query($sql) !== false;
    }

    /**
     * Switches the connection's current database. On the shared connection
     * this switches Laravel's too (recorded in docs/divergences.md).
     */
    public function selectDatabase(string $name): bool
    {
        // PostgreSQL can't switch databases on an open connection: only the
        // current one can be "selected" (recorded in docs/divergences.md).
        if ($this->isPostgres()) {
            return $name === $this->connection()->getDatabaseName();
        }

        // Always switch: comparing with Laravel's configured name would skip
        // switching back after an earlier switch away.
        return $this->query('USE `'.str_replace('`', '``', $name).'`') !== false;
    }

    /**
     * Runs several statements all-or-nothing: inside a savepoint when a
     * transaction is open, otherwise in a transaction of its own. If $work
     * returns false or throws, everything it did is rolled back.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T|false
     */
    public function atomically(callable $work): mixed
    {
        $pdo = $this->pdo();
        $nested = $pdo->inTransaction();

        $nested ? $pdo->exec('SAVEPOINT wm_atomic') : $pdo->beginTransaction();

        try {
            $result = $work();
        } catch (Throwable $e) {
            $nested ? $pdo->exec('ROLLBACK TO SAVEPOINT wm_atomic') : $pdo->rollBack();

            throw $e;
        }

        if ($result === false) {
            $nested ? $pdo->exec('ROLLBACK TO SAVEPOINT wm_atomic') : $pdo->rollBack();

            return false;
        }

        $nested ? $pdo->exec('RELEASE SAVEPOINT wm_atomic') : $pdo->commit();

        return $result;
    }

    /**
     * After an insert that gave its own value for a PostgreSQL table's
     * auto-numbered column: moves the counter past that value (as MySQL's
     * AUTO_INCREMENT does) and returns it, for $wpdb->insert_id. Null if the
     * insert didn't set that column.
     *
     * @param  array<string, mixed>  $values  Inserted values, keyed by column name.
     */
    public function adoptExplicitId(string $table, array $values): ?int
    {
        if (! $this->isPostgres()) {
            return null;
        }

        $dialect = $this->dialect();
        $pdo = $this->pdo();
        $autoNumbered = $dialect instanceof PostgresDialect ? $dialect->autoNumbered($pdo, $table) : null;

        if ($autoNumbered === null) {
            return null;
        }

        [$column, $sequence] = $autoNumbered;

        foreach ($values as $name => $value) {
            if (strtolower((string) $name) === $column && $value !== null && $value !== '') {
                $id = (int) $value;
                $quoted = $pdo->quote($sequence);
                $pdo->query("SELECT setval({$quoted}, GREATEST({$id}, (SELECT last_value FROM {$sequence})))");

                return $id;
            }
        }

        return null;
    }

    /**
     * A PostgreSQL table's columns in the shape of MySQL's SHOW FULL COLUMNS,
     * for WordPress's charset and length checks. Text is UTF-8 (utf8mb4);
     * PostgreSQL's single text type is reported as MySQL's largest, longtext.
     *
     * @return list<stdClass>
     */
    public function showFullColumns(string $table): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT column_name, data_type, character_maximum_length, numeric_precision, numeric_scale, is_nullable, column_default
             FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = ?
             ORDER BY ordinal_position'
        );
        $statement->execute([strtolower($table)]);

        $columns = [];

        foreach ($statement->fetchAll(PDO::FETCH_OBJ) as $column) {
            $length = $column->character_maximum_length;
            $type = match ($column->data_type) {
                'character varying' => "varchar({$length})",
                'character' => "char({$length})",
                'text', 'json', 'jsonb' => 'longtext',
                'bigint' => 'bigint(20)',
                'integer' => 'int(11)',
                'smallint' => 'smallint(6)',
                'boolean' => 'tinyint(1)',
                'numeric' => "decimal({$column->numeric_precision},{$column->numeric_scale})",
                'double precision' => 'double',
                'real' => 'float',
                'timestamp without time zone', 'timestamp with time zone' => 'datetime',
                'bytea' => 'longblob',
                default => $column->data_type,
            };
            $isText = in_array($column->data_type, ['character varying', 'character', 'text', 'json', 'jsonb'], true);

            $columns[] = (object) [
                'Field' => $column->column_name,
                'Type' => $type,
                'Collation' => $isText ? 'utf8mb4_unicode_520_ci' : null,
                'Null' => $column->is_nullable === 'YES' ? 'YES' : 'NO',
                'Key' => '',
                'Default' => $column->column_default,
                'Extra' => '',
                'Privileges' => 'select,insert,update,references',
                'Comment' => '',
            ];
        }

        return $columns;
    }

    /**
     * The columns of each primary or unique key of a PostgreSQL table, for REPLACE.
     *
     * @return list<list<string>>
     */
    public function uniqueKeys(string $table): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT array_to_string(array_agg(a.attname ORDER BY k.ord), \',\')
             FROM pg_index i
             JOIN pg_class c ON c.oid = i.indrelid
             CROSS JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS k(attnum, ord)
             JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = k.attnum
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE c.relname = ? AND n.nspname = current_schema()
               AND (i.indisunique OR i.indisprimary)
               AND i.indpred IS NULL AND NOT (0 = ANY (i.indkey))
             GROUP BY i.indexrelid'
        );
        $statement->execute([strtolower($table)]);

        return array_map(fn ($columns) => explode(',', $columns), $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function ping(): bool
    {
        return $this->query($this->isPostgres() ? 'SELECT 1' : 'DO 1') !== false;
    }

    /**
     * Forgets this connection. The shared Laravel connection stays open for Laravel.
     */
    public function close(): bool
    {
        if ($this->own !== null) {
            $this->own->disconnect();
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function sameDatabase(array $config, string $host, ?int $port, string $user, string $name): bool
    {
        return in_array($config['driver'] ?? null, ['mysql', 'mariadb', 'pgsql'], true)
            && ($config['host'] ?? null) === $host
            && ($port === null || (int) ($config['port'] ?? 3306) === $port)
            && ($config['username'] ?? null) === $user
            && ($config['database'] ?? null) === $name;
    }

    /**
     * Splits WordPress's "host:port" form.
     *
     * @return array{0: string, 1: int|null}
     */
    private static function splitHost(string $host): array
    {
        if (preg_match('/^(.+):(\d+)$/', $host, $m) === 1) {
            return [$m[1], (int) $m[2]];
        }

        return [$host, null];
    }

    private function scalar(string $sql): mixed
    {
        $result = $this->query($sql);

        return $result && $result->rows ? array_values((array) $result->rows[0])[0] : null;
    }

    private function recordError(Throwable $e): void
    {
        $info = $e instanceof PDOException ? ($e->errorInfo ?? []) : [];

        [$this->lastErrorNumber, $this->lastError] = $this->dialect()->error($info, $e->getMessage());
    }

    /**
     * Every value as a string (or null), as mysqli returns them.
     *
     * @return list<stdClass>
     */
    private function rows(PDOStatement $statement): array
    {
        $rows = [];

        while (($row = $statement->fetch(PDO::FETCH_OBJ)) !== false) {
            foreach (get_object_vars($row) as $key => $value) {
                if (is_bool($value)) {
                    // MySQL answers true/false (for example x LIKE y) with 1 and 0.
                    $row->$key = $value ? '1' : '0';
                } elseif (is_float($value)) {
                    // Shortest text that reads back as the same number (all digits, unlike (string)).
                    $row->$key = var_export($value, true);
                } elseif ($value !== null && ! is_string($value)) {
                    $row->$key = (string) $value;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Column details in mysqli_fetch_field()'s shape.
     *
     * @return list<stdClass>
     */
    private function columns(PDOStatement $statement): array
    {
        $columns = [];

        for ($i = 0, $count = $statement->columnCount(); $i < $count; $i++) {
            $meta = $statement->getColumnMeta($i) ?: [];
            $flags = 0;

            foreach ($meta['flags'] ?? [] as $flag) {
                $flags |= self::FLAGS[$flag] ?? 0;
            }

            $columns[] = (object) [
                'name' => $meta['name'] ?? '',
                'orgname' => $meta['name'] ?? '',
                'table' => $meta['table'] ?? '',
                'orgtable' => $meta['table'] ?? '',
                'def' => '',
                'db' => $this->connection()->getDatabaseName(),
                'catalog' => 'def',
                'max_length' => 0,
                'length' => (int) ($meta['len'] ?? 0),
                'charsetnr' => 0,
                'flags' => $flags,
                'type' => self::TYPES[$meta['native_type'] ?? ''] ?? 253,
                'decimals' => (int) ($meta['precision'] ?? 0),
            ];
        }

        return $columns;
    }
}
