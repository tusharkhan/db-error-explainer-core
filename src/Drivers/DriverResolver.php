<?php

namespace TusharKhan\DbErrorExplainer\Drivers;

use InvalidArgumentException;
use PDO;
use TusharKhan\DbErrorExplainer\Drivers\MySql\MySqlDriver;
use TusharKhan\DbErrorExplainer\Drivers\PgSql\PgSqlDriver;

final class DriverResolver
{
    /** @var array<string, class-string<DriverInterface>> */
    private const DRIVERS = [
        'mysql' => MySqlDriver::class,
        'pgsql' => PgSqlDriver::class,
    ];

    // Uses the given driver (instance or name), otherwise the PDO connection's driver.
    public static function resolve(PDO $pdo, DriverInterface|string|null $driver = null): DriverInterface
    {
        if ($driver instanceof DriverInterface) {
            return $driver;
        }

        if ($driver !== null) {
            $class = self::DRIVERS[strtolower($driver)]
                ?? throw new InvalidArgumentException("Unsupported database driver \"{$driver}\". Supported: " . implode(', ', array_keys(self::DRIVERS)));

            return new $class();
        }

        // Connections without a driver yet (e.g. SQLite) keep the MySQL behaviour until theirs lands
        $class = self::DRIVERS[$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)] ?? MySqlDriver::class;

        return new $class();
    }
}
