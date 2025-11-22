<?php

namespace TusharKhan\DbErrorExplainer\DTO;

/**
 * Normalized Exception Data Transfer Object
 * Contains extracted and parsed exception data
 */
class NormalizedException
{
    public function __construct(
        public readonly string $message,
        public readonly int $code,
        public readonly ?string $sqlState = null,
        public readonly ?string $table = null,
        public readonly ?string $column = null,
        public readonly ?string $constraint = null,
        public readonly ?string $database = null,
        public readonly array $rawData = []
    ) {}

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'code' => $this->code,
            'sql_state' => $this->sqlState,
            'table' => $this->table,
            'column' => $this->column,
            'constraint' => $this->constraint,
            'database' => $this->database,
            'raw_data' => $this->rawData,
        ];
    }
}
