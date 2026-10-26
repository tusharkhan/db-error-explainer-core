<?php

namespace Unit;

use TusharKhan\DbErrorExplainer\Parsing\MessageParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TestTableNameFetch extends TestCase
{
    #[DataProvider('tableNameErrorProvider')]
    public function test_get_table_name_from_sql_error($errorMessage, $expectedTableName)
    {
        $tableName = new MessageParser()->table($errorMessage);

        $this->assertEquals($expectedTableName, $tableName);
    }

    public static function tableNameErrorProvider()
    {
        return [
            ['Table \'test.test\' doesn\'t exist', 'test'],
            ["Unknown table 'mydb.mytable'", 'mytable'],
            ["Cannot add or update a child row: a foreign key constraint fails (`mydb`.`mytable`, CONSTRAINT `fk_name` FOREIGN KEY (`column`) REFERENCES `othertable` (`column`))", 'mytable'],
            ['ERROR: relation "mytable" does not exist', 'mytable'],
            ['ERROR: relation "myschema.mytable" does not exist', 'mytable'],
            ["Table 'mydb.mytable' doesn't exist", 'mytable'],
            ["Cannot find the object 'dbo.mytable', because it does not exist or you do not have permissions.", 'mytable'],
            ["Error 1452: Cannot add or update a child row: a foreign key constraint fails (mydb.orders, CONSTRAINT orders_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id))", 'orders']
        ];
    }
}