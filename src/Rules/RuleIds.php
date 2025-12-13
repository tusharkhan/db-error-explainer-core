<?php

namespace TusharKhan\DbErrorExplainer\Rules;

/**
 * Rule ID Constants
 *
 * This final class contains all rule identifiers used in the default_rules.php file.
 * Using constants ensures type safety, IDE autocomplete support, and prevents typos.
 *
 * @package TusharKhan\DbErrorExplainer\Rules
 */
final class RuleIds
{
    // Table and Database Structure Errors
    public const TABLE_ALREADY_EXISTS = 'table_already_exists';
    public const NO_DATABASE_SELECTED = 'no_database_selected';
    public const CANT_CREATE_FILE = 'cant_create_file';
    public const CANT_CREATE_TABLE = 'cant_create_table';
    public const CANT_CREATE_DATABASE = 'cant_create_database';
    public const CANT_CREATE_DATABASE_EXISTS = 'cant_create_database_exists';
    public const CANT_DROP_DATABASE = 'cant_drop_database';
    public const CANT_DROP_DATABASE_FILESYSTEM_ISSUE = 'cant_drop_database_filesystem_issue';
    public const UNKNOWN_DATABASE = 'unknown_database';
    public const UNKNOWN_TABLE = 'unknown_table';
    public const TABLE_NOT_FOUND = 'table_not_found';
    public const NON_UNIQUE_TABLE = 'non_unique_table';
    public const NO_TABLES_USED = 'no_tables_used';
    public const CANT_REOPEN_TABLE = 'cant_reopen_table';
    public const TABLE_CANT_HANDLE_BLOB = 'table_cant_handle_blob';
    public const NON_UPDATABLE_TABLE = 'non_updatable_table';
    public const TABLE_IN_SYSTEM_TABLESPACE = 'table_in_system_tablespace';

    // File and I/O Errors
    public const CANT_GET_STAT = 'cant_get_stat';
    public const CANT_LOCK = 'cant_lock';
    public const CANT_OPEN_FILE = 'cant_open_file';
    public const FILE_NOT_FOUND = 'file_not_found';
    public const CANT_READ_DIR = 'cant_read_dir';
    public const CHECKREAD_ERROR = 'checkread_error';
    public const IO_READ_ERROR = 'io_read_error';
    public const IO_WRITE_ERROR = 'io_write_error';

    // Key and Index Errors
    public const DUPLICATE_KEY = 'duplicate_key';
    public const DUPLICATE_KEY_NAME = 'duplicate_key_name';
    public const DUPLICATE_ENTRY = 'duplicate_entry';
    public const DUPLICATE_UNKNOWN_IN_INDEX = 'duplicate_unknown_in_index';
    public const UNKNOWN_KEY_CACHE = 'unknown_key_cache';
    public const KEY_PART_ZERO = 'key_part_zero';

    // Column Errors
    public const AMBIGUOUS_COLUMN = 'ambiguous_column';
    public const AMBIGUOUS_COLUMN_NAME = 'ambiguous_column_name';
    public const WRONG_FIELD_SPEC = 'wrong_field_spec';
    public const UNKNOWN_COLUMN = 'unknown_column';
    public const INVALID_USE_OF_NULL = 'invalid_use_of_null';
    public const NULL_CONSTRAINT_VIOLATION = 'null_constraint_violation';
    public const NO_DEFAULT_FOR_FIELD = 'no_default_for_field';
    public const ALTER_OPERATION_NOT_SUPPORTED_REASON_NOT_NULL = 'alter_operation_not_supported_reason_not_null';
    public const COLUMN_CHANGE_SIZE = 'column_change_size';
    public const COLUMN_ACCESS_DENIED = 'column_access_denied';

    // Access Control and Permissions
    public const ACCESS_DENIED = 'access_denied';
    public const TABLE_ACCESS_DENIED = 'table_access_denied';
    public const ILLEGAL_GRANT_FOR_TABLE = 'illegal_grant_for_table';
    public const GRANT_WRONG_HOST_OR_USER = 'grant_wrong_host_or_user';
    public const NONEXISTING_TABLE_GRANT = 'nonexisting_table_grant';
    public const NONEXISTING_GRANT = 'nonexisting_grant';
    public const REVOKE_GRANTS = 'revoke_grants';
    public const NOT_ALLOWED_COMMAND = 'not_allowed_command';
    public const HOST_IS_BLOCKED = 'host_is_blocked';
    public const HOST_NOT_PRIVILEGED = 'host_not_privileged';
    public const PASSWORD_ANONYMOUS_USER = 'password_anonymous_user';
    public const CANT_CREATE_USER_WITH_GRANT = 'cant_create_user_with_grant';
    public const NOT_SUPPORTED_AUTH_MODE = 'not_supported_auth_mode';
    public const MUST_CHANGE_PASSWORD_LOGIN = 'must_change_password_login';
    public const USER_ACCOUNT_BLOCKED = 'user_account_blocked';

    // Query Syntax and Execution Errors
    public const EMPTY_QUERY = 'empty_query';
    public const SYNTAX_ERROR_GENERAL = 'syntax_error_general';
    public const SYNTAX_ERROR = 'syntax_error';
    public const WRONG_VALUE_COUNT = 'wrong_value_count';
    public const MIX_OF_GROUP_FUNC_AND_FIELDS = 'mix_of_group_func_and_fields';
    public const TOO_BIG_SET = 'too_big_set';
    public const TOO_LONG_STRING = 'too_long_string';
    public const REGEXP_ERROR = 'regexp_error';
    public const REGEXP_INVALID_CAPTURE_GROUP = 'regexp_invalid_capture_group';
    public const RESERVED_SYNTAX = 'reserved_syntax';
    public const DEPRECATED_SYNTAX = 'deprecated_syntax';
    public const DEPRECATED_ZEROFILL = 'deprecated_zerofill';
    public const DEPRECATED_YEAR_UNSIGNED = 'deprecated_year_unsigned';

    // Thread and Process Errors
    public const NO_SUCH_THREAD = 'no_such_thread';
    public const KILL_DENIED = 'kill_denied';
    public const ABORTING_CONNECTION = 'aborting_connection';

    // Function and Procedure Errors
    public const CANT_FIND_DL_ENTRY = 'cant_find_dl_entry';
    public const FUNCTION_NOT_DEFINED = 'function_not_defined';
    public const SP_BAD_SQLSTATE = 'sp_bad_sqlstate';
    public const UDF_INVALID_CHARSET_ARGUMENT = 'udf_invalid_charset_argument';

    // Network and Communication Errors
    public const NET_PACKET_TOO_LARGE = 'net_packet_too_large';
    public const NET_READ_ERROR_FROM_PIPE = 'net_read_error_from_pipe';
    public const NET_FCNTL_ERROR = 'net_fcntl_error';
    public const NET_PACKETS_OUT_OF_ORDER = 'net_packets_out_of_order';
    public const NET_UNCOMPRESS_ERROR = 'net_uncompress_error';
    public const NET_READ_ERROR = 'net_read_error';
    public const NET_READ_INTERRUPTED = 'net_read_interrupted';
    public const NET_ERROR_ON_WRITE = 'net_error_on_write';
    public const NET_WRITE_INTERRUPTED = 'net_write_interrupted';

    // Data Type and Value Errors
    public const CANT_USE_OPTION_HERE = 'cant_use_option_here';
    public const NOT_SUPPORTED_YET = 'not_supported_yet';
    public const JT_VALUE_OUT_OF_RANGE = 'jt_value_out_of_range';
    public const DATA_TRUNCATED = 'data_truncated';
    public const DATA_TOO_LONG = 'data_too_long';
    public const OUT_OF_RANGE = 'out_of_range';
    public const INVALID_DEFAULT = 'invalid_default';
    public const WRONG_VALUE_FOR_TYPE = 'wrong_value_for_type';
    public const INVALID_CHARACTER_STRING = 'invalid_character_string';
    public const GIS_DATA_WRONG_ENDIANESS = 'gis_data_wrong_endianess';

    // Foreign Key and Constraint Errors
    public const FK_MISSING_PARENT = 'fk_missing_parent';
    public const FK_CANNOT_DELETE_PARENT = 'fk_cannot_delete_parent';
    public const BEFORE_DML_VALIDATION_ERROR = 'before_dml_validation_error';
    public const ROW_IN_WRONG_PARTITION = 'row_in_wrong_partition';

    // Collation and Character Set Errors
    public const CANT_AGGREGATE_COLLATIONS = 'cant_aggregate_collations';
    public const WARN_USING_OTHER_HANDLER = 'warn_using_other_handler';

    // Storage Engine Errors
    public const UNKNOWN_STORAGE_ENGINE = 'unknown_storage_engine';
    public const ILLEGAL_HA_CREATE_OPTION = 'illegal_ha_create_option';
    public const WARN_HOSTNAME_WONT_WORK = 'warn_hostname_wont_work';

    // System and Configuration Errors
    public const MASTER_FATAL_ERROR_READING_BINLOG = 'master_fatal_error_reading_binlog';
    public const FEATURE_DISABLED = 'feature_disabled';
    public const UNKNOWN_TIME_ZONE = 'unknown_time_zone';
    public const WARN_INVALID_TIMESTAMP = 'warn_invalid_timestamp';
    public const LOG_PURGE_UNKNOWN_ERROR = 'log_purge_unknown_error';
    public const RELAY_LOG_INIT_ERROR = 'relay_log_init_error';
    public const NO_BINARY_LOGGING = 'no_binary_logging';
    public const STARTUP_ERROR = 'startup_error';
    public const LOAD_FROM_FIXED_SIZE_ROWS = 'load_from_fixed_size_rows';
    public const TOO_MANY_PARAMS = 'too_many_params';
    public const NO_UNIQUE_LOGFILE = 'no_unique_logfile';
    public const RECORD_CANT_READ = 'record_cant_read';
    public const NAME_BECOMES_EMPTY = 'name_becomes_empty';
    public const QUERY_CACHE_DISABLED = 'query_cache_disabled';
    public const PREVENTS_VARIABLE_WITHOUT_RBR = 'prevents_variable_without_rbr';
    public const OUT_OF_MEMORY = 'out_of_memory';
    public const INCOMPATIBLE_CONFIG_VERSION = 'incompatible_config_version';

    // Foreign Server Errors
    public const FOREIGN_SERVER_DOESNT_EXIST = 'foreign_server_doesnt_exist';
    public const FOREIGN_SERVER_EXISTS = 'foreign_server_exists';
    public const UNKNOWN_LOCALE = 'unknown_locale';
    public const SLAVE_IGNORE_SERVER_IDS = 'slave_ignore_server_ids';

    // InnoDB and Storage Errors
    public const INNODB_FULLTEXT_LIMIT = 'innodb_fulltext_limit';
    public const INNODB_NO_FULLTEXT_TEMP_TABLE = 'innodb_no_fulltext_temp_table';
    public const INNODB_FULLTEXT_WRONG_DOCID_COLUMN = 'innodb_fulltext_wrong_docid_column';
    public const INNODB_FULLTEXT_WRONG_DOCID_INDEX = 'innodb_fulltext_wrong_docid_index';
    public const TABLESPACE_MISSING = 'tablespace_missing';
    public const TABLESPACE_EXISTS = 'tablespace_exists';
    public const TABLESPACE_DISCARDED = 'tablespace_discarded';
    public const IDENT_CAUSES_TOO_LONG_PATH = 'ident_causes_too_long_path';

    // Exception and Error Handling
    public const STD_UNKNOWN_EXCEPTION = 'std_unknown_exception';
    public const UNKNOWN_ERROR_NUMBER = 'unknown_error_number';

    // Security and SSL Errors
    public const SSL_LIBRARY_ERROR = 'ssl_library_error';
    public const USER_LOCK_WRONG_NAME = 'user_lock_wrong_name';
    public const CHANGE_MASTER_PASSWORD_LENGTH = 'change_master_password_length';

    // Local File and External Access
    public const LOCAL_FILES_DISABLED = 'local_files_disabled';
    public const MISPLACED_INTO = 'misplaced_into';

    // Group Replication and Cloning
    public const GROUP_REPLICATION_COMMUNICATION_LAYER_JOIN_ERROR = 'group_replication_communication_layer_join_error';
    public const CLONE_DONOR_ERROR = 'clone_donor_error';
    public const CLONE_PROTOCOL_ERROR = 'clone_protocol_error';
    public const CLONE_DONOR_VERSION_MISMATCH = 'clone_donor_version_mismatch';
    public const CLONE_NETWORK_PACKET_ERROR = 'clone_network_packet_error';

    /**
     * Private constructor to prevent instantiation
     */
    private function __construct()
    {
        // This class should never be instantiated
    }

    /**
     * Get all rule IDs as an array
     *
     * @return array<string>
     */
    public static function all(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }

    /**
     * Check if a rule ID exists
     *
     * @param string $ruleId
     * @return bool
     */
    public static function exists(string $ruleId): bool
    {
        return in_array($ruleId, self::all(), true);
    }
}

