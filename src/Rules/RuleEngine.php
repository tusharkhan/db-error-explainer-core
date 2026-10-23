<?php

namespace TusharKhan\DbErrorExplainer\Rules;

use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Lib\MySqlErrorCodes;
use TusharKhan\DbErrorExplainer\Lib\Helpers;
use TusharKhan\DbErrorExplainer\Migration\MigrationSuggester;

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
        $context['constraint'] = $context['constraint'] ?? $normalized->constraint;

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

        return $this->fillPlaceholders($rule['explanation'], $context);
    }

    /**
     * Generate migration suggestion from rule
     *
     * @return array{migration_suggestion: string, other_suggestions: string}
     */
    public function generateMigration(array $rule, array $context): array
    {
        $migration = $rule['migration'] ?? null;

        if (is_string($migration)) {
            $migration = $this->fillPlaceholders($migration, $context);
        } elseif (is_callable($migration)) {
            $migration = $migration($context);
        }

        return self::normalizeSuggestion($migration);
    }

    // Rules may return a string, an array or nothing; always hand back the same shape.
    public static function normalizeSuggestion(mixed $suggestion): array
    {
        if (is_array($suggestion)) {
            return [
                'migration_suggestion' => (string) ($suggestion['migration_suggestion'] ?? ''),
                'other_suggestions' => (string) ($suggestion['other_suggestions'] ?? ''),
            ];
        }

        return [
            'migration_suggestion' => is_string($suggestion) ? $suggestion : '',
            'other_suggestions' => '',
        ];
    }

    private function fillPlaceholders(string $template, array $context): string
    {
        foreach ($context as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $template = str_replace("{{$key}}", (string) $value, $template);
            }
        }

        return $template;
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
