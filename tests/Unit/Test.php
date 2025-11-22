<?php

namespace Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Test extends TestCase
{
    #[DataProvider('dataForTestTableNameMatchesExpected')]
    public function test_get_table_name_method($databaseError, $expectedTableName)
    {
        $actualTableName = \TusharKhan\DbErrorExplainer\Lib\Helpers::getTableName($databaseError);
        $this->assertEquals($expectedTableName, $actualTableName);
    }

    public static function dataForTestTableNameMatchesExpected(): array
    {
         return [
             // MySQL/MariaDB
             ["Table 'mydb.users' doesn't exist", 'users'],
             ["table `products` not found", 'products'],
             ["Duplicate entry for table 'orders'", 'orders'],
             ["SQLSTATE[42S02]: Base table or view not found: 1146 Table 'database.customers' doesn't exist", 'customers'],
             ["Foreign key constraint fails for table 'invoices'", 'invoices'],

             // PostgreSQL
             ['relation "employees" does not exist', 'employees'],
             ['table "public.posts" does not exist', 'posts'],
             ['ERROR: relation "comments" already exists', 'comments'],

             // SQLite
             ["no such table: categories", 'categories'],
             ["table sessions already exists", 'sessions'],
             ["no such table: main.logs", 'logs'],

             // SQL Server
             ["Invalid object name 'dbo.transactions'", 'transactions'],
             ["object 'sales' does not exist", 'sales'],

             // Oracle
             ["table or view users does not exist", 'users'],
             ["ORA-00942: table or view notifications does not exist", 'notifications'],

             // Mixed formats
             ['"schema"."my_table" not found', 'my_table'],
             ["Error in table user_profiles", 'user_profiles'],

             // Edge cases - should return null
             ["General SQL error", null],
             ["Connection timeout", null],
             ["Syntax error near WHERE", null],
         ];
    }
}