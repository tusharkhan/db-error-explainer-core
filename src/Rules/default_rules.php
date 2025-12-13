<?php

use TusharKhan\DbErrorExplainer\Lib\MySqlErrorCodes;
use TusharKhan\DbErrorExplainer\Lib\MySqlStates;

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
        'id' => 'table_already_exists',
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
        'id' => 'no_database_selected',
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
        'id' => 'cant_create_file',
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
        'id' => 'cant_create_table',
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
    // Foreign Key Constraint - Missing Parent Row
    [
        'id' => 'fk_missing_parent',
        'error_code' => [MySqlErrorCodes::ER_NO_REFERENCED_ROW_2],
        'sqlstate' => [MySqlStates::CONSTANT_VIOLATION],
        'message_regex' => '/foreign key constraint fails.*Cannot add or update/i',
        'extract' => function($normalized) {
            // Extract table and column from error message
            preg_match('/CONSTRAINT `([^`]+)`/', $normalized->message, $constraint);
            preg_match('/FOREIGN KEY \(`([^`]+)`\)/', $normalized->message, $column);
            preg_match('/REFERENCES `([^`]+)`/', $normalized->message, $parentTable);

            return [
                'table' => $normalized->table ?? 'unknown',
                'column' => $column[1] ?? 'unknown',
                'parent_table' => $parentTable[1] ?? 'unknown',
                'constraint' => $constraint[1] ?? null,
            ];
        },
        'explanation' => 'Foreign key constraint violation: The value you are trying to insert or update in column "{column}" does not exist in the parent table "{parent_table}". You must first insert the referenced record in the parent table.',
        'migration' => function($ctx) {
            return "ALTER TABLE `{$ctx['table']}`
                ADD CONSTRAINT `fk_{$ctx['table']}_{$ctx['column']}`
                FOREIGN KEY (`{$ctx['column']}`)
                REFERENCES `{$ctx['parent_table']}`(`id`)
                ON DELETE CASCADE;
            ";
        },
    ],

    // Foreign Key Constraint - Cannot Delete Parent
    [
        'id' => 'fk_cannot_delete_parent',
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
        'id' => 'duplicate_entry',
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
        'id' => 'null_constraint_violation',
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
        'id' => 'unknown_column',
        'error_code' => [MySqlErrorCodes::ER_BAD_FIELD_ERROR],
        'sqlstate' => [MySqlStates::COLUMN_NOT_FOUND],
        'message_regex' => '/Unknown column .* in/i',
        'extract' => function($normalized) {
            preg_match('/Unknown column \'([^\']+)\' in \'([^\']+)\'/', $normalized->message, $matches);

            return [
                'column' => $matches[1] ?? 'unknown',
                'clause' => $matches[2] ?? 'field list',
                'table' => $normalized->table ?? 'unknown',
            ];
        },
        'explanation' => 'Unknown column: Column "{column}" does not exist in the table. You may have a typo or the column needs to be added via migration.',
        'migration' => function($ctx) {
            return "-- Add the missing column:\n\nALTER TABLE `{$ctx['table']}` ADD `{$ctx['column']}` VARCHAR(255) NULL;\n\n --column type can be any valid MySQL column type";
        },
    ],

    // Table Doesn't Exist
    [
        'id' => 'table_not_found',
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
            return "CREATE TABLE `{$ctx['table']}` (id INT PRIMARY KEY AUTO_INCREMENT); \n\n -- Add columns as needed";
        },
    ],

    // Data Too Long
    [
        'id' => 'data_too_long',
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
        'id' => 'out_of_range',
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
        'id' => 'invalid_default',
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
        'id' => 'syntax_error',
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
        'id' => 'cant_create_database',
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
        'id' => 'cant_create_database_exists',
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
        'id' => 'cant_drop_database',
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
        'id' => 'cant_drop_database_filesystem_issue',
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
        'id' => 'record_cant_read',
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
        'id' => 'name_becomes_empty',
        'error_code' => [MySqlErrorCodes::ER_NAME_BECOMES_EMPTY],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Name has become empty when checking privilege/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Name has become empty: A name you provided (or MySQL generated automatically) turned into an empty string.This is a schema/metadata naming error.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'ambiguous_column_name',
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
        'id' => 'foreign_server_doesnt_exist',
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
        'id' => 'foreign_server_exists',
        'error_code' => [MySqlErrorCodes::ER_FOREIGN_SERVER_EXISTS],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Foreign server already exists/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Foreign server already exists: The specified foreign server for a federated table or foreign data wrapper already exists. You may need to drop the existing server first or use a different name.',
        'migration' => function($ctx) {return "DROP SERVER `".$ctx['server']."`;";}
    ],
    [
        'id' => 'illegal_ha_create_option',
        'error_code' => [MySqlErrorCodes::ER_ILLEGAL_HA_CREATE_OPTION],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Illegal HA create option/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Illegal HA create option: An invalid or unsupported storage engine option was specified during table creation. You need to check the storage engine options and correct them.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'unknown_locale',
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
        'id' => 'slave_ignore_server_ids',
        'error_code' => [MySqlErrorCodes::ER_SLAVE_IGNORE_SERVER_IDS],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Slave I/O thread is not running and can\'t be started when server id is in the ignore list/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Slave ignore server IDs: The slave server ID is in the ignore list, preventing the I/O thread from starting. You need to remove the server ID from the ignore list.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'query_cache_disabled',
        'error_code' => [MySqlErrorCodes::ER_QUERY_CACHE_DISABLED],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Query cache is disabled/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Query cache is disabled: The query cache feature is disabled in the MySQL server configuration. You need to enable it in the server settings if you want to use query caching.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'innodb_fulltext_limit',
        'error_code' => [MySqlErrorCodes::ER_INNODB_FT_LIMIT],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/InnoDB full-text index size limit reached/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'InnoDB full-text index size limit reached: The size limit for InnoDB full-text indexes has been reached. You need to optimize your full-text indexes or increase the limit in the server configuration.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'innodb_no_fulltext_temp_table',
        'error_code' => [MySqlErrorCodes::ER_INNODB_NO_FT_TEMP_TABLE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/InnoDB does not support temporary tables with full-text indexes/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'InnoDB does not support temporary tables with full-text indexes: You cannot create temporary tables with full-text indexes using the InnoDB storage engine. You need to use a different storage engine or avoid using full-text indexes on temporary tables.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'innodb_fulltext_wrong_docid_column',
        'error_code' => [MySqlErrorCodes::ER_INNODB_FT_WRONG_DOCID_COLUMN],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/InnoDB full-text index has wrong docid column/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'InnoDB full-text index has wrong docid column: The document ID column used in the InnoDB full-text index is incorrect. You need to ensure that the correct column is specified for the full-text index.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'innodb_fulltext_wrong_docid_index',
        'error_code' => [MySqlErrorCodes::ER_INNODB_FT_WRONG_DOCID_INDEX],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/InnoDB full-text index has wrong docid index/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'InnoDB full-text index has wrong docid index: The document ID index used in the InnoDB full-text index is incorrect. You need to ensure that the correct index is specified for the full-text index.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'table_in_system_tablespace',
        'error_code' => [MySqlErrorCodes::ER_TABLE_IN_SYSTEM_TABLESPACE],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Table is in system tablespace/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Table is in system tablespace: The table is located in the system tablespace, which may cause issues with certain operations. You need to move the table to a different tablespace if necessary.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'io_read_error',
        'error_code' => [MySqlErrorCodes::ER_IO_READ_ERROR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/I\/O read error/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'I/O read error: There was an input/output error while reading data from disk. Check your disk health and permissions.',
        'migration' => function($ctx) {return '';} 
    ],
    [
        'id' => 'io_write_error',
        'error_code' => [MySqlErrorCodes::ER_IO_WRITE_ERROR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/I\/O write error/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'I/O write error: There was an input/output error while writing data to disk. Check your disk health and permissions.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'tablespace_missing',
        'error_code' => [MySqlErrorCodes::ER_TABLESPACE_MISSING],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Tablespace missing for table/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Tablespace missing for table: The tablespace for the specified table is missing. You need to recreate the tablespace or restore it from backup.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'tablespace_exists',
        'error_code' => [MySqlErrorCodes::ER_TABLESPACE_EXISTS],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Tablespace already exists for table/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Tablespace already exists for table: The tablespace for the specified table already exists. You may need to drop the existing tablespace first or use a different name.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'tablespace_discarded',
        'error_code' => [MySqlErrorCodes::ER_TABLESPACE_DISCARDED],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Tablespace is discarded for table/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Tablespace is discarded for table: The tablespace for the specified table has been discarded. You need to import the tablespace or recreate it.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'duplicate_unknown_in_index',
        'error_code' => [MySqlErrorCodes::ER_DUP_UNKNOWN_IN_INDEX],
        'sqlstate' => [MySqlStates::CONSTANT_VIOLATION],
        'message_regex' => '/Duplicate entry found in index during index creation/i',
        'extract' => function($normalized) {return [];},
        'explanation' => 'Duplicate entry found in index during index creation: There are duplicate entries in the data that violate the unique constraint of the index being created. You need to remove or resolve the duplicate entries before creating the index.',
        'migration' => function($ctx) {return '';}
    ],
    [
        'id' => 'ident_causes_too_long_path',
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
        'id' => 'alter_operation_not_supported_reason_not_null',
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
        'id' => 'must_change_password_login',
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
        'id' => 'row_in_wrong_partition',
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
        'id' => 'std_unknown_exception',
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
        'id' => 'gis_data_wrong_endianess',
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
        'id' => 'change_master_password_length',
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
        'id' => 'user_lock_wrong_name',
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
        'id' => 'group_replication_communication_layer_join_error',
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
        'id' => 'before_dml_validation_error',
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
        'id' => 'prevents_variable_without_rbr',
        'error_code' => [MySqlErrorCodes::ER_PREVENTS_VARIABLE_WITHOUT_RBR],
        'sqlstate' => [MySqlStates::GENERAL_ERROR],
        'message_regex' => '/Prevents variable without RBR/i',
        'extract' => function($normalized) {
            return [];
        },
        'migration' => function ($ctx) {
            return '';
        }
    ]
];

