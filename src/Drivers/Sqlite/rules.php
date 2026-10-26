<?php

use TusharKhan\DbErrorExplainer\Drivers\Sqlite\SqliteMessageParser;
use TusharKhan\DbErrorExplainer\Lib\RuleIds;
use TusharKhan\DbErrorExplainer\Lib\SqliteErrorCodes;

// SQLite codes are coarse (1 = any SQL error, 19 = any constraint), so most rules also need their message to match.

$parser = new SqliteMessageParser();
$q = fn(?string $name, string $fallback) => '"' . str_replace('"', '""', $name ?: $fallback) . '"';

return [
    // Constraints (code 19)
    [
        'id' => RuleIds::DUPLICATE_ENTRY,
        'error_code' => [SqliteErrorCodes::SQLITE_CONSTRAINT],
        'message_regex' => '/UNIQUE constraint failed/i',
        'require_message' => true,
        'extract' => fn($normalized) => ['columns' => implode(', ', $parser->constraintColumns($normalized->message)) ?: 'unknown'],
        'explanation' => 'Duplicate entry: A row with the same {columns} already exists in "{table}", and a UNIQUE constraint (or the primary key) does not allow another.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => "Check before inserting, use INSERT ... ON CONFLICT({$ctx['columns']}) DO NOTHING / DO UPDATE, or Laravel's firstOrCreate() / upsert(). Validate with the 'unique' rule.",
        ],
    ],
    [
        'id' => RuleIds::FOREIGN_KEY_VIOLATION,
        'error_code' => [SqliteErrorCodes::SQLITE_CONSTRAINT],
        'message_regex' => '/FOREIGN KEY constraint failed/i',
        'require_message' => true,
        'extract' => fn($normalized) => [],
        'explanation' => 'Foreign key violation: Either the row points to a parent that does not exist, or you are deleting / updating a parent that other rows still reference. SQLite does not say which table or column.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => "-- List rows that break foreign keys:\nPRAGMA foreign_key_check;",
            'other_suggestions' => "On insert: make sure the parent id exists (Laravel 'exists' rule). On delete: delete the children first or use ->cascadeOnDelete().",
        ],
    ],
    [
        'id' => RuleIds::NULL_CONSTRAINT_VIOLATION,
        'error_code' => [SqliteErrorCodes::SQLITE_CONSTRAINT],
        'message_regex' => '/NOT NULL constraint failed/i',
        'require_message' => true,
        'extract' => fn($normalized) => ['column' => $normalized->column ?? 'unknown'],
        'explanation' => 'NULL not allowed: Column "{column}" of "{table}" cannot be NULL. Provide a value or give the column a default.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => "SQLite cannot ALTER a column in place. In a Laravel migration: \$table->string('{$ctx['column']}')->nullable()->change(); (Laravel rebuilds the table for you).",
        ],
    ],
    [
        'id' => RuleIds::CHECK_VIOLATION,
        'error_code' => [SqliteErrorCodes::SQLITE_CONSTRAINT],
        'message_regex' => '/CHECK constraint failed/i',
        'require_message' => true,
        'extract' => function ($normalized) {
            preg_match('/CHECK constraint failed: (.+)$/', $normalized->message, $matches);
            return ['condition' => trim($matches[1] ?? 'unknown')];
        },
        'explanation' => 'Check constraint failed: The row does not satisfy "{condition}". Change the value so the condition holds.',
        'migration' => fn($ctx) => '',
    ],
    [
        'id' => RuleIds::INCORRECT_VALUE,
        'error_code' => [SqliteErrorCodes::SQLITE_CONSTRAINT, SqliteErrorCodes::SQLITE_MISMATCH],
        'message_regex' => '/cannot store \w+ value|datatype mismatch/i',
        'require_message' => true,
        'extract' => function ($normalized) {
            preg_match('/cannot store (\w+) value in (\w+) column/i', $normalized->message, $matches);
            return ['value_type' => $matches[1] ?? 'this', 'type' => $matches[2] ?? 'the declared'];
        },
        'explanation' => 'Incorrect value: A {value_type} value cannot be stored in a {type} column of a STRICT table (or an INTEGER PRIMARY KEY). Cast or validate the value first.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => "Validate the input (e.g. Laravel 'integer' rule) or add a cast on the model.",
        ],
    ],

    // Schema and query (code 1)
    [
        'id' => RuleIds::UNKNOWN_COLUMN,
        'error_code' => [SqliteErrorCodes::SQLITE_ERROR],
        'message_regex' => '/no such column|has no column named/i',
        'require_message' => true,
        'extract' => fn($normalized) => ['column' => $normalized->column ?? 'unknown'],
        'explanation' => 'Unknown column: "{column}" does not exist. Check for typos or run pending migrations.',
        'migration' => fn($ctx) => "ALTER TABLE {$q($ctx['table'], 'your_table')} ADD COLUMN {$q($ctx['column'], 'your_column')} TEXT;",
    ],
    [
        'id' => RuleIds::TABLE_NOT_FOUND,
        'error_code' => [SqliteErrorCodes::SQLITE_ERROR],
        'message_regex' => '/no such table/i',
        'require_message' => true,
        'extract' => fn($normalized) => [],
        'explanation' => 'Table not found: "{table}" does not exist in this SQLite database. Run pending migrations, and check you are using the database file you expect.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => 'Laravel: php artisan migrate. Tests: use the RefreshDatabase trait so the in-memory database is migrated.',
        ],
    ],
    [
        'id' => RuleIds::TABLE_ALREADY_EXISTS,
        'error_code' => [SqliteErrorCodes::SQLITE_ERROR],
        'message_regex' => '/table "?\w+"? already exists/i',
        'require_message' => true,
        'extract' => fn($normalized) => [],
        'explanation' => 'Table already exists: "{table}" already exists. The migration probably ran before.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => "CREATE TABLE IF NOT EXISTS {$q($ctx['table'], 'your_table')} (...);",
            'other_suggestions' => "Guard the migration with Schema::hasTable('{$ctx['table']}').",
        ],
    ],
    [
        'id' => RuleIds::DUPLICATE_COLUMN,
        'error_code' => [SqliteErrorCodes::SQLITE_ERROR],
        'message_regex' => '/duplicate column name/i',
        'require_message' => true,
        'extract' => fn($normalized) => ['column' => $normalized->column ?? 'unknown'],
        'explanation' => 'Duplicate column: "{column}" already exists. The migration was probably run before or adds the column twice.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => "Guard the migration with Schema::hasColumn('your_table', '{$ctx['column']}').",
        ],
    ],
    [
        'id' => RuleIds::AMBIGUOUS_COLUMN,
        'error_code' => [SqliteErrorCodes::SQLITE_ERROR],
        'message_regex' => '/ambiguous column name/i',
        'require_message' => true,
        'extract' => fn($normalized) => ['column' => $normalized->column ?? 'unknown'],
        'explanation' => 'Ambiguous column: "{column}" exists in more than one joined table. Prefix it with the table name, e.g. users.{column}.',
        'migration' => fn($ctx) => '',
    ],
    [
        'id' => RuleIds::SYNTAX_ERROR,
        'error_code' => [SqliteErrorCodes::SQLITE_ERROR],
        'message_regex' => '/syntax error|incomplete input|unrecognized token/i',
        'require_message' => true,
        'extract' => function ($normalized) {
            preg_match('/near "([^"]*)"/', $normalized->message, $matches);
            return ['near' => $matches[1] ?? 'the end of the statement'];
        },
        'explanation' => 'Syntax error near "{near}": Check for typos, missing commas or quotes, and keywords used as names (quote them with double quotes).',
        'migration' => fn($ctx) => '',
    ],
    [
        'id' => RuleIds::WRONG_VALUE_COUNT,
        'error_code' => [SqliteErrorCodes::SQLITE_ERROR],
        'message_regex' => '/\d+ values for \d+ columns/i',
        'require_message' => true,
        'extract' => function ($normalized) {
            preg_match('/(\d+) values for (\d+) columns/', $normalized->message, $matches);
            return ['values' => $matches[1] ?? '?', 'columns' => $matches[2] ?? '?'];
        },
        'explanation' => 'Column count mismatch: The INSERT gives {values} values for {columns} columns. Make the column list and VALUES match.',
        'migration' => fn($ctx) => '',
    ],
    [
        'id' => RuleIds::CANT_DROP_COLUMN_OR_KEY,
        'error_code' => [SqliteErrorCodes::SQLITE_ERROR],
        'message_regex' => '/no such index/i',
        'require_message' => true,
        'extract' => function ($normalized) {
            preg_match('/no such index: (\w+)/', $normalized->message, $matches);
            return ['index' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Can\'t drop: The index "{index}" does not exist. The migration may have run already or the name is wrong.',
        'migration' => fn($ctx) => "DROP INDEX IF EXISTS {$q($ctx['index'], 'your_index')};",
    ],
    [
        'id' => RuleIds::FUNCTION_NOT_DEFINED,
        'error_code' => [SqliteErrorCodes::SQLITE_ERROR],
        'message_regex' => '/no such function/i',
        'require_message' => true,
        'extract' => function ($normalized) {
            preg_match('/no such function: (\w+)/', $normalized->message, $matches);
            return ['function' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Function not found: SQLite has no function "{function}". MySQL/PostgreSQL functions (e.g. NOW(), CONCAT()) often differ in SQLite.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => "Use the SQLite equivalent (datetime('now'), || for concatenation) or keep the query database-agnostic with the query builder.",
        ],
    ],

    // Database file
    [
        'id' => RuleIds::LOCK_WAIT_TIMEOUT,
        'error_code' => [SqliteErrorCodes::SQLITE_BUSY, SqliteErrorCodes::SQLITE_LOCKED],
        'message_regex' => '/database is locked|database table is locked/i',
        'extract' => fn($normalized) => [],
        'explanation' => 'Database is locked: Another connection is writing to the SQLite file and this one gave up waiting. SQLite allows only one writer at a time.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => "PRAGMA journal_mode = WAL;\nPRAGMA busy_timeout = 5000;",
            'other_suggestions' => "Laravel: set 'busy_timeout' and 'journal_mode' => 'wal' on the sqlite connection, keep transactions short, and avoid many concurrent queue workers writing to SQLite.",
        ],
    ],
    [
        'id' => RuleIds::READONLY_DATABASE,
        'error_code' => [SqliteErrorCodes::SQLITE_READONLY],
        'message_regex' => '/readonly database/i',
        'extract' => fn($normalized) => [],
        'explanation' => 'Read-only database: The process cannot write the SQLite file. SQLite needs write access to the file and to its directory (for the journal).',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => "Give the web server user write access, e.g. chown www-data database/ database/database.sqlite && chmod 664 database/database.sqlite. Also check the file was not opened with mode=ro.",
        ],
    ],
    [
        'id' => RuleIds::CANNOT_OPEN_DATABASE,
        'error_code' => [SqliteErrorCodes::SQLITE_CANTOPEN],
        'message_regex' => '/unable to open database file/i',
        'extract' => fn($normalized) => [],
        'explanation' => 'Cannot open database: The SQLite file or its directory does not exist, or the process cannot access it.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => "Create the file (touch database/database.sqlite), use an absolute path in DB_DATABASE, and make sure the directory exists and is writable.",
        ],
    ],
    [
        'id' => RuleIds::NOT_A_DATABASE,
        'error_code' => [SqliteErrorCodes::SQLITE_NOTADB],
        'message_regex' => '/file is not a database/i',
        'extract' => fn($normalized) => [],
        'explanation' => 'Not a database: The file DB_DATABASE points to is not an SQLite database (or is encrypted or corrupted).',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => 'Check DB_DATABASE points to the right file. If the file is disposable, delete it, recreate it and run the migrations.',
        ],
    ],
];
