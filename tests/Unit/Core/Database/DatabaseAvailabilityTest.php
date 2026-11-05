<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Database;

use App\Core\Database\DatabaseAvailability;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * When the test databases are not running, the failure says how to start them.
 */
final class DatabaseAvailabilityTest extends TestCase
{
    public function test_missing_database_gives_a_clear_hint(): void
    {
        $refused = new PDOException('SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it');

        $this->assertSame('Start the test databases: docker compose up -d --wait', DatabaseAvailability::hint($refused));

        $postgresRefused = new PDOException('SQLSTATE[08006] [7] connection to server at "127.0.0.1", port 5437 failed: Connection refused');
        $this->assertSame('Start the test databases: docker compose up -d --wait', DatabaseAvailability::hint($postgresRefused));
        $this->assertNull(DatabaseAvailability::hint(new RuntimeException('x')));
    }
}
