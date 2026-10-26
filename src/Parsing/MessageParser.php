<?php

namespace TusharKhan\DbErrorExplainer\Parsing;

// Pulls table, column, database and constraint names out of database error messages.
class MessageParser implements MessageParserInterface
{
    public function table(string $message): ?string {
        $patterns = [
            // Foreign key constraint errors (must come first!)
            // MySQL: (mydb.orders, CONSTRAINT ...) or (`mydb`.`orders`, CONSTRAINT ...)
            "/foreign key constraint fails\s*\((?:['\"`]?([a-zA-Z0-9_]+)['\"`]?\.)?['\"`]?([a-zA-Z0-9_]+)['\"`]?,/i",

            // PostgreSQL: on table "table_name"
            '/on table\s+"([a-zA-Z0-9_]+)"/i',
            '/on table\s+\'([a-zA-Z0-9_]+)\'/i',

            // Schema-qualified patterns
            // Catches `db`.`table` or 'db'.'table' or "db"."table"
            "/['\"`]([a-zA-Z0-9_]+)['\"`]\.['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // Catches 'db.table' or `db.table` or "db.table" (within same quotes)
            "/['\"`]([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)['\"`]/i",

            // Unquoted forms must run before the generic "table X" below, which would grab "or" / "main"
            "/no such table: (?:[a-zA-Z0-9_]+\.)?([a-zA-Z0-9_]+)/i",
            "/table or view ([a-zA-Z0-9_]+)/i",

            // MySQL/MariaDB
            "/Table '([^']+)'/i",
            "/table ['\"`]?([a-zA-Z0-9_]+)['\"`]?/i",
            "/for table '([^']+)'/i",
            "/from table ['\"`]?([a-zA-Z0-9_]+)['\"`]?/i",
            "/into table ['\"`]?([a-zA-Z0-9_]+)['\"`]?/i",

            // MySQL: Table 'db.table' doesn't exist
            "/Table ['\"`](?:[a-zA-Z0-9_]+\.)?([a-zA-Z0-9_]+)['\"`] doesn't exist/i",

            // PostgreSQL
            '/relation "([^"]+)"/i',
            '/table "([^"]+)"/i',

            // PostgreSQL: relation "table" already exists
            '/relation\s+"([a-zA-Z0-9_]+)"\s+already exists/i',

            // PostgreSQL: permission denied for table "table_name"
            '/permission denied for table\s+"([a-zA-Z0-9_]+)"/i',

            // SQLite
            "/no such table: ([a-zA-Z0-9_]+)/i",
            "/table ([a-zA-Z0-9_]+) already exists/i",

            // SQL Server
            "/Invalid object name '([^']+)'/i",
            "/object '([^']+)'/i",

            // SQL Server: Cannot find the object "table" because it does not exist
            '/Cannot find the object\s+"([a-zA-Z0-9_]+)"/i',

            // Oracle
            "/table or view ([a-zA-Z0-9_]+)/i",

            // Oracle: ORA-00942 table or view does not exist
            '/ORA-00942:.*"([A-Z0-9_]+)"/i',

            // Generic backtick/quote patterns (last resort)
            "/`([a-zA-Z0-9_]+)`/",
            '/"([a-zA-Z0-9_]+)"/i',
            // The following is extremely generic and may match column names, etc.
            // Use only if absolutely necessary.
            // "/['\"`]([a-zA-Z0-9_]+)['\"`]/i"
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                // If we have 2 captures, it's a schema.table pattern
                if (isset($matches[2]) && !empty($matches[2])) {
                    // Return the table name (second capture)
                    return trim($matches[2], '`"\' ');
                }

                // Single capture - check if it contains a dot
                $tableName = $matches[1];
                if (str_contains($tableName, '.')) {
                    $parts = explode('.', $tableName);
                    $tableName = end($parts);
                }
                return trim($tableName, '`"\' ');
            }
        }

        return null;
    }

    public function column(string $message): ?string
    {
        $patterns = [
            // Foreign key constraint errors (must come first!)
            // FOREIGN KEY (column_name) or FOREIGN KEY (`column_name`)
            '/FOREIGN\s+KEY\s+\([`"\']?([a-zA-Z0-9_]+)[`"\']?\)/i',

            // MySQL: Unknown column 'column_name' in 'field list'
            "/Unknown column ['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // MySQL/MariaDB: Column 'column_name' or Column `column_name`
            "/Column\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // MySQL: Field 'column_name' doesn't have a default value
            "/Field\s+['\"`]([a-zA-Z0-9_]+)['\"`]\\s+doesn't have a default value/i",

            // Duplicate entry for key 'table.column' or 'column'
            "/Duplicate entry\\s+['\"][^'\"]+['\"]\\s+for key\\s+['\"](?:[a-zA-Z0-9_]+\\.)?([a-zA-Z0-9_]+)['\"]/i",

            // UNIQUE constraint failed: table.column
            "/UNIQUE constraint failed:\\s+[a-zA-Z0-9_]+\\.([a-zA-Z0-9_]+)/i",

            // Oracle: ORA-01400: cannot insert NULL into ('SCHEMA'.'TABLE'.'COLUMN')
            "/ORA-01400:.*\\(['\"][^'\"]+['\"]\\.['\"][^'\"]+['\"]\\.['\"]([a-zA-Z0-9_]+)['\"]/i",

            // PostgreSQL: column "column_name" does not exist
            '/column\s+"([a-zA-Z0-9_]+)"\s+does not exist/i',

            // Generic: column "column_name" or column 'column_name' or column `column_name`
            '/column\s+["\']([a-zA-Z0-9_]+)["\']/i',
            '/column\s+`([a-zA-Z0-9_]+)`/i',

            // SQL Server: Invalid column name 'column_name'
            "/Invalid column name\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // SQL Server: The column name 'column_name' is not valid
            "/The column name\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // Oracle: ORA-00904: 'column_name': invalid identifier
            "/ORA-\\d+:\\s+['\"`]([a-zA-Z0-9_]+)['\"`]:/i",

            // Oracle: invalid identifier 'column_name'
            "/invalid identifier\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // SQLite: no such column: column_name (without quotes)
            "/no such column:\\s+(?:[a-zA-Z0-9_]+\\.)?([a-zA-Z0-9_]+)/i",

            // Syntax errors: near 'column_name'
            "/near\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // Duplicate column name 'column_name' (specific pattern before generic "Duplicate column")
            "/Duplicate column name\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // Duplicate column 'column_name'
            "/Duplicate column\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // Ambiguous column name 'column_name' (specific pattern before generic "Ambiguous column")
            "/Ambiguous column name\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // Ambiguous column 'column_name'
            "/Ambiguous column\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",


            // REFERENCES `table` (`column`)
            '/REFERENCES\s+[`"\'][a-zA-Z0-9_]+[`"\']\s+\([`"\']([a-zA-Z0-9_]+)[`"\']\)/i',

            // REFERENCES table (column) - without quotes
            '/REFERENCES\s+[a-zA-Z0-9_]+\s*\(([a-zA-Z0-9_]+)\)/i',

            // Generic: column name: column_name (without quotes)
            '/column\s+name:\\s+([a-zA-Z0-9_]+)/i',

            // Invalid column name: column_name (without quotes)
            '/invalid\s+column\s+name:\\s+([a-zA-Z0-9_]+)/i',

            // PostgreSQL: null value in column "column_name"
            '/null value in column\s+"([a-zA-Z0-9_]+)"/i',

            // PostgreSQL: value too long for type character varying for column "column_name"
            '/for column\s+"([a-zA-Z0-9_]+)"/i',

            // MySQL: Data truncated for column 'column_name'
            "/Data truncated for column\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // MySQL: Incorrect value for column 'column_name'
            "/Incorrect.*for column\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // SQL Server: Cannot insert the value NULL into column 'column_name'
            "/into column\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // SQL Server: Conversion failed ... for column 'column_name'
            "/for column\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return trim($matches[1], '`"\' ');
            }
        }

        return null;
    }

    public function database(string $message): ?string
    {
        // Common patterns to look for.
        // We use ['"`] to allow single quotes, double quotes, or backticks.
        $patterns = [
            // Foreign key constraint errors (must come first!)
            // MySQL: (mydb.orders, CONSTRAINT ...) or (`mydb`.`orders`, CONSTRAINT ...)
            "/foreign key constraint fails\s*\(['\"`]?([a-zA-Z0-9_]+)['\"`]?\.['\"`]?([a-zA-Z0-9_]+)['\"`]?,/i",

            // MySQL/MariaDB: Unknown database 'db_name'
            "/Unknown database ['\"`]?([a-zA-Z0-9_]+)['\"`]?/i",

            // MySQL/MariaDB: Access denied for user ... to database 'db_name'
            "/to database ['\"`]?([a-zA-Z0-9_]+)['\"`]?/i",

            // MySQL/MariaDB: Can't create database 'db_name'
            "/Can't create database ['\"`]?([a-zA-Z0-9_]+)['\"`]?/i",

            // MySQL/MariaDB: Can't drop database 'db_name'
            "/Can't drop database ['\"`]?([a-zA-Z0-9_]+)['\"`]?/i",

            // PostgreSQL: database "db_name" already exists
            '/database\s+"([a-zA-Z0-9_]+)"\s+already exists/i',

            // PostgreSQL: database "db_name" does not exist
            // SQL Server: Cannot open database "db_name"
            "/database\s+['\"`]?([a-zA-Z0-9_]+)['\"`]?\s+(?:does not exist|requested|matches)/i",

            // SQL Server: Cannot open database "db_name" requested by the login
            "/Cannot open database\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // Oracle: ORA-01109: database not open
            "/ORA-\\d+:.*database\\s+['\"]?([a-zA-Z0-9_]+)['\"]?/i",

            // CockroachDB: database "db_name" does not exist
            '/database\s+"([a-zA-Z0-9_]+)"\s+does not exist/i',

            // Generic database patterns
            "/database ['\"`]?([a-zA-Z0-9_]+)['\"`]?/i",
            "/DATABASE['\"`]?([a-zA-Z0-9_]+)['\"`]?/i",

            // Catches 'db'.'table' or `db`.`table` or "db"."table" (separate quotes)
            "/['\"`]([a-zA-Z0-9_]+)['\"`]\.['\"`]([a-zA-Z0-9_]+)['\"`]/i",
            "/['\"`]([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)['\"`]/i",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                // Return the first captured group
                return $matches[1];
            }
        }

        // Fallback: Try to find "database 'name'" generically if specific messages failed
        // Be careful: The user's example "near 'DATABASE'" should NOT trigger this.
        // So we look for "database" followed immediately by a quoted string.
        if (preg_match("/database\s+['\"`]+([a-zA-Z0-9_]+)['\"`]+/i", $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function constraintName(string $message): ?string
    {
        $patterns = [
            // MySQL: CONSTRAINT `constraint_name` or CONSTRAINT 'constraint_name'
            '/CONSTRAINT\s+[`"\']([a-zA-Z0-9_]+)[`"\']/i',

            // MySQL: CONSTRAINT constraint_name (without quotes)
            '/CONSTRAINT\s+([a-zA-Z0-9_]+)\s+(?:FOREIGN|PRIMARY|UNIQUE|CHECK)/i',

            // MySQL: for key 'constraint_name' (PRIMARY, UNIQUE, etc.)
            "/for key\\s+['\"`]([a-zA-Z0-9_.]+)['\"`]/i",

            // PostgreSQL: constraint "constraint_name"
            '/constraint\\s+"([a-zA-Z0-9_]+)"/i',

            // PostgreSQL: foreign key constraint "constraint_name"
            '/foreign key constraint\\s+"([a-zA-Z0-9_]+)"/i',

            // PostgreSQL: unique constraint "constraint_name"
            '/unique constraint\\s+"([a-zA-Z0-9_]+)"/i',

            // PostgreSQL: check constraint "constraint_name"
            '/check constraint\\s+"([a-zA-Z0-9_]+)"/i',

            // SQL Server: Violation of UNIQUE KEY constraint 'constraint_name'
            "/Violation of UNIQUE KEY constraint\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // SQL Server: Violation of PRIMARY KEY constraint 'constraint_name'
            "/Violation of PRIMARY KEY constraint\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // SQL Server: FOREIGN KEY constraint "constraint_name"
            '/FOREIGN KEY constraint\\s+"([a-zA-Z0-9_]+)"/i',

            // SQL Server: REFERENCE constraint "constraint_name"
            '/REFERENCE constraint\\s+"([a-zA-Z0-9_]+)"/i',

            // SQL Server: CHECK constraint "constraint_name"
            '/CHECK constraint\\s+"([a-zA-Z0-9_]+)"/i',

            // SQLite: constraint failed: table.column or constraint_name
            '/constraint failed:\\s+([a-zA-Z0-9_.]+)/i',

            // SQLite: UNIQUE constraint failed: table.column
            '/UNIQUE constraint failed:\\s+([a-zA-Z0-9_.]+)/i',

            // SQLite: NOT NULL constraint failed: table.column
            '/NOT NULL constraint failed:\\s+([a-zA-Z0-9_.]+)/i',

            // SQLite: CHECK constraint failed: constraint_name
            '/CHECK constraint failed:\\s+([a-zA-Z0-9_]+)/i',

            // Oracle: unique constraint (SCHEMA.CONSTRAINT_NAME) violated
            '/unique constraint\\s+\\(([A-Z0-9_.]+)\\)/i',

            // Oracle: constraint (SCHEMA.CONSTRAINT_NAME) violated
            '/constraint\\s+\\(([A-Z0-9_.]+)\\)/i',

            // Oracle: integrity constraint (SCHEMA.CONSTRAINT_NAME) violated
            '/integrity constraint\\s+\\(([A-Z0-9_.]+)\\)/i',

            // Oracle: check constraint (SCHEMA.CONSTRAINT_NAME) violated
            '/check constraint\\s+\\(([A-Z0-9_.]+)\\)/i',

            // Generic: constraint 'constraint_name'
            "/constraint\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",

            // SQL Server: violates the constraint "constraint_name"
            '/violates the constraint\\s+"([a-zA-Z0-9_]+)"/i',

            // Generic: constraint name 'constraint_name' (without quotes)
            '/constraint\\s+name:\\s+([a-zA-Z0-9_]+)/i',

            // MySQL: Column 'column_name' cannot be null (NOT NULL constraint)
            "/Column\\s+['\"`]([a-zA-Z0-9_]+)['\"`]\\s+cannot be null/i",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return trim($matches[1], '`"\' ()');
            }
        }

        return null;
    }

    // FOREIGN KEY, PRIMARY KEY, UNIQUE, CHECK, NOT NULL, DEFAULT or null.
    public function constraintType(string $message): ?string
    {
        $typePatterns = [
            'FOREIGN KEY' => [
                '/foreign\s+key/i',
                '/REFERENCES/i',
                '/a foreign key constraint fails/i',
                '/violates foreign key constraint/i',
                '/referential integrity constraint/i',
                '/FK_/i',
                '/_fkey/i',
                '/_foreign/i',
                '/parent key not found/i',       // Oracle
                '/child record found/i',         // Oracle
                '/ORA-02291/i',                  // Oracle FK parent key not found
                '/ORA-02292/i',                  // Oracle FK child record found
            ],
            'PRIMARY KEY' => [
                '/primary\s+key/i',
                '/Duplicate entry.*for key\s+[\'"`]PRIMARY[\'"`]/i',
                '/Violation of PRIMARY KEY/i',
                '/PK_/i',
                '/_pkey/i',
            ],
            'UNIQUE' => [
                '/unique\s+(?:key|constraint|index)/i',
                '/Duplicate entry.*for key/i',
                '/UNIQUE constraint failed/i',
                '/violates unique constraint/i',
                '/Violation of UNIQUE KEY/i',
                '/UK_/i',
                '/_unique/i',
                '/UQ_/i',
            ],
            'CHECK' => [
                '/check\s+constraint/i',
                '/CHECK constraint failed/i',
                '/violates check constraint/i',
                '/CK_/i',
                '/CHK_/i',
            ],
            'NOT NULL' => [
                '/NOT NULL constraint failed/i',
                '/cannot be null/i',
                '/null value in column/i',
                '/cannot insert NULL/i',
                '/doesn\'t have a default value/i',
                '/Column.*cannot be null/i',
                '/violates not-null constraint/i',
            ],
            'DEFAULT' => [
                '/no default value/i',
                '/default value for/i',
                '/invalid default/i',
            ],
        ];

        foreach ($typePatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $message)) {
                    return $type;
                }
            }
        }

        return null;
    }

    // Referenced table/column and ON DELETE / ON UPDATE actions.
    public function foreignKeyInfo(string $message): array
    {
        $result = [
            'referenced_table' => null,
            'referenced_column' => null,
            'on_delete' => 'CASCADE',
            'on_update' => 'CASCADE',
        ];

        // Patterns for extracting referenced table
        $referencedTablePatterns = [
            // REFERENCES `table` or REFERENCES 'table' or REFERENCES "table"
            '/REFERENCES\s+[`"\']([a-zA-Z0-9_]+)[`"\']/i',
            // REFERENCES table (without quotes)
            '/REFERENCES\s+([a-zA-Z0-9_]+)\s*\(/i',
            // MySQL: parent table 'table_name'
            "/parent table\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",
            // PostgreSQL: referenced table "table_name"
            '/referenced table\\s+"([a-zA-Z0-9_]+)"/i',
            // SQL Server: table "table_name", column 'column_name' referenced
            '/table\\s+["\']([a-zA-Z0-9_]+)["\'],\\s*column/i',
            // CockroachDB: referenced by table "table_name"
            '/referenced by table\\s+"([a-zA-Z0-9_]+)"/i',
            // Oracle: references SCHEMA.TABLE
            '/references\\s+[a-zA-Z0-9_]+\\.([a-zA-Z0-9_]+)/i',
        ];

        foreach ($referencedTablePatterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $result['referenced_table'] = trim($matches[1], '`"\' ');
                break;
            }
        }

        // Patterns for extracting referenced column
        $referencedColumnPatterns = [
            // REFERENCES table (`column`) or REFERENCES table ('column') or REFERENCES table ("column")
            '/REFERENCES\s+[`"\']?[a-zA-Z0-9_]+[`"\']?\s*\([`"\']([a-zA-Z0-9_]+)[`"\']\)/i',
            // REFERENCES table (column) - without quotes
            '/REFERENCES\s+[a-zA-Z0-9_]+\s*\(([a-zA-Z0-9_]+)\)/i',
            // PostgreSQL: column "column_name" referenced
            '/column\\s+"([a-zA-Z0-9_]+)"\\s+referenced/i',
            // MySQL: parent column 'column_name'
            "/parent column\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",
            // Generic: references column 'column_name'
            "/references column\\s+['\"`]([a-zA-Z0-9_]+)['\"`]/i",
            // Oracle: references SCHEMA.TABLE (COLUMN)
            '/references\\s+[a-zA-Z0-9_]+\\.[a-zA-Z0-9_]+\\s*\\(([a-zA-Z0-9_]+)\\)/i',
        ];

        foreach ($referencedColumnPatterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $result['referenced_column'] = trim($matches[1], '`"\' ');
                break;
            }
        }

        // Patterns for ON DELETE action
        $onDeletePatterns = [
            '/ON\s+DELETE\s+(CASCADE|SET\s+NULL|SET\s+DEFAULT|RESTRICT|NO\s+ACTION)/i',
        ];

        foreach ($onDeletePatterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $result['on_delete'] = strtoupper(trim($matches[1]));
                break;
            }
        }

        // Patterns for ON UPDATE action
        $onUpdatePatterns = [
            '/ON\s+UPDATE\s+(CASCADE|SET\s+NULL|SET\s+DEFAULT|RESTRICT|NO\s+ACTION)/i',
        ];

        foreach ($onUpdatePatterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $result['on_update'] = strtoupper(trim($matches[1]));
                break;
            }
        }

        return $result;
    }

    // Everything known about the violated constraint, in one array.
    public function constraintDetails(string $message): array
    {
        $result = [
            'constraint_name' => null,
            'constraint_type' => null,
            'table' => null,
            'column' => null,
            'referenced_table' => null,
            'referenced_column' => null,
            'on_delete' => null,
            'on_update' => null,
        ];

        // Extract constraint name
        $result['constraint_name'] = $this->constraintName($message);

        // Detect constraint type
        $result['constraint_type'] = $this->constraintType($message);

        // Extract table name
        $result['table'] = $this->table($message);

        // Extract column name
        $result['column'] = $this->column($message);

        // Extract foreign key specific info
        if ($result['constraint_type'] === 'FOREIGN KEY') {
            $fkInfo = $this->foreignKeyInfo($message);
            $result = array_merge($result, $fkInfo);
        }

        return $result;
    }

    public function violatingValue(string $message): ?string
    {
        $patterns = [
            // MySQL: Duplicate entry 'value' for key
            "/Duplicate entry\\s+['\"]([^'\"]+)['\"]\\s+for key/i",
            // MySQL: Out of range value for column 'col' at row
            "/Out of range value\\s+['\"]([^'\"]+)['\"]/i",
            // MySQL: Incorrect value: 'value' for column
            "/Incorrect.*value:\\s+['\"]([^'\"]+)['\"]\\s+for/i",
            // MySQL: Data too long for column (no value typically)
            // PostgreSQL: value "value" is out of range
            '/value\\s+"([^"]+)"\\s+is out of range/i',
            // PostgreSQL: invalid input value for enum: "value"
            '/invalid input value for enum:\\s+"([^"]+)"/i',
            // SQL Server: String or binary data would be truncated in column 'col', value = 'value'
            "/value\\s*=\\s*['\"]([^'\"]+)['\"]/i",
            // Generic: value 'value'
            "/value\\s+['\"]([^'\"]+)['\"]/i",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public function violationSummary(string $message): array
    {
        $result = [
            'type' => $this->constraintType($message),
            'name' => $this->constraintName($message),
            'table' => $this->table($message),
            'column' => $this->column($message),
            'value' => $this->violatingValue($message),
            'details' => null,
        ];

        // Build details string
        $details = [];
        if ($result['type']) {
            $details[] = "Constraint Type: {$result['type']}";
        }
        if ($result['name']) {
            $details[] = "Constraint Name: {$result['name']}";
        }
        if ($result['table']) {
            $details[] = "Table: {$result['table']}";
        }
        if ($result['column']) {
            $details[] = "Column: {$result['column']}";
        }
        if ($result['value']) {
            $details[] = "Violating Value: {$result['value']}";
        }

        $result['details'] = implode(', ', $details);

        return $result;
    }
}
