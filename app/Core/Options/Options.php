<?php

declare(strict_types=1);

namespace App\Core\Options;

/**
 * Options for the app's own Laravel code, with types.
 *
 * Each method goes through WordPress's doors (get_option(), update_option(),
 * delete_option()), so Laravel code and plugins share one cache, one set of
 * filters and one set of announcements (AGENTS.md rule 2).
 */
final class Options
{
    /**
     * An option's value, or $default when it doesn't exist.
     */
    public function get(string $name, mixed $default = false): mixed
    {
        // WordPress tells "no default given" apart from a default of false.
        return func_num_args() > 1 ? get_option($name, $default) : get_option($name);
    }

    /**
     * Adds or changes an option. Returns false when nothing changed, as
     * update_option() does.
     *
     * @param  bool|null  $autoload  Whether to load it on every request; null lets WordPress decide.
     */
    public function set(string $name, mixed $value, ?bool $autoload = null): bool
    {
        return update_option($name, $value, $autoload);
    }

    /**
     * Deletes an option. Returns false when there was no such option.
     */
    public function delete(string $name): bool
    {
        return delete_option($name);
    }
}
