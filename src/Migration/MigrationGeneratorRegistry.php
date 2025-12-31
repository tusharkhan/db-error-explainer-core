<?php

namespace TusharKhan\DbErrorExplainer\Migration;

use TusharKhan\DbErrorExplainer\Migration\Contracts\MigrationGeneratorInterface;
use TusharKhan\DbErrorExplainer\Migration\Generators\AccessDeniedGenerator;
use TusharKhan\DbErrorExplainer\Migration\Generators\AddColumnGenerator;
use TusharKhan\DbErrorExplainer\Migration\Generators\ConstraintGenerator;
use TusharKhan\DbErrorExplainer\Migration\Generators\CreateDatabaseGenerator;
use TusharKhan\DbErrorExplainer\Migration\Generators\CreateTableGenerator;
use TusharKhan\DbErrorExplainer\Migration\Generators\ForeignKeyGenerator;
use TusharKhan\DbErrorExplainer\Migration\Generators\ModifyColumnGenerator;
use TusharKhan\DbErrorExplainer\Migration\Generators\SyntaxErrorGenerator;
use TusharKhan\DbErrorExplainer\Migration\Generators\TableExistGenerator;

/**
 * Registry for migration generators
 *
 * Manages all available migration generators and routes requests to the appropriate one
 */
class MigrationGeneratorRegistry
{
    /**
     * Registered generators
     *
     * @var array<MigrationGeneratorInterface>
     */
    private array $generators = [];

    /**
     * Whether generators are sorted by priority
     *
     * @var bool
     */
    private bool $sorted = false;

    /**
     * Create a new registry with default generators
     *
     * @return self
     */
    public static function createWithDefaults(): self
    {
        $registry = new self();

        // Register default generators
        $registry->register(new CreateTableGenerator());
        $registry->register(new AddColumnGenerator());
        $registry->register(new ForeignKeyGenerator());
        $registry->register(new ConstraintGenerator());
        $registry->register(new ModifyColumnGenerator());
        $registry->register(new SyntaxErrorGenerator());
        $registry->register(new AccessDeniedGenerator());
        $registry->register(new CreateDatabaseGenerator());
        $registry->register(new TableExistGenerator());

        return $registry;
    }

    /**
     * Register a generator
     *
     * @param MigrationGeneratorInterface $generator
     * @return self
     */
    public function register(MigrationGeneratorInterface $generator): self
    {
        $this->generators[] = $generator;
        $this->sorted = false;

        return $this;
    }

    /**
     * Find generator for the given rule ID
     *
     * @param string $ruleId
     * @return MigrationGeneratorInterface|null
     */
    public function findGenerator(string $ruleId): ?MigrationGeneratorInterface
    {
        $this->ensureSorted();

        foreach ($this->generators as $generator) {
            if ($generator->supports($ruleId)) {
                return $generator;
            }
        }

        return null;
    }

    /**
     * Generate migration for the given rule ID
     *
     * @param string $ruleId
     * @param array $context
     * @return string|null
     */
    public function generate(string $ruleId, array $context): ?string
    {
        $generator = $this->findGenerator($ruleId);

        if ($generator === null) {
            return null;
        }

        // Add rule_id to context for generators that need it
        $context['rule_id'] = $ruleId;

        return $generator->generate($context);
    }

    /**
     * Check if a generator exists for the given rule ID
     *
     * @param string $ruleId
     * @return bool
     */
    public function hasGenerator(string $ruleId): bool
    {
        return $this->findGenerator($ruleId) !== null;
    }

    /**
     * Get all registered generators
     *
     * @return array<MigrationGeneratorInterface>
     */
    public function getGenerators(): array
    {
        $this->ensureSorted();

        return $this->generators;
    }

    /**
     * Get all supported rule IDs
     *
     * @return array<string>
     */
    public function getSupportedRuleIds(): array
    {
        $ruleIds = [];

        foreach ($this->generators as $generator) {
            // We need to use reflection or a method to get supported IDs
            if (method_exists($generator, 'getSupportedRuleIds')) {
                $ruleIds = array_merge($ruleIds, $generator->getSupportedRuleIds());
            }
        }

        return array_unique($ruleIds);
    }

    /**
     * Sort generators by priority (higher first)
     */
    private function ensureSorted(): void
    {
        if ($this->sorted) {
            return;
        }

        usort($this->generators, function (MigrationGeneratorInterface $a, MigrationGeneratorInterface $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        $this->sorted = true;
    }
}

