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
        return Helpers::extractTable($errorMessage);
    }

    private function extractColumn(string $message): ?string
    {
        return Helpers::extractColumn($message);
    }

    private function extractConstraint(string $message): ?string
    {
        return Helpers::extractConstraint($message);
    }

    private function extractDatabase(string $message): ?string
    {
        return Helpers::extractDatabase($message);
    }
}
