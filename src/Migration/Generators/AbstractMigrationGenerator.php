<?php

namespace TusharKhan\DbErrorExplainer\Migration\Generators;

use TusharKhan\DbErrorExplainer\Migration\Contracts\MigrationGeneratorInterface;

/**
 * Abstract base class for migration generators
 */
abstract class AbstractMigrationGenerator implements MigrationGeneratorInterface
{
    /**
     * Rule IDs that this generator supports
     *
     * @var array<string>
     */
    protected array $supportedRuleIds = [];

    /**
     * Default priority
     *
     * @var int
     */
    protected int $priority = 0;

    /**
     * {@inheritdoc}
     */
    public function supports(string $ruleId): bool
    {
        return in_array($ruleId, $this->supportedRuleIds, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get all supported rule IDs
     *
     * @return array<string>
     */
    public function getSupportedRuleIds(): array
    {
        return $this->supportedRuleIds;
    }

    /**
     * Wrap table name with backticks
     *
     * @param string $name
     * @return string
     */
    protected function wrapIdentifier(string $name): string
    {
        return "`{$name}`";
    }

    /**
     * Format database.table reference
     *
     * @param string|null $database
     * @param string $table
     * @return string
     */
    protected function formatTableReference(?string $database, string $table): string
    {
        if ($database && $database !== 'unknown') {
            return $this->wrapIdentifier($database) . '.' . $this->wrapIdentifier($table);
        }
        return $this->wrapIdentifier($table);
    }

    /**
     * Add SQL comment
     *
     * @param string $comment
     * @return string
     */
    protected function sqlComment(string $comment): string
    {
        return "--{$comment}\n";
    }
}

