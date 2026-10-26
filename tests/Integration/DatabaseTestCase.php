<?php

namespace Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    /** @var array<string, PDO> */
    private static array $connections = [];

    // MySQL connection from DB_DSN / DB_USER / DB_PASS; skips when unreachable.
    protected function pdo(): PDO
    {
        return $this->connect('DB');
    }

    // PostgreSQL connection from PG_DSN / PG_USER / PG_PASS; skips when unreachable.
    protected function pgsql(): PDO
    {
        return $this->connect('PG');
    }

    private function connect(string $prefix): PDO
    {
        if (!isset(self::$connections[$prefix])) {
            try {
                self::$connections[$prefix] = new PDO(
                    getenv("{$prefix}_DSN") ?: '',
                    getenv("{$prefix}_USER") ?: null,
                    getenv("{$prefix}_PASS") ?: null
                );
            } catch (PDOException $e) {
                $this->markTestSkipped("{$prefix} database not reachable: " . $e->getMessage());
            }
        }

        return self::$connections[$prefix];
    }
}
