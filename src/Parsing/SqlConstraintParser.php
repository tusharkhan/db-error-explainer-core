<?php

namespace TusharKhan\DbErrorExplainer\Parsing;

// Lists the constraints declared in a CREATE TABLE / ALTER TABLE statement.
class SqlConstraintParser
{
    public function fromSql(string $sql): array
    {
        $constraints = [];

        // Extract FOREIGN KEY constraints
        $fkPattern = '/(?:CONSTRAINT\s+[`"\']?([a-zA-Z0-9_]+)[`"\']?\s+)?FOREIGN\s+KEY\s*\(([^)]+)\)\s*REFERENCES\s+[`"\']?([a-zA-Z0-9_]+)[`"\']?\s*\(([^)]+)\)(?:\s+ON\s+DELETE\s+(CASCADE|SET\s+NULL|SET\s+DEFAULT|RESTRICT|NO\s+ACTION))?(?:\s+ON\s+UPDATE\s+(CASCADE|SET\s+NULL|SET\s+DEFAULT|RESTRICT|NO\s+ACTION))?/i';
        if (preg_match_all($fkPattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $constraints[] = [
                    'constraint_name' => !empty($match[1]) ? trim($match[1], '`"\' ') : null,
                    'constraint_type' => 'FOREIGN KEY',
                    'columns' => $this->parseColumnList($match[2]),
                    'referenced_table' => trim($match[3], '`"\' '),
                    'referenced_columns' => $this->parseColumnList($match[4]),
                    'on_delete' => isset($match[5]) ? strtoupper(trim($match[5])) : null,
                    'on_update' => isset($match[6]) ? strtoupper(trim($match[6])) : null,
                    'expression' => null,
                ];
            }
        }

        // Extract PRIMARY KEY constraints
        $pkPattern = '/(?:CONSTRAINT\s+[`"\']?([a-zA-Z0-9_]+)[`"\']?\s+)?PRIMARY\s+KEY\s*\(([^)]+)\)/i';
        if (preg_match_all($pkPattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $constraints[] = [
                    'constraint_name' => !empty($match[1]) ? trim($match[1], '`"\' ') : null,
                    'constraint_type' => 'PRIMARY KEY',
                    'columns' => $this->parseColumnList($match[2]),
                    'referenced_table' => null,
                    'referenced_columns' => null,
                    'on_delete' => null,
                    'on_update' => null,
                    'expression' => null,
                ];
            }
        }

        // Extract UNIQUE constraints
        $uniquePattern = '/(?:CONSTRAINT\s+[`"\']?([a-zA-Z0-9_]+)[`"\']?\s+)?UNIQUE\s*(?:KEY|INDEX)?\s*(?:[`"\']?[a-zA-Z0-9_]+[`"\']?)?\s*\(([^)]+)\)/i';
        if (preg_match_all($uniquePattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $constraints[] = [
                    'constraint_name' => !empty($match[1]) ? trim($match[1], '`"\' ') : null,
                    'constraint_type' => 'UNIQUE',
                    'columns' => $this->parseColumnList($match[2]),
                    'referenced_table' => null,
                    'referenced_columns' => null,
                    'on_delete' => null,
                    'on_update' => null,
                    'expression' => null,
                ];
            }
        }

        // Extract CHECK constraints
        $checkPattern = '/(?:CONSTRAINT\s+[`"\']?([a-zA-Z0-9_]+)[`"\']?\s+)?CHECK\s*\(([^)]+)\)/i';
        if (preg_match_all($checkPattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $constraints[] = [
                    'constraint_name' => !empty($match[1]) ? trim($match[1], '`"\' ') : null,
                    'constraint_type' => 'CHECK',
                    'columns' => [],
                    'referenced_table' => null,
                    'referenced_columns' => null,
                    'on_delete' => null,
                    'on_update' => null,
                    'expression' => trim($match[2]),
                ];
            }
        }

        // Extract NOT NULL constraints from column definitions
        $notNullPattern = '/[`"\']?([a-zA-Z0-9_]+)[`"\']?\s+[A-Z]+(?:\([^)]*\))?\s+NOT\s+NULL/i';
        if (preg_match_all($notNullPattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $constraints[] = [
                    'constraint_name' => null,
                    'constraint_type' => 'NOT NULL',
                    'columns' => [trim($match[1], '`"\' ')],
                    'referenced_table' => null,
                    'referenced_columns' => null,
                    'on_delete' => null,
                    'on_update' => null,
                    'expression' => null,
                ];
            }
        }

        // Extract DEFAULT constraints from column definitions
        $defaultPattern = '/[`"\']?([a-zA-Z0-9_]+)[`"\']?\s+[A-Z]+(?:\([^)]*\))?\s+.*DEFAULT\s+([^\s,]+)/i';
        if (preg_match_all($defaultPattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $constraints[] = [
                    'constraint_name' => null,
                    'constraint_type' => 'DEFAULT',
                    'columns' => [trim($match[1], '`"\' ')],
                    'referenced_table' => null,
                    'referenced_columns' => null,
                    'on_delete' => null,
                    'on_update' => null,
                    'expression' => trim($match[2], '\'" '),
                ];
            }
        }

        return $constraints;
    }

    // "`a`, `b`" -> ["a", "b"]
    private function parseColumnList(string $columnList): array
    {
        $columns = explode(',', $columnList);
        return array_map(function ($col) {
            return trim($col, " `\"'\t\n\r");
        }, $columns);
    }
}
