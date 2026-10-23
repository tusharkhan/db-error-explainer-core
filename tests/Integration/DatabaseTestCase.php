<?php

namespace Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    private static ?PDO $pdo = null;

    // Connection comes from DB_DSN / DB_USER / DB_PASS; skips when unreachable.
    protected function pdo(): PDO
    {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO(getenv('DB_DSN') ?: '', getenv('DB_USER') ?: null, getenv('DB_PASS') ?: null);
            } catch (PDOException $e) {
                $this->markTestSkipped('Database not reachable: ' . $e->getMessage());
            }
        }

        return self::$pdo;
    }
}
