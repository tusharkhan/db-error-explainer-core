<?php

namespace TusharKhan\DbErrorExplainer\DTO;

/**
 * Normalized Exception Data Transfer Object
 * Contains extracted and parsed exception data
 */
readonly class NormalizedException
{
    public function __construct(
        public string  $message,
        public int     $code,
        public ?string $sqlState = null,
        public ?string $stateClass = null,
        public ?string $stateSubClass = null,
        public ?string $table = null,
        public ?string $column = null,
        public ?string $constraint = null,
        public ?string $database = null,
        public array   $rawData = []
    ) {}

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'code' => $this->code,
            'sql_state' => $this->sqlState,
            'state_class' => $this->stateClass,
            'state_subclass' => $this->stateSubClass,
            'table' => $this->table,
            'column' => $this->column,
            'constraint' => $this->constraint,
            'database' => $this->database,
            'raw_data' => $this->rawData,
        ];
    }
}
