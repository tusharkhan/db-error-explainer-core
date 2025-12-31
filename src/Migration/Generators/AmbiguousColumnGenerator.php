<?php

namespace TusharKhan\DbErrorExplainer\Migration\Generators;

use TusharKhan\DbErrorExplainer\Lib\RuleIds;
use TusharKhan\DbErrorExplainer\Migration\Generators\AbstractMigrationGenerator;

class AmbiguousColumnGenerator extends AbstractMigrationGenerator
{
    protected array $supportedRuleIds = [
        RuleIds::AMBIGUOUS_COLUMN_NAME
    ];

    /**
     * @inheritDoc
     */
    public function generate(array $context): string
    {
        $column = $context['column'] ?? 'your_column';
        return "-- The column name `{$column}` is ambiguous because it exists in multiple tables used in the query.\n-- To resolve this, prefix the column with the appropriate table name or alias, e.g., `table_name.{$column}`.";
    }
}