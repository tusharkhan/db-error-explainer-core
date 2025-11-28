<?php

namespace TusharKhan\DbErrorExplainer;

use PDO;
use Throwable;
use TusharKhan\DbErrorExplainer\DTO\Diagnosis;
use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Lib\Helpers;
use TusharKhan\DbErrorExplainer\Normalizer\ExceptionNormalizer;
use TusharKhan\DbErrorExplainer\Classification\ErrorClassifier;
use TusharKhan\DbErrorExplainer\Rules\RuleEngine;
use TusharKhan\DbErrorExplainer\Introspection\Introspector;
use TusharKhan\DbErrorExplainer\Migration\MigrationSuggester;
use TusharKhan\DbErrorExplainer\Logging\LoggerAdapter;
use Psr\Log\LoggerInterface;

/**
 * Main Explainer Class
 * Orchestrates the analysis of database errors
 */
class Explainer
{
    private PDO $pdo;
    private array $config;
    private ?LoggerAdapter $logger;
    private array $rules;
    private ExceptionNormalizer $normalizer;
    private ErrorClassifier $classifier;
    private RuleEngine $ruleEngine;
    private ?Introspector $introspector;
    private MigrationSuggester $migrationSuggester;

    public function __construct(PDO $pdo, array $config = [], ?LoggerInterface $logger = null)
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'allow_introspection' => true,
            'redact_sensitive_data' => true,
            'custom_rules' => [],
        ], $config);

        // Initialize logger
        $this->logger = new LoggerAdapter($logger);

        // Load rules
        $this->rules = array_merge(
            RuleEngine::loadDefaultRules(),
            $this->config['custom_rules']
        );

        // Initialize components
        $this->normalizer = new ExceptionNormalizer();
        $this->classifier = new ErrorClassifier($this->rules);
        $this->ruleEngine = new RuleEngine();
        $this->introspector = $this->config['allow_introspection'] ? new Introspector($pdo) : null;
        $this->migrationSuggester = new MigrationSuggester();
    }

    /**
     * Main method to explain a database exception
     */
    public function explain(Throwable $exception): Diagnosis
    {
        try {
            // Step 1: Normalize the exception
            $normalized = $this->normalizer->normalize($exception);

            // Step 2: Classify error and select best matching rule
            $rule = $this->classifier->getBestMatch($normalized);

            if ($rule === null) {
                return $this->createUnknownErrorDiagnosis($normalized);
            }

            // Step 3: Execute rule and extract context
            $context = $this->ruleEngine->executeRule($rule, $normalized);

            // Step 4: Optional introspection for additional context
            if ($this->introspector && $normalized->table) {
                $context = $this->enrichContextWithIntrospection($context, $normalized);
            }

            // Step 5: Generate explanation
            $explanation = $this->ruleEngine->generateExplanation($rule, $context);

            // Step 6: Generate migration suggestion
            $migrationSuggestion = $this->ruleEngine->generateMigration($rule, $context);

            // Step 7: Build diagnosis
            $diagnosis = new Diagnosis(
                errorCode: (string) $normalized->code,
                errorType: $this->classifier->classifyErrorType($normalized->code),
                explanation: $explanation,
                migrationSuggestion: $migrationSuggestion,
                context: $this->config['redact_sensitive_data'] ? $this->redactSensitiveData($context) : $context,
                sqlState: $normalized->sqlState,
                originalMessage: $this->config['redact_sensitive_data'] 
                    ? $this->redactSensitiveData(['msg' => $normalized->message])['msg']
                    : $normalized->message,
                metadata: [
                    'rule_id' => $rule['id'] ?? 'unknown',
                    'introspection_enabled' => $this->config['allow_introspection'],
                    'timestamp' => date('Y-m-d H:i:s'),
                ]
            );

            // Step 8: Optional logging
            $this->logger->logDiagnosis($diagnosis->toArray());

            return $diagnosis;

        } catch (Throwable $e) {
            // If analysis fails, return a basic diagnosis
            $this->logger->logError('Failed to analyze exception', [
                'error' => $e->getMessage(),
                'original_exception' => $exception->getMessage(),
            ]);

            return new Diagnosis(
                errorCode: (string) $exception->getCode(),
                errorType: 'analysis_failed',
                explanation: 'Failed to analyze the database error: ' . $e->getMessage(),
                originalMessage: $exception->getMessage()
            );
        }
    }

    /**
     * Add a custom rule
     */
    public function addCustomRule(array $rule): void
    {
        $this->rules[] = $rule;
        $this->classifier = new ErrorClassifier($this->rules);
    }

    /**
     * Enrich context with database introspection
     */
    private function enrichContextWithIntrospection(array $context, NormalizedException $normalized): array
    {
        if (!$this->introspector || !$normalized->table) {
            return $context;
        }

        try {
            // Check if table exists
            $context['table_exists'] = $this->introspector->tableExists(
                $normalized->table,
                $normalized->database
            );

            // Get foreign keys if applicable
            if (in_array($normalized->code, [1451, 1452])) {
                $foreignKeys = $this->introspector->getForeignKeys(
                    $normalized->table,
                    $normalized->database
                );
                $context['foreign_keys'] = $foreignKeys;
            }

            // Get unique constraints if applicable
            if ($normalized->code === 1062) {
                $uniqueConstraints = $this->introspector->getUniqueConstraints(
                    $normalized->table,
                    $normalized->database
                );
                $context['unique_constraints'] = $uniqueConstraints;
            }

            // Get table columns
            $columns = $this->introspector->getTableColumns(
                $normalized->table,
                $normalized->database
            );
            $context['table_columns'] = array_column($columns, 'COLUMN_NAME');

        } catch (Throwable $e) {
            $this->logger->logWarning('Introspection failed', [
                'table' => $normalized->table,
                'error' => $e->getMessage(),
            ]);
        }

        return $context;
    }

    /**
     * Create diagnosis for unknown errors
     */
    private function createUnknownErrorDiagnosis(NormalizedException $normalized): Diagnosis
    {
        $errorType = $this->classifier->classifyErrorType($normalized->code);

        $explanation = "MySQL Error {$normalized->code}: " . $normalized->message;

        if ($errorType !== 'unknown_error') {
            $explanation .= "\n\nThis appears to be a {$errorType} error.";
        }

        return new Diagnosis(
            errorCode: (string) $normalized->code,
            errorType: $errorType,
            explanation: $explanation,
            sqlState: $normalized->sqlState,
            originalMessage: $normalized->message,
            metadata: [
                'rule_id' => 'none',
                'timestamp' => date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Redact sensitive data from context
     */
    private function redactSensitiveData(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'auth'];

        array_walk_recursive($data, function (&$value, $key) use ($sensitiveKeys) {
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    $value = '***REDACTED***';
                }
            }
        });

        return $data;
    }

    /**
     * Get current configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get loaded rules count
     */
    public function getRulesCount(): int
    {
        return count($this->rules);
    }
}
