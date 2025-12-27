<?php

namespace TusharKhan\DbErrorExplainer\Migration;

use TusharKhan\DbErrorExplainer\Lib\RuleIds;

/**
 * Migration Suggester
 * Generates migration code suggestions for fixing database errors
 */
class MigrationSuggester
{
    public function __construct(protected string $ruleId, protected array $context = []){}

    public function suggest()
    {
        return [
            'migration_suggestion' => match ($this->ruleId) {
                RuleIds::TABLE_NOT_FOUND => $this->suggestCreateTable($this->context),
                RuleIds::FK_MISSING_PARENT => $this->suggestAddForeignKey(
                    $this->context['database'],
                    $this->context['table'],
                    $this->context['column'],
                    $this->context['parent_table'],
                    $this->context['parent_column'] ?? 'id',
                    $this->context['referenced_column'] ?? 'id',
                    $this->context['on_delete'] ?? 'CASCADE',
                    $this->context['on_update'] ?? 'CASCADE'
                ),
                RuleIds::UNKNOWN_COLUMN => $this->suggestAddColumn($this->context['table'], $this->context['column']),
                default => ''
            },
            'other_suggestions' => ''
        ];
    }

    /**
     * Generate a CREATE TABLE migration suggestion
     */
    public function suggestCreateTable(array $context = []): string
    {
        $migration = "--Create the missing table\n";
        $migration .= "CREATE TABLE `{$context['database']}.{$context['table']}` (\n";
        $migration .= "    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n";
        $columns = $context['table_columns'] ?? [];

        foreach ($columns as $column) {
            $migration .= "    `{$column['name']}` {$column['type']}";
            if (!empty($column['nullable'])) {
                $migration .= " NULL";
            } else {
                $migration .= " NOT NULL";
            }
            $migration .= ",\n";
        }

        $migration .= "    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
        $migration .= "    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n";
        $migration .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        return $migration;
    }

    /**
     * Generate an ADD COLUMN migration suggestion
     */
    public function suggestAddColumn(string $tableName, string $columnName, string $type = 'VARCHAR(255)', bool $nullable = false): string
    {
        $migration = "--Add the missing column\n";
        $migration .= "ALTER TABLE `{$tableName}` \n";
        $migration .= "ADD COLUMN `{$columnName}` {$type}";

        if ($nullable) {
            $migration .= " NULL";
        } else {
            $migration .= " NOT NULL";
        }

        $migration .= "; --column type can be any valid MySQL column type";

        return $migration;
    }

    /**
     * Generate a foreign key constraint suggestion
     */
    public function suggestAddForeignKey(
        string $database,
        string $tableName,
        string $columnName,
        string $referencedTable,
        string $parentColumn = 'id',
        string $referencedColumn = 'id',
        string $onDelete = 'CASCADE',
        string $onUpdate = 'CASCADE'
    ): string {
        $constraintName = "{$tableName}_{$parentColumn}_foreign";

        $migration = "// Add foreign key constraint \n";
        $migration .= "ALTER TABLE `{$database}.{$tableName}` \n";
        $migration .= "ADD CONSTRAINT `{$constraintName}` \n";
        $migration .= "FOREIGN KEY (`{$parentColumn}`) \n";
        $migration .= "REFERENCES `{$referencedTable}` (`{$referencedColumn}`) \n";
        $migration .= "ON DELETE {$onDelete} \n";
        $migration .= "ON UPDATE {$onUpdate};";

        return $migration;
    }

    /**
     * Generate a unique constraint suggestion
     */
    public function suggestAddUniqueConstraint(string $tableName, array $columns): string
    {
        $constraintName = "{$tableName}_" . implode('_', $columns) . "_unique";
        $columnList = implode('`, `', $columns);

        $migration = "// Add unique constraint\n";
        $migration .= "ALTER TABLE `{$tableName}` \n";
        $migration .= "ADD CONSTRAINT `{$constraintName}` \n";
        $migration .= "UNIQUE (`{$columnList}`);";

        return $migration;
    }

    /**
     * Generate a drop constraint suggestion
     */
    public function suggestDropConstraint(string $tableName, string $constraintName, string $type = 'FOREIGN KEY'): string
    {
        $migration = "// Drop the constraint\n";
        $migration .= "ALTER TABLE `{$tableName}` \n";
        $migration .= "DROP {$type} `{$constraintName}`;";

        return $migration;
    }

    /**
     * Generate a modify column suggestion
     */
    public function suggestModifyColumn(string $tableName, string $columnName, string $newType): string
    {
        $migration = "// Modify column type\n";
        $migration .= "ALTER TABLE `{$tableName}` \n";
        $migration .= "MODIFY COLUMN `{$columnName}` {$newType};";

        return $migration;
    }
}
