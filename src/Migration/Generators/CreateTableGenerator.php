<?php

namespace TusharKhan\DbErrorExplainer\Migration\Generators;

use TusharKhan\DbErrorExplainer\Lib\RuleIds;

/**
 * Generates CREATE TABLE migration suggestions
 */
class CreateTableGenerator extends AbstractMigrationGenerator
{
    protected array $supportedRuleIds = [
        RuleIds::TABLE_NOT_FOUND,
        RuleIds::UNKNOWN_TABLE,
    ];

    protected int $priority = 100;

    /**
     * {@inheritdoc}
     */
    public function generate(array $context): string
    {
        $database = $context['database'] ?? null;
        $table = $context['table'] ?? 'unknown_table';
        $columns = $context['table_columns'] ?? [];

        $tableRef = $this->formatTableReference($database, $table);

        $migration = "--Create the missing table\n";
        $migration .= "CREATE TABLE {$tableRef} (\n";
        $migration .= "    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n";

        foreach ($columns as $column) {
            $migration .= $this->generateColumnDefinition($column);
        }

        $migration .= "    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
        $migration .= "    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n";
        $migration .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        return $migration;
    }

    /**
     * Generate column definition
     *
     * @param array $column
     * @return string
     */
    private function generateColumnDefinition(array $column): string
    {
        $name = $column['name'] ?? 'unknown_column';
        $type = $column['type'] ?? 'VARCHAR(255)';
        $nullable = !empty($column['nullable']);

        $definition = "    `{$name}` {$type}";
        $definition .= $nullable ? " NULL" : " NOT NULL";
        $definition .= ",\n";

        return $definition;
    }
}

