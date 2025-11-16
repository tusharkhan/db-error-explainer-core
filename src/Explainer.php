<?php

namespace TusharKhan\DbErrorExplainer;

use PDO;
use Throwable;
use TusharKhan\DbErrorExplainer\DTO\Diagnosis;
use TusharKhan\DbErrorExplainer\Logging\LoggerAdapter;

class Explainer
{
    private PDO $pdo;
    private array $config;
    private ?LoggerAdapter $logger;
    public function __construct(PDO $pdo, array $config = []){
        $this->pdo = $pdo;
        $this->config = array_merge([
            'allow_introspection' => true,
            'redact_sensitive_data' => true,
            'custom_rules' => [],
        ], $config);
    }

    public function explain(Throwable $exception)
    {
        // Normalize exception

        // classify error and select rule

        // execute rule matching

        // optional introspection

        // migration suggestion

        // build diagnosis

        // logging
    }

    public function addCustomRule(array $rule) : void
    {
        $this->config['custom_rules'][] = $rule;
    }
}