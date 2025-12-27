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
            ]
        ];
    }
}