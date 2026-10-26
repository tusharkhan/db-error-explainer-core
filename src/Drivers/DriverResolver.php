<?php

namespace TusharKhan\DbErrorExplainer\Drivers;

use InvalidArgumentException;
use PDO;
use TusharKhan\DbErrorExplainer\Drivers\MySql\MySqlDriver;

final class DriverResolver
{
    /** @var array<string, class-string<DriverInterface>> */
    private const DRIVERS = [
        'mysql' => MySqlDriver::class,
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

        // MySQL is the only driver so far; other connections keep today's MySQL behaviour until theirs lands
        $class = self::DRIVERS[$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)] ?? MySqlDriver::class;

        return new $class();
    }
}
