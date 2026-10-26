<?php

namespace TusharKhan\DbErrorExplainer\Normalizer;

use PDOException;
use Throwable;
use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Lib\SqlState;
use TusharKhan\DbErrorExplainer\Parsing\MessageParser;
use TusharKhan\DbErrorExplainer\Parsing\MessageParserInterface;

/**
 * Exception Normalizer
 * Extracts and parses database exception information
 */
class ExceptionNormalizer
{
    public function __construct(private MessageParserInterface $parser = new MessageParser()) {}

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

            $stateClass = SqlState::classOf($sqlState);
            $stateSubClass = SqlState::subclassOf($sqlState);
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
        return $this->parser->table($errorMessage);
    }

    private function extractColumn(string $message): ?string
    {
        return $this->parser->column($message);
    }

    private function extractConstraint(string $message): array
    {
        return $this->parser->constraintDetails($message);
    }

    private function extractDatabase(string $message): ?string
    {
        return $this->parser->database($message);
    }
}
