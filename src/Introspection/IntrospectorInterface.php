<?php

namespace TusharKhan\DbErrorExplainer\Introspection;

// Read-only schema lookups used to enrich a diagnosis.
interface IntrospectorInterface
{
    public function tableExists(string $table, ?string $database = null): bool;

    // Rows use INFORMATION_SCHEMA column names (COLUMN_NAME, DATA_TYPE, ...) whatever the database.
    public function getTableColumns(string $table, ?string $database = null): array;

    public function getForeignKeys(string $table, ?string $database = null): array;

    public function getUniqueConstraints(string $table, ?string $database = null): array;

    public function getTableInfo(string $table, ?string $database = null): ?array;

    // Table that owns a named constraint, for messages that only mention the constraint.
    public function tableForConstraint(string $constraint, ?string $database = null): ?string;

    public function getColumnType(?string $table, ?string $column, ?string $database = null): ?string;
}
