<?php

namespace Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TestTableNameFetch extends TestCase
{
    #[DataProvider('mysql_errors')]
    public function test_get_table_name_from_sql_error($errorMessage, $expectedTableName)
    {
        $tableName = \TusharKhan\DbErrorExplainer\Lib\Helpers::extractTable($errorMessage);

        $this->assertEquals($expectedTableName, $tableName);
    }

    public static function mysql_errors()
    {
        return [
            ['Table \'test.test\' doesn\'t exist', 'test'],
            ["Table 'mydb.mytable' doesn't exist", 'mytable'],
            ["Unknown table 'mydb.mytable'", 'mytable'],
            ["Cannot add or update a child row: a foreign key constraint fails (`mydb`.`mytable`, CONSTRAINT `fk_name` FOREIGN KEY (`column`) REFERENCES `othertable` (`column`))", 'mytable'],
            ['ERROR: relation "mytable" does not exist', 'mytable'],
            ['ERROR: relation "myschema.mytable" does not exist', 'mytable'],
            ["Table 'mydb.mytable' doesn't exist", 'mytable'],
            ["Cannot find the object 'dbo.mytable', because it does not exist or you do not have permissions.", 'mytable']
        ];
    }
}