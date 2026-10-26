<?php

namespace TusharKhan\DbErrorExplainer\Classification;

use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Lib\MySqlErrorCodes;
use TusharKhan\DbErrorExplainer\Rules\Rule;

/**
 * Error Classifier
 * Classifies database errors and selects matching rules
 */
class ErrorClassifier
{
    /** @var list<Rule> */
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

    /** @param array<Rule|array> $rules */
    public function __construct(array $rules)
    {
        $this->rules = array_values(array_map(Rule::from(...), $rules));
    }

    /** @return list<Rule> */
    public function findMatchingRules(NormalizedException $normalized): array
    {
        return array_values(array_filter($this->rules, fn(Rule $rule) => $rule->matches($normalized)));
    }

    public function getBestMatch(NormalizedException $normalized): ?Rule
    {
        $matches = $this->findMatchingRules($normalized);

        // Several rules can share a code; prefer the one whose message_regex fits.
        return array_find($matches, fn(Rule $rule) => $rule->matchesMessage($normalized->message))
            ?? $matches[0] ?? null;
    }

    // Used when no rule fully matched: fall back to any rule listing this code.
    public function classifyErrorType(int $errorCode): string
    {
        $rule = array_find($this->rules, fn(Rule $rule) => $rule->handlesCode($errorCode));

        return match (true) {
            $rule !== null => $rule->id,
            in_array($errorCode, self::FOREIGN_KEY_CODES, true) => 'foreign_key_constraint',
            $errorCode === MySqlErrorCodes::ER_GET_ERRMSG => 'get_error_msg',
            $errorCode === MySqlErrorCodes::ER_GET_TEMPORARY_ERRMSG => 'get_temporary_error_msg',
            default => 'unknown_error',
        };
    }
}
