<?php

declare(strict_types=1);

namespace App\Core\Cache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The engine behind WordPress's object cache (WP_Object_Cache).
 *
 * Every value lives in memory for the request, as in WordPress. When a
 * Laravel cache store is configured (config wordpress.object_cache), values
 * are also written to it, so the next request finds them and Laravel's own
 * code shares the same store. Groups marked non-persistent stay in memory.
 *
 * Values are serialized into the store by this class, so every store keeps a
 * copy rather than a shared object, and a value PHP can't serialize (such as
 * a closure) simply stays in memory. Flushing moves a version number instead
 * of emptying the store, so Laravel's own entries survive.
 */
final class ObjectCache
{
    private const VERSION_KEY = 'wm:object-cache:version';

    private const GROUP_VERSION_KEY = 'wm:object-cache:group:';

    /** @var array<string, array<int|string, mixed>> */
    private array $runtime = [];

    /** @var array<string, true> */
    private array $nonPersistentGroups = [];

    /** @var array<string, true> */
    private array $globalGroups = [];

    private ?int $version = null;

    /** @var array<string, int> */
    private array $groupVersions = [];

    private int $hits = 0;

    private int $misses = 0;

    public function __construct(private readonly ?Repository $store) {}

    /**
     * The cache for this app: memory only, or memory in front of the Laravel
     * store named by config wordpress.object_cache.
     */
    public static function fromConfig(): self
    {
        $store = config('wordpress.object_cache');

        return new self(is_string($store) && $store !== '' ? Cache::store($store) : null);
    }

    public function isPersistent(): bool
    {
        return $this->store !== null;
    }

    public function get(int|string $key, string $group, bool $force = false, ?bool &$found = null): mixed
    {
        $persistent = $this->persists($group);

        if (! ($force && $persistent) && $this->inRuntime($key, $group)) {
            return $this->hit($this->runtime[$group][$key], $found);
        }

        if ($persistent && ($stored = $this->fromStore($key, $group)) !== null) {
            $this->runtime[$group][$key] = $stored[0];

            return $this->hit($stored[0], $found);
        }

        $found = false;
        $this->misses++;

        return false;
    }

    public function add(int|string $key, mixed $data, string $group, int $expire = 0): bool
    {
        return $this->exists($key, $group) ? false : $this->set($key, $data, $group, $expire);
    }

    public function replace(int|string $key, mixed $data, string $group, int $expire = 0): bool
    {
        return $this->exists($key, $group) ? $this->set($key, $data, $group, $expire) : false;
    }

    public function set(int|string $key, mixed $data, string $group, int $expire = 0): bool
    {
        $this->runtime[$group][$key] = is_object($data) ? clone $data : $data;

        if ($this->persists($group)) {
            $this->toStore($key, $group, $data, $expire);
        }

        return true;
    }

    public function delete(int|string $key, string $group): bool
    {
        $existed = $this->inRuntime($key, $group);
        unset($this->runtime[$group][$key]);

        if ($this->persists($group)) {
            $existed = $this->store->forget($this->storeKey($key, $group)) || $existed;
        }

        return $existed;
    }

    public function incr(int|string $key, int $offset, string $group): int|false
    {
        return $this->adjust($key, $offset, $group);
    }

    public function decr(int|string $key, int $offset, string $group): int|false
    {
        return $this->adjust($key, -$offset, $group);
    }

    public function flush(): bool
    {
        $this->runtime = [];

        if ($this->store !== null) {
            $this->version = $this->bump(self::VERSION_KEY);
        }

        return true;
    }

    public function flushRuntime(): bool
    {
        $this->runtime = [];

        return true;
    }

    public function flushGroup(string $group): bool
    {
        unset($this->runtime[$group]);

        if ($this->store !== null) {
            $this->groupVersions[$group] = $this->bump(self::GROUP_VERSION_KEY.hash('xxh128', $group));
        }

        return true;
    }

    /** Forgets one group in memory only, leaving the store alone. */
    public function flushRuntimeGroup(string $group): void
    {
        unset($this->runtime[$group]);
    }

    /** @param  list<string>  $groups */
    public function addNonPersistentGroups(array $groups): void
    {
        $this->nonPersistentGroups += array_fill_keys($groups, true);
    }

    /** @param  list<string>  $groups */
    public function addGlobalGroups(array $groups): void
    {
        $this->globalGroups += array_fill_keys($groups, true);
    }

    /** @return array<string, true> */
    public function globalGroups(): array
    {
        return $this->globalGroups;
    }

    public function hits(): int
    {
        return $this->hits;
    }

    public function misses(): int
    {
        return $this->misses;
    }

    /** @return array<string, array<int|string, mixed>> */
    public function runtime(): array
    {
        return $this->runtime;
    }

    private function hit(mixed $value, ?bool &$found): mixed
    {
        $found = true;
        $this->hits++;

        return is_object($value) ? clone $value : $value;
    }

    private function exists(int|string $key, string $group): bool
    {
        if ($this->inRuntime($key, $group)) {
            return true;
        }

        if ($this->persists($group) && ($stored = $this->fromStore($key, $group)) !== null) {
            $this->runtime[$group][$key] = $stored[0];

            return true;
        }

        return false;
    }

    private function inRuntime(int|string $key, string $group): bool
    {
        return isset($this->runtime[$group]) && array_key_exists($key, $this->runtime[$group]);
    }

    /**
     * WordPress's rules: only an existing value changes, a non-numeric value
     * counts as 0, and the result never goes below 0.
     */
    private function adjust(int|string $key, int $offset, string $group): int|false
    {
        if (! $this->exists($key, $group)) {
            return false;
        }

        $value = $this->runtime[$group][$key];
        $value = max(0, (is_numeric($value) ? $value : 0) + $offset);
        $this->set($key, $value, $group);

        return $value;
    }

    private function persists(string $group): bool
    {
        return $this->store !== null && ! isset($this->nonPersistentGroups[$group]);
    }

    /**
     * The stored value wrapped in a one-item list (so a stored false is told
     * apart from a miss), or null when the store has nothing usable.
     *
     * @return array{0: mixed}|null
     */
    private function fromStore(int|string $key, string $group): ?array
    {
        $raw = $this->store->get($this->storeKey($key, $group));

        if (! is_string($raw)) {
            return null;
        }

        $value = @unserialize($raw);

        return is_array($value) && array_key_exists(0, $value) ? $value : null;
    }

    private function toStore(int|string $key, string $group, mixed $data, int $expire): void
    {
        $storeKey = $this->storeKey($key, $group);

        try {
            $raw = serialize([$data]);
        } catch (Throwable) {
            // Can't be stored (a closure, say): it stays in memory for this
            // request, and no older copy may be left behind in the store.
            $this->store->forget($storeKey);

            return;
        }

        $expire > 0 ? $this->store->put($storeKey, $raw, $expire) : $this->store->forever($storeKey, $raw);
    }

    /**
     * A key every store accepts (Memcached refuses spaces and long keys), tied
     * to the current flush version and the group's version.
     */
    private function storeKey(int|string $key, string $group): string
    {
        $this->version ??= (int) $this->store->get(self::VERSION_KEY, 0);
        $this->groupVersions[$group] ??= (int) $this->store->get(self::GROUP_VERSION_KEY.hash('xxh128', $group), 0);

        return 'wm:'.hash('xxh128', $this->version.'|'.$group.'|'.$this->groupVersions[$group].'|'.$key);
    }

    private function bump(string $versionKey): int
    {
        $version = $this->store->increment($versionKey);

        if (! is_int($version)) {
            $version = (int) $this->store->get($versionKey, 0) + 1;
            $this->store->forever($versionKey, $version);
        }

        return $version;
    }
}
