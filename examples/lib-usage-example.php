<?php

/**
 * Example: Using Lib classes in Core package
 *
 * This demonstrates how to use the shared Lib utilities
 * in the framework-agnostic Core package
 */

namespace TusharKhan\DbErrorExplainer\Example;

use TusharKhan\DbErrorExplainer\Lib\Helpers;
use TusharKhan\DbErrorExplainer\Lib\MySqlErrorCodes;

class CoreExample
{
    /**
     * Analyze a PDO exception and extract information
     */
    public function analyzeException(\PDOException $exception): array
    {
        $errorCode = (int) $exception->getCode();
        $errorMessage = $exception->getMessage();

        // Extract table name using Helpers
        $tableName = Helpers::getTableName($errorMessage);

        // Determine error type using MySqlErrorCodes constants
        $errorType = $this->getErrorType($errorCode);

        return [
            'error_code' => $errorCode,
            'error_type' => $errorType,
            'table_name' => $tableName,
            'message' => $errorMessage,
            'is_constraint_violation' => $this->isConstraintViolation($errorCode),
            'is_duplicate_entry' => $errorCode === MySqlErrorCodes::ER_DUP_ENTRY,
            'is_table_not_found' => $errorCode === MySqlErrorCodes::ER_BAD_TABLE_ERROR,
        ];
    }

    /**
     * Get human-readable error type
     */
    private function getErrorType(int $errorCode): string
    {
        return match($errorCode) {
            MySqlErrorCodes::ER_DUP_ENTRY => 'Duplicate Entry',
            MySqlErrorCodes::ER_BAD_TABLE_ERROR => 'Table Not Found',
            MySqlErrorCodes::ER_BAD_DB_ERROR => 'Database Not Found',
            MySqlErrorCodes::ER_TABLE_EXISTS_ERROR => 'Table Already Exists',
            MySqlErrorCodes::ER_CANT_CREATE_TABLE => 'Cannot Create Table',
            MySqlErrorCodes::ER_PARSE_ERROR => 'SQL Syntax Error',
            MySqlErrorCodes::ER_INVALID_USE_OF_NULL => 'Invalid NULL Value',
            default => 'Unknown Error',
        };
    }

    /**
     * Check if error is a constraint violation
     */
    private function isConstraintViolation(int $errorCode): bool
    {
        $constraintErrors = [
            MySqlErrorCodes::ER_DUP_ENTRY,
            MySqlErrorCodes::ER_DUP_KEYNAME,
            MySqlErrorCodes::ER_INVALID_USE_OF_NULL,
        ];

        return in_array($errorCode, $constraintErrors, true);
    }

    /**
     * Example: Parse multiple error messages
     */
    public function parseMultipleErrors(array $errorMessages): array
    {
        $results = [];

        foreach ($errorMessages as $message) {
            $tableName = Helpers::getTableName($message);
            if ($tableName) {
                $results[] = [
                    'message' => $message,
                    'table' => $tableName,
                ];
            }
        }

        return $results;
    }
}

// Usage Example:
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    require_once __DIR__ . '/../vendor/autoload.php';

    $example = new CoreExample();

    // Simulate a PDO exception
    $mockException = new \PDOException(
        "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '123' for key 'users.email'",
        1062
    );

    echo "=== Core Package Example ===\n\n";

    $analysis = $example->analyzeException($mockException);

    echo "Error Analysis:\n";
    print_r($analysis);

    echo "\n=== Multiple Error Messages ===\n\n";

    $messages = [
        "Table 'mydb.users' doesn't exist",
        "Duplicate entry '456' for key 'orders.PRIMARY'",
        "Unknown column 'email' in 'field list'",
        "Table 'products' already exists",
    ];

    $parsed = $example->parseMultipleErrors($messages);
    print_r($parsed);
}

