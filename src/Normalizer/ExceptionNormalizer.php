<?php

namespace TusharKhan\DbErrorExplainer\Normalizer;

use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use Throwable;
use PDOException;

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
        if ($exception instanceof PDOException && isset($exception->errorInfo[0])) {
            $sqlState = $exception->errorInfo[0];
            $code = $exception->errorInfo[1] ?? $code;
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

    private function extractTable(string $message): ?string
    {
        // Pattern: table `database`.`table_name`
        if (preg_match('/table\s+`?([a-zA-Z0-9_]+)`?\.`?([a-zA-Z0-9_]+)`?/i', $message, $matches)) {
            return $matches[2];
        }
        
        // Pattern: Table 'table_name'
        if (preg_match("/Table\s+'([a-zA-Z0-9_]+)'/i", $message, $matches)) {
            return $matches[1];
        }
        
        // Pattern: for table `table_name`
        if (preg_match('/for\s+table\s+`?([a-zA-Z0-9_]+)`?/i', $message, $matches)) {
            return $matches[1];
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
