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
            
            // Basic Operations (1002-1099)
            $errorCode === MySqlErrorCodes::ER_CANT_GET_STAT => 'cant_get_stat',
            $errorCode === MySqlErrorCodes::ER_CANT_LOCK => 'cant_lock',
            $errorCode === MySqlErrorCodes::ER_CANT_OPEN_FILE => 'cant_open_file',
            $errorCode === MySqlErrorCodes::ER_FILE_NOT_FOUND => 'file_not_found',
            $errorCode === MySqlErrorCodes::ER_CANT_READ_DIR => 'cant_read_dir',
            $errorCode === MySqlErrorCodes::ER_CHECKREAD => 'checkread_error',
            $errorCode === MySqlErrorCodes::ER_DUP_KEY => 'duplicate_key',
            
            // Database Errors (1045-1099)
            $errorCode === MySqlErrorCodes::ER_ACCESS_DENIED_ERROR => 'access_denied',
            $errorCode === MySqlErrorCodes::ER_NO_DB_ERROR => 'no_database_selected',
            $errorCode === MySqlErrorCodes::ER_CANT_CREATE_FILE => 'cant_create_file',
            $errorCode === MySqlErrorCodes::ER_CANT_CREATE_TABLE => 'cant_create_table',
            $errorCode === MySqlErrorCodes::ER_BAD_NULL_ERROR => 'null_constraint_violation',
            $errorCode === MySqlErrorCodes::ER_NO_DEFAULT_FOR_FIELD => 'no_default_for_field',
            $errorCode === MySqlErrorCodes::ER_BAD_DB_ERROR => 'unknown_database',
            $errorCode === MySqlErrorCodes::ER_BAD_TABLE_ERROR => 'unknown_table',
            $errorCode === MySqlErrorCodes::ER_NON_UNIQ_ERROR => 'ambiguous_column',
            $errorCode === MySqlErrorCodes::ER_DUP_KEYNAME => 'duplicate_key_name',
            $errorCode === MySqlErrorCodes::ER_WRONG_FIELD_SPEC => 'wrong_field_spec',
            $errorCode === MySqlErrorCodes::ER_EMPTY_QUERY => 'empty_query',
            $errorCode === MySqlErrorCodes::ER_NONUNIQ_TABLE => 'non_unique_table',
            
            // Thread and Connection Errors (1094-1099)
            $errorCode === MySqlErrorCodes::ER_NO_SUCH_THREAD => 'no_such_thread',
            $errorCode === MySqlErrorCodes::ER_KILL_DENIED_ERROR => 'kill_denied',
            $errorCode === MySqlErrorCodes::ER_NO_TABLES_USED => 'no_tables_used',
            $errorCode === MySqlErrorCodes::ER_TOO_BIG_SET => 'too_big_set',
            $errorCode === MySqlErrorCodes::ER_NO_UNIQUE_LOGFILE => 'no_unique_logfile',
            
            // Function and Library Errors (1127-1135)
            $errorCode === MySqlErrorCodes::ER_CANT_FIND_DL_ENTRY => 'cant_find_dl_entry',
            $errorCode === MySqlErrorCodes::ER_FUNCTION_NOT_DEFINED => 'function_not_defined',
            $errorCode === MySqlErrorCodes::ER_HOST_IS_BLOCKED => 'host_is_blocked',
            $errorCode === MySqlErrorCodes::ER_HOST_NOT_PRIVILEGED => 'host_not_privileged',
            $errorCode === MySqlErrorCodes::ER_PASSWORD_ANONYMOUS_USER => 'password_anonymous_user',
            
            // Column and Table Errors (1136-1149)
            $errorCode === MySqlErrorCodes::ER_WRONG_VALUE_COUNT_ON_ROW => 'wrong_value_count',
            $errorCode === MySqlErrorCodes::ER_CANT_REOPEN_TABLE => 'cant_reopen_table',
            $errorCode === MySqlErrorCodes::ER_INVALID_USE_OF_NULL => 'invalid_use_of_null',
            $errorCode === MySqlErrorCodes::ER_REGEXP_ERROR => 'regexp_error',
            $errorCode === MySqlErrorCodes::ER_MIX_OF_GROUP_FUNC_AND_FIELDS => 'mix_of_group_func_and_fields',
            $errorCode === MySqlErrorCodes::ER_NONEXISTING_GRANT => 'nonexisting_grant',
            $errorCode === MySqlErrorCodes::ER_TABLEACCESS_DENIED_ERROR => 'table_access_denied',
            $errorCode === MySqlErrorCodes::ER_COLUMNACCESS_DENIED_ERROR => 'column_access_denied',
            $errorCode === MySqlErrorCodes::ER_ILLEGAL_GRANT_FOR_TABLE => 'illegal_grant_for_table',
            $errorCode === MySqlErrorCodes::ER_GRANT_WRONG_HOST_OR_USER => 'grant_wrong_host_or_user',
            $errorCode === MySqlErrorCodes::ER_NONEXISTING_TABLE_GRANT => 'nonexisting_table_grant',
            $errorCode === MySqlErrorCodes::ER_NOT_ALLOWED_COMMAND => 'not_allowed_command',
            $errorCode === MySqlErrorCodes::ER_SYNTAX_ERROR => 'syntax_error_general',
            
            // Connection Errors (1152-1161)
            $errorCode === MySqlErrorCodes::ER_ABORTING_CONNECTION => 'aborting_connection',
            $errorCode === MySqlErrorCodes::ER_NET_PACKET_TOO_LARGE => 'net_packet_too_large',
            $errorCode === MySqlErrorCodes::ER_NET_READ_ERROR_FROM_PIPE => 'net_read_error_from_pipe',
            $errorCode === MySqlErrorCodes::ER_NET_FCNTL_ERROR => 'net_fcntl_error',
            $errorCode === MySqlErrorCodes::ER_NET_PACKETS_OUT_OF_ORDER => 'net_packets_out_of_order',
            $errorCode === MySqlErrorCodes::ER_NET_UNCOMPRESS_ERROR => 'net_uncompress_error',
            $errorCode === MySqlErrorCodes::ER_NET_READ_ERROR => 'net_read_error',
            $errorCode === MySqlErrorCodes::ER_NET_READ_INTERRUPTED => 'net_read_interrupted',
            $errorCode === MySqlErrorCodes::ER_NET_ERROR_ON_WRITE => 'net_error_on_write',
            $errorCode === MySqlErrorCodes::ER_NET_WRITE_INTERRUPTED => 'net_write_interrupted',
            $errorCode === MySqlErrorCodes::ER_TOO_LONG_STRING => 'too_long_string',
            $errorCode === MySqlErrorCodes::ER_TABLE_CANT_HANDLE_BLOB => 'table_cant_handle_blob',
            
            // Command and Option Errors (1234-1299)
            $errorCode === MySqlErrorCodes::ER_CANT_USE_OPTION_HERE => 'cant_use_option_here',
            $errorCode === MySqlErrorCodes::ER_NOT_SUPPORTED_YET => 'not_supported_yet',
            $errorCode === MySqlErrorCodes::ER_MASTER_FATAL_ERROR_READING_BINLOG => 'master_fatal_error_reading_binlog',
            $errorCode === MySqlErrorCodes::ER_JT_VALUE_OUT_OF_RANGE => 'jt_value_out_of_range',
            $errorCode === MySqlErrorCodes::WARN_DATA_TRUNCATED => 'data_truncated',
            $errorCode === MySqlErrorCodes::ER_WARN_USING_OTHER_HANDLER => 'warn_using_other_handler',
            $errorCode === MySqlErrorCodes::ER_CANT_AGGREGATE_2COLLATIONS => 'cant_aggregate_collations',
            $errorCode === MySqlErrorCodes::ER_REVOKE_GRANTS => 'revoke_grants',
            $errorCode === MySqlErrorCodes::ER_UNKNOWN_KEY_CACHE => 'unknown_key_cache',
            $errorCode === MySqlErrorCodes::ER_WARN_HOSTNAME_WONT_WORK => 'warn_hostname_wont_work',
            $errorCode === MySqlErrorCodes::ER_UNKNOWN_STORAGE_ENGINE => 'unknown_storage_engine',
            $errorCode === MySqlErrorCodes::ER_WARN_DEPRECATED_SYNTAX => 'deprecated_syntax',
            $errorCode === MySqlErrorCodes::ER_NON_UPDATABLE_TABLE => 'non_updatable_table',
            $errorCode === MySqlErrorCodes::ER_FEATURE_DISABLED => 'feature_disabled',
            $errorCode === MySqlErrorCodes::ER_NOT_SUPPORTED_AUTH_MODE => 'not_supported_auth_mode',
            $errorCode === MySqlErrorCodes::ER_GET_ERRMSG => 'get_error_msg',
            $errorCode === MySqlErrorCodes::ER_GET_TEMPORARY_ERRMSG => 'get_temporary_error_msg',
            $errorCode === MySqlErrorCodes::ER_UNKNOWN_TIME_ZONE => 'unknown_time_zone',
            $errorCode === MySqlErrorCodes::ER_WARN_INVALID_TIMESTAMP => 'warn_invalid_timestamp',
            $errorCode === MySqlErrorCodes::ER_INVALID_CHARACTER_STRING => 'invalid_character_string',
            
            // Replication and Log Errors (1379-1391)
            $errorCode === MySqlErrorCodes::ER_LOG_PURGE_UNKNOWN_ERR => 'log_purge_unknown_error',
            $errorCode === MySqlErrorCodes::ER_RELAY_LOG_INIT => 'relay_log_init_error',
            $errorCode === MySqlErrorCodes::ER_NO_BINARY_LOGGING => 'no_binary_logging',
            $errorCode === MySqlErrorCodes::ER_RESERVED_SYNTAX => 'reserved_syntax',
            $errorCode === MySqlErrorCodes::ER_PS_MANY_PARAM => 'too_many_params',
            $errorCode === MySqlErrorCodes::ER_KEY_PART_0 => 'key_part_zero',
            
            // Stored Procedure and Data Errors (1406-1411)
            $errorCode === MySqlErrorCodes::ER_DATA_TOO_LONG => 'data_too_long',
            $errorCode === MySqlErrorCodes::ER_SP_BAD_SQLSTATE => 'sp_bad_sqlstate',
            $errorCode === MySqlErrorCodes::ER_STARTUP => 'startup_error',
            $errorCode === MySqlErrorCodes::ER_LOAD_FROM_FIXED_SIZE_ROWS_TO_VAR => 'load_from_fixed_size_rows',
            $errorCode === MySqlErrorCodes::ER_CANT_CREATE_USER_WITH_GRANT => 'cant_create_user_with_grant',
            $errorCode === MySqlErrorCodes::ER_WRONG_VALUE_FOR_TYPE => 'wrong_value_for_type',
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
