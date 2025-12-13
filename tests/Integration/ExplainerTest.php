<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Lib\Helpers;

class ExplainerTest extends TestCase
{
    private function fakePdoException(string $message, ?array $errorInfo = null, $code = 0): \PDOException
    {
        $e = new \PDOException($message, (int) $code);
        if ($errorInfo !== null) {
            $e->errorInfo = $errorInfo;
        }
        return $e;
    }

    public function test_explainer_has_migration_suggester(){
        $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');

        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain($this->fakePdoException(
            "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'orders.orders' doesn't exist",
            ['42S02', 1146, "Table 'orders.orders' doesn't exist"],
            1146
        ));
    Helpers::dd($diagnosis->migrationSuggestion);
        $this->assertInstanceOf(\TusharKhan\DbErrorExplainer\Migration\MigrationSuggester::class, $diagnosis->migrationSuggestion);
    }
}