<?php

declare(strict_types=1);

namespace App\Core\Meta;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The rows of WordPress's meta tables (postmeta now; usermeta, commentmeta,
 * termmeta, and any a plugin registers, later), read and written through
 * Laravel's query builder, so the same code runs on every database.
 *
 * WordPress picks a meta table by name (_get_meta_table()), so methods take the
 * table and its columns: the object column ('post_id', 'user_id', ...) and the
 * meta ID column ('meta_id', or 'umeta_id' for users). WordPress's flow stays
 * in wp-api/functions/meta.php, which calls one method per query WordPress
 * runs, so query counts match.
 */
final class MetaRepository
{
    /** Whether the object already has meta under $key. */
    public function hasKey(string $table, string $objectColumn, int $objectId, string $key): bool
    {
        return $this->table($table)->where('meta_key', $key)->where($objectColumn, $objectId)->exists();
    }

    /**
     * Inserts one meta row. Returns its ID.
     *
     * @param  array<string, int|string|null>  $row
     */
    public function insert(string $table, string $idColumn, array $row): int
    {
        return (int) $this->table($table)->insertGetId($row, $idColumn);
    }

    /**
     * The meta IDs of the rows matching $where (column => value), in the
     * database's order, as text, as WordPress's get_col() gives them.
     *
     * @param  array<string, int|string|null>  $where
     * @return list<string>
     */
    public function ids(string $table, string $idColumn, array $where): array
    {
        return array_map('strval', $this->table($table)->where($where)->pluck($idColumn)->all());
    }

    /**
     * The object IDs that have meta under $key (and, when given, $value), as text.
     *
     * @return list<string>
     */
    public function objectIds(string $table, string $objectColumn, string $key, ?string $value): array
    {
        $query = $this->table($table)->where('meta_key', $key);

        if ($value !== null) {
            $query->where('meta_value', $value);
        }

        return array_map('strval', $query->pluck($objectColumn)->all());
    }

    /**
     * Changes the rows matching $where. Returns the rows changed.
     *
     * @param  array<string, int|string|null>  $values
     * @param  array<string, int|string|null>  $where
     */
    public function update(string $table, array $values, array $where): int
    {
        return $this->table($table)->where($where)->update($values);
    }

    /**
     * Deletes meta rows by ID. Returns the rows deleted.
     *
     * @param  list<int>  $ids
     */
    public function deleteIds(string $table, string $idColumn, array $ids): int
    {
        return $this->table($table)->whereIn($idColumn, $ids)->delete();
    }

    /** One meta row, every column as text (as $wpdb gives rows), or null. */
    public function row(string $table, string $idColumn, int $metaId): ?stdClass
    {
        $row = $this->table($table)->where($idColumn, $metaId)->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        foreach (get_object_vars($row) as $column => $value) {
            $row->{$column} = $value === null ? null : (string) $value;
        }

        return $row;
    }

    /**
     * Every meta row of the given objects, ordered by meta ID, as WordPress's
     * update_meta_cache() reads them: object ID, key and value.
     *
     * @param  list<int>  $objectIds
     * @return list<array{object_id: int, meta_key: string, meta_value: string|null}>
     */
    public function forObjects(string $table, string $objectColumn, string $idColumn, array $objectIds): array
    {
        $rows = [];

        foreach ($this->table($table)->whereIn($objectColumn, $objectIds)->orderBy($idColumn)->get([$objectColumn, 'meta_key', 'meta_value']) as $row) {
            $rows[] = [
                'object_id' => (int) $row->{$objectColumn},
                'meta_key' => (string) $row->meta_key,
                'meta_value' => $row->meta_value === null ? null : (string) $row->meta_value,
            ];
        }

        return $rows;
    }

    private function table(string $table): Builder
    {
        return DB::table($table);
    }
}
