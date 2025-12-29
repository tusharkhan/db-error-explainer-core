<?php

namespace TusharKhan\DbErrorExplainer\Migration\Generators;

use TusharKhan\DbErrorExplainer\Lib\RuleIds;

/**
 * Generates constraint-related migration suggestions
 */
class ConstraintGenerator extends AbstractMigrationGenerator
{
    protected array $supportedRuleIds = [
        RuleIds::DUPLICATE_ENTRY,
        RuleIds::DUPLICATE_KEY,
        RuleIds::DUPLICATE_KEY_NAME,
    ];

    protected int $priority = 80;

    /**
     * {@inheritdoc}
     */
    public function generate(array $context): string
    {
        $ruleId = $context['rule_id'] ?? '';

        return match ($ruleId) {
            RuleIds::DUPLICATE_KEY_NAME => $this->generateRemoveDuplicateKeyName($context),
            default => $this->generateRemoveUniqueConstraint($context),
        };
    }

    /**
     * Generate suggestion to remove unique constraint
     *
     * @param array $context
     * @return string
     */
    private function generateRemoveUniqueConstraint(array $context): string
    {
        $table = $context['table'] ?? 'your_table';
        $constraint = $context['constraint'] ?? $context['column'] ?? 'unknown_constraint';

        // Handle case where constraint might be an array
        if (is_array($constraint)) {
            $constraint = $constraint[0] ?? 'unknown_constraint';
        }

        $database = $context['database'] ?? null;

        $tableRef = $this->formatTableReference($database, $table);

        $migration = "-- If this unique constraint is incorrect, remove it:\n";
        $migration .= "ALTER TABLE {$tableRef}\n";
        $migration .= "DROP INDEX `{$constraint}`;\n";
        $migration .= "-- If this unique constraint is incorrect, remove it";

        return $migration;
    }

    /**
     * Generate suggestion for duplicate key name
     *
     * @param array $context
     * @return string
     */
    private function generateRemoveDuplicateKeyName(array $context): string
    {
        $table = $context['table'] ?? 'unknown_table';
        $keyName = $context['key_name'] ?? $context['constraint'] ?? 'unknown_key';
        $database = $context['database'] ?? null;

        $tableRef = $this->formatTableReference($database, $table);

        $migration = $this->sqlComment("Drop the existing key with the same name:");
        $migration .= "ALTER TABLE {$tableRef}\n";
        $migration .= "DROP INDEX `{$keyName}`;\n";
        $migration .= "\n";
        $migration .= $this->sqlComment("Then recreate with a unique name or the original definition");

        return $migration;
    }

    /**
     * Generate suggestion to add unique constraint
     *
     * @param string $table
     * @param array $columns
     * @param string|null $database
     * @return string
     */
    public function generateAddUniqueConstraint(string $table, array $columns, ?string $database = null): string
    {
        $tableRef = $this->formatTableReference($database, $table);
        $constraintName = "{$table}_" . implode('_', $columns) . "_unique";
        $columnList = implode('`, `', $columns);

        $migration = $this->sqlComment("Add unique constraint");
        $migration .= "ALTER TABLE {$tableRef}\n";
        $migration .= "ADD CONSTRAINT `{$constraintName}`\n";
        $migration .= "UNIQUE (`{$columnList}`);";

        return $migration;
    }

    /**
     * Generate suggestion to drop a constraint
     *
     * @param string $table
     * @param string $constraintName
     * @param string $type
     * @param string|null $database
     * @return string
     */
    public function generateDropConstraint(
        string $table,
        string $constraintName,
        string $type = 'FOREIGN KEY',
        ?string $database = null
    ): string {
        $tableRef = $this->formatTableReference($database, $table);

        $migration = $this->sqlComment("Drop the constraint");
        $migration .= "ALTER TABLE {$tableRef}\n";
        $migration .= "DROP {$type} `{$constraintName}`;";

        return $migration;
    }
}

