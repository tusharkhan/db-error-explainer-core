<?php

namespace TusharKhan\DbErrorExplainer\Migration\Generators;

use TusharKhan\DbErrorExplainer\Lib\RuleIds;

class TableExistGenerator extends AbstractMigrationGenerator
{

    protected array $supportedRuleIds = [
        RuleIds::TABLE_ALREADY_EXISTS
    ];

    /**
     * @inheritDoc
     */
    public function generate(array $context): string
    {
        $table = $context['table'] ?? 'your_table';
        $message = $this->sqlComment("If this table is incorrect, remove it:") ;
        $message .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $message .= $this->sqlComment("Use if statement to create the table only if it doesn't exist:") ;
        $message .= "CREATE TABLE IF NOT EXISTS `{$table}` (\n    `id` INT PRIMARY KEY AUTO_INCREMENT
);\n";

        $message .= $this->sqlComment("Add columns as needed");
        return $message;
    }
}