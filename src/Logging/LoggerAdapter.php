<?php

namespace TusharKhan\DbErrorExplainer\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Logger Adapter
 * Wraps PSR-3 logger for optional logging
 */
class LoggerAdapter
{
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function logWarning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function logDebug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function logDiagnosis(array $diagnosis): void
    {
        $this->logger->info('Database error diagnosed', [
            'error_code' => $diagnosis['error_code'] ?? 'unknown',
            'error_type' => $diagnosis['error_type'] ?? 'unknown',
            'explanation' => $diagnosis['explanation'] ?? '',
            'has_migration' => !empty($diagnosis['migration_suggestion']),
        ]);
    }
}
