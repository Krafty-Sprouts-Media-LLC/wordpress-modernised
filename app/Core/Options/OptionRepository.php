<?php

declare(strict_types=1);

namespace App\Core\Options;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The rows of WordPress's options table, read and written through Laravel's
 * query builder, so the same code runs on MySQL, MariaDB and PostgreSQL.
 *
 * Only storage lives here. WordPress's flow (filters, actions, the alloptions
 * and notoptions cache entries) stays in the doors, wp-api/functions/option.php,
 * which call one method per query WordPress runs, so query counts match.
 * Values are the stored (serialized) text.
 */
final class OptionRepository
{
    /**
     * The autoloaded options: name => stored value.
     *
     * @param  list<string>  $autoloadValues  The autoload values that load (wp_autoload_values_to_autoload()).
     * @return array<string, string>
     */
    public function autoloaded(array $autoloadValues): array
    {
        return $this->pairs($this->table()->whereIn('autoload', $autoloadValues));
    }

    /**
     * Every option: name => stored value. WordPress's fallback when nothing is
     * marked for autoloading.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->pairs($this->table());
    }

    /** The stored value of one option, or null when there is no such option. */
    public function find(string $name): ?string
    {
        $value = $this->table()->where('option_name', $name)->limit(1)->value('option_value');

        return $value === null ? null : (string) $value;
    }

    /**
     * The stored values of the options that exist among $names.
     *
     * @param  list<string>  $names
     * @return array<string, string>
     */
    public function findMany(array $names): array
    {
        return $this->pairs($this->table()->whereIn('option_name', $names));
    }

    /** The autoload value of one option, or null when there is no such option. */
    public function autoloadOf(string $name): ?string
    {
        $autoload = $this->table()->where('option_name', $name)->limit(1)->value('autoload');

        return $autoload === null ? null : (string) $autoload;
    }

    /**
     * Inserts an option, or overwrites it if the name is taken (WordPress's
     * INSERT … ON DUPLICATE KEY UPDATE, written portably). Returns the rows
     * affected, as the database reports them.
     */
    public function upsert(string $name, string $value, string $autoload): int
    {
        return $this->table()->upsert(
            [['option_name' => $name, 'option_value' => $value, 'autoload' => $autoload]],
            ['option_name'],
            ['option_value', 'autoload'],
        );
    }

    /**
     * Changes one option's columns. Returns the rows changed.
     *
     * @param  array{option_value?: string, autoload?: string}  $values
     */
    public function update(string $name, array $values): int
    {
        return $this->table()->where('option_name', $name)->update($values);
    }

    /** Deletes one option. Returns the rows deleted. */
    public function delete(string $name): int
    {
        return $this->table()->where('option_name', $name)->delete();
    }

    /**
     * Which of the named options don't have the wanted autoload value yet.
     *
     * @param  array<string, list<string>>  $namesByAutoload  Wanted autoload value => option names.
     * @return list<string>
     */
    public function needingAutoload(array $namesByAutoload): array
    {
        $query = $this->table()->select('option_name');

        foreach ($namesByAutoload as $autoload => $names) {
            if ($names !== []) {
                $query->orWhere(fn (Builder $where) => $where
                    ->where('autoload', '!=', $autoload)
                    ->whereIn('option_name', $names));
            }
        }

        return array_map('strval', $query->pluck('option_name')->all());
    }

    /**
     * Sets the autoload value of the named options. Returns the rows changed.
     *
     * @param  list<string>  $names
     */
    public function setAutoload(string $autoload, array $names): int
    {
        return $this->table()->whereIn('option_name', $names)->update(['autoload' => $autoload]);
    }

    /**
     * Deletes every expired transient: its value row and its timeout row,
     * for transients ('_transient_') and site transients ('_site_transient_').
     * As in WordPress, a pair is deleted only when both rows exist and the
     * timeout, read as a number, is before $now. Returns the rows deleted.
     *
     * WordPress does this with a MySQL-only multi-table DELETE; here the
     * timeouts are compared in PHP, which reads a number the way MySQL does
     * ('123abc' is 123, text is 0), so it works on every database.
     */
    public function deleteExpiredTransients(int $now): int
    {
        $deleted = 0;

        foreach (['_transient_', '_site_transient_'] as $prefix) {
            $timeoutPrefix = $prefix.'timeout_';
            $expired = [];

            foreach ($this->table()->where('option_name', 'like', $this->startsWith($timeoutPrefix))->get(['option_name', 'option_value']) as $row) {
                if ((int) $row->option_value < $now) {
                    $expired[] = substr((string) $row->option_name, strlen($timeoutPrefix));
                }
            }

            foreach (array_chunk($expired, 500) as $names) {
                $withValues = $this->table()
                    ->whereIn('option_name', array_map(fn (string $name) => $prefix.$name, $names))
                    ->pluck('option_name')
                    ->map(fn ($name) => substr((string) $name, strlen($prefix)))
                    ->all();

                if ($withValues === []) {
                    continue;
                }

                $deleted += $this->table()->whereIn('option_name', [
                    ...array_map(fn (string $name) => $prefix.$name, $withValues),
                    ...array_map(fn (string $name) => $timeoutPrefix.$name, $withValues),
                ])->delete();
            }
        }

        return $deleted;
    }

    /** A LIKE pattern for names starting with $prefix, with LIKE's wildcards escaped. */
    private function startsWith(string $prefix): string
    {
        return addcslashes($prefix, '\\%_').'%';
    }

    private function table(): Builder
    {
        return DB::table(config('wordpress.table_prefix').'options');
    }

    /** @return array<string, string> */
    private function pairs(Builder $query): array
    {
        $pairs = [];

        foreach ($query->get(['option_name', 'option_value']) as $row) {
            $pairs[(string) $row->option_name] = (string) $row->option_value;
        }

        return $pairs;
    }
}
