<?php

namespace Unit;

use TusharKhan\DbErrorExplainer\Parsing\MessageParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TestDatabaseNameFetch extends TestCase
{
    #[DataProvider('generateErrorMessage')]
    public function test_get_database_name_from_sql_error_message($errorMessage, $expectedDatabaseName){
        $actualDatabaseName = new MessageParser()->database($errorMessage);

        $this->assertEquals($expectedDatabaseName, $actualDatabaseName);
    }

    public static function generateErrorMessage(){
        return [
            ["SQLSTATE[42S02]: Base table or view not found: 1146 Table 'my_database.users' doesn't exist", "my_database"],
            ["SQLSTATE[42S02]: Unknown database 'nonexistent_db'", "nonexistent_db"],
            ["Access denied for user 'user'@'localhost' to database 'restricted_db'", "restricted_db"],
            ["database 'test_db' does not exist", "test_db"],
            ["Table 'sample_db'.'orders' doesn't exist", "sample_db"],
            ["sQLSTATE[HY000]: General error: 1007 Can't create database 'test'; database exists", "test"],
            ["Cannot open database 'production_db' requested by the login.", "production_db"],
            ["General SQL error without database info", 'info'],
            ["Connection timeout occurred", null],
            ["Error 1452: Cannot add or update a child row: a foreign key constraint fails (mydb.orders, CONSTRAINT orders_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id))", "mydb"]
        ];
    }
}