<?php

namespace TusharKhan\DbErrorExplainer;

use PDO;
use Psr\Log\LoggerInterface;
use Throwable;
use TusharKhan\DbErrorExplainer\Classification\ErrorClassifier;
use TusharKhan\DbErrorExplainer\DTO\Diagnosis;
use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Drivers\DriverInterface;
use TusharKhan\DbErrorExplainer\Drivers\DriverResolver;
use TusharKhan\DbErrorExplainer\Introspection\IntrospectorInterface;
use TusharKhan\DbErrorExplainer\Lib\RuleIds;
use TusharKhan\DbErrorExplainer\Logging\LoggerAdapter;
use TusharKhan\DbErrorExplainer\Normalizer\ExceptionNormalizer;
use TusharKhan\DbErrorExplainer\Rules\Rule;
use TusharKhan\DbErrorExplainer\Rules\RuleEngine;

/**
 * Main Explainer Class
 * Orchestrates the analysis of database errors
 */
class Explainer
{
    private array $config;
    private LoggerAdapter $logger;
    private array $rules;
    private ExceptionNormalizer $normalizer;
    private ErrorClassifier $classifier;
    private RuleEngine $ruleEngine;
    private ?IntrospectorInterface $introspector;
    private DriverInterface $driver;

    public function __construct(PDO $pdo, array $config = [], ?LoggerInterface $logger = null)
    {
        $this->config = array_merge([
            'allow_introspection' => true,
            'redact_sensitive_data' => true,
            'custom_rules' => [],
            // null = detect from the PDO connection; or a name like "mysql", or a DriverInterface
            'driver' => null,
        ], $config);

        $this->driver = DriverResolver::resolve($pdo, $this->config['driver']);

        // Initialize logger
        $this->logger = new LoggerAdapter($logger);

        // Load rules
        $this->rules = array_merge(
            $this->driver->rules(),
            array_map(Rule::from(...), $this->config['custom_rules'])
        );

        // Initialize components
        $this->normalizer = new ExceptionNormalizer($this->driver->parser());
        $this->classifier = new ErrorClassifier($this->rules, $this->driver);
        $this->ruleEngine = new RuleEngine($this->driver);
        $this->introspector = $this->config['allow_introspection'] ? $this->driver->introspector($pdo) : null;
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
            if ($this->introspector) {
                $context = $this->enrichContextWithIntrospection($context, $normalized, $rule->id);
            }

            // Step 5: Generate explanation
            $explanation = $this->ruleEngine->generateExplanation($rule, $context);

            // Step 6: Generate migration suggestion
            $migrationSuggestion = $this->ruleEngine->generateMigration($rule, $context);

            // Step 7: Build diagnosis
            $diagnosis = new Diagnosis(
                errorCode: $this->driver->errorCode($normalized),
                errorType: $rule->id,
                explanation: $explanation,
                migrationSuggestion: $migrationSuggestion,
                context: $this->config['redact_sensitive_data'] ? $this->redactSensitiveData($context) : $context,
                sqlState: $normalized->sqlState,
                originalMessage: $this->config['redact_sensitive_data'] 
                    ? $this->redactSensitiveData(['msg' => $normalized->message])['msg']
                    : $normalized->message,
                metadata: [
                    'rule_id' => $rule->id,
                    'driver' => $this->driver->name(),
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
    public function addCustomRule(Rule|array $rule): void
    {
        $this->rules[] = Rule::from($rule);
        $this->classifier = new ErrorClassifier($this->rules, $this->driver);
    }

    /**
     * Enrich context with database introspection
     */
    private function enrichContextWithIntrospection(array $context, NormalizedException $normalized, string $ruleId): array
    {
        if (!$this->introspector) {
            return $context;
        }

        // The parsed table, not the rule's context value, which may be a placeholder like 'your_table'
        $table = $normalized->table;

        try {
            // Some messages name only the constraint (e.g. PostgreSQL duplicates); look its table up
            $constraintName = $normalized->constraint['constraint_name'] ?? null;
            if (!$table && $constraintName) {
                $table = $this->introspector->tableForConstraint($constraintName, $normalized->database);
                if ($table) {
                    $context['table'] = $table;
                }
            }

            if (!$table) {
                return $context;
            }

            // Check if table exists
            $context['table_exists'] = $this->introspector->tableExists(
                $table,
                $normalized->database
            );

            // Get foreign keys if applicable
            if (in_array($ruleId, [RuleIds::FK_MISSING_PARENT, RuleIds::FK_CANNOT_DELETE_PARENT], true)) {
                $foreignKeys = $this->introspector->getForeignKeys(
                    $table,
                    $normalized->database
                );
                $context['foreign_keys'] = $foreignKeys;
            }

            // Get unique constraints if applicable
            if ($ruleId === RuleIds::DUPLICATE_ENTRY) {
                $uniqueConstraints = $this->introspector->getUniqueConstraints(
                    $table,
                    $normalized->database
                );
                $context['unique_constraints'] = $uniqueConstraints;
            }

            // Get table columns
            $columns = $this->introspector->getTableColumns(
                $table,
                $normalized->database
            );
            $context['table_columns'] = array_column($columns, 'COLUMN_NAME');

            // get column type
            $columnType = $this->introspector->getColumnType($table, $normalized->column, $normalized->database);
            $context['column_type'] = $columnType;

        } catch (Throwable $e) {
            $this->logger->logWarning('Introspection failed', [
                'table' => $table,
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
        $errorType = $this->classifier->classifyErrorType($normalized);

        $errorCode = $this->driver->errorCode($normalized);
        $explanation = "Database error {$errorCode}: " . $normalized->message;

        if ($errorType !== 'unknown_error') {
            $explanation .= "\n\nThis appears to be a {$errorType} error.";
        }

        return new Diagnosis(
            errorCode: $errorCode,
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
        // Exact names only; substring matching also hid fields like "key" (the unique index name).
        $sensitiveKeys = ['password', 'passwd', 'pwd', 'secret', 'token', 'api_key', 'access_token', 'auth_token'];

        array_walk_recursive($data, function (&$value, $key) use ($sensitiveKeys) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $value = '***REDACTED***';
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
