<?php

namespace TusharKhan\DbErrorExplainer\Migration\Generators;

use TusharKhan\DbErrorExplainer\Lib\RuleIds;

/**
 * Generates ADD COLUMN migration suggestions
 */
class AddColumnGenerator extends AbstractMigrationGenerator
{
    protected array $supportedRuleIds = [
        RuleIds::UNKNOWN_COLUMN,
    ];

    protected int $priority = 90;

    /**
     * {@inheritdoc}
     */
    public function generate(array $context): string
    {
        $table = $context['table'] ?? 'your_table';
        $column = $context['column'] ?? 'unknown_column';
        $type = $context['column_type'] ?? 'VARCHAR(255)';
        $nullable = $context['nullable'] ?? true;
        $database = $context['database'] ?? null;

        $tableRef = $this->formatTableReference($database, $table);
        $nullableStr = $nullable ? 'NULL' : 'NOT NULL';

        $migration = "--Add the missing column\n";
        $migration .= "ALTER TABLE {$tableRef} \n";
        $migration .= "ADD COLUMN `{$column}` {$type} {$nullableStr};\n";
        $migration .= "--column type can be any valid MySQL column type";

        return $migration;
    }
}

