<?php

namespace Integration;

class PostgresConnectionTest extends DatabaseTestCase
{
    public function test_postgres_is_reachable()
    {
        $this->assertSame('pgsql', $this->pgsql()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }
}
