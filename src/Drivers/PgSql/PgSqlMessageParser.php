<?php

namespace TusharKhan\DbErrorExplainer\Drivers\PgSql;

use TusharKhan\DbErrorExplainer\Parsing\MessageParserInterface;

// PostgreSQL messages quote names with "..." and put details on DETAIL / HINT lines.
class PgSqlMessageParser implements MessageParserInterface
{
    public function table(string $message): ?string
    {
        $patterns = [
            // insert or update on table "orders" / update or delete on table "users"
            '/on table "(?:\w+\.)?(\w+)"/i',
            // column "x" of relation "users" / new row for relation "users" / relation "users" does not exist
            '/(?:of|for|in)? ?relation "(?:\w+\.)?(\w+)"/i',
            // permission denied for table users
            '/permission denied for table "?(?:\w+\.)?(\w+)"?/i',
        ];

        return $this->first($patterns, $message);
    }

    public function column(string $message): ?string
    {
        $patterns = [
            // DETAIL:  Key (email)=(a@b.c) — first column of a composite key
            '/Key \("?(\w+)"?(?:,[^)]*)?\)=/',
            // Key columns "user_id" and "id" are of incompatible types
            '/Key columns "(\w+)"/',
            // column reference "id" is ambiguous
            '/column reference "(?:\w+\.)?(\w+)"/i',
            // column "name" ... / column "users.id" must appear in the GROUP BY clause
            '/column "(?:\w+\.)?(\w+)"/i',
        ];

        return $this->first($patterns, $message);
    }

    public function database(string $message): ?string
    {
        return $this->first(['/database "(\w+)"/i'], $message);
    }

    public function constraintDetails(string $message): array
    {
        return [
            'constraint_name' => $this->first(['/constraint "(\w+)"/i'], $message),
            'constraint_type' => $this->constraintType($message),
            'table' => $this->table($message),
            'column' => $this->column($message),
            'referenced_table' => $this->first(['/is not present in table "(?:\w+\.)?(\w+)"/i'], $message),
            'referenced_column' => null,
            'on_delete' => null,
            'on_update' => null,
        ];
    }

    public function constraintType(string $message): ?string
    {
        return match (true) {
            (bool) preg_match('/foreign key constraint/i', $message) => 'FOREIGN KEY',
            (bool) preg_match('/unique constraint/i', $message) => 'UNIQUE',
            (bool) preg_match('/not-null constraint/i', $message) => 'NOT NULL',
            (bool) preg_match('/check constraint/i', $message) => 'CHECK',
            default => null,
        };
    }

    // "Key (email)=(a@b.c)" -> ['columns' => 'email', 'value' => 'a@b.c']
    public function keyValue(string $message): array
    {
        if (!preg_match('/Key \((.+?)\)=\((.*?)\)/s', $message, $matches)) {
            return ['columns' => null, 'value' => null];
        }

        return ['columns' => str_replace('"', '', $matches[1]), 'value' => $matches[2]];
    }

    // Text of the HINT: line, which PostgreSQL often uses to suggest the fix.
    public function hint(string $message): ?string
    {
        return preg_match('/HINT:\s+(.+)$/m', $message, $matches) ? trim($matches[1]) : null;
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
