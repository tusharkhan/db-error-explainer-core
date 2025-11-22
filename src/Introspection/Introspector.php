<?php

namespace TusharKhan\DbErrorExplainer\Introspection;

use PDO;

/**
 * Database Introspector
 * Performs safe read-only schema queries
 */
class Introspector
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
}
