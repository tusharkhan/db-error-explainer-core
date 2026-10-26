<?php

namespace Unit;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Support\FakePdoException;
use TusharKhan\DbErrorExplainer\Drivers\DriverResolver;
use TusharKhan\DbErrorExplainer\Drivers\MySql\MySqlDriver;
use TusharKhan\DbErrorExplainer\Drivers\Sqlite\SqliteDriver;
use TusharKhan\DbErrorExplainer\Explainer;

class DriverResolverTest extends TestCase
{
    public function test_resolves_by_name_case_insensitively()
    {
        $this->assertInstanceOf(MySqlDriver::class, DriverResolver::resolve(new PDO('sqlite::memory:'), 'MySQL'));
    }

    public function test_driver_instance_is_used_as_is()
    {
        $driver = new MySqlDriver();

        $this->assertSame($driver, DriverResolver::resolve(new PDO('sqlite::memory:'), $driver));
    }

    public function test_unknown_driver_name_is_rejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported database driver "oracle"');

        DriverResolver::resolve(new PDO('sqlite::memory:'), 'oracle');
    }

    public function test_sqlite_connection_is_detected()
    {
        $this->assertInstanceOf(SqliteDriver::class, DriverResolver::resolve(new PDO('sqlite::memory:')));
    }

    public function test_diagnosis_reports_the_driver()
    {
        $explainer = new Explainer(new PDO('sqlite::memory:'), ['driver' => 'mysql', 'allow_introspection' => false]);
        $message = "Duplicate entry 'a' for key 'users_email_unique'";

        $diagnosis = $explainer->explain(FakePdoException::make($message, ['23000', 1062, $message], 1062));

        $this->assertSame('mysql', $diagnosis->metadata['driver']);
    }

    public function test_mariadb_uses_the_mysql_driver()
    {
        $this->assertInstanceOf(MySqlDriver::class, DriverResolver::resolve(null, 'mariadb'));
        $this->assertTrue(DriverResolver::supports('MariaDB'));
        $this->assertFalse(DriverResolver::supports('sqlsrv'));
    }

    public function test_driver_name_is_required_without_a_connection()
    {
        $this->expectException(InvalidArgumentException::class);

        DriverResolver::resolve(null);
    }

    public function test_explainer_works_without_a_connection()
    {
        $message = 'SQLSTATE[HY000] [2002] Connection refused';

        $diagnosis = new Explainer(null, ['driver' => 'mysql'])->explain(FakePdoException::make($message, ['HY000', 2002, 'Connection refused'], 2002));

        $this->assertSame('connection_refused', $diagnosis->metadata['rule_id']);
        $this->assertFalse(isset($diagnosis->context['table_exists']));
    }
}
