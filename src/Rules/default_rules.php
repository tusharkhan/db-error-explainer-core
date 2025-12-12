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
            preg_match('/CREATE DATABASE `([^`]+)`/', $normalized->message, $matches);
            return ['database' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Can\'t create database: There was an error creating the database "{database}". Check your database name or permissions.',
        'migration' => function($ctx) {
            return 'CREATE DATABASE `{$ctx["database"]}`.';
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
    ]
];

