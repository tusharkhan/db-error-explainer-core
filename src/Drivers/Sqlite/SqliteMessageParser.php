<?php

namespace TusharKhan\DbErrorExplainer\Drivers\Sqlite;

use TusharKhan\DbErrorExplainer\Parsing\MessageParserInterface;

// SQLite messages are short and unquoted: "UNIQUE constraint failed: users.email", "no such table: posts".
class SqliteMessageParser implements MessageParserInterface
{
    public function table(string $message): ?string
    {
        return $this->first([
            // UNIQUE / NOT NULL constraint failed: users.email
            '/constraint failed: (\w+)\.\w+/i',
            '/no such table: (?:\w+\.)?(\w+)/i',
            '/table "?(\w+)"? (?:already exists|has no column named)/i',
            // cannot store TEXT value in INTEGER column users.age
            '/column (\w+)\.\w+$/i',
        ], $message);
    }

    public function column(string $message): ?string
    {
        return $this->first([
            '/constraint failed: \w+\.(\w+)/i',
            '/no such column: "?(?:\w+\.)?(\w+)"?/i',
            '/has no column named (\w+)/i',
            '/duplicate column name: (\w+)/i',
            '/ambiguous column name: (?:\w+\.)?(\w+)/i',
            '/column \w+\.(\w+)$/i',
        ], $message);
    }

    public function database(string $message): ?string
    {
        return null;
    }

    public function constraintDetails(string $message): array
    {
        return [
            // Named CHECK constraints are reported by name, unnamed ones by their expression
            'constraint_name' => $this->first(['/CHECK constraint failed: (\w+)$/i'], $message),
            'constraint_type' => $this->constraintType($message),
            'table' => $this->table($message),
            'column' => $this->column($message),
            'referenced_table' => null,
            'referenced_column' => null,
            'on_delete' => null,
            'on_update' => null,
        ];
    }

    public function constraintType(string $message): ?string
    {
        return preg_match('/^(UNIQUE|NOT NULL|CHECK|FOREIGN KEY|PRIMARY KEY) constraint failed/i', $this->stripPrefix($message), $matches)
            ? strtoupper($matches[1])
            : null;
    }

    // "UNIQUE constraint failed: items.a, items.b" -> ['a', 'b']
    public function constraintColumns(string $message): array
    {
        preg_match_all('/\w+\.(\w+)/', explode('constraint failed:', $message, 2)[1] ?? '', $matches);

        return $matches[1];
    }

    // PDO prepends "SQLSTATE[23000]: Integrity constraint violation: 19 " to the SQLite text
    private function stripPrefix(string $message): string
    {
        return preg_replace('/^SQLSTATE\[\w+\]:[^:]*: \d+ /', '', $message);
    }

    private function first(array $patterns, string $message): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
