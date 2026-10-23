<?php

namespace TusharKhan\DbErrorExplainer\DTO;

/**
 * Diagnosis Data Transfer Object
 * Contains the analysis result of a database error
 */
readonly class Diagnosis
{
    public function __construct(
        public string  $errorCode,
        public string  $errorType,
        public string  $explanation,
        public array   $migrationSuggestion = ['migration_suggestion' => '', 'other_suggestions' => ''],
        public array   $context = [],
        public ?string $sqlState = null,
        public ?string $originalMessage = null,
        public array   $metadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode,
            'error_type' => $this->errorType,
            'explanation' => $this->explanation,
            'migration_suggestion' => $this->migrationSuggestion,
            'context' => $this->context,
            'sql_state' => $this->sqlState,
            'original_message' => $this->originalMessage,
            'metadata' => $this->metadata,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    public function format(): string
    {
        $output = "\n=== Database Error Diagnosis ===\n\n";
        $output .= "Error Type: {$this->errorType}\n";
        $output .= "Error Code: {$this->errorCode}";
        
        if ($this->sqlState) {
            $output .= " (SQLSTATE: {$this->sqlState})";
        }
        
        $output .= "\n\nExplanation:\n{$this->explanation}\n";
        
        $fix = $this->migrationSuggestion['migration_suggestion'] ?? '';
        if ($fix !== '') {
            $output .= "\nSuggested Fix:\n{$fix}\n";
        }

        $other = $this->migrationSuggestion['other_suggestions'] ?? '';
        if ($other !== '') {
            $output .= "\nOther Suggestions:\n{$other}\n";
        }

        if (!empty($this->context)) {
            $output .= "\nContext:\n";
            foreach ($this->context as $key => $value) {
                $value = is_scalar($value) || $value === null ? (string) $value : json_encode($value);
                $output .= "  - {$key}: {$value}\n";
            }
        }
        
        return $output;
    }
}
