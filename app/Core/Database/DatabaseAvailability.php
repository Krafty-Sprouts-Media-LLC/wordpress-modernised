<?php

declare(strict_types=1);

namespace App\Core\Database;

use Throwable;

/**
 * Turns "could not connect to the database" into a plain instruction, so a
 * developer who forgot to start the test databases knows what to do.
 */
final class DatabaseAvailability
{
    public const HINT = 'Start the test databases: docker compose up -d --wait';

    /**
     * The hint for a connection-refused error (MySQL/MariaDB error 2002), or null for anything else.
     */
    public static function hint(Throwable $e): ?string
    {
        for ($error = $e; $error !== null; $error = $error->getPrevious()) {
            if (str_contains($error->getMessage(), '[2002]')) {
                return self::HINT;
            }
        }

        return null;
    }
}
