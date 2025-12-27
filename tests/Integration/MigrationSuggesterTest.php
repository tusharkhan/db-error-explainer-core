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
                'ERROR: insert or update on table "orders" violates foreign key constraint "fk_user" DETAIL: Key (user_id)=(99) is not present in table "users".',
                '// Add foreign key constraint ALTER TABLE orders ADD CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id);'
            ]
        ];
    }
}