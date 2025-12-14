<?php

use TusharKhan\DbErrorExplainer\Lib\MySqlErrorCodes;
use TusharKhan\DbErrorExplainer\Lib\MySqlStates;
use TusharKhan\DbErrorExplainer\Lib\RuleIds;
use TusharKhan\DbErrorExplainer\Migration\MigrationSuggester;

/**
 * Default error classification rules for DB Error Explainer
 *
 * Each rule contains:
 * - id: unique identifier
 * - error_code: array of MySQL error codes to match
 * - sqlstate: optional SQLSTATE codes
 * - message_regex: regex pattern to match error message
 * - extract: closure to extract context from normalized exception
 * - explanation: human-readable explanation template
 * - migration: closure to generate Laravel Blueprint migration code
 */

// TODO: Add more rules for other error types

return [
    [
        'id' => RuleIds::TABLE_ALREADY_EXISTS,
        'error_code' => [MySqlErrorCodes::ER_TABLE_EXISTS_ERROR],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION_BASE_TABLE_OR_VIEW_ALREADY_EXIST],
        'message_regex' => '/Table \'([^\']+)\' already exists/i',
        'extract' => function($normalized) {
            return ['table' => $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Table already exists: The table "{table}" already exists. You may have a typo or the table needs to be deleted via migration.',
        'migration' => function($ctx) {
            $message = "---\n// If this table is incorrect, remove it:\nDROP TABLE IF EXISTS `{$ctx['table']}`";
            $message .= "\n\n---\n// Use if statement to create the table only if it doesn't exist:\nCREATE TABLE IF NOT EXISTS `{$ctx['table']}` (id INT PRIMARY KEY AUTO_INCREMENT); \n\n -- Add columns as needed";

            return $message;
        }
    ],
    [
        'id' => RuleIds::NO_DATABASE_SELECTED,
        'error_code' => [MySqlErrorCodes::ER_NO_DB_ERROR],
        'sqlstate' => [MySqlStates::INVALID_CATALOG_NAME],
        'message_regex' => '/No database selected/i',
        'extract' => function($normalized) {
            // extract database name from error message
            preg_match("/database\s+'([a-zA-Z0-9_]+)'/i", $normalized->message, $matches);
            return ['database' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'No database selected: First you need to select a database before running any queries.',
        'migration' => function($ctx) {
            return 'USE database_name;';
        }
    ],
    [
        'id' => RuleIds::CANT_CREATE_FILE,
        'error_code' => MysqlErrorCodes::ER_CANT_CREATE_FILE,
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Can\'t create file/i',
        'extract' => function($normalized) {
            //extract file name from error message
            preg_match('/(CREATE TEMPORARY FILE|CREATE TEMPORARY TABLE) `([^`]+)`/', $normalized->message, $matches);
            return ['file' => $matches[2] ?? 'unknown'];
        },
        'explanation' => 'Can\'t create file: There was an error creating a temporary file. Check your disk space or permissions.',
        'migration' => function($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::CANT_CREATE_TABLE,
        'error_code' => MysqlErrorCodes::ER_CANT_CREATE_TABLE,
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Can\'t create table/i',
        'extract' => function($normalized) {
            // extract table name from error message
            preg_match('/CREATE TABLE `([^`]+)`/', $normalized->message, $matches);
            return ['table' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Can\'t create table: There was an error creating the table "{table}". Check your table name or permissions.',
        'migration' => function($ctx) {
            return 'CREATE TABLE `{$ctx["table"]}`.';
        }
    ],
    
    // Basic Operations Errors (1002-1099)
    [
        'id' => RuleIds::CANT_GET_STAT,
        'error_code' => [MySqlErrorCodes::ER_CANT_GET_STAT],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Can\'t get status of/i',
        'extract' => function($normalized) {
            preg_match("/Can't get status of '([^']+)'/", $normalized->message, $matches);
            return ['file' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Cannot get status: MySQL cannot get the status of file or directory "{file}". Check if the file exists and has proper permissions.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::CANT_LOCK,
        'error_code' => [MySqlErrorCodes::ER_CANT_LOCK],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Can\'t lock file/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Cannot lock file: MySQL cannot acquire a lock on a file. This may be due to another process holding the lock or permission issues.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::CANT_OPEN_FILE,
        'error_code' => [MySqlErrorCodes::ER_CANT_OPEN_FILE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Can\'t open file/i',
        'extract' => function($normalized) {
            preg_match("/Can't open file[: ]*'?([^']+)'?/", $normalized->message, $matches);
            return ['file' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Cannot open file: MySQL cannot open the file "{file}". Check if the file exists and has proper read permissions.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::FILE_NOT_FOUND,
        'error_code' => [MySqlErrorCodes::ER_FILE_NOT_FOUND],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/File .* not found/i',
        'extract' => function($normalized) {
            preg_match("/File '?([^']+)'? not found/", $normalized->message, $matches);
            return ['file' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'File not found: The file "{file}" was not found. Ensure the file exists in the expected location.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::CANT_READ_DIR,
        'error_code' => [MySqlErrorCodes::ER_CANT_READ_DIR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Can\'t read dir of/i',
        'extract' => function($normalized) {
            preg_match("/Can't read dir of '([^']+)'/", $normalized->message, $matches);
            return ['directory' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Cannot read directory: MySQL cannot read the directory "{directory}". Check directory permissions.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::CHECKREAD_ERROR,
        'error_code' => [MySqlErrorCodes::ER_CHECKREAD],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Record has wrong checksum/i',
        'extract' => function($normalized) {
            return ['table' => $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Checksum error: A record in table "{table}" has a wrong checksum, indicating data corruption. Run REPAIR TABLE to fix.',
        'migration' => function($ctx) { return "REPAIR TABLE `{$ctx['table']}`;"; }
    ],
    [
        'id' => RuleIds::DUPLICATE_KEY,
        'error_code' => [MySqlErrorCodes::ER_DUP_KEY],
        'sqlstate' => [MySqlStates::CONSTANT_VIOLATION],
        'message_regex' => '/Can\'t write; duplicate key in table/i',
        'extract' => function($normalized) {
            return ['table' => $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Duplicate key: Cannot write to table "{table}" because a duplicate key was found. Check for unique constraint violations.',
        'migration' => function($ctx) { return ''; }
    ],
    
    // Access and Authentication Errors
    [
        'id' => RuleIds::ACCESS_DENIED,
        'error_code' => [MySqlErrorCodes::ER_ACCESS_DENIED_ERROR],
        'sqlstate' => [MySqlStates::INVALID_AUTHORIZATION],
        'message_regex' => '/Access denied for user/i',
        'extract' => function($normalized) {
            preg_match("/Access denied for user '([^']+)'@'([^']+)'/", $normalized->message, $matches);
            return [
                'user' => $matches[1] ?? 'unknown',
                'host' => $matches[2] ?? 'unknown'
            ];
        },
        'explanation' => 'Access denied: User "{user}"@"{host}" is not authorized to access the database. Check your credentials and user permissions.',
        'migration' => function($ctx) { 
            return "-- Grant access to user:\nGRANT ALL PRIVILEGES ON database_name.* TO '{$ctx['user']}'@'{$ctx['host']}';"; 
        }
    ],
    [
        'id' => RuleIds::UNKNOWN_DATABASE,
        'error_code' => [MySqlErrorCodes::ER_BAD_DB_ERROR],
        'sqlstate' => [MySqlStates::INVALID_CATALOG_NAME],
        'message_regex' => '/Unknown database/i',
        'extract' => function($normalized) {
            preg_match("/Unknown database '([^']+)'/", $normalized->message, $matches);
            return ['database' => $matches[1] ?? $normalized->database ?? 'unknown'];
        },
        'explanation' => 'Unknown database: The database "{database}" does not exist. Create it first or check for typos in the database name.',
        'migration' => function($ctx) { 
            return "CREATE DATABASE `{$ctx['database']}`;"; 
        }
    ],
    [
        'id' => RuleIds::UNKNOWN_TABLE,
        'error_code' => [MySqlErrorCodes::ER_BAD_TABLE_ERROR],
        'sqlstate' => [MySqlStates::TABLE_OR_VIEW_NOT_FOUND],
        'message_regex' => '/Unknown table/i',
        'extract' => function($normalized) {
            preg_match("/Unknown table '([^']+)'/", $normalized->message, $matches);
            return ['table' => $matches[1] ?? $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Unknown table: The table "{table}" does not exist or was not found. Check the table name or create it.',
        'migration' => function($ctx) { 
            return "CREATE TABLE `{$ctx['table']}` (id INT PRIMARY KEY AUTO_INCREMENT);"; 
        }
    ],
    [
        'id' => RuleIds::AMBIGUOUS_COLUMN,
        'error_code' => [MySqlErrorCodes::ER_NON_UNIQ_ERROR],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Column .* in .* is ambiguous/i',
        'extract' => function($normalized) {
            preg_match("/Column '([^']+)'/", $normalized->message, $matches);
            return ['column' => $matches[1] ?? $normalized->column ?? 'unknown'];
        },
        'explanation' => 'Ambiguous column: Column "{column}" exists in multiple tables in the query. Qualify it with the table name (e.g., table.column).',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::DUPLICATE_KEY_NAME,
        'error_code' => [MySqlErrorCodes::ER_DUP_KEYNAME],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Duplicate key name/i',
        'extract' => function($normalized) {
            preg_match("/Duplicate key name '([^']+)'/", $normalized->message, $matches);
            return ['key' => $matches[1] ?? 'unknown', 'table' => $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Duplicate key name: An index or key named "{key}" already exists. Use a different name for the new key.',
        'migration' => function($ctx) { 
            return "-- Drop existing key first:\nALTER TABLE `{$ctx['table']}` DROP INDEX `{$ctx['key']}`;"; 
        }
    ],
    [
        'id' => RuleIds::WRONG_FIELD_SPEC,
        'error_code' => [MySqlErrorCodes::ER_WRONG_FIELD_SPEC],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Incorrect column specifier/i',
        'extract' => function($normalized) {
            preg_match("/Incorrect column specifier for column '([^']+)'/", $normalized->message, $matches);
            return ['column' => $matches[1] ?? $normalized->column ?? 'unknown'];
        },
        'explanation' => 'Wrong field specification: The column "{column}" has an incorrect specifier. Check the column definition syntax.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::EMPTY_QUERY,
        'error_code' => [MySqlErrorCodes::ER_EMPTY_QUERY],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Query was empty/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Empty query: The SQL query is empty. Provide a valid SQL statement.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NON_UNIQUE_TABLE,
        'error_code' => [MySqlErrorCodes::ER_NONUNIQ_TABLE],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Not unique table\/alias/i',
        'extract' => function($normalized) {
            preg_match("/Not unique table\/alias: '([^']+)'/", $normalized->message, $matches);
            return ['table' => $matches[1] ?? $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Non-unique table: The table or alias "{table}" is used more than once without a unique alias. Use different aliases for each occurrence.',
        'migration' => function($ctx) { return ''; }
    ],
    
    // Thread and Connection Errors (1094-1099)
    [
        'id' => RuleIds::NO_SUCH_THREAD,
        'error_code' => [MySqlErrorCodes::ER_NO_SUCH_THREAD],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Unknown thread id/i',
        'extract' => function($normalized) {
            preg_match('/Unknown thread id: (\d+)/', $normalized->message, $matches);
            return ['thread_id' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Unknown thread: Thread ID "{thread_id}" does not exist. The thread may have already terminated.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::KILL_DENIED,
        'error_code' => [MySqlErrorCodes::ER_KILL_DENIED_ERROR],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/You are not owner of thread/i',
        'extract' => function($normalized) {
            preg_match('/thread (\d+)/', $normalized->message, $matches);
            return ['thread_id' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Kill denied: You do not have permission to kill thread "{thread_id}". Only the SUPER privilege or the thread owner can kill it.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NO_TABLES_USED,
        'error_code' => [MySqlErrorCodes::ER_NO_TABLES_USED],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/No tables used/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'No tables used: The query does not reference any tables. Add a FROM clause if you need to select from a table.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::TOO_BIG_SET,
        'error_code' => [MySqlErrorCodes::ER_TOO_BIG_SET],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Too big SET/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Too big SET: The SET column contains too many values. MySQL limits SET columns to 64 members.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NO_UNIQUE_LOGFILE,
        'error_code' => [MySqlErrorCodes::ER_NO_UNIQUE_LOGFILE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Can\'t generate a unique log-filename/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'No unique logfile: MySQL cannot generate a unique log filename. Check your log file configuration.',
        'migration' => function($ctx) { return ''; }
    ],
    
    // Function and Library Errors (1127-1135)
    [
        'id' => RuleIds::CANT_FIND_DL_ENTRY,
        'error_code' => [MySqlErrorCodes::ER_CANT_FIND_DL_ENTRY],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Can\'t find symbol .* in library/i',
        'extract' => function($normalized) {
            preg_match("/Can't find symbol '([^']+)' in library/", $normalized->message, $matches);
            return ['symbol' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Symbol not found: Cannot find symbol "{symbol}" in the shared library. The UDF library may be corrupted or incompatible.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::FUNCTION_NOT_DEFINED,
        'error_code' => [MySqlErrorCodes::ER_FUNCTION_NOT_DEFINED],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/FUNCTION .* does not exist/i',
        'extract' => function($normalized) {
            preg_match("/FUNCTION ([^\s]+) does not exist/", $normalized->message, $matches);
            return ['function' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Function not defined: The function "{function}" does not exist. Check the function name or create the function first.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::HOST_IS_BLOCKED,
        'error_code' => [MySqlErrorCodes::ER_HOST_IS_BLOCKED],
        'sqlstate' => [MySqlStates::CONNECTION_REJECTED],
        'message_regex' => '/Host .* is blocked/i',
        'extract' => function($normalized) {
            preg_match("/Host '([^']+)' is blocked/", $normalized->message, $matches);
            return ['host' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Host blocked: The host "{host}" is blocked due to too many connection errors. Run FLUSH HOSTS or increase max_connect_errors.',
        'migration' => function($ctx) { return "FLUSH HOSTS;"; }
    ],
    [
        'id' => RuleIds::HOST_NOT_PRIVILEGED,
        'error_code' => [MySqlErrorCodes::ER_HOST_NOT_PRIVILEGED],
        'sqlstate' => [MySqlStates::INVALID_AUTHORIZATION],
        'message_regex' => '/Host .* is not allowed to connect/i',
        'extract' => function($normalized) {
            preg_match("/Host '([^']+)' is not allowed/", $normalized->message, $matches);
            return ['host' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Host not privileged: The host "{host}" is not allowed to connect to this MySQL server. Update user privileges.',
        'migration' => function($ctx) { 
            return "-- Allow host to connect:\nCREATE USER 'username'@'{$ctx['host']}' IDENTIFIED BY 'password';"; 
        }
    ],
    [
        'id' => RuleIds::PASSWORD_ANONYMOUS_USER,
        'error_code' => [MySqlErrorCodes::ER_PASSWORD_ANONYMOUS_USER],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/You are not allowed to create a user with GRANT/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Password for anonymous user: Cannot set password for anonymous user. Create a named user instead.',
        'migration' => function($ctx) { return ''; }
    ],
    
    // Column and Table Access Errors (1136-1149)
    [
        'id' => RuleIds::WRONG_VALUE_COUNT,
        'error_code' => [MySqlErrorCodes::ER_WRONG_VALUE_COUNT_ON_ROW],
        'sqlstate' => [MySqlStates::INSERT_VALUE_LIST_NOT_MATCH],
        'message_regex' => '/Column count doesn\'t match value count/i',
        'extract' => function($normalized) {
            preg_match('/at row (\d+)/', $normalized->message, $matches);
            return ['row' => $matches[1] ?? '1'];
        },
        'explanation' => 'Wrong value count: The number of columns does not match the number of values at row {row}. Ensure INSERT values match the column count.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::CANT_REOPEN_TABLE,
        'error_code' => [MySqlErrorCodes::ER_CANT_REOPEN_TABLE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Can\'t reopen table/i',
        'extract' => function($normalized) {
            preg_match("/Can't reopen table: '([^']+)'/", $normalized->message, $matches);
            return ['table' => $matches[1] ?? $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Cannot reopen table: Cannot reopen table "{table}". This often occurs when referencing a temporary table multiple times in the same query.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::INVALID_USE_OF_NULL,
        'error_code' => [MySqlErrorCodes::ER_INVALID_USE_OF_NULL],
        'sqlstate' => [MySqlStates::CONSTANT_VIOLATION],
        'message_regex' => '/Invalid use of NULL value/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Invalid use of NULL: NULL value is not allowed in this context. Check for NOT NULL constraints or use a non-NULL value.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::REGEXP_ERROR,
        'error_code' => [MySqlErrorCodes::ER_REGEXP_ERROR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Got error .* from regexp/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Regular expression error: The regular expression pattern is invalid. Check the regex syntax.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::MIX_OF_GROUP_FUNC_AND_FIELDS,
        'error_code' => [MySqlErrorCodes::ER_MIX_OF_GROUP_FUNC_AND_FIELDS],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Mixing of GROUP columns/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Mix of GROUP functions and fields: You are mixing aggregate functions with non-aggregated columns without GROUP BY. Add all non-aggregated columns to GROUP BY.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NONEXISTING_GRANT,
        'error_code' => [MySqlErrorCodes::ER_NONEXISTING_GRANT],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/There is no such grant defined/i',
        'extract' => function($normalized) {
            preg_match("/for user '([^']+)'@'([^']+)'/", $normalized->message, $matches);
            return ['user' => $matches[1] ?? 'unknown', 'host' => $matches[2] ?? 'unknown'];
        },
        'explanation' => 'Non-existing grant: The specified grant for user "{user}"@"{host}" does not exist.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::TABLE_ACCESS_DENIED,
        'error_code' => [MySqlErrorCodes::ER_TABLEACCESS_DENIED_ERROR],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/.* command denied to user/i',
        'extract' => function($normalized) {
            preg_match("/user '([^']+)'@'([^']+)' for table '([^']+)'/", $normalized->message, $matches);
            return [
                'user' => $matches[1] ?? 'unknown',
                'host' => $matches[2] ?? 'unknown',
                'table' => $matches[3] ?? $normalized->table ?? 'unknown'
            ];
        },
        'explanation' => 'Table access denied: User "{user}"@"{host}" does not have permission to access table "{table}".',
        'migration' => function($ctx) { 
            return "GRANT SELECT, INSERT, UPDATE, DELETE ON `{$ctx['table']}` TO '{$ctx['user']}'@'{$ctx['host']}';"; 
        }
    ],
    [
        'id' => RuleIds::COLUMN_ACCESS_DENIED,
        'error_code' => [MySqlErrorCodes::ER_COLUMNACCESS_DENIED_ERROR],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/.* command denied .* for column/i',
        'extract' => function($normalized) {
            preg_match("/column '([^']+)' in table '([^']+)'/", $normalized->message, $matches);
            return [
                'column' => $matches[1] ?? $normalized->column ?? 'unknown',
                'table' => $matches[2] ?? $normalized->table ?? 'unknown'
            ];
        },
        'explanation' => 'Column access denied: You do not have permission to access column "{column}" in table "{table}".',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::ILLEGAL_GRANT_FOR_TABLE,
        'error_code' => [MySqlErrorCodes::ER_ILLEGAL_GRANT_FOR_TABLE],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Illegal GRANT\/REVOKE command/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Illegal grant for table: The GRANT or REVOKE command is not valid for this table. Check the privilege syntax.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::GRANT_WRONG_HOST_OR_USER,
        'error_code' => [MySqlErrorCodes::ER_GRANT_WRONG_HOST_OR_USER],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Incorrect host or user/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Wrong host or user: The specified host or user in the GRANT statement is incorrect. Check the user and host format.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NONEXISTING_TABLE_GRANT,
        'error_code' => [MySqlErrorCodes::ER_NONEXISTING_TABLE_GRANT],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/There is no such table grant/i',
        'extract' => function($normalized) {
            return ['table' => $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Non-existing table grant: No grants exist for table "{table}" to revoke.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NOT_ALLOWED_COMMAND,
        'error_code' => [MySqlErrorCodes::ER_NOT_ALLOWED_COMMAND],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/The used command is not allowed/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Command not allowed: This command is not allowed with this MySQL connection. Check if LOCAL INFILE or other features are disabled.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::SYNTAX_ERROR_GENERAL,
        'error_code' => [MySqlErrorCodes::ER_SYNTAX_ERROR],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/You have an error in your SQL syntax/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'SQL syntax error: There is a syntax error in your SQL query. Check for typos, missing quotes, or incorrect SQL keywords.',
        'migration' => function($ctx) { return ''; }
    ],
    
    // Connection Errors (1152-1161)
    [
        'id' => RuleIds::ABORTING_CONNECTION,
        'error_code' => [MySqlErrorCodes::ER_ABORTING_CONNECTION],
        'sqlstate' => [MySqlStates::COMMUNICATION_LINK_FAILURE],
        'message_regex' => '/Aborted connection/i',
        'extract' => function($normalized) {
            preg_match('/connection (\d+)/', $normalized->message, $matches);
            return ['connection_id' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Connection aborted: Connection {connection_id} was aborted. This can happen due to timeout, network issues, or client disconnect.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NET_PACKET_TOO_LARGE,
        'error_code' => [MySqlErrorCodes::ER_NET_PACKET_TOO_LARGE],
        'sqlstate' => [MySqlStates::COMMUNICATION_LINK_FAILURE],
        'message_regex' => '/Got a packet bigger than/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Packet too large: The network packet exceeds the maximum allowed size. Increase max_allowed_packet in MySQL configuration.',
        'migration' => function($ctx) { return "-- Increase max_allowed_packet:\nSET GLOBAL max_allowed_packet = 64*1024*1024;"; }
    ],
    [
        'id' => RuleIds::NET_READ_ERROR_FROM_PIPE,
        'error_code' => [MySqlErrorCodes::ER_NET_READ_ERROR_FROM_PIPE],
        'sqlstate' => [MySqlStates::COMMUNICATION_LINK_FAILURE],
        'message_regex' => '/Got an error reading communication packets/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Network read error: Error reading from the network pipe. Check network connectivity.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NET_FCNTL_ERROR,
        'error_code' => [MySqlErrorCodes::ER_NET_FCNTL_ERROR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Got an error from fcntl/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Network fcntl error: A file control operation failed on the network socket.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NET_PACKETS_OUT_OF_ORDER,
        'error_code' => [MySqlErrorCodes::ER_NET_PACKETS_OUT_OF_ORDER],
        'sqlstate' => [MySqlStates::COMMUNICATION_LINK_FAILURE],
        'message_regex' => '/Got packets out of order/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Packets out of order: Network packets arrived out of order. This may indicate network issues or a bug.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NET_UNCOMPRESS_ERROR,
        'error_code' => [MySqlErrorCodes::ER_NET_UNCOMPRESS_ERROR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Couldn\'t uncompress communication packet/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Uncompress error: Could not decompress the network packet. Check if compression is properly configured.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NET_READ_ERROR,
        'error_code' => [MySqlErrorCodes::ER_NET_READ_ERROR],
        'sqlstate' => [MySqlStates::COMMUNICATION_LINK_FAILURE],
        'message_regex' => '/Got an error reading communication packets/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Network read error: Error reading data from the network. Check your network connection.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NET_READ_INTERRUPTED,
        'error_code' => [MySqlErrorCodes::ER_NET_READ_INTERRUPTED],
        'sqlstate' => [MySqlStates::COMMUNICATION_LINK_FAILURE],
        'message_regex' => '/Got timeout reading communication packets/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Network read interrupted: A timeout occurred while reading from the network. Consider increasing wait_timeout.',
        'migration' => function($ctx) { return "SET GLOBAL wait_timeout = 28800;"; }
    ],
    [
        'id' => RuleIds::NET_ERROR_ON_WRITE,
        'error_code' => [MySqlErrorCodes::ER_NET_ERROR_ON_WRITE],
        'sqlstate' => [MySqlStates::COMMUNICATION_LINK_FAILURE],
        'message_regex' => '/Got an error writing communication packets/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Network write error: Error writing data to the network. Check your network connection.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NET_WRITE_INTERRUPTED,
        'error_code' => [MySqlErrorCodes::ER_NET_WRITE_INTERRUPTED],
        'sqlstate' => [MySqlStates::COMMUNICATION_LINK_FAILURE],
        'message_regex' => '/Got timeout writing communication packets/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Network write interrupted: A timeout occurred while writing to the network.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::TOO_LONG_STRING,
        'error_code' => [MySqlErrorCodes::ER_TOO_LONG_STRING],
        'sqlstate' => [MySqlStates::DATA_TOO_LONG],
        'message_regex' => '/String is too long/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'String too long: The string value is too long for the column type. Use TEXT or LONGTEXT for larger strings.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::TABLE_CANT_HANDLE_BLOB,
        'error_code' => [MySqlErrorCodes::ER_TABLE_CANT_HANDLE_BLOB],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/The used table type doesn\'t support BLOB/i',
        'extract' => function($normalized) {
            return ['table' => $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Table cannot handle BLOB: The storage engine for table "{table}" does not support BLOB columns. Use InnoDB instead.',
        'migration' => function($ctx) { 
            return "ALTER TABLE `{$ctx['table']}` ENGINE=InnoDB;"; 
        }
    ],
    
    // Command and Option Errors (1234-1299)
    [
        'id' => RuleIds::CANT_USE_OPTION_HERE,
        'error_code' => [MySqlErrorCodes::ER_CANT_USE_OPTION_HERE],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Incorrect usage of/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Cannot use option here: The SQL option or clause cannot be used in this context. Check the SQL syntax.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NOT_SUPPORTED_YET,
        'error_code' => [MySqlErrorCodes::ER_NOT_SUPPORTED_YET],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/This version of MySQL doesn\'t yet support/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Feature not supported: This feature is not yet supported in your MySQL version. Consider upgrading MySQL.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::MASTER_FATAL_ERROR_READING_BINLOG,
        'error_code' => [MySqlErrorCodes::ER_MASTER_FATAL_ERROR_READING_BINLOG],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Got fatal error .* from source when reading data from binary log/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Binlog read error: Fatal error reading from the binary log. Check the replication configuration and binary logs.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::JT_VALUE_OUT_OF_RANGE,
        'error_code' => [MySqlErrorCodes::ER_JT_VALUE_OUT_OF_RANGE],
        'sqlstate' => [MySqlStates::DATA_TRUNCATED],
        'message_regex' => '/Out of range value/i',
        'extract' => function($normalized) {
            preg_match("/column '([^']+)'/", $normalized->message, $matches);
            return ['column' => $matches[1] ?? $normalized->column ?? 'unknown'];
        },
        'explanation' => 'Value out of range: The value for column "{column}" is out of the allowed range. Use a larger data type.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::DATA_TRUNCATED,
        'error_code' => [MySqlErrorCodes::WARN_DATA_TRUNCATED],
        'sqlstate' => [MySqlStates::WARNING],
        'message_regex' => '/Data truncated for column/i',
        'extract' => function($normalized) {
            preg_match("/column '([^']+)'/", $normalized->message, $matches);
            return ['column' => $matches[1] ?? $normalized->column ?? 'unknown', 'table' => $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Data truncated: Data was truncated for column "{column}". The value is too large or of incorrect format.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::WARN_USING_OTHER_HANDLER,
        'error_code' => [MySqlErrorCodes::ER_WARN_USING_OTHER_HANDLER],
        'sqlstate' => [MySqlStates::WARNING],
        'message_regex' => '/Using storage engine .* for table/i',
        'extract' => function($normalized) {
            preg_match("/storage engine ([^ ]+)/", $normalized->message, $matches);
            return ['engine' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Using different storage engine: MySQL is using storage engine "{engine}" instead of the requested one.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::CANT_AGGREGATE_COLLATIONS,
        'error_code' => [MySqlErrorCodes::ER_CANT_AGGREGATE_2COLLATIONS],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Illegal mix of collations/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Collation mismatch: Cannot aggregate columns with different collations. Use COLLATE clause or ensure consistent collations.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::REVOKE_GRANTS,
        'error_code' => [MySqlErrorCodes::ER_REVOKE_GRANTS],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Can\'t revoke all privileges/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Cannot revoke grants: Cannot revoke all privileges. Some privileges may be required or missing.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::UNKNOWN_KEY_CACHE,
        'error_code' => [MySqlErrorCodes::ER_UNKNOWN_KEY_CACHE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Unknown key cache/i',
        'extract' => function($normalized) {
            preg_match("/Unknown key cache '([^']+)'/", $normalized->message, $matches);
            return ['cache' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Unknown key cache: The key cache "{cache}" does not exist. Create it first or use the default cache.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::WARN_HOSTNAME_WONT_WORK,
        'error_code' => [MySqlErrorCodes::ER_WARN_HOSTNAME_WONT_WORK],
        'sqlstate' => [MySqlStates::WARNING],
        'message_regex' => '/\'--skip-name-resolve\' is on/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Hostname warning: Hostnames cannot be resolved because --skip-name-resolve is enabled. Use IP addresses instead.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::UNKNOWN_STORAGE_ENGINE,
        'error_code' => [MySqlErrorCodes::ER_UNKNOWN_STORAGE_ENGINE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Unknown storage engine/i',
        'extract' => function($normalized) {
            preg_match("/Unknown storage engine '([^']+)'/", $normalized->message, $matches);
            return ['engine' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Unknown storage engine: The storage engine "{engine}" is not available. Use InnoDB or another supported engine.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::DEPRECATED_SYNTAX,
        'error_code' => [MySqlErrorCodes::ER_WARN_DEPRECATED_SYNTAX],
        'sqlstate' => [MySqlStates::WARNING],
        'message_regex' => '/is deprecated and will be removed/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Deprecated syntax: The syntax used is deprecated and will be removed in a future MySQL version. Update your query.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NON_UPDATABLE_TABLE,
        'error_code' => [MySqlErrorCodes::ER_NON_UPDATABLE_TABLE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/The target table .* of the .* is not updatable/i',
        'extract' => function($normalized) {
            preg_match("/target table ([^ ]+)/", $normalized->message, $matches);
            return ['table' => $matches[1] ?? $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Non-updatable table: Table "{table}" cannot be updated in this context. Views or derived tables may not be updatable.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::FEATURE_DISABLED,
        'error_code' => [MySqlErrorCodes::ER_FEATURE_DISABLED],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/is disabled/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Feature disabled: This MySQL feature is disabled. Enable it in the server configuration.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NOT_SUPPORTED_AUTH_MODE,
        'error_code' => [MySqlErrorCodes::ER_NOT_SUPPORTED_AUTH_MODE],
        'sqlstate' => [MySqlStates::INVALID_AUTHORIZATION],
        'message_regex' => '/Client does not support authentication protocol/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Auth mode not supported: The client does not support the requested authentication protocol. Update your client or change the authentication method.',
        'migration' => function($ctx) { 
            return "-- Change authentication method:\nALTER USER 'username'@'host' IDENTIFIED WITH mysql_native_password BY 'password';"; 
        }
    ],
    [
        'id' => RuleIds::UNKNOWN_TIME_ZONE,
        'error_code' => [MySqlErrorCodes::ER_UNKNOWN_TIME_ZONE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Unknown or incorrect time zone/i',
        'extract' => function($normalized) {
            preg_match("/time zone: '([^']+)'/", $normalized->message, $matches);
            return ['timezone' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Unknown time zone: The time zone "{timezone}" is not recognized. Import time zone tables or use a valid zone name.',
        'migration' => function($ctx) { 
            return "-- Import time zone data:\n-- mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql"; 
        }
    ],
    [
        'id' => RuleIds::WARN_INVALID_TIMESTAMP,
        'error_code' => [MySqlErrorCodes::ER_WARN_INVALID_TIMESTAMP],
        'sqlstate' => [MySqlStates::WARNING],
        'message_regex' => '/Invalid TIMESTAMP/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Invalid timestamp: The timestamp value is invalid. Ensure it is within the valid range (1970-2038 for 32-bit).',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::INVALID_CHARACTER_STRING,
        'error_code' => [MySqlErrorCodes::ER_INVALID_CHARACTER_STRING],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Invalid .* character string/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Invalid character string: The string contains invalid characters for the specified character set.',
        'migration' => function($ctx) { return ''; }
    ],
    
    // Replication and Log Errors (1379-1391)
    [
        'id' => RuleIds::LOG_PURGE_UNKNOWN_ERROR,
        'error_code' => [MySqlErrorCodes::ER_LOG_PURGE_UNKNOWN_ERR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Unknown error during log purge/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Log purge error: An unknown error occurred while purging binary logs.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::RELAY_LOG_INIT_ERROR,
        'error_code' => [MySqlErrorCodes::ER_RELAY_LOG_INIT],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Failed to initialize the relay log/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Relay log init error: Failed to initialize the relay log. Check replication configuration and file permissions.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::NO_BINARY_LOGGING,
        'error_code' => [MySqlErrorCodes::ER_NO_BINARY_LOGGING],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/You are not using binary logging/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'No binary logging: Binary logging is not enabled. Enable it in the MySQL configuration if needed.',
        'migration' => function($ctx) { return "-- Enable binary logging in my.cnf:\n-- log-bin=mysql-bin"; }
    ],
    [
        'id' => RuleIds::RESERVED_SYNTAX,
        'error_code' => [MySqlErrorCodes::ER_RESERVED_SYNTAX],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/The syntax .* is reserved/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Reserved syntax: The syntax you used is reserved and cannot be used this way. Check MySQL documentation.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::TOO_MANY_PARAMS,
        'error_code' => [MySqlErrorCodes::ER_PS_MANY_PARAM],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Prepared statement contains too many placeholders/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Too many parameters: The prepared statement has too many placeholders. Reduce the number or batch your queries.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::KEY_PART_ZERO,
        'error_code' => [MySqlErrorCodes::ER_KEY_PART_0],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Key part .* length cannot be 0/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Key part zero length: An index key part cannot have zero length. Specify a valid length for the key.',
        'migration' => function($ctx) { return ''; }
    ],
    
    // Stored Procedure and Data Errors (1406-1411)
    [
        'id' => RuleIds::NO_DEFAULT_FOR_FIELD,
        'error_code' => [MySqlErrorCodes::ER_NO_DEFAULT_FOR_FIELD],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Field .* doesn\'t have a default value/i',
        'extract' => function($normalized) {
            preg_match("/Field '([^']+)' doesn't have a default value/", $normalized->message, $matches);
            return ['column' => $matches[1] ?? $normalized->column ?? 'unknown', 'table' => $normalized->table ?? 'unknown'];
        },
        'explanation' => 'No default value: Column "{column}" requires a value but none was provided and no default exists. Provide a value or set a default.',
        'migration' => function($ctx) { 
            return "-- Set a default value:\nALTER TABLE `{$ctx['table']}` ALTER COLUMN `{$ctx['column']}` SET DEFAULT 'value';\n\n-- Or make it nullable:\nALTER TABLE `{$ctx['table']}` MODIFY `{$ctx['column']}` VARCHAR(255) NULL;"; 
        }
    ],
    [
        'id' => RuleIds::SP_BAD_SQLSTATE,
        'error_code' => [MySqlErrorCodes::ER_SP_BAD_SQLSTATE],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Bad SQLSTATE/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Bad SQLSTATE: Invalid SQLSTATE code in stored procedure. Use a valid 5-character SQLSTATE code.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::STARTUP_ERROR,
        'error_code' => [MySqlErrorCodes::ER_STARTUP],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/during startup/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Startup error: An error occurred during MySQL server startup. Check the error log for details.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::LOAD_FROM_FIXED_SIZE_ROWS,
        'error_code' => [MySqlErrorCodes::ER_LOAD_FROM_FIXED_SIZE_ROWS_TO_VAR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/fixed row format/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Fixed size rows error: Cannot load from fixed-size rows to variables. Check your LOAD DATA syntax.',
        'migration' => function($ctx) { return ''; }
    ],
    [
        'id' => RuleIds::CANT_CREATE_USER_WITH_GRANT,
        'error_code' => [MySqlErrorCodes::ER_CANT_CREATE_USER_WITH_GRANT],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/You are not allowed to create a user with GRANT/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Cannot create user with GRANT: Use CREATE USER statement first, then GRANT privileges separately.',
        'migration' => function($ctx) { 
            return "-- Create user first:\nCREATE USER 'username'@'host' IDENTIFIED BY 'password';\n-- Then grant privileges:\nGRANT SELECT ON database.* TO 'username'@'host';"; 
        }
    ],
    [
        'id' => RuleIds::WRONG_VALUE_FOR_TYPE,
        'error_code' => [MySqlErrorCodes::ER_WRONG_VALUE_FOR_TYPE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Incorrect .* value/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Wrong value for type: The value is incorrect for the specified data type. Check the format and type compatibility.',
        'migration' => function($ctx) { return ''; }
    ],
    
    // Foreign Key Constraint - Missing Parent Row
    [
        'id' => RuleIds::FK_MISSING_PARENT,
        'error_code' => [MySqlErrorCodes::ER_NO_REFERENCED_ROW_2],
        'sqlstate' => [MySqlStates::CONSTANT_VIOLATION],
        'message_regex' => '/foreign key constraint fails.*Cannot add or update/i',
        'extract' => function($normalized) {
            // Extract table and column from error message
            preg_match('/CONSTRAINT `([^`]+)`/', $normalized->message, $constraint);
            preg_match('/FOREIGN KEY \(`([^`]+)`\)/', $normalized->message, $column);
            preg_match('/REFERENCES `([^`]+)`/', $normalized->message, $parentTable);
            preg_match('/\(`([^`]+)`\)/', $normalized->message, $parentColumn);

            return [
                'table' => $normalized->table ?? 'unknown',
                'column' => $column[1] ?? 'unknown',
                'parent_table' => $parentTable[1] ?? 'unknown',
                'constraint' => $constraint[1] ?? null,
                'parent_column' => $parentColumn[1] ?? null
            ];
        },
        'explanation' => 'Foreign key constraint violation: The value you are trying to insert or update in column "{column}" does not exist in the parent table "{parent_table}". You must first insert the referenced record in the parent table.',
        'migration' => function($ctx) {
            return (new \TusharKhan\DbErrorExplainer\Migration\MigrationSuggester(RuleIds::FK_MISSING_PARENT, $ctx))->suggest();
        },
    ],

    // Foreign Key Constraint - Cannot Delete Parent
    [
        'id' => RuleIds::FK_CANNOT_DELETE_PARENT,
        'error_code' => [MySqlErrorCodes::ER_ROW_IS_REFERENCED_2],
        'sqlstate' => [MySqlStates::CONSTANT_VIOLATION],
        'message_regex' => '/Cannot delete or update a parent row.*foreign key constraint fails/i',
        'extract' => function($normalized) {
            preg_match('/CONSTRAINT `([^`]+)`/', $normalized->message, $constraint);
            preg_match('/table `([^`]+)`\.`([^`]+)`/', $normalized->message, $childTable);

            return [
                'table' => $normalized->table ?? 'unknown',
                'child_table' => $childTable[2] ?? 'unknown',
                'constraint' => $constraint[1] ?? null,
            ];
        },
        'explanation' => 'Foreign key constraint violation: Cannot delete this record because child records exist in table "{child_table}". You must first delete the dependent records or use CASCADE delete.',
        'migration' => function($ctx) {
            $dropConstraint = "ALTER TABLE `{$ctx['child_table']}`
                DROP FOREIGN KEY `{$ctx['constraint']}`";

            $addConstraint = "ALTER TABLE `{$ctx['child_table']}`
                ADD CONSTRAINT `fk_{$ctx['child_table']}_parent_id`
                FOREIGN KEY (`parent_id`)
                REFERENCES `{$ctx['table']}`(`id`)
                ON DELETE CASCADE";

            return "{$dropConstraint};\n{$addConstraint};";
        },
    ],

    // Duplicate Entry (Unique Constraint Violation)
    [
        'id' => RuleIds::DUPLICATE_ENTRY,
        'error_code' => [MySqlErrorCodes::ER_DUP_ENTRY],
        'sqlstate' => [MySqlStates::CONSTANT_VIOLATION],
        'message_regex' => '/Duplicate entry .* for key/i',
        'extract' => function($normalized) {
            preg_match('/Duplicate entry \'([^\']+)\' for key \'([^\']+)\'/', $normalized->message, $matches);

            return [
                'table' => $normalized->table ?? 'unknown',
                'value' => $matches[1] ?? 'unknown',
                'key' => $matches[2] ?? 'unknown',
            ];
        },
        'explanation' => 'Duplicate entry: A record with the value "{value}" already exists for the unique key "{key}". You cannot insert duplicate values in unique or primary key columns.',
        'migration' => function($ctx) {
            return "-- If this unique constraint is incorrect, remove it:
                                ALTER TABLE `{$ctx['table']}` DROP INDEX `{$ctx['key']}`";
        },
    ],

    // NOT NULL Constraint Violation
    [
        'id' => RuleIds::NULL_CONSTRAINT_VIOLATION,
        'error_code' => [MySqlErrorCodes::ER_BAD_NULL_ERROR, MySqlErrorCodes::ER_NO_DEFAULT_FOR_FIELD],
        'sqlstate' => [MySqlStates::CONSTANT_VIOLATION, MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Column .* cannot be null|doesn\'t have a default value/i',
        'extract' => function($normalized) {
            preg_match('/Column \'([^\']+)\' cannot be null/', $normalized->message, $matches);
            if (empty($matches)) {
                preg_match('/Field \'([^\']+)\' doesn\'t have a default value/', $normalized->message, $matches);
            }

            return [
                'table' => $normalized->table ?? 'unknown',
                'column' => $matches[1] ?? 'unknown',
            ];
        },
        'explanation' => 'NULL constraint violation: Column "{column}" requires a value but received NULL. You must provide a value or make the column nullable.',
        'migration' => function($ctx) {
            $makeColumnNullable = "-- Option 1: Make the column nullable: \n\nALTER TABLE `{$ctx['table']}` MODIFY `{$ctx['column']}` VARCHAR(255) NULL";
            $setDefaultValue = "-- Option 2: Set a default value: \n\nALTER TABLE `{$ctx['table']}` MODIFY `{$ctx['column']}` VARCHAR(255) NOT NULL DEFAULT 'value';";

            return "{$makeColumnNullable}\n\n{$setDefaultValue}";
        },
    ],

    // Unknown Column
    [
        'id' => RuleIds::UNKNOWN_COLUMN,
        'error_code' => [MySqlErrorCodes::ER_BAD_FIELD_ERROR],
        'sqlstate' => [MySqlStates::COLUMN_NOT_FOUND],
        'message_regex' => '/Unknown column .* in/i',
        'extract' => function($normalized) {
            preg_match('/Unknown column \'([^\']+)\' in \'([^\']+)\'/', $normalized->message, $matches);

            return [
                'column' => $normalized->column ?? 'unknown',
                'clause' => $matches[2] ?? 'field list',
                'table' => $normalized->table ?? 'unknown',
            ];
        },
        'explanation' => 'Unknown column: Column "{column}" does not exist in the table. You may have a typo or the column needs to be added via migration.',
        'migration' => function($ctx) {
            return (new MigrationSuggester(RuleIds::UNKNOWN_COLUMN, $ctx))->suggest();
        },
    ],

    // Table Doesn't Exist
    [
        'id' => RuleIds::TABLE_NOT_FOUND,
        'error_code' => [MySqlErrorCodes::ER_NO_SUCH_TABLE],
        'sqlstate' => [MySqlStates::TABLE_OR_VIEW_NOT_FOUND],
        'message_regex' => '/Table .* doesn\'t exist/i',
        'extract' => function($normalized) {
            preg_match('/Table \'([^\']+)\.([^\']+)\' doesn\'t exist/', $normalized->message, $matches);

            return [
                'database' => $matches[1] ?? 'unknown',
                'table' => $matches[2] ?? ($normalized->table ?? 'unknown'),
            ];
        },
        'explanation' => 'Table not found: The table "{table}" does not exist in the database. You need to create it via migration or run pending migrations.',
        'migration' => function($ctx) {
            return (new \TusharKhan\DbErrorExplainer\Migration\MigrationSuggester(RuleIds::TABLE_NOT_FOUND, $ctx))->suggest();
        },
    ],

    // Data Too Long
    [
        'id' => RuleIds::DATA_TOO_LONG,
        'error_code' => [MySqlErrorCodes::ER_DATA_TOO_LONG],
        'sqlstate' => [MySqlStates::DATA_TOO_LONG],
        'message_regex' => '/Data too long for column/i',
        'extract' => function($normalized) {
            preg_match('/Data too long for column \'([^\']+)\' at row (\d+)/', $normalized->message, $matches);

            return [
                'table' => $normalized->table ?? 'unknown',
                'column' => $matches[1] ?? 'unknown',
                'row' => $matches[2] ?? 'unknown',
            ];
        },
        'explanation' => 'Data too long: The value for column "{column}" exceeds the maximum length defined in the schema. You need to either shorten the data or increase the column size.',
        'migration' => function($ctx) {
                return "-- Increase the column size:\n\nALTER TABLE `{$ctx['table']}` MODIFY `{$ctx['column']}` VARCHAR(255) NULL;\n\n --column type can be any valid MySQL column type";
        },
    ],

    // Out of Range Value
    [
        'id' => RuleIds::OUT_OF_RANGE,
        'error_code' => [MySqlErrorCodes::ER_WARN_DATA_OUT_OF_RANGE],
        'sqlstate' => [MySqlStates::DATA_TRUNCATED],
        'message_regex' => '/Out of range value for column/i',
        'extract' => function($normalized) {
            preg_match('/Out of range value for column \'([^\']+)\' at row (\d+)/', $normalized->message, $matches);

            return [
                'table' => $normalized->table ?? 'unknown',
                'column' => $matches[1] ?? 'unknown',
                'row' => $matches[2] ?? 'unknown',
            ];
        },
        'explanation' => 'Out of range: The value for column "{column}" is too large or too small for the defined data type. You need to use a larger data type.',
        'migration' => function($ctx) {
            return "-- Change the column type:\n\nALTER TABLE `{$ctx['table']}` MODIFY `{$ctx['column']}` INT NULL;\n\n --column type can be any valid MySQL column type \n\n TINYINT → SMALLINT → MEDIUMINT → INT → BIGINT";
        },
    ],

    // Invalid Default Value
    [
        'id' => RuleIds::INVALID_DEFAULT,
        'error_code' => [MySqlErrorCodes::ER_INVALID_DEFAULT],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Invalid default value for/i',
        'extract' => function($normalized) {
            preg_match('/Invalid default value for \'([^\']+)\'/', $normalized->message, $matches);

            return [
                'table' => $normalized->table ?? 'unknown',
                'column' => $matches[1] ?? 'unknown',
            ];
        },
        'explanation' => 'Invalid default value: The default value specified for column "{column}" is not valid for its data type.',
        'migration' => function($ctx) {
            $fixDefaultValue = "-- Fix the default value:\n\nALTER TABLE `{$ctx['table']}` MODIFY `{$ctx['column']}` VARCHAR(255) NULL DEFAULT 'value';";
            $removeDefaultValue = "-- or you can Remove the default value:\n\nALTER TABLE `{$ctx['table']}` MODIFY `{$ctx['column']}` VARCHAR(255) NULL;";
            return $fixDefaultValue . "\n\n" . $removeDefaultValue;
        },
    ],

    // Syntax Error
    [
        'id' => RuleIds::SYNTAX_ERROR,
        'error_code' => [MySqlErrorCodes::ER_PARSE_ERROR],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/You have an error in your SQL syntax/i',
        'extract' => function($normalized) {
            return [
                'query' => $normalized->sql ?? 'unknown',
            ];
        },
        'explanation' => 'SQL syntax error: There is a syntax error in your SQL query. Check the query for typos, missing quotes, or incorrect SQL keywords.',
        'migration' => function($ctx) {
            return "// This is a query syntax error, not a schema issue.\n"
                . "// Review your query for:\n"
                . "// - Missing or extra quotes\n"
                . "// - Missing commas\n"
                . "// - Incorrect SQL keywords\n"
                . "// - Reserved words used as column names\n"
                . "// \n"
                . "// Query: {$ctx['query']}";
        },
    ],
    [
        'id' => RuleIds::CANT_CREATE_DATABASE,
        'error_code' => [MySqlErrorCodes::ER_CANT_CREATE_DB],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Can\'t create database/i',
        'extract' => function($normalized) {
            return ['database' => $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Can\'t create database: There was an error creating the database "{database}". Check your database name or permissions.',
        'migration' => function($ctx) {
            return 'CREATE DATABASE ' .$ctx["database"];
        }
    ],
    [
        'id' => RuleIds::CANT_CREATE_DATABASE_EXISTS,
        'error_code' => [MySqlErrorCodes::ER_DB_CREATE_EXISTS],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Can\'t create database; database exists/i',
        'extract' => function($normalized) {
            return ['database' => $normalized->database ?? 'unknown'];
        },
        'explanation' => 'Can\'t create database: The database "{database}" already exists. Check your database name or permissions.',
        'migration' => function($ctx) {
            return 'CREATE DATABASE `'.$ctx['database'].'2`.';
        }
    ],
    [
        'id' => RuleIds::CANT_DROP_DATABASE,
        'error_code' => [MySqlErrorCodes::ER_DB_DROP_EXISTS],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Can\'t drop database \'([^`]+)\'; database doesn\'t exist/i',
        'extract' => function($normalized) {
            preg_match('/Can\'t drop database \'([^\']+)\'; database doesn\'t exist/', $normalized->message, $matches);
            return ['database' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Can\'t drop database: The database "{database}" does not exist. Check your database name or permissions.',
        'migration' => function($ctx) {
            // check if database exists before dropping it
            return "if (database_exists('{$ctx['database']}')) {\n    DROP DATABASE `{$ctx['database']}`;\n}";
        }
    ],
    [
        'id' => RuleIds::CANT_DROP_DATABASE_FILESYSTEM_ISSUE,
        'error_code' => [MySqlErrorCodes::ER_DB_DROP_RMDIR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => 'Error dropping database: .*',
        'extract' => function($normalized) {
            preg_match('/Error dropping database: (.*)/', $normalized->message, $matches);
            return ['error' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'database drop failure due to filesystem issues. Check directory permissions for the MySQL data folder. Make sure no files in the database folder are in use.',
        'migration' => function($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::RECORD_CANT_READ,
        'error_code' => [MySqlErrorCodes::ER_CANT_FIND_SYSTEM_REC],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Can\'t find system record in the given space/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Record cannot be read: MySQL is trying to read its own internal system tables (metadata tables), but the data is missing, corrupted, or unreadable. It indicates a server-level or system-table problem',
        'migration' => function($ctx) {
            return 'REPAIR TABLE '.$ctx["table"].'; REPAIR TABLE mysql.user;';
        }
    ],
    [
        'id' => RuleIds::NAME_BECOMES_EMPTY,
        'error_code' => [MySqlErrorCodes::ER_NAME_BECOMES_EMPTY],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Name has become empty when checking privilege/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Name has become empty: A name you provided (or MySQL generated automatically) turned into an empty string.This is a schema/metadata naming error.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::AMBIGUOUS_COLUMN_NAME,
        'error_code' => [MySqlErrorCodes::ER_AMBIGUOUS_FIELD_TERM],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Column \'([^\']+)\' in (where|field list|order clause) is ambiguous/i',
        'extract' => function($normalized) {
            preg_match('/Column \'([^\']+)\' in (where|field list|order clause) is ambiguous/', $normalized->message, $matches);
            return ['column' => $matches[1] ?? 'unknown', 'clause' => $matches[2] ?? 'unknown'];
        },
        'explanation' => 'Ambiguous column name: The column "{column}" is ambiguous in the {clause}. You need to qualify it with the table name or alias.',
        'migration' => function($ctx) {
            return "-- Qualify the column with table name or alias:\n\nSELECT t.`{$ctx['column']}` FROM `{$ctx['table']}` AS t WHERE t.`{$ctx['column']}` = 'value';";
        }
    ],
    [
        'id' => RuleIds::FOREIGN_SERVER_DOESNT_EXIST,
        'error_code' => [MySqlErrorCodes::ER_FOREIGN_SERVER_DOESNT_EXIST],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Foreign server does not exist/i',
        'extract' => function($normalized) {return [
            'database' => $normalized->database ?? 'unknown'
        ];},
        'explanation' => 'Foreign server does not exist: The specified foreign server for a federated table or foreign data wrapper does not exist. You need to create the foreign server first.',
        'migration' => function($ctx) {return "CREATE SERVER foreign_server_name FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'hostname', DATABASE '". $ctx['database'] ."', USER 'username', PASSWORD 'password');";}
    ],
    [
        'id' => RuleIds::FOREIGN_SERVER_EXISTS,
        'error_code' => [MySqlErrorCodes::ER_FOREIGN_SERVER_EXISTS],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Foreign server already exists/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Foreign server already exists: The specified foreign server for a federated table or foreign data wrapper already exists. You may need to drop the existing server first or use a different name.',
        'migration' => function($ctx) {return "DROP SERVER `".$ctx['server']."`;";}
    ],
    [
        'id' => RuleIds::ILLEGAL_HA_CREATE_OPTION,
        'error_code' => [MySqlErrorCodes::ER_ILLEGAL_HA_CREATE_OPTION],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Illegal HA create option/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Illegal HA create option: An invalid or unsupported storage engine option was specified during table creation. You need to check the storage engine options and correct them.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::UNKNOWN_LOCALE,
        'error_code' => [MySqlErrorCodes::ER_UNKNOWN_LOCALE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Unknown locale: .*/i',
        'extract' => function($normalized) {
            preg_match('/Unknown locale: (.*)/', $normalized->message, $matches);
            return ['locale' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Unknown locale: The specified locale "{locale}" is not recognized by MySQL. You need to use a valid locale identifier.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::SLAVE_IGNORE_SERVER_IDS,
        'error_code' => [MySqlErrorCodes::ER_SLAVE_IGNORE_SERVER_IDS],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Slave I/O thread is not running and can\'t be started when server id is in the ignore list/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Slave ignore server IDs: The slave server ID is in the ignore list, preventing the I/O thread from starting. You need to remove the server ID from the ignore list.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::QUERY_CACHE_DISABLED,
        'error_code' => [MySqlErrorCodes::ER_QUERY_CACHE_DISABLED],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Query cache is disabled/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Query cache is disabled: The query cache feature is disabled in the MySQL server configuration. You need to enable it in the server settings if you want to use query caching.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::INNODB_FULLTEXT_LIMIT,
        'error_code' => [MySqlErrorCodes::ER_INNODB_FT_LIMIT],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/InnoDB full-text index size limit reached/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'InnoDB full-text index size limit reached: The size limit for InnoDB full-text indexes has been reached. You need to optimize your full-text indexes or increase the limit in the server configuration.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::INNODB_NO_FULLTEXT_TEMP_TABLE,
        'error_code' => [MySqlErrorCodes::ER_INNODB_NO_FT_TEMP_TABLE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/InnoDB does not support temporary tables with full-text indexes/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'InnoDB does not support temporary tables with full-text indexes: You cannot create temporary tables with full-text indexes using the InnoDB storage engine. You need to use a different storage engine or avoid using full-text indexes on temporary tables.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::INNODB_FULLTEXT_WRONG_DOCID_COLUMN,
        'error_code' => [MySqlErrorCodes::ER_INNODB_FT_WRONG_DOCID_COLUMN],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/InnoDB full-text index has wrong docid column/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'InnoDB full-text index has wrong docid column: The document ID column used in the InnoDB full-text index is incorrect. You need to ensure that the correct column is specified for the full-text index.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::INNODB_FULLTEXT_WRONG_DOCID_INDEX,
        'error_code' => [MySqlErrorCodes::ER_INNODB_FT_WRONG_DOCID_INDEX],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/InnoDB full-text index has wrong docid index/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'InnoDB full-text index has wrong docid index: The document ID index used in the InnoDB full-text index is incorrect. You need to ensure that the correct index is specified for the full-text index.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::TABLE_IN_SYSTEM_TABLESPACE,
        'error_code' => [MySqlErrorCodes::ER_TABLE_IN_SYSTEM_TABLESPACE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Table is in system tablespace/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Table is in system tablespace: The table is located in the system tablespace, which may cause issues with certain operations. You need to move the table to a different tablespace if necessary.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::IO_READ_ERROR,
        'error_code' => [MySqlErrorCodes::ER_IO_READ_ERROR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/I\/O read error/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'I/O read error: There was an input/output error while reading data from disk. Check your disk health and permissions.',
        'migration' => function($ctx) {return '';} 
    ],
    [
        'id' => RuleIds::IO_WRITE_ERROR,
        'error_code' => [MySqlErrorCodes::ER_IO_WRITE_ERROR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/I\/O write error/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'I/O write error: There was an input/output error while writing data to disk. Check your disk health and permissions.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::TABLESPACE_MISSING,
        'error_code' => [MySqlErrorCodes::ER_TABLESPACE_MISSING],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Tablespace missing for table/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Tablespace missing for table: The tablespace for the specified table is missing. You need to recreate the tablespace or restore it from backup.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::TABLESPACE_EXISTS,
        'error_code' => [MySqlErrorCodes::ER_TABLESPACE_EXISTS],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Tablespace already exists for table/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Tablespace already exists for table: The tablespace for the specified table already exists. You may need to drop the existing tablespace first or use a different name.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::TABLESPACE_DISCARDED,
        'error_code' => [MySqlErrorCodes::ER_TABLESPACE_DISCARDED],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Tablespace is discarded for table/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Tablespace is discarded for table: The tablespace for the specified table has been discarded. You need to import the tablespace or recreate it.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::DUPLICATE_UNKNOWN_IN_INDEX,
        'error_code' => [MySqlErrorCodes::ER_DUP_UNKNOWN_IN_INDEX],
        'sqlstate' => [MySqlStates::CONSTANT_VIOLATION],
        'message_regex' => '/Duplicate entry found in index during index creation/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Duplicate entry found in index during index creation: There are duplicate entries in the data that violate the unique constraint of the index being created. You need to remove or resolve the duplicate entries before creating the index.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => RuleIds::IDENT_CAUSES_TOO_LONG_PATH,
        'error_code' => [MySqlErrorCodes::ER_IDENT_CAUSES_TOO_LONG_PATH],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Identifier name \'([^\']+)\' is too long/i',
        'extract' => function($normalized) {
            preg_match('/Identifier name \'([^\']+)\' is too long/', $normalized->message, $matches);
            return ['identifier' => $matches[1] ?? 'unknown'];
        },
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::ALTER_OPERATION_NOT_SUPPORTED_REASON_NOT_NULL,
        'error_code' => [MySqlErrorCodes::ER_ALTER_OPERATION_NOT_SUPPORTED_REASON_NOT_NULL],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Alter operation not supported reason: column \'([^\']+)\' is NOT NULL/i',
        'extract' => function($normalized) {
            return ['column' => $normalized->column ?? 'unknown'];
        },
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::MUST_CHANGE_PASSWORD_LOGIN,
        'error_code' => [MySqlErrorCodes::ER_MUST_CHANGE_PASSWORD_LOGIN],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/User must change password/i',
        'extract' => function($normalized) {
            return [];
        },
        'migration' => function ($ctx) {
            return "ALTER USER 'username'@'host' IDENTIFIED BY 'new_password';";
        }
    ],
    [
        'id' => RuleIds::ROW_IN_WRONG_PARTITION,
        'error_code' => [MySqlErrorCodes::ER_ROW_IN_WRONG_PARTITION],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Row does not belong to any partition/i',
        'extract' => function($normalized) {
            return [];
        },
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::STD_UNKNOWN_EXCEPTION,
        'error_code' => [MySqlErrorCodes::ER_STD_UNKNOWN_EXCEPTION],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Unknown exception/i',
        'extract' => function($normalized) {
            return [];
        },
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::GIS_DATA_WRONG_ENDIANESS,
        'error_code' => [MySqlErrorCodes::ER_GIS_DATA_WRONG_ENDIANESS],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/GIS data has wrong endianess/i',
        'extract' => function($normalized) {
            return [];
        },
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::CHANGE_MASTER_PASSWORD_LENGTH,
        'error_code' => [MySqlErrorCodes::ER_CHANGE_MASTER_PASSWORD_LENGTH],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Master password length is incorrect/i',
        'extract' => function($normalized) {
            return [];
        },
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::USER_LOCK_WRONG_NAME,
        'error_code' => [MySqlErrorCodes::ER_USER_LOCK_WRONG_NAME],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/User lock name is incorrect/i',
        'extract' => function($normalized) {
            return [];
        },
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::GROUP_REPLICATION_COMMUNICATION_LAYER_JOIN_ERROR,
        'error_code' => [MySqlErrorCodes::ER_GROUP_REPLICATION_COMMUNICATION_LAYER_JOIN_ERROR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Group Replication communication layer join error/i',
        'extract' => function($normalized) {
            return [];
        },
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::BEFORE_DML_VALIDATION_ERROR,
        'error_code' => [MySqlErrorCodes::ER_BEFORE_DML_VALIDATION_ERROR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Before DML validation error/i',
        'extract' => function($normalized) {
            return [];
        },
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::PREVENTS_VARIABLE_WITHOUT_RBR,
        'error_code' => [MySqlErrorCodes::ER_PREVENTS_VARIABLE_WITHOUT_RBR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Prevents variable without RBR/i',
        'extract' => function($normalized) {
            return [];
        },
        'migration' => function ($ctx) {
            return '';
        }
    ],

    // Recent Error Codes (3800+)
    [
        'id' => RuleIds::DEPRECATED_ZEROFILL,
        'error_code' => [MySqlErrorCodes::ER_WARN_DEPRECATED_ZEROFILL],
        'sqlstate' => [MySqlStates::WARNING],
        'message_regex' => '/ZEROFILL .* is deprecated/i',
        'extract' => function($normalized) {
            return ['column' => $normalized->column ?? 'unknown', 'table' => $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Deprecated ZEROFILL: The ZEROFILL attribute on column "{column}" is deprecated and will be removed in a future MySQL version. Consider removing it from your schema.',
        'migration' => function ($ctx) {
            return "-- Remove ZEROFILL from column:\nALTER TABLE `{$ctx['table']}` MODIFY `{$ctx['column']}` INT NOT NULL;";
        }
    ],
    [
        'id' => RuleIds::CLONE_DONOR_ERROR,
        'error_code' => [MySqlErrorCodes::ER_CLONE_DONOR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Clone Donor Error/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Clone Donor Error: An error occurred on the clone donor server. Check the donor server logs for more details.',
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::CLONE_PROTOCOL_ERROR,
        'error_code' => [MySqlErrorCodes::ER_CLONE_PROTOCOL],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Clone Protocol Error/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Clone Protocol Error: A protocol error occurred during the clone operation. Ensure both donor and recipient are compatible MySQL versions.',
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::CLONE_DONOR_VERSION_MISMATCH,
        'error_code' => [MySqlErrorCodes::ER_CLONE_DONOR_VERSION],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Clone Donor version mismatch/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Clone Donor Version Mismatch: The donor server version is incompatible with the recipient. Both servers must be running compatible MySQL versions.',
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::UNKNOWN_ERROR_NUMBER,
        'error_code' => [MySqlErrorCodes::ER_DA_UNKNOWN_ERROR_NUMBER],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Unknown error number/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Unknown Error Number: An unknown error occurred. This may indicate a bug or corrupted data.',
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::COLUMN_CHANGE_SIZE,
        'error_code' => [MySqlErrorCodes::ER_COLUMN_CHANGE_SIZE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Column .* cannot be changed/i',
        'extract' => function($normalized) {
            return ['column' => $normalized->column ?? 'unknown'];
        },
        'explanation' => 'Column Change Size Error: Cannot change the size of column "{column}". This operation may require rebuilding the table or is not supported for this column type.',
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::REGEXP_INVALID_CAPTURE_GROUP,
        'error_code' => [MySqlErrorCodes::ER_REGEXP_INVALID_CAPTURE_GROUP_NAME],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Invalid capture group name/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Invalid Regex Capture Group: The regular expression contains an invalid capture group name. Check your regex syntax.',
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::SSL_LIBRARY_ERROR,
        'error_code' => [MySqlErrorCodes::ER_DA_SSL_LIBRARY_ERROR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/SSL library error/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'SSL Library Error: An error occurred in the SSL library. Check your SSL configuration and certificates.',
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::LOCAL_FILES_DISABLED,
        'error_code' => [MySqlErrorCodes::ER_CLIENT_LOCAL_FILES_DISABLED],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Loading local data is disabled/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Local Files Disabled: The LOAD DATA LOCAL INFILE command is disabled. Enable it by setting local_infile=1 in your MySQL configuration.',
        'migration' => function ($ctx) {
            return "-- Enable local infile in MySQL configuration:\n-- SET GLOBAL local_infile = 1;";
        }
    ],
    [
        'id' => RuleIds::INCOMPATIBLE_CONFIG_VERSION,
        'error_code' => [MySqlErrorCodes::ER_IMP_INCOMPATIBLE_CFG_VERSION],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Incompatible .* version/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Incompatible Configuration Version: The configuration file version is incompatible with the current MySQL version.',
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::OUT_OF_MEMORY,
        'error_code' => [MySqlErrorCodes::ER_DA_OOM],
        'sqlstate' => [MySqlStates::OUT_OF_MEMORY],
        'message_regex' => '/Out of memory/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Out of Memory: MySQL ran out of memory. Consider increasing the memory allocation or optimizing your queries.',
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::UDF_INVALID_CHARSET_ARGUMENT,
        'error_code' => [MySqlErrorCodes::ER_DA_UDF_INVALID_ARGUMENT_TO_SET_CHARSET],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Invalid argument to set charset/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'UDF Invalid Charset Argument: An invalid charset argument was passed to a user-defined function.',
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::MISPLACED_INTO,
        'error_code' => [MySqlErrorCodes::ER_MISPLACED_INTO],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Misplaced INTO/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Misplaced INTO: The INTO clause is in the wrong position in your SQL query. Check the SQL syntax.',
        'migration' => function ($ctx) {
            return '';
        }
    ],
    [
        'id' => RuleIds::USER_ACCOUNT_BLOCKED,
        'error_code' => [MySqlErrorCodes::ER_USER_ACCESS_DENIED_FOR_USER_ACCOUNT_BLOCKED_BY_PASSWORD_LOCK],
        'sqlstate' => [MySqlStates::SYNTAX_ERROR_OR_ACCESS_VIOLATION],
        'message_regex' => '/Account .* is blocked/i',
        'extract' => function($normalized) {
            preg_match('/Account \'([^\']+)\'/', $normalized->message, $matches);
            return ['user' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'User Account Blocked: The user account "{user}" is blocked due to too many failed login attempts. Wait for the lockout period to expire or have an administrator unlock the account.',
        'migration' => function ($ctx) {
            return "-- Unlock the user account:\nALTER USER 'username'@'%' ACCOUNT UNLOCK;";
        }
    ],
    [
        'id' => RuleIds::DEPRECATED_YEAR_UNSIGNED,
        'error_code' => [MySqlErrorCodes::ER_WARN_DEPRECATED_YEAR_UNSIGNED],
        'sqlstate' => [MySqlStates::WARNING],
        'message_regex' => '/UNSIGNED .* YEAR .* is deprecated/i',
            'extract' => function($normalized) {
            return ['column' => $normalized->column ?? 'unknown', 'table' => $normalized->table ?? 'unknown'];
        },
        'explanation' => 'Deprecated UNSIGNED YEAR: The UNSIGNED attribute on YEAR column "{column}" is deprecated. Remove the UNSIGNED attribute from the column definition.',
        'migration' => function ($ctx) {
            return "-- Remove UNSIGNED from YEAR column:\nALTER TABLE `{$ctx['table']}` MODIFY `{$ctx['column']}` YEAR NOT NULL;";
        }
    ],
    [
        'id' => RuleIds::CLONE_NETWORK_PACKET_ERROR,
        'error_code' => [MySqlErrorCodes::ER_CLONE_NETWORK_PACKET],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Clone network packet error/i',
        'extract' => function($normalized) {
            return [];
        },
        'explanation' => 'Clone Network Packet Error: A network error occurred during the clone operation. Check network connectivity between donor and recipient servers.',
        'migration' => function ($ctx) {
            return '';
        }
    ]
];

