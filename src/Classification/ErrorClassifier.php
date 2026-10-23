<?php

namespace TusharKhan\DbErrorExplainer\Classification;

use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Lib\MySqlErrorCodes;

/**
 * Error Classifier
 * Classifies database errors and selects matching rules
 */
class ErrorClassifier
{
    private array $rules;

    // FK errors without a dedicated rule still get a useful type.
    private const FOREIGN_KEY_CODES = [
        MySqlErrorCodes::ER_FOREIGN_KEY_ON_PARTITIONED,
        MySqlErrorCodes::ER_DROP_INDEX_FK,
        MySqlErrorCodes::ER_FOREIGN_DUPLICATE_KEY_OLD_UNUSED,
        MySqlErrorCodes::ER_TRUNCATE_ILLEGAL_FK,
        MySqlErrorCodes::ER_TABLE_IN_FK_CHECK,
        MySqlErrorCodes::ER_PARTITION_EXCHANGE_FOREIGN_KEY,
        MySqlErrorCodes::ER_FOREIGN_DUPLICATE_KEY_WITH_CHILD_INFO,
        MySqlErrorCodes::ER_FOREIGN_DUPLICATE_KEY_WITHOUT_CHILD_INFO,
        MySqlErrorCodes::ER_DISCARD_FK_CHECKS_RUNNING,
        MySqlErrorCodes::ER_FK_NO_INDEX_CHILD,
        MySqlErrorCodes::ER_FK_NO_INDEX_PARENT,
        MySqlErrorCodes::ER_FK_FAIL_ADD_SYSTEM,
        MySqlErrorCodes::ER_FK_INCORRECT_OPTION,
        MySqlErrorCodes::ER_FK_DUP_NAME,
        MySqlErrorCodes::ER_FK_COLUMN_CANNOT_DROP,
        MySqlErrorCodes::ER_FK_COLUMN_CANNOT_DROP_CHILD,
        MySqlErrorCodes::ER_FK_COLUMN_NOT_NULL,
        MySqlErrorCodes::ER_FK_COLUMN_CANNOT_CHANGE,
        MySqlErrorCodes::ER_FK_COLUMN_CANNOT_CHANGE_CHILD,
        MySqlErrorCodes::ER_ALTER_OPERATION_NOT_SUPPORTED_REASON_FK_RENAME,
        MySqlErrorCodes::ER_ALTER_OPERATION_NOT_SUPPORTED_REASON_FK_CHECK,
        MySqlErrorCodes::ER_FK_DEPTH_EXCEEDED,
        MySqlErrorCodes::ER_WRONG_FK_OPTION_FOR_GENERATED_COLUMN,
        MySqlErrorCodes::ER_CANNOT_ADD_FOREIGN_BASE_COL_VIRTUAL,
        MySqlErrorCodes::ER_CANNOT_ADD_FOREIGN_BASE_COL_STORED,
        MySqlErrorCodes::ER_FK_CANNOT_DROP_PARENT,
        MySqlErrorCodes::ER_FK_CANNOT_USE_VIRTUAL_COLUMN,
        MySqlErrorCodes::ER_FK_NO_COLUMN_PARENT,
        MySqlErrorCodes::ER_FK_CANNOT_CHANGE_ENGINE,
        MySqlErrorCodes::ER_FK_INCOMPATIBLE_COLUMNS,
        MySqlErrorCodes::ER_CHECK_CONSTRAINT_CLAUSE_USING_FK_REFER_ACTION_COLUMN,
        MySqlErrorCodes::ER_ALTER_CONSTRAINT_ENFORCEMENT_NOT_SUPPORTED,
        MySqlErrorCodes::ER_FOREIGN_KEY_WITH_ATOMIC_CREATE_SELECT,
    ];

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

        // Several rules can share a code; prefer the one whose message_regex fits.
        return array_find(
            $matches,
            fn(array $rule) => isset($rule['message_regex']) && preg_match($rule['message_regex'], $normalized->message)
        ) ?? $matches[0] ?? null;
    }

    /**
     * Check if a rule matches the normalized exception
     */
    private function ruleMatches(array $rule, NormalizedException $normalized): bool
    {
        // Check SQLSTATE if defined in rule
        if (isset($rule['sqlstate'])) {
            $sqlStateMatches = is_array($rule['sqlstate'])
                ? in_array($normalized->sqlState, $rule['sqlstate'])
                : $normalized->sqlState === $rule['sqlstate'];
            
            if (!$sqlStateMatches) {
                return false;
            }
        }

        // Check error code if defined in rule
        if (isset($rule['error_code'])) {
            $codeMatches = is_array($rule['error_code'])
                ? in_array($normalized->code, $rule['error_code'])
                : $normalized->code === $rule['error_code'];
            
            if (!$codeMatches) {
                return false;
            }
        }

        return true;
    }

    // Used when no rule fully matched: fall back to any rule listing this code.
    public function classifyErrorType(int $errorCode): string
    {
        $rule = array_find(
            $this->rules,
            fn(array $rule) => isset($rule['id']) && in_array($errorCode, (array) ($rule['error_code'] ?? []))
        );

        return match (true) {
            $rule !== null => $rule['id'],
            in_array($errorCode, self::FOREIGN_KEY_CODES, true) => 'foreign_key_constraint',
            $errorCode === MySqlErrorCodes::ER_GET_ERRMSG => 'get_error_msg',
            $errorCode === MySqlErrorCodes::ER_GET_TEMPORARY_ERRMSG => 'get_temporary_error_msg',
            default => 'unknown_error',
        };
    }
}
