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
        // TODO - Add more error types
        return match (true) {
            in_array($errorCode, [
                MySqlErrorCodes::ER_ROW_IS_REFERENCED_2,
                MySqlErrorCodes::ER_NO_REFERENCED_ROW_2,
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
                MySqlErrorCodes::ER_GRP_RPL_FK_WITH_CASCADE_UNSUPPORTED,
                MySqlErrorCodes::ER_SCHEMA_NAME_IN_UPPER_CASE_NOT_ALLOWED_FOR_FK,
                MySqlErrorCodes::ER_TABLE_NAME_IN_UPPER_CASE_NOT_ALLOWED_FOR_FK,
            ]) => 'foreign_key_constraint',
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
