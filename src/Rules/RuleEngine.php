<?php

namespace TusharKhan\DbErrorExplainer\Rules;

use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Lib\MySqlErrorCodes;
use TusharKhan\DbErrorExplainer\Parsing\MessageParser;
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
    public function executeRule(Rule $rule, NormalizedException $normalized): array
    {
        $context = $rule->extract !== null ? ($rule->extract)($normalized) : [];

        // Fall back to parsing the message when the rule found no table
        if (empty($context['table']) && empty($normalized->table)) {
            $extractedTable = new MessageParser()->table($normalized->message);
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
    public function generateExplanation(Rule $rule, array $context): string
    {
        // Rules without their own text fall back to the error code's description
        $template = $rule->explanation ?? ($context['error_description'] ?? 'An error occurred') . '.';

        return $this->fillPlaceholders($template, $context);
    }

    /**
     * Generate migration suggestion from rule
     *
     * @return array{migration_suggestion: string, other_suggestions: string}
     */
    public function generateMigration(Rule $rule, array $context): array
    {
        $migration = $rule->migration;

        if (is_string($migration)) {
            $migration = $this->fillPlaceholders($migration, $context);
        } elseif ($migration !== null) {
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

    /** @return list<Rule> */
    public static function loadDefaultRules(): array
    {
        return array_map(Rule::fromArray(...), require __DIR__ . '/default_rules.php');
    }
}
