<?php

namespace Unit;

use TusharKhan\DbErrorExplainer\Parsing\MessageParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TestColumnNameFetch extends TestCase
{
    #[DataProvider('columnErrorProvider')]
    public function test_fetch_column_name_from_sql_errors($errorMessage, $expectedColumnName)
    {
        $columnName = new MessageParser()->column($errorMessage);
        $this->assertEquals($expectedColumnName, $columnName);
    }

    public static function columnErrorProvider()
    {
        return [
            // MySQL/MariaDB - Column not found errors
            ["SQLSTATE[42S22]: Column not found: 1054 Unknown column 'column_name' in 'field list'", "column_name"],
            ["SQLSTATE[42S22]: Column not found: 1054 Unknown column 'column_name' in 'where clause'", "column_name"],
            ["SQLSTATE[42S22]: Column not found: 1054 Unknown column 'column_name' in 'order clause'", "column_name"],
            ["SQLSTATE[42S22]: Column not found: 1054 Unknown column 'column_name' in 'having clause'", "column_name"],
            ["SQLSTATE[42S22]: Column not found: 1054 Unknown column 'column_name' in 'on clause'", "column_name"],
            ["SQLSTATE[42S22]: Column not found: 1054 Unknown column 'column_name' in 'group clause'", "column_name"],
            ["Unknown column 'column_name' in 'field list'", "column_name"],
            ["ERROR 1054 (42S22): Unknown column 'nonexistent_column' in 'field list'", "nonexistent_column"],
            ["You have an error in your SQL syntax; check the manual that corresponds to your MySQL version for the right syntax to use near 'column_name' order by...", "column_name"],

            // PostgreSQL - Column errors
            ['ERROR: column "column_name" does not exist', "column_name"],
            ['ERROR: column "user_email" does not exist', "user_email"],
            ['ERROR: column "created_at" does not exist at character 34', "created_at"],
            ['column "status" of relation "users" does not exist', "status"],

            // SQL Server - Column errors
            ["Invalid column name 'column_name'.", "column_name"],
            ["Invalid column name 'user_id'.", "user_id"],
            ["The column name 'invalid_col' is not valid.", "invalid_col"],

            // Oracle - Column errors
            ["ORA-00904: 'column_name': invalid identifier", "column_name"],
            ["ORA-00904: 'USER_NAME': invalid identifier", "USER_NAME"],

            // SQLite - Column errors
            ["no such column: column_name", "column_name"],
            ["no such column: users.email_address", "email_address"],

            // Generic column errors
            ["invalid column name: column_name", "column_name"],
            ["Column 'user_status' cannot be null", "user_status"],
            ["Column `age` cannot be null", "age"],
            ["Column \"price\" cannot be null", "price"],

            // Duplicate column errors
            ["Duplicate column name 'column_name'", "column_name"],
            ["Duplicate column 'email'", "email"],

            // Ambiguous column errors
            ["Ambiguous column name 'user_id'", "user_id"],
            ["Column 'name' in field list is ambiguous", "name"],

            // Foreign key constraint errors
            ["Cannot add foreign key constraint. Foreign key (`user_id`) references...", "user_id"],
            ["FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)", "order_id"],

            // Data type errors
            ["Data too long for column 'description' at row 1", "description"],
            ["Out of range value for column 'age' at row 1", "age"],

            // Backtick variations
            ["Unknown column `column_with_backticks` in 'field list'", "column_with_backticks"],
            ["Column `updated_at` cannot be null", "updated_at"],

            // Double quote variations
            ['Unknown column "column_with_quotes" in field list', "column_with_quotes"],

            // Mixed case columns
            ["Unknown column 'userId' in 'where clause'", "userId"],
            ["Column 'createdAt' in field list is ambiguous", "createdAt"],
            ["Error 1452: Cannot add or update a child row: a foreign key constraint fails (mydb.orders, CONSTRAINT orders_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id))", "user_id"],

            // Additional NULL constraint errors
            ["SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'email' cannot be null", "email"],
            ["ERROR 1048 (23000): Column 'password' cannot be null", "password"],

            // Default value errors
            ["ERROR 1364 (HY000): Field 'username' doesn't have a default value", "username"],
            ["SQLSTATE[HY000]: General error: 1364 Field 'created_by' doesn't have a default value", "created_by"],

            // CHECK constraint errors
            ["Check constraint 'check_age' is violated for column 'age'", "age"],
            ["CHECK constraint failed for column 'salary'", "salary"],

            // Incorrect value/data type errors
            ["Incorrect integer value 'abc' for column 'quantity' at row 1", "quantity"],
            ["Incorrect decimal value for column 'price' at row 1", "price"],
            ["Incorrect datetime value '2023-13-45' for column 'created_at' at row 1", "created_at"],

            // Auto-increment errors
            ["Duplicate entry '123' for key 'PRIMARY' in column 'id'", "id"],

            // JSON column errors
            ["Invalid JSON text in column 'metadata' at position 0", "metadata"],
            ["JSON column 'settings' cannot be null", "settings"],

            // ENUM errors
            ["Data truncated for column 'status' at row 1", "status"],

            // Index-related column errors
            ["Duplicate entry 'test@example.com' for key 'users.email'", "email"],
            ["Key column 'user_id' doesn't exist in table", "user_id"],

            // PostgreSQL specific
            ["ERROR: null value in column 'name' violates not-null constraint", "name"],
            ['ERROR: column "first_name" of relation "users" does not exist', "first_name"],

            // SQL Server specific
            ["Cannot insert the value NULL into column 'phone', table 'dbo.users'", "phone"],
            ["Conversion failed when converting the varchar value 'text' to data type int for column 'count'", "count"],

            // Oracle specific
            ["ORA-01400: cannot insert NULL into ('SCHEMA'.'TABLE'.'COLUMN_NAME')", "COLUMN_NAME"],
            ["ORA-01722: invalid number for column 'amount'", "amount"],

            // Unique constraint with column
            ["UNIQUE constraint failed: users.username", "username"],
            ["Duplicate entry 'admin' for key 'users.username'", "username"],
        ];
    }
}