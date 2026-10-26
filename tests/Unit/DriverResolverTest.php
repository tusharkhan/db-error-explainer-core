<?php

namespace Unit;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Support\FakePdoException;
use TusharKhan\DbErrorExplainer\Drivers\DriverResolver;
use TusharKhan\DbErrorExplainer\Drivers\MySql\MySqlDriver;
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

    public function test_connection_without_a_driver_falls_back_to_mysql()
    {
        $this->assertInstanceOf(MySqlDriver::class, DriverResolver::resolve(new PDO('sqlite::memory:')));
    }

    public function test_diagnosis_reports_the_driver()
    {
        $explainer = new Explainer(new PDO('sqlite::memory:'), ['driver' => 'mysql', 'allow_introspection' => false]);
        $message = "Duplicate entry 'a' for key 'users_email_unique'";

        $diagnosis = $explainer->explain(FakePdoException::make($message, ['23000', 1062, $message], 1062));

        $this->assertSame('mysql', $diagnosis->metadata['driver']);
    }
}
