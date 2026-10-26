<?php

use TusharKhan\DbErrorExplainer\Drivers\PgSql\PgSqlMessageParser;
use TusharKhan\DbErrorExplainer\Lib\PgSqlStates;
use TusharKhan\DbErrorExplainer\Lib\RuleIds;

// PostgreSQL rules match on SQLSTATE (the driver code is always 7). Rule ids are shared with MySQL.

$parser = new PgSqlMessageParser();
$q = fn(?string $name, string $fallback) => '"' . str_replace('"', '""', $name ?: $fallback) . '"';

return [
    // Integrity constraints
    [
        'id' => RuleIds::DUPLICATE_ENTRY,
        'sqlstate' => [PgSqlStates::UNIQUE_VIOLATION],
        'message_regex' => '/duplicate key value violates unique constraint/i',
        'extract' => function ($normalized) use ($parser) {
            $key = $parser->keyValue($normalized->message);
            return [
                'column' => $key['columns'] ?? $normalized->column ?? 'unknown',
                'value' => $key['value'] ?? 'unknown',
                'constraint' => $normalized->constraint['constraint_name'] ?? 'unknown',
            ];
        },
        'explanation' => 'Duplicate entry: A row with {column} = "{value}" already exists, and the unique constraint "{constraint}" does not allow another.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => "Check before inserting, use INSERT ... ON CONFLICT ({$ctx['column']}) DO NOTHING / DO UPDATE, or Laravel's firstOrCreate() / upsert(). Validate with the 'unique' rule.",
        ],
    ],
    [
        'id' => RuleIds::FK_MISSING_PARENT,
        'sqlstate' => [PgSqlStates::FOREIGN_KEY_VIOLATION],
        'message_regex' => '/insert or update on table/i',
        'extract' => function ($normalized) use ($parser) {
            $key = $parser->keyValue($normalized->message);
            return [
                'column' => $key['columns'] ?? 'unknown',
                'value' => $key['value'] ?? 'unknown',
                'parent_table' => $normalized->constraint['referenced_table'] ?? 'unknown',
            ];
        },
        'explanation' => 'Foreign key violation: {column} = "{value}" does not exist in the parent table "{parent_table}". Create the parent row first or use an existing id.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => "Validate with Laravel's 'exists:{$ctx['parent_table']},id' rule before saving.",
        ],
    ],
    [
        'id' => RuleIds::FK_CANNOT_DELETE_PARENT,
        'sqlstate' => [PgSqlStates::FOREIGN_KEY_VIOLATION],
        'message_regex' => '/update or delete on table/i',
        'extract' => function ($normalized) use ($parser) {
            preg_match('/referenced from table "(?:\w+\.)?(\w+)"/', $normalized->message, $matches);
            return [
                'child_table' => $matches[1] ?? 'unknown',
                'constraint' => $normalized->constraint['constraint_name'] ?? 'unknown',
                'parent_column' => $parser->keyValue($normalized->message)['columns'] ?? 'id',
            ];
        },
        'explanation' => 'Cannot delete or update parent row: Rows in "{child_table}" still reference this row of "{table}" (constraint "{constraint}"). Delete or update those rows first, or let the foreign key cascade.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => "-- Let deletes cascade to {$ctx['child_table']} (replace your_column with its foreign key column):\nALTER TABLE {$q($ctx['child_table'], 'child_table')} DROP CONSTRAINT {$q($ctx['constraint'], 'constraint')},\n    ADD CONSTRAINT {$q($ctx['constraint'], 'constraint')} FOREIGN KEY (\"your_column\") REFERENCES {$q($ctx['table'], 'parent_table')} ({$q($ctx['parent_column'], 'id')}) ON DELETE CASCADE;",
            'other_suggestions' => "Laravel: ->constrained()->cascadeOnDelete(), or delete the children first.",
        ],
    ],
    [
        'id' => RuleIds::NULL_CONSTRAINT_VIOLATION,
        'sqlstate' => [PgSqlStates::NOT_NULL_VIOLATION],
        'message_regex' => '/violates not-null constraint/i',
        'extract' => fn($normalized) => ['column' => $normalized->column ?? 'unknown'],
        'explanation' => 'NULL not allowed: Column "{column}" of "{table}" cannot be NULL. Provide a value or give the column a default.',
        'migration' => fn($ctx) => "-- Option 1: allow NULL\nALTER TABLE {$q($ctx['table'], 'your_table')} ALTER COLUMN {$q($ctx['column'], 'your_column')} DROP NOT NULL;\n\n-- Option 2: set a default\nALTER TABLE {$q($ctx['table'], 'your_table')} ALTER COLUMN {$q($ctx['column'], 'your_column')} SET DEFAULT 'value';",
    ],
    [
        'id' => RuleIds::CHECK_VIOLATION,
        'sqlstate' => [PgSqlStates::CHECK_VIOLATION],
        'message_regex' => '/violates check constraint/i',
        'extract' => fn($normalized) => ['constraint' => $normalized->constraint['constraint_name'] ?? 'unknown'],
        'explanation' => 'Check constraint failed: The row breaks the check constraint "{constraint}" on "{table}". Change the value so it satisfies the condition.',
        'migration' => fn($ctx) => "-- See the condition:\nSELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname = '{$ctx['constraint']}';",
    ],

    // Schema
    [
        'id' => RuleIds::UNKNOWN_COLUMN,
        'sqlstate' => [PgSqlStates::UNDEFINED_COLUMN],
        'message_regex' => '/column .* does not exist/i',
        'extract' => fn($normalized) => [
            'column' => $normalized->column ?? 'unknown',
            'hint' => $parser->hint($normalized->message) ?? '',
        ],
        'explanation' => 'Unknown column: "{column}" does not exist. Check for typos or run pending migrations. {hint}',
        'migration' => fn($ctx) => "ALTER TABLE {$q($ctx['table'], 'your_table')} ADD COLUMN {$q($ctx['column'], 'your_column')} VARCHAR(255);",
    ],
    [
        'id' => RuleIds::TABLE_NOT_FOUND,
        'sqlstate' => [PgSqlStates::UNDEFINED_TABLE],
        'message_regex' => '/relation .* does not exist/i',
        'extract' => fn($normalized) => [],
        'explanation' => 'Table not found: The table "{table}" does not exist in the current schema. Run pending migrations, check the name, or check search_path if it lives in another schema.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => "CREATE TABLE {$q($ctx['table'], 'your_table')} (\n    \"id\" BIGSERIAL PRIMARY KEY,\n    \"created_at\" TIMESTAMP,\n    \"updated_at\" TIMESTAMP\n);",
            'other_suggestions' => 'Laravel: php artisan migrate',
        ],
    ],
    [
        'id' => RuleIds::TABLE_ALREADY_EXISTS,
        'sqlstate' => [PgSqlStates::DUPLICATE_TABLE],
        'message_regex' => '/relation .* already exists/i',
        'extract' => fn($normalized) => [],
        'explanation' => 'Table already exists: "{table}" already exists. The migration probably ran before.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => "CREATE TABLE IF NOT EXISTS {$q($ctx['table'], 'your_table')} (...);",
            'other_suggestions' => "Guard the migration with Schema::hasTable('{$ctx['table']}').",
        ],
    ],
    [
        'id' => RuleIds::DUPLICATE_COLUMN,
        'sqlstate' => [PgSqlStates::DUPLICATE_COLUMN],
        'message_regex' => '/column .* already exists/i',
        'extract' => fn($normalized) => ['column' => $normalized->column ?? 'unknown'],
        'explanation' => 'Duplicate column: "{column}" already exists on "{table}". The migration was probably run before or adds the column twice.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => "ALTER TABLE {$q($ctx['table'], 'your_table')} ADD COLUMN IF NOT EXISTS {$q($ctx['column'], 'your_column')} ...;",
            'other_suggestions' => "Guard the migration with Schema::hasColumn('{$ctx['table']}', '{$ctx['column']}').",
        ],
    ],
    [
        'id' => RuleIds::AMBIGUOUS_COLUMN,
        'sqlstate' => [PgSqlStates::AMBIGUOUS_COLUMN],
        'message_regex' => '/is ambiguous/i',
        'extract' => fn($normalized) => ['column' => $normalized->column ?? 'unknown'],
        'explanation' => 'Ambiguous column: "{column}" exists in more than one joined table. Prefix it with the table name, e.g. users.{column}.',
        'migration' => fn($ctx) => '',
    ],
    [
        'id' => RuleIds::FK_INCOMPATIBLE_COLUMNS,
        'sqlstate' => [PgSqlStates::DATATYPE_MISMATCH],
        'message_regex' => '/foreign key constraint .* cannot be implemented/i',
        'require_message' => true,
        'extract' => function ($normalized) {
            preg_match('/Key columns "(\w+)" and "(\w+)" are of incompatible types: ([\w ]+) and ([\w ]+)\./', $normalized->message, $matches);
            return [
                'column' => $matches[1] ?? 'unknown',
                'referenced_column' => $matches[2] ?? 'id',
                'column_type' => $matches[3] ?? 'unknown',
                'referenced_type' => $matches[4] ?? 'unknown',
            ];
        },
        'explanation' => 'Foreign key type mismatch: "{column}" is {column_type} but the referenced "{referenced_column}" is {referenced_type}. Both columns must have compatible types.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => "ALTER TABLE {$q($ctx['table'], 'your_table')} ALTER COLUMN {$q($ctx['column'], 'your_column')} TYPE {$ctx['referenced_type']} USING {$q($ctx['column'], 'your_column')}::{$ctx['referenced_type']};",
            'other_suggestions' => "Laravel: use \$table->foreignId('{$ctx['column']}')->constrained().",
        ],
    ],
    [
        'id' => RuleIds::CANT_DROP_COLUMN_OR_KEY,
        'sqlstate' => [PgSqlStates::UNDEFINED_OBJECT],
        'message_regex' => '/(index|constraint) "?\w+"? .*does not exist/i',
        'require_message' => true,
        'extract' => function ($normalized) {
            preg_match('/(index|constraint) "?(\w+)"?/i', $normalized->message, $matches);
            return ['object_type' => strtolower($matches[1] ?? 'object'), 'object' => $matches[2] ?? 'unknown'];
        },
        'explanation' => 'Can\'t drop: The {object_type} "{object}" does not exist. The migration may have run already or the name is wrong.',
        'migration' => fn($ctx) => $ctx['object_type'] === 'index'
            ? "DROP INDEX IF EXISTS {$q($ctx['object'], 'your_index')};"
            : "ALTER TABLE {$q($ctx['table'], 'your_table')} DROP CONSTRAINT IF EXISTS {$q($ctx['object'], 'your_constraint')};",
    ],

    // Query
    [
        'id' => RuleIds::SYNTAX_ERROR,
        'sqlstate' => [PgSqlStates::SYNTAX_ERROR],
        'message_regex' => '/syntax error/i',
        'extract' => function ($normalized) {
            preg_match('/at or near "([^"]*)"/', $normalized->message, $matches);
            return ['near' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Syntax error near "{near}": Check for typos, missing commas or quotes, and reserved words used as names (quote them with double quotes).',
        'migration' => fn($ctx) => '',
    ],
    [
        'id' => RuleIds::ONLY_FULL_GROUP_BY,
        'sqlstate' => [PgSqlStates::GROUPING_ERROR],
        'message_regex' => '/must appear in the GROUP BY clause/i',
        'extract' => function ($normalized) {
            preg_match('/column "([^"]+)"/', $normalized->message, $matches);
            return ['column' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'GROUP BY error: "{column}" is selected but is neither in the GROUP BY clause nor wrapped in an aggregate function.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => 'Add the column to GROUP BY, wrap it in an aggregate such as MAX(), or select only grouped columns.',
        ],
    ],
    [
        'id' => RuleIds::FUNCTION_NOT_DEFINED,
        'sqlstate' => [PgSqlStates::UNDEFINED_FUNCTION],
        'message_regex' => '/function .* does not exist/i',
        'extract' => function ($normalized) use ($parser) {
            preg_match('/function (\S+\(.*?\)) does not exist/', $normalized->message, $matches);
            return ['function' => $matches[1] ?? 'unknown', 'hint' => $parser->hint($normalized->message) ?? ''];
        },
        'explanation' => 'Function not found: {function} does not exist with these argument types. {hint}',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => 'Check the name, add explicit casts (e.g. ::text), or install the extension that provides it (CREATE EXTENSION ...).',
        ],
    ],
    [
        'id' => RuleIds::TABLE_ACCESS_DENIED,
        'sqlstate' => [PgSqlStates::INSUFFICIENT_PRIVILEGE],
        'message_regex' => '/permission denied/i',
        'extract' => fn($normalized) => [],
        'explanation' => 'Permission denied: The database user is not allowed to access "{table}".',
        'migration' => fn($ctx) => "GRANT SELECT, INSERT, UPDATE, DELETE ON {$q($ctx['table'], 'your_table')} TO your_user;",
    ],

    // Data
    [
        'id' => RuleIds::DATA_TOO_LONG,
        'sqlstate' => [PgSqlStates::STRING_DATA_RIGHT_TRUNCATION],
        'message_regex' => '/value too long for type/i',
        'extract' => function ($normalized) {
            preg_match('/value too long for type (.+?)(?:\n|$)/', $normalized->message, $matches);
            return ['type' => trim($matches[1] ?? 'unknown')];
        },
        'explanation' => 'Data too long: The value does not fit in a column of type {type}. Shorten the value or widen the column.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => "ALTER TABLE {$q($ctx['table'], 'your_table')} ALTER COLUMN \"your_column\" TYPE VARCHAR(500);",
            'other_suggestions' => "Validate the length first (Laravel 'max:' rule), or use TEXT for free-form content.",
        ],
    ],
    [
        'id' => RuleIds::OUT_OF_RANGE,
        'sqlstate' => [PgSqlStates::NUMERIC_VALUE_OUT_OF_RANGE],
        'message_regex' => '/out of range/i',
        'extract' => function ($normalized) {
            preg_match('/(\w+) out of range|out of range for type (\w+)/', $normalized->message, $matches);
            return ['type' => ($matches[1] ?? '') ?: ($matches[2] ?? 'numeric')];
        },
        'explanation' => 'Out of range: The number is too large or too small for type {type}.',
        'migration' => fn($ctx) => "-- Widen the column, e.g. integer -> bigint:\nALTER TABLE {$q($ctx['table'], 'your_table')} ALTER COLUMN \"your_column\" TYPE BIGINT;",
    ],
    [
        'id' => RuleIds::INCORRECT_VALUE,
        'sqlstate' => [PgSqlStates::INVALID_TEXT_REPRESENTATION, PgSqlStates::INVALID_DATETIME_FORMAT, PgSqlStates::DATETIME_FIELD_OVERFLOW],
        'message_regex' => '/invalid input syntax|date\/time field value out of range/i',
        'extract' => function ($normalized) {
            preg_match('/for type (\w+(?: \w+)*): "(.*?)"|out of range: "(.*?)"/', $normalized->message, $matches);
            return [
                'type' => ($matches[1] ?? '') ?: 'date/time',
                'value' => ($matches[2] ?? '') ?: ($matches[3] ?? 'unknown'),
            ];
        },
        'explanation' => 'Incorrect value: "{value}" is not a valid {type}. Validate or cast the value before saving it.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => "Validate the input (e.g. Laravel 'integer' / 'date' rules) or add a cast on the model.",
        ],
    ],
    [
        'id' => RuleIds::DIVISION_BY_ZERO,
        'sqlstate' => [PgSqlStates::DIVISION_BY_ZERO],
        'message_regex' => '/division by zero/i',
        'extract' => fn($normalized) => [],
        'explanation' => 'Division by zero: The query divides by a value that is 0. Guard it with NULLIF(divisor, 0).',
        'migration' => fn($ctx) => "-- Returns NULL instead of failing:\nSELECT a / NULLIF(b, 0) FROM ...;",
    ],

    // Concurrency
    [
        'id' => RuleIds::DEADLOCK,
        'sqlstate' => [PgSqlStates::DEADLOCK_DETECTED],
        'message_regex' => '/deadlock detected/i',
        'extract' => fn($normalized) => [],
        'explanation' => 'Deadlock: Two transactions were waiting on each other\'s locks, so PostgreSQL cancelled one. It is safe to retry the transaction.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => "Retry the transaction (Laravel: DB::transaction(\$callback, attempts: 3)), keep transactions short, and touch rows in the same order everywhere.",
        ],
    ],
    [
        'id' => RuleIds::SERIALIZATION_FAILURE,
        'sqlstate' => [PgSqlStates::SERIALIZATION_FAILURE],
        'message_regex' => '/could not serialize access/i',
        'extract' => fn($normalized) => [],
        'explanation' => 'Serialization failure: A concurrent transaction changed the same data under REPEATABLE READ or SERIALIZABLE isolation. Retry the transaction.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => 'Wrap the work in a retry loop (Laravel: DB::transaction($callback, attempts: 3)).',
        ],
    ],
    [
        'id' => RuleIds::LOCK_WAIT_TIMEOUT,
        'sqlstate' => [PgSqlStates::LOCK_NOT_AVAILABLE],
        'message_regex' => '/lock timeout|could not obtain lock/i',
        'extract' => fn($normalized) => [],
        'explanation' => 'Lock timeout: The query waited longer than lock_timeout for a lock held by another transaction.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => "-- Find the blocking sessions:\nSELECT pid, state, query, now() - xact_start AS running_for\nFROM pg_stat_activity WHERE cardinality(pg_blocking_pids(pid)) > 0 OR pid IN (SELECT unnest(pg_blocking_pids(pid)) FROM pg_stat_activity);",
            'other_suggestions' => 'Look for long-running transactions holding locks; keep transactions short and retry.',
        ],
    ],
    [
        'id' => RuleIds::QUERY_TIMEOUT,
        'sqlstate' => [PgSqlStates::QUERY_CANCELED],
        'message_regex' => '/statement timeout|canceling statement/i',
        'extract' => fn($normalized) => [],
        'explanation' => 'Query timed out: The statement ran longer than statement_timeout and was cancelled.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => "-- Find out why it is slow:\nEXPLAIN ANALYZE <your query>;",
            'other_suggestions' => 'Add a missing index, fetch less data (chunk / paginate), or raise statement_timeout for this job only.',
        ],
    ],

    // Connection
    [
        'id' => RuleIds::ACCESS_DENIED,
        'sqlstate' => [PgSqlStates::INVALID_PASSWORD, PgSqlStates::CONNECTION_FAILURE],
        'message_regex' => '/password authentication failed for user "([^"]+)"/i',
        'require_message' => true,
        'extract' => function ($normalized) {
            preg_match('/for user "([^"]+)"/', $normalized->message, $matches);
            return ['user' => $matches[1] ?? 'unknown'];
        },
        'explanation' => 'Access denied: Password authentication failed for user "{user}". Check DB_USERNAME / DB_PASSWORD.',
        'migration' => fn($ctx) => "-- If the credentials are right, reset the password:\nALTER USER {$q($ctx['user'], 'your_user')} WITH PASSWORD 'new_password';",
    ],
    [
        'id' => RuleIds::UNKNOWN_DATABASE,
        'sqlstate' => [PgSqlStates::INVALID_CATALOG_NAME, PgSqlStates::CONNECTION_FAILURE],
        'message_regex' => '/database "\w+" does not exist/i',
        'require_message' => true,
        'extract' => fn($normalized) => ['database' => $normalized->database ?? 'unknown'],
        'explanation' => 'Unknown database: The database "{database}" does not exist. Create it or check DB_DATABASE.',
        'migration' => fn($ctx) => "CREATE DATABASE {$q($ctx['database'], 'your_database')};",
    ],
    [
        'id' => RuleIds::CONNECTION_REFUSED,
        'sqlstate' => [PgSqlStates::CONNECTION_FAILURE, PgSqlStates::SQLCLIENT_UNABLE_TO_ESTABLISH_SQLCONNECTION],
        'message_regex' => '/Connection refused|could not translate host name|No such file or directory|timeout expired|could not connect/i',
        'require_message' => true,
        'extract' => function ($normalized) {
            preg_match('/failed: (.+?)(?:\n|$)/', $normalized->message, $matches);
            return ['reason' => trim($matches[1] ?? 'unknown')];
        },
        'explanation' => 'Can\'t connect: The PostgreSQL server could not be reached ({reason}). Check that it is running and that the host and port are correct.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => 'Check DB_HOST / DB_PORT (default 5432). In Docker use the service name (e.g. postgres), not localhost.',
        ],
    ],
    [
        'id' => RuleIds::TOO_MANY_CONNECTIONS,
        'sqlstate' => [PgSqlStates::TOO_MANY_CONNECTIONS],
        'message_regex' => '/too many clients|too many connections/i',
        'extract' => fn($normalized) => [],
        'explanation' => 'Too many connections: PostgreSQL reached max_connections and refused a new one.',
        'migration' => fn($ctx) => [
            'migration_suggestion' => "SHOW max_connections;\nSELECT count(*) FROM pg_stat_activity;",
            'other_suggestions' => 'Use a pooler such as PgBouncer, reduce workers / FPM children, or raise max_connections.',
        ],
    ],
    [
        'id' => RuleIds::SERVER_GONE_AWAY,
        'sqlstate' => [PgSqlStates::GENERAL_ERROR, PgSqlStates::ADMIN_SHUTDOWN, PgSqlStates::CONNECTION_FAILURE],
        'message_regex' => '/server closed the connection|terminating connection|no connection to the server/i',
        'require_message' => true,
        'extract' => fn($normalized) => [],
        'explanation' => 'Connection lost: The server closed the connection, e.g. after a restart, an idle timeout, or pg_terminate_backend().',
        'migration' => fn($ctx) => [
            'migration_suggestion' => '',
            'other_suggestions' => 'Long-running workers should reconnect (Laravel: DB::reconnect()) or be restarted regularly.',
        ],
    ],
];
