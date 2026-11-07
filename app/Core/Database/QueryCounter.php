<?php

declare(strict_types=1);

namespace App\Core\Database;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use wpdb;

/**
 * Keeps one query count and one query log for the whole connection. Queries
 * the house runs through Laravel are added to $wpdb->num_queries (WordPress's
 * get_num_queries()) and, when SAVEQUERIES is on, to $wpdb->queries, the log
 * debugging tools read. Queries $wpdb runs itself are already counted by it.
 */
final class QueryCounter
{
    public static function register(Dispatcher $events): void
    {
        $events->listen(QueryExecuted::class, [self::class, 'record']);
    }

    public static function record(QueryExecuted $event): void
    {
        $wpdb = $GLOBALS['wpdb'] ?? null;

        if (! $wpdb instanceof wpdb
            || WpdbConnection::isLoggingOwnQuery()
            || $event->connectionName !== config('database.default')) {
            return;
        }

        $wpdb->num_queries++;

        if (defined('SAVEQUERIES') && SAVEQUERIES) {
            $seconds = $event->time / 1000;
            $wpdb->log_query($event->toRawSql(), $seconds, $wpdb->get_caller(), microtime(true) - $seconds, []);
        }
    }
}
