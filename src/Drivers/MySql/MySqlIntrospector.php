<?php

namespace TusharKhan\DbErrorExplainer\Drivers\MySql;

use PDO;
use TusharKhan\DbErrorExplainer\Introspection\IntrospectorInterface;

// Read-only schema queries against MySQL's INFORMATION_SCHEMA.
class MySqlIntrospector implements IntrospectorInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get table columns information
     */
    public function getTableColumns(string $table, ?string $database = null): array
    {
        $sql = "SELECT 
                    COLUMN_NAME, 
                    DATA_TYPE, 
                    IS_NULLABLE, 
                    COLUMN_KEY, 
                    COLUMN_DEFAULT,
                    EXTRA
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = :table";

        $params = ['table' => $table];

        if ($database) {
            $sql .= " AND TABLE_SCHEMA = :database";
            $params['database'] = $database;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get foreign key constraints for a table
     */
    public function getForeignKeys(string $table, ?string $database = null): array
    {
        $sql = "SELECT 
                    CONSTRAINT_NAME,
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_NAME = :table 
                AND REFERENCED_TABLE_NAME IS NOT NULL";

        $params = ['table' => $table];

        if ($database) {
            $sql .= " AND TABLE_SCHEMA = :database";
            $params['database'] = $database;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get unique constraints for a table
     */
    public function getUniqueConstraints(string $table, ?string $database = null): array
    {
        $sql = "SELECT 
                    INDEX_NAME,
                    COLUMN_NAME,
                    NON_UNIQUE
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_NAME = :table 
                AND NON_UNIQUE = 0";

        $params = ['table' => $table];

        if ($database) {
            $sql .= " AND TABLE_SCHEMA = :database";
            $params['database'] = $database;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if a table exists
     */
    public function tableExists(string $table, ?string $database = null): bool
    {
        $sql = "SELECT COUNT(*) 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_NAME = :table";

        $params = ['table' => $table];

        if ($database) {
            $sql .= " AND TABLE_SCHEMA = :database";
            $params['database'] = $database;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Get table schema information
     */
    public function getTableInfo(string $table, ?string $database = null): ?array
    {
        $sql = "SELECT * 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_NAME = :table";

        $params = ['table' => $table];

        if ($database) {
            $sql .= " AND TABLE_SCHEMA = :database";
            $params['database'] = $database;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function tableForConstraint(string $constraint, ?string $database = null): ?string
    {
        // MySQL 8 prefixes the key with the table ("users.users_email_unique")
        if (str_contains($constraint, '.')) {
            return explode('.', $constraint, 2)[0];
        }

        $stmt = $this->pdo->prepare(
            'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_NAME = :constraint AND TABLE_SCHEMA = COALESCE(:database, DATABASE())'
        );
        $stmt->execute(['constraint' => $constraint, 'database' => $database]);

        return $stmt->fetchColumn() ?: null;
    }

    public function getColumnType(?string $table, ?string $column, ?string $database = null): ?string
    {
        if (!$table || !$column) {
            return null;
        }

        $sql = "SELECT DATA_TYPE 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = :table 
                AND COLUMN_NAME = :column";

        $params = [
            'table' => $table,
            'column' => $column
        ];

        if ($database) {
            $sql .= " AND TABLE_SCHEMA = :database";
            $params['database'] = $database;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['DATA_TYPE'] : null;
    }
}
