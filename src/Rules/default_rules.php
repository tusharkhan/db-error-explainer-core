<?php

use TusharKhan\DbErrorExplainer\Lib\MySqlErrorCodes;

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
        'sqlstate' => ['3D000'],
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
        'sqlstate' => ['HY000'],
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
        'sqlstate' => ['HY000'],
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
        'error_code' => [1452],
        'sqlstate' => ['23000'],
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
            return "// To fix this issue, ensure the parent record exists:\n"
                . "// INSERT INTO {$ctx['parent_table']} (id) VALUES (?);\n\n"
                . "// Or add the foreign key constraint if missing:\n"
                . "Schema::table('{$ctx['table']}', function (Blueprint \$table) {\n"
                . "    \$table->foreign('{$ctx['column']}')\n"
                . "          ->references('id')\n"
                . "          ->on('{$ctx['parent_table']}')\n"
                . "          ->onDelete('cascade');\n"
                . "});";
        },
    ],

    // Foreign Key Constraint - Cannot Delete Parent
    [
        'id' => 'fk_cannot_delete_parent',
        'error_code' => [1451],
        'sqlstate' => ['23000'],
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
            return "// Option 1: Add CASCADE to automatically delete child records:\n"
                . "Schema::table('{$ctx['child_table']}', function (Blueprint \$table) {\n"
                . "    \$table->dropForeign(['{$ctx['constraint']}']);\n"
                . "    \$table->foreign('parent_id')\n"
                . "          ->references('id')\n"
                . "          ->on('{$ctx['table']}')\n"
                . "          ->onDelete('cascade');\n"
                . "});\n\n"
                . "// Option 2: Set NULL on delete:\n"
                . "// ->onDelete('set null');";
        },
    ],

    // Duplicate Entry (Unique Constraint Violation)
    [
        'id' => 'duplicate_entry',
        'error_code' => [1062],
        'sqlstate' => ['23000'],
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
            return "// If this unique constraint is incorrect, remove it:\n"
                . "Schema::table('{$ctx['table']}', function (Blueprint \$table) {\n"
                . "    \$table->dropUnique('{$ctx['key']}');\n"
                . "});\n\n"
                . "// Or use updateOrCreate to handle duplicates:\n"
                . "// Model::updateOrCreate(['email' => \$email], \$data);";
        },
    ],

    // NOT NULL Constraint Violation
    [
        'id' => 'null_constraint_violation',
        'error_code' => [1048, 1364],
        'sqlstate' => ['23000', 'HY000'],
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
            return "// Option 1: Make the column nullable:\n"
                . "Schema::table('{$ctx['table']}', function (Blueprint \$table) {\n"
                . "    \$table->string('{$ctx['column']}')->nullable()->change();\n"
                . "});\n\n"
                . "// Option 2: Set a default value:\n"
                . "// \$table->string('{$ctx['column']}')->default('value')->change();\n\n"
                . "// Option 3: Always provide the value in your code:\n"
                . "// Model::create(['{$ctx['column']}' => 'required_value']);";
        },
    ],

    // Unknown Column
    [
        'id' => 'unknown_column',
        'error_code' => [1054],
        'sqlstate' => ['42S22'],
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
            return "// Add the missing column:\n"
                . "Schema::table('{$ctx['table']}', function (Blueprint \$table) {\n"
                . "    \$table->string('{$ctx['column']}')->nullable();\n"
                . "    // Adjust the data type as needed:\n"
                . "    // \$table->integer('{$ctx['column']}');\n"
                . "    // \$table->text('{$ctx['column']}');\n"
                . "    // \$table->timestamp('{$ctx['column']}');\n"
                . "});";
        },
    ],

    // Table Doesn't Exist
    [
        'id' => 'table_not_found',
        'error_code' => [1146],
        'sqlstate' => ['42S02'],
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
            return "// Create the missing table:\n"
                . "Schema::create('{$ctx['table']}', function (Blueprint \$table) {\n"
                . "    \$table->id();\n"
                . "    // Add your columns here\n"
                . "    \$table->timestamps();\n"
                . "});\n\n"
                . "// Or run pending migrations:\n"
                . "// php artisan migrate";
        },
    ],

    // Data Too Long
    [
        'id' => 'data_too_long',
        'error_code' => [1406],
        'sqlstate' => ['22001'],
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
            return "// Increase the column size:\n"
                . "Schema::table('{$ctx['table']}', function (Blueprint \$table) {\n"
                . "    // Change VARCHAR to larger size:\n"
                . "    \$table->string('{$ctx['column']}', 500)->change();\n"
                . "    \n"
                . "    // Or change to TEXT for unlimited length:\n"
                . "    // \$table->text('{$ctx['column']}')->change();\n"
                . "});";
        },
    ],

    // Out of Range Value
    [
        'id' => 'out_of_range',
        'error_code' => [MySqlErrorCodes::ER_WARN_DATA_OUT_OF_RANGE],
        'sqlstate' => ['22003'],
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
            return "// Change to a larger integer type:\n"
                . "Schema::table('{$ctx['table']}', function (Blueprint \$table) {\n"
                . "    // TINYINT → SMALLINT → MEDIUMINT → INT → BIGINT\n"
                . "    \$table->integer('{$ctx['column']}')->change();  // INT\n"
                . "    // \$table->bigInteger('{$ctx['column']}')->change();  // BIGINT\n"
                . "    // \$table->unsignedBigInteger('{$ctx['column']}')->change();  // Unsigned for larger positive values\n"
                . "});";
        },
    ],

    // Invalid Default Value
    [
        'id' => 'invalid_default',
        'error_code' => [1067],
        'sqlstate' => ['42000'],
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
            return "// Fix the default value:\n"
                . "Schema::table('{$ctx['table']}', function (Blueprint \$table) {\n"
                . "    \$table->timestamp('{$ctx['column']}')->useCurrent()->change();\n"
                . "    // Or remove the default:\n"
                . "    // \$table->timestamp('{$ctx['column']}')->nullable()->change();\n"
                . "});";
        },
    ],

    // Syntax Error
    [
        'id' => 'syntax_error',
        'error_code' => [1064],
        'sqlstate' => ['42000'],
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
];

