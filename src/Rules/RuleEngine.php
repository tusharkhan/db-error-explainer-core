<?php

namespace TusharKhan\DbErrorExplainer\Rules;

use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Lib\MySqlErrorCodes;
use TusharKhan\DbErrorExplainer\Lib\Helpers;

/**
 * Rule Engine
 * Executes rule matching and context extraction
 */
class RuleEngine
{
    /**
     * Execute a rule and extract context
     */
    public function executeRule(array $rule, NormalizedException $normalized): array
    {
        $context = [];

        // Execute the extract function if defined
        if (isset($rule['extract']) && is_callable($rule['extract'])) {
            $context = $rule['extract']($normalized);
        }

        // Try to extract table name using Helpers if not already set
        if (empty($context['table']) && empty($normalized->table)) {
            $extractedTable = Helpers::extractTable($normalized->message);
            if ($extractedTable) {
                $context['table'] = $extractedTable;
            }
        }

        // Add default context
        $context['error_code'] = $normalized->code;
        $context['error_description'] = MySqlErrorCodes::getDescription($normalized->code);
        $context['table'] = $context['table'] ?? $normalized->table ?? null;
        $context['column'] = $context['column'] ?? $normalized->column ?? null;
        $context['constraint'] = $context['constraint'] ?? $normalized->constraint ?? null;

        return $context;
    }

    /**
     * Generate explanation from rule template
     */
    public function generateExplanation(array $rule, array $context): string
    {
        if (!isset($rule['explanation'])) {
            return 'An error occurred';
        }

        $explanation = $rule['explanation'];

        // Replace placeholders with context values
        foreach ($context as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $explanation = str_replace("{{$key}}", (string)$value, $explanation);
            }
        }

        return $explanation;
    }

    /**
     * Generate migration suggestion from rule
     */
    public function generateMigration(array $rule, array $context): ?string
    {
        if (!isset($rule['migration'])) {
            return null;
        }

        if (is_callable($rule['migration'])) {
            return $rule['migration']($context);
        }

        if (is_string($rule['migration'])) {
            // Replace placeholders
            $migration = $rule['migration'];
            foreach ($context as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $migration = str_replace("{{$key}}", (string)$value, $migration);
                }
            }
            return $migration;
        }

        return null;
    }

    /**
     * Load default rules from file
     */
    public static function loadDefaultRules(): array
    {
        $rulesFile = __DIR__ . '/default_rules.php';
        if (file_exists($rulesFile)) {
            return require $rulesFile;
        }
        return [];
    }
}
