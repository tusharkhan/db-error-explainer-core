<?php

namespace Unit;

use PHPUnit\Framework\TestCase;

class TestDatabaseNameFetchTest extends TestCase
{
    public function test_get_database_name_from_sql_error_message(){
        $errorMessage = "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'my_database.users' doesn't exist";
        $expectedDatabaseName = "my_database";

        $actualDatabaseName = \TusharKhan\DbErrorExplainer\Lib\Helpers::extractDatabase($errorMessage);

        $this->assertEquals($expectedDatabaseName, $actualDatabaseName);


    }
}