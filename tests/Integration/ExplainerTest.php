<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Lib\Helpers;
use TusharKhan\DbErrorExplainer\Lib\MySqlStates;
use TusharKhan\DbErrorExplainer\Lib\RuleIds;

class ExplainerTest extends DatabaseTestCase
{

    public function test_explainer_has_migration_suggester(){
        $pdo = $this->pdo();

        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'orders.orders' doesn't exist",
            ['42S02', 1146, "Table 'orders.orders' doesn't exist"],
            1146
        ));

        $this->assertIsArray($diagnosis->migrationSuggestion);
    }

    public function test_table_not_found_explain()
    {
        $pdo = $this->pdo();
        $explainer = new \TusharKhan\DbErrorExplainer\Explainer($pdo);
        $diagnosis = $explainer->explain(Helpers::fakePdoException(
            "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'orders.orders' doesn't exist",
            ['42S02', 1146, "Table 'orders.orders' doesn't exist"],
            1146
        ));

        $this->assertEquals(RuleIds::TABLE_NOT_FOUND, $diagnosis->errorType);
        $this->assertEquals(MySqlStates::TABLE_OR_VIEW_NOT_FOUND, $diagnosis->sqlState);
    }
}