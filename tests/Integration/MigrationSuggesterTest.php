<?php

namespace Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Lib\Helpers;

class MigrationSuggesterTest extends TestCase
{
    #[DataProvider('foreignKeyRulesDataProvider')]
    public function test_foreign_key_rule($errorMessage, $suggestionMessage)
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');

        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);

        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            $errorMessage,
            [ "23000", 1452, $errorMessage],
            1452
            ));

        $this->assertIsArray($diagnosis->migrationSuggestion);
        $this->assertEquals($suggestionMessage, $diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public function test_column_not_found_suggestion()
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');

        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "Error 1054: Unknown column 'user_email' in 'field list'",
            ['42S22', 1054, "Unknown column 'user_email' in 'field list'"],
            1054
        ));

        $expectedSuggestion = "--Add the missing column
ALTER TABLE `your_table` 
ADD COLUMN `user_email` VARCHAR(255) NULL;
--column type can be any valid MySQL column type";


        $this->assertIsArray($diagnosis->migrationSuggestion);
        $this->assertEquals($expectedSuggestion, $diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public function test_duplicate_entry_suggestion()
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');
        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "Error 1062: Duplicate entry 'example@gmail.com' for key 'users_email_unique'",
            ['23000', 1062, "Duplicate entry 'example@gmail.com' for key 'users_email_unique'"],
            1062
        ));

        $expectedSuggestion = "-- If this unique constraint is incorrect, remove it:
ALTER TABLE `your_table`
DROP INDEX `users_email_unique`;
-- If this unique constraint is incorrect, remove it";
        $this->assertIsArray($diagnosis->migrationSuggestion);
        $this->assertEquals($expectedSuggestion, $diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public function test_table_not_found_suggestion()
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');
        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "Error 1146: Table 'test.non_existent_table' doesn't exist",
            ['42S02', 1146, "Table 'test.non_existent_table' doesn't exist"],
            1146
        ));

        $expectedSuggestion = "--Create the missing table
CREATE TABLE `test.non_existent_table` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $this->assertIsArray($diagnosis->migrationSuggestion);
        $this->assertEquals($expectedSuggestion, $diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public function test_null_constrains_violation()
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');
        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "Column 'name' cannot be null",
            ['23000', 1048, "Column 'name' cannot be null"],
            1048
        ));

        $this->assertIsArray($diagnosis->migrationSuggestion);
        $this->assertEquals("--Option 1: Make the column nullable:
ALTER TABLE `your_table` MODIFY `name` VARCHAR(255) NULL;

--Option 2: Set a default value:
ALTER TABLE `your_table` MODIFY `name` VARCHAR(255) NOT NULL DEFAULT 'default_value';", $diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public function test_data_too_long()
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');
        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "Data too long for column 'name' at row 1",
            ['22001', 1406, "Data too long for column 'name' at row 1"],
            1406
        ));

        $this->assertIsArray($diagnosis->migrationSuggestion);
        $this->assertEquals("--Modify column type to accommodate the data
ALTER TABLE `your_table`
MODIFY `name` VARCHAR(255);
--Common type upgrades:
--  VARCHAR(n) -> VARCHAR(larger_n) or TEXT
--  INT -> BIGINT
--  FLOAT -> DOUBLE or DECIMAL(p,s)
", $diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public function test_syntax_error()
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');
        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near 'ALTER TABLE `your_table`' at line 1",
            ['42000', 1064, "You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near 'ALTER TABLE `your_table`' at line 1"],
            1064
        ));

        $this->assertIsArray($diagnosis->migrationSuggestion);

        $this->assertEquals("--This is a query syntax error, not a schema issue.- Review your query for:- Missing or extra quotes- Missing commas- Incorrect SQL keywords- Reserved words used as column names\n", $diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public function test_access_denied_suggestion()
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');
        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "Access denied for user 'test_user'@'localhost' (using password: YES)",
            ['28000', 1045, "Access denied for user 'test_user'@'localhost' (using password: YES)"],
            1045
        ));

        $expectedSuggestion = "--Grant access to user:
GRANT ALL PRIVILEGES ON test_user.* TO 'test_user'@'%';";

        $this->assertIsArray($diagnosis->migrationSuggestion);
        $this->assertEquals($expectedSuggestion, $diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public function test_unknown_database_suggestion()
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');
        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "Unknown database 'non_existent_db'",
            ['3D000', 1049, "Unknown database 'non_existent_db'"],
            1049
        ));

        $expectedSuggestion = "-- Create the database:
CREATE DATABASE `non_existent_db`;";

        $this->assertIsArray($diagnosis->migrationSuggestion);
        $this->assertEquals($expectedSuggestion, $diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public function test_table_exists_suggestion()
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');
        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "Table 'test.existing_table' already exists",
            ['42S01', 1050, "Table 'test.existing_table' already exists"],
            1050
        ));

        $expectedSuggestion = "--If this table is incorrect, remove it:
DROP TABLE IF EXISTS `existing_table`;
--Use if statement to create the table only if it doesn't exist:
CREATE TABLE IF NOT EXISTS `existing_table` (
    `id` INT PRIMARY KEY AUTO_INCREMENT
);
--Add columns as needed\n";

        $this->assertIsArray($diagnosis->migrationSuggestion);
        $this->assertEquals($expectedSuggestion, $diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public function test_wrong_value_for_type_suggestion()
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');
        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "Incorrect integer value: 'abc' for column 'age' at row 1",
            ['21S01', 1366, "Incorrect integer value: 'abc' for column 'age' at row 1"],
            1366
        ));
        $this->assertIsArray($diagnosis->migrationSuggestion);
        $this->assertEmpty($diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public function test_data_truncated_suggestion()
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');
        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "Data truncated for column 'description' at row 1",
            ['01000', 1265, "Data truncated for column 'description' at row 1"],
            1265
        ));

        $this->assertIsArray($diagnosis->migrationSuggestion);
        $this->assertEquals("--Modify column type to accommodate the data
ALTER TABLE `your_table`
MODIFY `description` VARCHAR(255);
--Common type upgrades:
--  VARCHAR(n) -> VARCHAR(larger_n) or TEXT
--  INT -> BIGINT
--  FLOAT -> DOUBLE or DECIMAL(p,s)
", $diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public function test_ambiguous_column_name_suggestion()
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');
        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "Column 'id' in field list is ambiguous",
            ['HY000', 1475, "Column 'id' in field list is ambiguous"],
            1475
        ));

        $this->assertIsArray($diagnosis->migrationSuggestion);
        $this->assertEquals("-- The column name `id` is ambiguous because it exists in multiple tables used in the query.
-- To resolve this, prefix the column with the appropriate table name or alias, e.g., `table_name.id`.", $diagnosis->migrationSuggestion['migration_suggestion']);
    }   

    public function test_cant_create_table_suggestion()
    {
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');
        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "Can't create table 'test.new_table' (errno: 150 'Foreign key constraint is incorrectly formed')",
            ['HY000', 1005, "Can't create table 'test.new_table' (errno: 150 'Foreign key constraint is incorrectly formed')"],
            1005
        ));

        $this->assertIsArray($diagnosis->migrationSuggestion);
        $this->assertEquals("--Create table
CREATE TABLE `test.new_table` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", $diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public static function foreignKeyRulesDataProvider(): array
    {
        return [
            [
                "Error 1452: Cannot add or update a child row: a foreign key constraint fails (test.orders, CONSTRAINT orders_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id))",
                "// Add foreign key constraint
ALTER TABLE `test.orders`
ADD CONSTRAINT `orders_user_id_foreign`
FOREIGN KEY (`user_id`)
REFERENCES `users` (`id`)
ON DELETE CASCADE
ON UPDATE CASCADE;"
            ],
            [
                "Error 1452: Cannot add or update a child row: a foreign key constraint fails (test.payments, CONSTRAINT payments_order_id_foreign FOREIGN KEY (order_id) REFERENCES orders(id))",
                "// Add foreign key constraint
ALTER TABLE `test.payments`
ADD CONSTRAINT `payments_order_id_foreign`
FOREIGN KEY (`order_id`)
REFERENCES `orders` (`id`)
ON DELETE CASCADE
ON UPDATE CASCADE;"
            ],
            // RuleIds::FK_CANNOT_DELETE_PARENT
            [
                "Error 1451: Cannot delete or update a parent row: a foreign key constraint fails (test.orders, CONSTRAINT orders_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id))",
                "// Add foreign key constraint
ALTER TABLE `test.orders`
ADD CONSTRAINT `orders_user_id_foreign`
FOREIGN KEY (`user_id`)
REFERENCES `users` (`id`)
ON DELETE CASCADE
ON UPDATE CASCADE;"
            ],
        ];
    }
}