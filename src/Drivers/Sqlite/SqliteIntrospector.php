<?php

namespace TusharKhan\DbErrorExplainer\Drivers\Sqlite;

use PDO;
use TusharKhan\DbErrorExplainer\Introspection\IntrospectorInterface;

// Schema lookups via sqlite_master and PRAGMAs; $database is the attached schema name (default "main").
// Rows are aliased to MySQL's INFORMATION_SCHEMA names so callers see the same shape.
class SqliteIntrospector implements IntrospectorInterface
{
    public function __construct(private PDO $pdo) {}

    public function tableExists(string $table, ?string $database = null): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->schema($database)}.sqlite_master WHERE type = 'table' AND name = :table");
        $stmt->execute(['table' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    public function getTableColumns(string $table, ?string $database = null): array
    {
        return array_map(fn(array $col) => [
            'COLUMN_NAME' => $col['name'],
            'DATA_TYPE' => strtolower($col['type']),
            'IS_NULLABLE' => $col['notnull'] ? 'NO' : 'YES',
            'COLUMN_KEY' => $col['pk'] ? 'PRI' : '',
            'COLUMN_DEFAULT' => $col['dflt_value'],
        ], $this->pragma('table_info', $table, $database));
    }

    public function getForeignKeys(string $table, ?string $database = null): array
    {
        return array_map(fn(array $fk) => [
            'CONSTRAINT_NAME' => null,
            'COLUMN_NAME' => $fk['from'],
            'REFERENCED_TABLE_NAME' => $fk['table'],
            'REFERENCED_COLUMN_NAME' => $fk['to'],
        ], $this->pragma('foreign_key_list', $table, $database));
    }

    public function getUniqueConstraints(string $table, ?string $database = null): array
    {
        $rows = [];
        foreach ($this->pragma('index_list', $table, $database) as $index) {
            if (!$index['unique']) {
                continue;
            }
            foreach ($this->pragma('index_info', $index['name'], $database) as $col) {
                $rows[] = ['INDEX_NAME' => $index['name'], 'COLUMN_NAME' => $col['name'], 'NON_UNIQUE' => 0];
            }
        }

        return $rows;
    }

    public function getTableInfo(string $table, ?string $database = null): ?array
    {
        $stmt = $this->pdo->prepare("SELECT type AS TABLE_TYPE, name AS TABLE_NAME, sql AS CREATE_STATEMENT FROM {$this->schema($database)}.sqlite_master WHERE name = :table");
        $stmt->execute(['table' => $table]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // SQLite names indexes, not constraints; autoindexes are named after their table
    public function tableForConstraint(string $constraint, ?string $database = null): ?string
    {
        $stmt = $this->pdo->prepare("SELECT tbl_name FROM {$this->schema($database)}.sqlite_master WHERE name = :name");
        $stmt->execute(['name' => $constraint]);

        return $stmt->fetchColumn() ?: null;
    }

    public function getColumnType(?string $table, ?string $column, ?string $database = null): ?string
    {
        if (!$table || !$column) {
            return null;
        }

        $match = array_find($this->getTableColumns($table, $database), fn(array $col) => $col['COLUMN_NAME'] === $column);

        return $match['DATA_TYPE'] ?? null;
    }

    private function pragma(string $name, string $argument, ?string $database): array
    {
        // PRAGMAs don't take bound parameters, so quote the identifier ourselves
        $quoted = '"' . str_replace('"', '""', $argument) . '"';

        return $this->pdo->query("PRAGMA {$this->schema($database)}.{$name}({$quoted})")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function schema(?string $database): string
    {
        return '"' . str_replace('"', '""', $database ?? 'main') . '"';
    }
}
