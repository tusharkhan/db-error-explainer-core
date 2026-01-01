<?php

namespace TusharKhan\DbErrorExplainer\Migration\Generators;

use TusharKhan\DbErrorExplainer\Lib\RuleIds;

class SyntaxErrorGenerator extends AbstractMigrationGenerator
{
    protected array $supportedRuleIds = [
        RuleIds::SYNTAX_ERROR
    ];

    /**
     * @inheritDoc
     */
    public function generate(array $context): string
    {
        return $this->sqlComment("This is a query syntax error, not a schema issue."
            . "- Review your query for:"
            . "- Missing or extra quotes"
            . "- Missing commas"
            . "- Incorrect SQL keywords"
            . "- Reserved words used as column names");
    }
}