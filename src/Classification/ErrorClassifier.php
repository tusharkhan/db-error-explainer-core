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
        return (
        (isset($rule['sqlstate']) && is_array($rule['sqlstate']) && $normalized->sqlState)
            && (in_array($normalized->sqlState, $rule['sqlstate']))
        ) && (isset($rule['error_code']) && is_array($rule['error_code']) && in_array($normalized->code, $rule['error_code']));
    }

    /**
     * Classify error type based on error code
     */
    public function classifyErrorType(int $errorCode): string
    {
        // TODO - Add more error types
        return match (true) {
            in_array($errorCode, [
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
            $errorCode === MySqlErrorCodes::ER_NO_DB_ERROR => 'no_database_selected',
            $errorCode === MySqlErrorCodes::ER_CANT_CREATE_FILE => 'cant_create_file',
            $errorCode === MySqlErrorCodes::ER_CANT_CREATE_TABLE => 'cant_create_table',
            $errorCode === MySqlErrorCodes::ER_NO_REFERENCED_ROW_2 => 'fk_missing_parent',
            $errorCode === MySqlErrorCodes::ER_ROW_IS_REFERENCED_2 => 'fk_cannot_delete_parent',
            $errorCode === MySqlErrorCodes::ER_DUP_ENTRY => 'duplicate_entry',
            $errorCode === MySqlErrorCodes::ER_BAD_FIELD_ERROR => 'unknown_column',
            $errorCode === MySqlErrorCodes::ER_NO_SUCH_TABLE => 'table_not_found',
            $errorCode === MySqlErrorCodes::ER_WARN_DATA_OUT_OF_RANGE => 'out_of_range',
            $errorCode === MySqlErrorCodes::ER_INVALID_DEFAULT => 'invalid_default',
            $errorCode === MySqlErrorCodes::ER_PARSE_ERROR => 'syntax_error',
            $errorCode === MySqlErrorCodes::ER_CANT_CREATE_DB => 'cant_create_database',
            $errorCode === MySqlErrorCodes::ER_DB_CREATE_EXISTS => 'cant_create_database_exists',
            $errorCode === MySqlErrorCodes::ER_DB_DROP_EXISTS => 'cant_drop_database',
            $errorCode === MySqlErrorCodes::ER_DB_DROP_RMDIR => 'cant_drop_database_filesystem_issue',
            $errorCode === MySqlErrorCodes::ER_TABLE_EXISTS_ERROR => 'table_already_exists',
            $errorCode === MySqlErrorCodes::ER_CANT_FIND_SYSTEM_REC => 'record_cant_read',
            $errorCode === MySqlErrorCodes::ER_NAME_BECOMES_EMPTY => 'name_becomes_empty',
            $errorCode === MySqlErrorCodes::ER_AMBIGUOUS_FIELD_TERM => 'ambiguous_column_name',
            $errorCode === MySqlErrorCodes::ER_FOREIGN_SERVER_DOESNT_EXIST => 'foreign_server_doesnt_exist',
            $errorCode === MySqlErrorCodes::ER_FOREIGN_SERVER_EXISTS => 'foreign_server_exists',
            $errorCode === MySqlErrorCodes::ER_ILLEGAL_HA_CREATE_OPTION => 'illegal_ha_create_option',
            $errorCode === MySqlErrorCodes::ER_UNKNOWN_LOCALE => 'unknown_locale',
            $errorCode === MySqlErrorCodes::ER_SLAVE_IGNORE_SERVER_IDS => 'slave_ignore_server_ids',
            $errorCode === MySqlErrorCodes::ER_QUERY_CACHE_DISABLED => 'query_cache_disabled',

            // InnoDB and Storage Errors (1795-1863)
            $errorCode === MySqlErrorCodes::ER_INNODB_FT_LIMIT => 'innodb_fulltext_limit',
            $errorCode === MySqlErrorCodes::ER_INNODB_NO_FT_TEMP_TABLE => 'innodb_no_fulltext_temp_table',
            $errorCode === MySqlErrorCodes::ER_INNODB_FT_WRONG_DOCID_COLUMN => 'innodb_fulltext_wrong_docid_column',
            $errorCode === MySqlErrorCodes::ER_INNODB_FT_WRONG_DOCID_INDEX => 'innodb_fulltext_wrong_docid_index',
            $errorCode === MySqlErrorCodes::ER_TABLE_IN_SYSTEM_TABLESPACE => 'table_in_system_tablespace',
            $errorCode === MySqlErrorCodes::ER_IO_READ_ERROR => 'io_read_error',
            $errorCode === MySqlErrorCodes::ER_IO_WRITE_ERROR => 'io_write_error',
            $errorCode === MySqlErrorCodes::ER_TABLESPACE_MISSING => 'tablespace_missing',
            $errorCode === MySqlErrorCodes::ER_TABLESPACE_EXISTS => 'tablespace_exists',
            $errorCode === MySqlErrorCodes::ER_TABLESPACE_DISCARDED => 'tablespace_discarded',
            $errorCode === MySqlErrorCodes::ER_DUP_UNKNOWN_IN_INDEX => 'duplicate_unknown_in_index',
            $errorCode === MySqlErrorCodes::ER_IDENT_CAUSES_TOO_LONG_PATH => 'ident_causes_too_long_path',
            $errorCode === MySqlErrorCodes::ER_ALTER_OPERATION_NOT_SUPPORTED_REASON_NOT_NULL => 'alter_operation_not_supported_reason_not_null',
            $errorCode === MySqlErrorCodes::ER_MUST_CHANGE_PASSWORD_LOGIN => 'must_change_password_login',
            $errorCode === MySqlErrorCodes::ER_ROW_IN_WRONG_PARTITION => 'row_in_wrong_partition',

            // Advanced Error Codes (3000+)
            $errorCode === MySqlErrorCodes::ER_STD_UNKNOWN_EXCEPTION => 'std_unknown_exception',
            $errorCode === MySqlErrorCodes::ER_GIS_DATA_WRONG_ENDIANESS => 'gis_data_wrong_endianess',
            $errorCode === MySqlErrorCodes::ER_CHANGE_MASTER_PASSWORD_LENGTH => 'change_master_password_length',
            $errorCode === MySqlErrorCodes::ER_USER_LOCK_WRONG_NAME => 'user_lock_wrong_name',
            $errorCode === MySqlErrorCodes::ER_GROUP_REPLICATION_COMMUNICATION_LAYER_JOIN_ERROR => 'group_replication_communication_layer_join_error',
            $errorCode === MySqlErrorCodes::ER_BEFORE_DML_VALIDATION_ERROR => 'before_dml_validation_error',
            $errorCode === MySqlErrorCodes::ER_PREVENTS_VARIABLE_WITHOUT_RBR => 'prevents_variable_without_rbr',

            // Recent Error Codes (3800+)
            $errorCode === MySqlErrorCodes::ER_WARN_DEPRECATED_ZEROFILL => 'deprecated_zerofill',
            $errorCode === MySqlErrorCodes::ER_CLONE_DONOR => 'clone_donor_error',
            $errorCode === MySqlErrorCodes::ER_CLONE_PROTOCOL => 'clone_protocol_error',
            $errorCode === MySqlErrorCodes::ER_CLONE_DONOR_VERSION => 'clone_donor_version_mismatch',
            $errorCode === MySqlErrorCodes::ER_DA_UNKNOWN_ERROR_NUMBER => 'unknown_error_number',
            $errorCode === MySqlErrorCodes::ER_COLUMN_CHANGE_SIZE => 'column_change_size',
            $errorCode === MySqlErrorCodes::ER_REGEXP_INVALID_CAPTURE_GROUP_NAME => 'regexp_invalid_capture_group',
            $errorCode === MySqlErrorCodes::ER_DA_SSL_LIBRARY_ERROR => 'ssl_library_error',
            $errorCode === MySqlErrorCodes::ER_CLIENT_LOCAL_FILES_DISABLED => 'local_files_disabled',
            $errorCode === MySqlErrorCodes::ER_IMP_INCOMPATIBLE_CFG_VERSION => 'incompatible_config_version',
            $errorCode === MySqlErrorCodes::ER_DA_OOM => 'out_of_memory',
            $errorCode === MySqlErrorCodes::ER_DA_UDF_INVALID_ARGUMENT_TO_SET_CHARSET => 'udf_invalid_charset_argument',
            $errorCode === MySqlErrorCodes::ER_MISPLACED_INTO => 'misplaced_into',
            $errorCode === MySqlErrorCodes::ER_USER_ACCESS_DENIED_FOR_USER_ACCOUNT_BLOCKED_BY_PASSWORD_LOCK => 'user_account_blocked',
            $errorCode === MySqlErrorCodes::ER_WARN_DEPRECATED_YEAR_UNSIGNED => 'deprecated_year_unsigned',
            $errorCode === MySqlErrorCodes::ER_CLONE_NETWORK_PACKET => 'clone_network_packet_error',

            default => 'unknown_error'
        };
    }
}
