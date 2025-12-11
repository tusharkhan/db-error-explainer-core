<?php

namespace Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TestExtractConstraint extends TestCase
{
    #[DataProvider('constraintErrorProvider')]
    public function test_extract_constraint_from_sql_errors($errorMessage, $expectedConstraint)
    {
        $constraint = \TusharKhan\DbErrorExplainer\Lib\Helpers::extractConstraint($errorMessage);

        $this->assertEquals($expectedConstraint, $constraint);
    }

    public static function constraintErrorProvider()
    {
        return [
            // MySQL/MariaDB - PRIMARY KEY violations
            ['SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'1\' for key \'PRIMARY\'', 'PRIMARY'],
            ['Duplicate entry \'123\' for key \'PRIMARY\'', 'PRIMARY'],
            ['ERROR 1062 (23000): Duplicate entry \'test@example.com\' for key \'PRIMARY\'', 'PRIMARY'],

            // MySQL/MariaDB - UNIQUE constraint violations
            ['SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'test@test.com\' for key \'users.email_unique\'', 'users.email_unique'],
            ['Duplicate entry \'john_doe\' for key \'username_unique\'', 'username_unique'],

            // MySQL/MariaDB - FOREIGN KEY constraint violations
            ['SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`testdb`.`orders`, CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`))', 'fk_user_id'],
            ['SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`shop`.`order_items`, CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`))', 'order_items_ibfk_1'],
            ['Cannot add or update a child row: a foreign key constraint fails (CONSTRAINT `fk_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`))', 'fk_category_id'],

            // MySQL/MariaDB - NOT NULL constraint violations
            ['SQLSTATE[23000]: Integrity constraint violation: 1048 Column \'name\' cannot be null', 'name'],
            ['SQLSTATE[23000]: Integrity constraint violation: 1048 Column \'email\' cannot be null', 'email'],
            ['Column \'user_id\' cannot be null', 'user_id'],

            // PostgreSQL - Foreign key constraint violations
            ['SQLSTATE[23503]: Foreign key violation: 7 ERROR: insert or update on table "orders" violates foreign key constraint "orders_user_id_fkey"', 'orders_user_id_fkey'],
            ['ERROR: insert or update on table "comments" violates foreign key constraint "comments_post_id_fkey"', 'comments_post_id_fkey'],
            ['ERROR: update or delete on table "users" violates foreign key constraint "orders_user_id_fkey" on table "orders"', 'orders_user_id_fkey'],

            // PostgreSQL - Unique constraint violations
            ['SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "users_email_key"', 'users_email_key'],
            ['ERROR: duplicate key value violates unique constraint "products_sku_key"', 'products_sku_key'],
            ['ERROR: duplicate key value violates unique constraint "uk_username"', 'uk_username'],

            // PostgreSQL - Check constraint violations
            ['ERROR: new row for relation "products" violates check constraint "products_price_check"', 'products_price_check'],
            ['ERROR: new row for relation "users" violates check constraint "users_age_check"', 'users_age_check'],

            // PostgreSQL - NOT NULL constraint violations
            ['ERROR: null value in column "email" violates not-null constraint', null], // This doesn't have a constraint name
            ['ERROR: null value in column "name" of relation "users" violates not-null constraint', null],

            // SQLite - UNIQUE constraint violations
            ['SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: users.email', 'users.email'],
            ['UNIQUE constraint failed: products.sku', 'products.sku'],
            ['UNIQUE constraint failed: accounts.username', 'accounts.username'],

            // SQLite - FOREIGN KEY constraint violations
            ['FOREIGN KEY constraint failed', null], // SQLite often doesn't specify the constraint name

            // SQLite - CHECK constraint violations
            ['CHECK constraint failed: positive_price', 'positive_price'],
            ['CHECK constraint failed: valid_age', 'valid_age'],

            // SQLite - NOT NULL constraint violations
            ['NOT NULL constraint failed: users.email', 'users.email'],
            ['NOT NULL constraint failed: products.name', 'products.name'],

            // Oracle - Unique constraint violations
            ['ORA-00001: unique constraint (HR.USERS_EMAIL_UK) violated', 'HR.USERS_EMAIL_UK'],
            ['ORA-00001: unique constraint (SCHEMA.UK_USERNAME) violated', 'SCHEMA.UK_USERNAME'],
            ['ORA-00001: unique constraint (PRODUCTS_SKU_UK) violated', 'PRODUCTS_SKU_UK'],

            // Oracle - Foreign key constraint violations
            ['ORA-02291: integrity constraint (HR.ORDERS_USER_FK) violated - parent key not found', 'HR.ORDERS_USER_FK'],
            ['ORA-02292: integrity constraint (SCHEMA.FK_USER_ID) violated - child record found', 'SCHEMA.FK_USER_ID'],

            // Oracle - Check constraint violations
            ['ORA-02290: check constraint (HR.SALARY_CHECK) violated', 'HR.SALARY_CHECK'],
            ['ORA-02290: check constraint (USERS.AGE_CHECK) violated', 'USERS.AGE_CHECK'],

            // SQL Server - Unique constraint violations
            ['Violation of UNIQUE KEY constraint \'UQ_Email\'. Cannot insert duplicate key.', 'UQ_Email'],
            ['Violation of UNIQUE KEY constraint \'UK_Username\'. Cannot insert duplicate key in object \'dbo.Users\'.', 'UK_Username'],

            // SQL Server - Primary key violations
            ['Violation of PRIMARY KEY constraint \'PK_Users\'. Cannot insert duplicate key.', 'PK_Users'],

            // SQL Server - Foreign key violations
            ['The INSERT statement conflicted with the FOREIGN KEY constraint "FK_Orders_Users". The conflict occurred in database "MyDB", table "dbo.Users", column \'id\'.', 'FK_Orders_Users'],
            ['The DELETE statement conflicted with the REFERENCE constraint "FK_Comments_Posts".', 'FK_Comments_Posts'],

            // SQL Server - Check constraint violations
            ['The INSERT statement conflicted with the CHECK constraint "CK_Age". The conflict occurred in database "MyDB", table "dbo.Users", column \'age\'.', 'CK_Age'],

            // MariaDB - Similar to MySQL
            ['Duplicate entry \'test\' for key \'idx_unique_username\'', 'idx_unique_username'],
            ['Cannot add or update a child row: a foreign key constraint fails (CONSTRAINT `fk_posts_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE)', 'fk_posts_users'],

            // Generic constraint error formats
            ['constraint failed: unique_email_constraint', 'unique_email_constraint'],
            ['constraint failed: users_email_unique', 'users_email_unique'],
        ];
    }
}