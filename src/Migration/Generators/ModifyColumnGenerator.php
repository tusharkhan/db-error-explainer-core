<?php

namespace TusharKhan\DbErrorExplainer\Migration\Generators;

use TusharKhan\DbErrorExplainer\Lib\RuleIds;

/**
 * Generates column modification migration suggestions
 */
class ModifyColumnGenerator extends AbstractMigrationGenerator
{
    protected array $supportedRuleIds = [
        RuleIds::DATA_TOO_LONG,
        RuleIds::DATA_TRUNCATED,
        RuleIds::COLUMN_CHANGE_SIZE,
        RuleIds::WRONG_VALUE_FOR_TYPE,
        RuleIds::OUT_OF_RANGE,
        RuleIds::NULL_CONSTRAINT_VIOLATION
    ];

    protected int $priority = 75;

    /**
     * {@inheritdoc}
     */
    public function generate(array $context): string
    {
        return match ($context['rule_id'] ?? '') {
            RuleIds::COLUMN_CHANGE_SIZE, 
            RuleIds::WRONG_VALUE_FOR_TYPE, 
            RuleIds::OUT_OF_RANGE, 
            RuleIds::DATA_TOO_LONG, 
            RuleIds::DATA_TRUNCATED => $this->generateModifyColumnMigration($context),
            RuleIds::NULL_CONSTRAINT_VIOLATION => $this->generateRemoveConstraintMigration($context),
        };
    }

    private function generateModifyColumnMigration(array $context): string {
        $table = $context['table'] ?? 'your_table';
        $column = $context['column'] ?? 'your_column';
        $newType = $context['new_type'] ?? $context['suggested_type'] ?? 'VARCHAR(255)';
        $database = $context['database'] ?? null;

        $tableRef = $this->formatTableReference($database, $table);

        $migration = $this->sqlComment("Modify column type to accommodate the data");
        $migration .= "ALTER TABLE {$tableRef}\n";
        $migration .= "MODIFY `{$column}` {$newType};\n";
        $migration .= $this->sqlComment("Common type upgrades:");
        $migration .= $this->sqlComment("  VARCHAR(n) -> VARCHAR(larger_n) or TEXT");
        $migration .= $this->sqlComment("  INT -> BIGINT");
        $migration .= $this->sqlComment("  FLOAT -> DOUBLE or DECIMAL(p,s)");

        return $migration;
    }

    private function generateRemoveConstraintMigration(array $context): string {
        return $this->generateRemoveNullConstraintMigration($context);
    }

    private function generateRemoveNullConstraintMigration(array $context): string {
        $table = $context['table'] ?? 'unknown_table';
        $column = $context['column'] ?? 'unknown_column';
        $database = $context['database'] ?? null;
        $columnType = $context['column_type'] ?? 'VARCHAR(255)';

        $tableRef = $this->formatTableReference($database, $table);

        $message = $this->sqlComment("Option 1: Make the column nullable:");
        $message .= "ALTER TABLE {$tableRef} MODIFY `{$column}` {$columnType} NULL;\n\n";
        $message .= $this->sqlComment("Option 2: Set a default value:");
        $message .= "ALTER TABLE {$tableRef} MODIFY `{$column}` {$columnType} NOT NULL DEFAULT 'default_value';";
        
        return $message;
    }

}

