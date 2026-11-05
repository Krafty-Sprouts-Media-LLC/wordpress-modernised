<?php

declare(strict_types=1);

namespace App\Core\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;
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
        ]);
        unset($config['name']);

        return new self(DB::build($config));
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

        return new self(new MySqlConnection($pdo, '', '', $default->getConfig()));
    }

    /**
     * The live PDO. For the site's database this is always Laravel's current
     * one, even after Laravel reconnects.
     */
    public function pdo(): PDO
    {
        return $this->connection()->getPdo();
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

        try {
            $statement = $this->pdo()->query($sql);
        } catch (PDOException $e) {
            $this->recordError($e);

            return false;
        }

        if ($statement->columnCount() === 0) {
            return new WpdbResult(isResultSet: false, affectedRows: $statement->rowCount());
        }

        return new WpdbResult(
            isResultSet: true,
            rows: $this->rows($statement),
            columns: $this->columns($statement),
            affectedRows: $statement->rowCount(),
        );
    }

    /**
     * Escapes text for use inside quotes in SQL, as mysqli_real_escape_string() does.
     */
    public function escape(string $text): string
    {
        return substr($this->pdo()->quote($text), 1, -1);
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function lastErrorNumber(): int
    {
        return $this->lastErrorNumber;
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo()->lastInsertId();
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
        return (string) $this->scalar('SELECT @@character_set_client');
    }

    /**
     * Sets the connection's character set and collation, as WordPress does on connecting.
     */
    public function setCharset(string $charset, string $collate = ''): bool
    {
        $sql = 'SET NAMES '.$this->pdo()->quote($charset);

        if ($collate !== '') {
            $sql .= ' COLLATE '.$this->pdo()->quote($collate);
        }

        return $this->query($sql) !== false;
    }

    public function selectDatabase(string $name): bool
    {
        if ($name === $this->connection()->getDatabaseName()) {
            return true;
        }

        return $this->query('USE `'.str_replace('`', '``', $name).'`') !== false;
    }

    public function ping(): bool
    {
        return $this->query('DO 1') !== false;
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
        return in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)
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

        $this->lastErrorNumber = (int) ($info[1] ?? 0);
        $this->lastError = (string) ($info[2] ?? $e->getMessage());
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
                if ($value !== null && ! is_string($value)) {
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
