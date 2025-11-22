<?php

namespace TusharKhan\DbErrorExplainer\Classification;

use TusharKhan\DbErrorExplainer\DTO\NormalizedException;

/**
 * Error Classifier
 * Classifies database errors and selects matching rules
 */
class ErrorClassifier
{
    private array $rules;

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * Find matching rules for the given error
     */
    public function findMatchingRules(NormalizedException $normalized): array
    {
        $matchingRules = [];

        foreach ($this->rules as $rule) {
            if ($this->ruleMatches($rule, $normalized)) {
                $matchingRules[] = $rule;
            }
        }

        return $matchingRules;
    }

    /**
     * Get the best matching rule
     */
    public function getBestMatch(NormalizedException $normalized): ?array
    {
        $matches = $this->findMatchingRules($normalized);
        return !empty($matches) ? $matches[0] : null;
    }

    /**
     * Check if a rule matches the normalized exception
     */
    private function ruleMatches(array $rule, NormalizedException $normalized): bool
    {
        // Check error code
        if (isset($rule['error_code']) && is_array($rule['error_code'])) {
            if (!in_array($normalized->code, $rule['error_code'])) {
                return false;
            }
        }

        // Check SQLSTATE
        if (isset($rule['sqlstate']) && is_array($rule['sqlstate']) && $normalized->sqlState) {
            if (!in_array($normalized->sqlState, $rule['sqlstate'])) {
                return false;
            }
        }

        // Check message regex
        if (isset($rule['message_regex'])) {
            if (!preg_match($rule['message_regex'], $normalized->message)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Classify error type based on error code
     */
    public function classifyErrorType(int $errorCode): string
    {
        return match (true) {
            in_array($errorCode, [1451, 1452]) => 'foreign_key_constraint',
            in_array($errorCode, [1062, 1022, 1586, 1859]) => 'duplicate_entry',
            in_array($errorCode, [1146, 1051]) => 'table_not_found',
            in_array($errorCode, [1054, 1110]) => 'column_not_found',
            in_array($errorCode, [1364, 1048]) => 'null_constraint',
            $errorCode === 1064 => 'syntax_error',
            $errorCode === 1049 => 'database_not_found',
            $errorCode === 1406 => 'data_too_long',
            in_array($errorCode, [1265, 1264]) => 'data_truncated',
            default => 'unknown_error'
        };
    }
}
