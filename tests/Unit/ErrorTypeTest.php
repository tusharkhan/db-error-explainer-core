<?php

namespace Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Classification\ErrorClassifier;
use TusharKhan\DbErrorExplainer\Explainer;
use TusharKhan\DbErrorExplainer\Lib\Helpers;
use TusharKhan\DbErrorExplainer\Lib\MySqlErrorCodes;
use TusharKhan\DbErrorExplainer\Lib\RuleIds;
use TusharKhan\DbErrorExplainer\Rules\RuleEngine;

class ErrorTypeTest extends TestCase
{
    private ErrorClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new ErrorClassifier(RuleEngine::loadDefaultRules());
    }

    public function test_code_with_a_rule_uses_the_rule_id()
    {
        $this->assertSame(RuleIds::DUPLICATE_ENTRY, $this->classifier->classifyErrorType(MySqlErrorCodes::ER_DUP_ENTRY));
    }

    public function test_foreign_key_code_without_a_rule()
    {
        $this->assertSame('foreign_key_constraint', $this->classifier->classifyErrorType(MySqlErrorCodes::ER_FK_NO_INDEX_PARENT));
    }

    public function test_unknown_code()
    {
        $this->assertSame('unknown_error', $this->classifier->classifyErrorType(999999));
    }

    public function test_matched_rule_sets_error_type()
    {
        $explainer = new Explainer(new PDO('sqlite::memory:'), ['allow_introspection' => false]);
        $message = "Column 'name' cannot be null";

        $diagnosis = $explainer->explain(Helpers::fakePdoException($message, ['23000', 1048, $message], 1048));

        $this->assertSame(RuleIds::NULL_CONSTRAINT_VIOLATION, $diagnosis->errorType);
        $this->assertSame($diagnosis->metadata['rule_id'], $diagnosis->errorType);
    }

    public function test_code_known_but_sqlstate_unmatched_still_gets_a_type()
    {
        $explainer = new Explainer(new PDO('sqlite::memory:'), ['allow_introspection' => false]);

        $diagnosis = $explainer->explain(Helpers::fakePdoException('dup', ['XX000', 1062, 'dup'], 1062));

        $this->assertSame(RuleIds::DUPLICATE_ENTRY, $diagnosis->errorType);
    }
}
