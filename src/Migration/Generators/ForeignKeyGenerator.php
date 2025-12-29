<?php

namespace TusharKhan\DbErrorExplainer\Migration\Generators;

use TusharKhan\DbErrorExplainer\Lib\RuleIds;

/**
 * Generates foreign key constraint migration suggestions
 */
class ForeignKeyGenerator extends AbstractMigrationGenerator
{
    protected array $supportedRuleIds = [
        RuleIds::FK_MISSING_PARENT,
        RuleIds::FK_CANNOT_DELETE_PARENT,
    ];

    protected int $priority = 85;

    /**
     * Default ON DELETE action
     */
    private const DEFAULT_ON_DELETE = 'CASCADE';

    /**
     * Default ON UPDATE action
     */
    private const DEFAULT_ON_UPDATE = 'CASCADE';

    /**
     * {@inheritdoc}
     */
    public function generate(array $context): string
    {
        $database = $context['database'] ?? null;
        $table = $context['table'] ?? 'unknown_table';
        $column = $context['column'] ?? 'id';
        $parentTable = $context['parent_table'] ?? $context['referenced_table'] ?? 'unknown_parent_table';
        $parentColumn = $context['parent_column'] ?? 'id';
        $referencedColumn = $context['referenced_column'] ?? 'id';
        $onDelete = $context['on_delete'] ?? self::DEFAULT_ON_DELETE;
        $onUpdate = $context['on_update'] ?? self::DEFAULT_ON_UPDATE;

        $tableRef = $this->formatTableReference($database, $table);
        $constraintName = $context['constraint'] ?? $this->generateConstraintName($table, $parentColumn);

        $migration = "// Add foreign key constraint\n";
        $migration .= "ALTER TABLE {$tableRef}\n";
        $migration .= "ADD CONSTRAINT `{$constraintName}`\n";
        $migration .= "FOREIGN KEY (`{$parentColumn}`)\n";
        $migration .= "REFERENCES `{$parentTable}` (`{$referencedColumn}`)\n";
        $migration .= "ON DELETE {$onDelete}\n";
        $migration .= "ON UPDATE {$onUpdate};";

        return $migration;
    }

    /**
     * Generate a constraint name
     *
     * @param string $table
     * @param string $column
     * @return string
     */
    private function generateConstraintName(string $table, string $column): string
    {
        return "{$table}_{$column}_foreign";
    }
}

