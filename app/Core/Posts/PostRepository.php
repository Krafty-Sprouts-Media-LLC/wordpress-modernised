<?php

declare(strict_types=1);

namespace App\Core\Posts;

use App\Core\Database\Dialect;
use App\Core\Database\Dialects;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The rows of WordPress's posts table, read and written through Laravel's
 * query builder, so the same code runs on MySQL, MariaDB and PostgreSQL.
 *
 * Rows come back as $wpdb gives them to WordPress (column names like `ID`,
 * every value as text, 0000-00-00 for empty dates on PostgreSQL), and
 * values go in with WordPress's names; the dialect does the translation.
 * WordPress's flow stays in wp-api/functions/post.php, which calls one
 * method per query WordPress runs, so query counts match.
 */
final class PostRepository
{
    /** One post row, or null. */
    public function find(int $id): ?stdClass
    {
        $row = $this->table()->where($this->column('ID'), $id)->limit(1)->first();

        return $row instanceof stdClass ? $this->dialect()->fromStorage($this->name(), $row) : null;
    }

    /**
     * The rows of the given posts, in the database's order.
     *
     * @param  list<int>  $ids
     * @return list<stdClass>
     */
    public function findMany(array $ids): array
    {
        return $this->rows($this->table()->whereIn($this->column('ID'), $ids));
    }

    /** Whether a post with this ID exists. */
    public function exists(int $id): bool
    {
        return $this->table()->where($this->column('ID'), $id)->exists();
    }

    /**
     * Inserts a post. Returns its new ID.
     *
     * @param  array<string, mixed>  $columns  WordPress's column names.
     */
    public function insert(array $columns): int
    {
        return (int) $this->table()->insertGetId($this->toStorage($columns), $this->column('ID'));
    }

    /**
     * Changes the posts matching $where. Returns the rows changed.
     *
     * @param  array<string, mixed>  $columns  WordPress's column names.
     * @param  array<string, mixed>  $where
     */
    public function update(array $columns, array $where): int
    {
        return $this->table()->where($this->toStorage($where))->update($this->toStorage($columns));
    }

    /** Deletes one post. Returns the rows deleted. */
    public function delete(int $id): int
    {
        return $this->table()->where($this->column('ID'), $id)->delete();
    }

    /**
     * Every post matching $where (WordPress's column names), in the database's order.
     *
     * @param  array<string, mixed>  $where
     * @return list<stdClass>
     */
    public function where(array $where): array
    {
        return $this->rows($this->table()->where($this->toStorage($where)));
    }

    /**
     * The IDs of the posts matching $where, as text, as $wpdb's get_col() gives them.
     *
     * @param  array<string, mixed>  $where
     * @return list<string>
     */
    public function ids(array $where): array
    {
        return array_map('strval', $this->table()->where($this->toStorage($where))->pluck($this->column('ID'))->all());
    }

    /**
     * Whether another post already uses the slug, as wp_unique_post_slug()
     * asks: optionally only among some post types, and with a parent.
     *
     * @param  list<string>|null  $postTypes
     */
    public function slugTaken(string $slug, int $exceptId, ?array $postTypes = null, ?int $parent = null): bool
    {
        $query = $this->table()->where('post_name', $slug)->where($this->column('ID'), '!=', $exceptId);

        if ($postTypes !== null) {
            $query->whereIn('post_type', $postTypes);
        }

        if ($parent !== null) {
            $query->where('post_parent', $parent);
        }

        return $query->limit(1)->value('post_name') !== null;
    }

    /**
     * Trashed posts using a slug, among the given post types, except one post.
     *
     * @param  list<string>  $postTypes
     * @return list<stdClass>
     */
    public function trashedWithSlug(string $slug, array $postTypes, int $exceptId): array
    {
        return $this->rows($this->table()
            ->where('post_name', $slug)
            ->where('post_status', 'trash')
            ->whereIn('post_type', $postTypes)
            ->where($this->column('ID'), '!=', $exceptId));
    }

    /**
     * The IDs of a post's comments, newest first, as text: wp_delete_post()
     * deletes them with the post. (The comments area itself comes later.)
     *
     * @return list<string>
     */
    public function commentIds(int $postId): array
    {
        $table = config('wordpress.table_prefix').'comments';
        $columns = $this->dialect()->toStorage($table, ['comment_ID' => null, 'comment_post_ID' => null]);
        [$id, $postColumn] = array_keys($columns);

        return array_map('strval', DB::table($table)->where($postColumn, $postId)->orderByDesc($id)->pluck($id)->all());
    }

    private function table(): Builder
    {
        return DB::table($this->name());
    }

    private function name(): string
    {
        return config('wordpress.table_prefix').'posts';
    }

    private function dialect(): Dialect
    {
        return Dialects::current();
    }

    private function column(string $name): string
    {
        return (string) array_key_first($this->toStorage([$name => null]));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function toStorage(array $values): array
    {
        return $this->dialect()->toStorage($this->name(), $values);
    }

    /** @return list<stdClass> */
    private function rows(Builder $query): array
    {
        $rows = [];

        foreach ($query->get() as $row) {
            $rows[] = $this->dialect()->fromStorage($this->name(), $row);
        }

        return $rows;
    }
}
