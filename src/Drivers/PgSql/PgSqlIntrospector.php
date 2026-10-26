<?php

namespace TusharKhan\DbErrorExplainer\Drivers\PgSql;

use PDO;
use TusharKhan\DbErrorExplainer\Introspection\IntrospectorInterface;

// Read-only schema queries; $database is the PostgreSQL schema and defaults to current_schema().
// Columns are aliased to MySQL's INFORMATION_SCHEMA names so callers see the same row shape.
class PgSqlIntrospector implements IntrospectorInterface
{
    public function __construct(private PDO $pdo) {}

    public function tableExists(string $table, ?string $database = null): bool
    {
        return (bool) $this->value(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_name = :table AND table_schema = COALESCE(CAST(:schema AS text), current_schema())',
            $table,
            $database
        );
    }

    public function getTableColumns(string $table, ?string $database = null): array
    {
        return $this->rows(
            'SELECT column_name AS "COLUMN_NAME", data_type AS "DATA_TYPE", is_nullable AS "IS_NULLABLE",
                    column_default AS "COLUMN_DEFAULT", character_maximum_length AS "CHARACTER_MAXIMUM_LENGTH"
             FROM information_schema.columns
             WHERE table_name = :table AND table_schema = COALESCE(CAST(:schema AS text), current_schema())
             ORDER BY ordinal_position',
            $table,
            $database
        );
    }

    public function getForeignKeys(string $table, ?string $database = null): array
    {
        return $this->rows(
            'SELECT tc.constraint_name AS "CONSTRAINT_NAME", kcu.column_name AS "COLUMN_NAME",
                    ccu.table_name AS "REFERENCED_TABLE_NAME", ccu.column_name AS "REFERENCED_COLUMN_NAME"
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON kcu.constraint_name = tc.constraint_name AND kcu.table_schema = tc.table_schema
             JOIN information_schema.constraint_column_usage ccu
               ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
             WHERE tc.constraint_type = \'FOREIGN KEY\'
               AND tc.table_name = :table AND tc.table_schema = COALESCE(CAST(:schema AS text), current_schema())',
            $table,
            $database
        );
    }

    public function getUniqueConstraints(string $table, ?string $database = null): array
    {
        return $this->rows(
            'SELECT tc.constraint_name AS "INDEX_NAME", kcu.column_name AS "COLUMN_NAME", 0 AS "NON_UNIQUE"
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON kcu.constraint_name = tc.constraint_name AND kcu.table_schema = tc.table_schema
             WHERE tc.constraint_type IN (\'UNIQUE\', \'PRIMARY KEY\')
               AND tc.table_name = :table AND tc.table_schema = COALESCE(CAST(:schema AS text), current_schema())',
            $table,
            $database
        );
    }

    public function getTableInfo(string $table, ?string $database = null): ?array
    {
        return $this->rows(
            'SELECT table_schema AS "TABLE_SCHEMA", table_name AS "TABLE_NAME", table_type AS "TABLE_TYPE"
             FROM information_schema.tables
             WHERE table_name = :table AND table_schema = COALESCE(CAST(:schema AS text), current_schema())',
            $table,
            $database
        )[0] ?? null;
    }

    public function tableForConstraint(string $constraint, ?string $database = null): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.relname FROM pg_constraint con
             JOIN pg_class c ON c.oid = con.conrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE con.conname = :constraint AND n.nspname = COALESCE(CAST(:schema AS text), current_schema())'
        );
        $stmt->execute(['constraint' => $constraint, 'schema' => $database]);

        return $stmt->fetchColumn() ?: null;
    }

    public function getColumnType(?string $table, ?string $column, ?string $database = null): ?string
    {
        if (!$table || !$column) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT data_type FROM information_schema.columns
             WHERE table_name = :table AND column_name = :column AND table_schema = COALESCE(CAST(:schema AS text), current_schema())'
        );
        $stmt->execute(['table' => $table, 'column' => $column, 'schema' => $database]);

        return $stmt->fetchColumn() ?: null;
    }

    private function rows(string $sql, string $table, ?string $schema): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['table' => $table, 'schema' => $schema]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function value(string $sql, string $table, ?string $schema): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['table' => $table, 'schema' => $schema]);

        return $stmt->fetchColumn();
    }
}
