<?php

namespace TusharKhan\DbErrorExplainer\Normalizer;

use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use Throwable;
use PDOException;
use TusharKhan\DbErrorExplainer\Lib\Helpers;

/**
 * Exception Normalizer
 * Extracts and parses database exception information
 */
class ExceptionNormalizer
{
    public function normalize(Throwable $exception): NormalizedException
    {
        $message = $exception->getMessage();
        $code = (int) $exception->getCode();
        
        // Extract SQLSTATE if available
        $sqlState = null;
        $stateClass = null;
        $stateSubClass = null;
        if ($exception instanceof PDOException && isset($exception->errorInfo[0])) {
            $sqlState = $exception->errorInfo[0];
            $code = $exception->errorInfo[1] ?? $code;

            $stateClass = Helpers::getSqlStateClass($sqlState);
            $stateSubClass = Helpers::getSqlStateSubClass($sqlState);
        }
        
        // Extract table name
        $table = $this->extractTable($message);
        
        // Extract column name
        $column = $this->extractColumn($message);
        
        // Extract constraint name
        $constraint = $this->extractConstraint($message);
        
        // Extract database name
        $database = $this->extractDatabase($message);
        
        return new NormalizedException(
            message: $message,
            code: $code,
            sqlState: $sqlState,
            stateClass: $stateClass,
            stateSubClass: $stateSubClass,
            table: $table,
            column: $column,
            constraint: $constraint,
            database: $database,
            rawData: [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]
        );
    }

    private function extractTable(string $errorMessage): ?string {
        $patterns = [
            // MySQL/MariaDB
            "/table ['\"`]?([a-zA-Z0-9_]+)['\"`]?/i",
            "/Table '([^']+)'/i",
            "/for table '([^']+)'/i",

            // PostgreSQL
            '/relation "([^"]+)"/i',
            '/table "([^"]+)"/i',

            // SQLite
            "/table ([a-zA-Z0-9_]+)/i",
            "/no such table: ([a-zA-Z0-9_]+)/i",

            // SQL Server
            "/object '([^']+)'/i",
            "/Invalid object name '([^']+)'/i",

            // Oracle
            "/table or view ([a-zA-Z0-9_]+)/i",

            // Generic patterns
            "/`([a-zA-Z0-9_]+)`/",
            '/"([a-zA-Z0-9_]+)"/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $errorMessage, $matches)) {
                // Clean schema prefix if exists (e.g., schema.table)
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

    private function extractColumn(string $message): ?string
    {
        // Pattern: Column 'column_name'
        if (preg_match("/Column\s+'([a-zA-Z0-9_]+)'/i", $message, $matches)) {
            return $matches[1];
        }
        
        // Pattern: column `column_name`
        if (preg_match('/column\s+`([a-zA-Z0-9_]+)`/i', $message, $matches)) {
            return $matches[1];
        }
        
        // Pattern: FOREIGN KEY (`column_name`)
        if (preg_match('/FOREIGN\s+KEY\s+\(`([a-zA-Z0-9_]+)`\)/i', $message, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    private function extractConstraint(string $message): ?string
    {
        // Pattern: CONSTRAINT `constraint_name`
        if (preg_match('/CONSTRAINT\s+`([a-zA-Z0-9_]+)`/i', $message, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    private function extractDatabase(string $message): ?string
    {
        // Pattern: database 'db_name'
        if (preg_match("/database\s+'([a-zA-Z0-9_]+)'/i", $message, $matches)) {
            return $matches[1];
        }
        
        // Pattern: `database`.`table`
        if (preg_match('/`([a-zA-Z0-9_]+)`\.`[a-zA-Z0-9_]+`/i', $message, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}
