<?php

namespace TusharKhan\DbErrorExplainer\Migration;

/**
 * Migration Suggester
 * Generates migration code suggestions for fixing database errors
 */
class MigrationSuggester
{
    /**
     * Generate a CREATE TABLE migration suggestion
     */
    public function suggestCreateTable(string $tableName, array $columns = []): string
    {
        $migration = "// Create the missing table\n";
        $migration .= "CREATE TABLE `{$tableName}` (\n";
        $migration .= "    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n";

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
        $migration = "// Add the missing column\n";
        $migration .= "ALTER TABLE `{$tableName}` \n";
        $migration .= "ADD COLUMN `{$columnName}` {$type}";

        if ($nullable) {
            $migration .= " NULL";
        } else {
            $migration .= " NOT NULL";
        }

        $migration .= ";";

        return $migration;
    }

    /**
     * Generate a foreign key constraint suggestion
     */
    public function suggestAddForeignKey(
        string $tableName,
        string $columnName,
        string $referencedTable,
        string $referencedColumn = 'id',
        string $onDelete = 'CASCADE',
        string $onUpdate = 'CASCADE'
    ): string {
        $constraintName = "{$tableName}_{$columnName}_foreign";

        $migration = "// Add foreign key constraint\n";
        $migration .= "ALTER TABLE `{$tableName}` \n";
        $migration .= "ADD CONSTRAINT `{$constraintName}` \n";
        $migration .= "FOREIGN KEY (`{$columnName}`) \n";
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
