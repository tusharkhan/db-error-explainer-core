<?php

namespace TusharKhan\DbErrorExplainer\Migration\Contracts;

/**
 * Interface for migration generators
 *
 * Each generator handles a specific type of migration suggestion
 */
interface MigrationGeneratorInterface
{
    /**
     * Check if this generator supports the given rule ID
     *
     * @param string $ruleId
     * @return bool
     */
    public function supports(string $ruleId): bool;

    /**
     * Generate migration suggestion
     *
     * @param array $context
     * @return string
     */
    public function generate(array $context): string;

    /**
     * Get the priority of this generator (higher = checked first)
     *
     * @return int
     */
    public function getPriority(): int;
}

