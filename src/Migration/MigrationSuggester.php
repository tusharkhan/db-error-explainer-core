<?php

namespace TusharKhan\DbErrorExplainer\Migration;

use TusharKhan\DbErrorExplainer\Migration\Contracts\MigrationGeneratorInterface;

/**
 * Migration Suggester
 *
 * Generates migration code suggestions for fixing database errors.
 * Uses a registry of specialized generators for different error types.
 */
class MigrationSuggester
{
    /**
     * Generator registry
     */
    private MigrationGeneratorRegistry $registry;

    /**
     * Create a new MigrationSuggester
     *
     * @param string $ruleId The rule ID to generate migration for
     * @param array $context Context data for generating the migration
     * @param MigrationGeneratorRegistry|null $registry Custom registry (uses defaults if null)
     */
    public function __construct(
        protected string $ruleId,
        protected array $context = [],
        ?MigrationGeneratorRegistry $registry = null
    ) {
        $this->registry = $registry ?? MigrationGeneratorRegistry::createWithDefaults();
    }

    /**
     * Generate migration suggestion
     *
     * @return array{migration_suggestion: string, other_suggestions: string}
     */
    public function suggest(): array
    {
        $migrationSuggestion = $this->registry->generate($this->ruleId, $this->context);

        return [
            'migration_suggestion' => $migrationSuggestion ?? '',
            'other_suggestions' => $this->getOtherSuggestions(),
        ];
    }

    /**
     * Get additional suggestions based on the rule
     *
     * @return string
     */
    private function getOtherSuggestions(): string
    {
        // Can be extended to provide additional suggestions
        return '';
    }

    /**
     * Check if a migration suggestion is available for the current rule
     *
     * @return bool
     */
    public function hasSuggestion(): bool
    {
        return $this->registry->hasGenerator($this->ruleId);
    }

    /**
     * Get the underlying generator for the current rule
     *
     * @return MigrationGeneratorInterface|null
     */
    public function getGenerator(): ?MigrationGeneratorInterface
    {
        return $this->registry->findGenerator($this->ruleId);
    }

    /**
     * Get the registry
     *
     * @return MigrationGeneratorRegistry
     */
    public function getRegistry(): MigrationGeneratorRegistry
    {
        return $this->registry;
    }

    /**
     * Static factory method for quick suggestions
     *
     * @param string $ruleId
     * @param array $context
     * @return string
     */
    public static function for(string $ruleId, array $context = []): string
    {
        $suggester = new self($ruleId, $context);
        $result = $suggester->suggest();

        return $result['migration_suggestion'];
    }
}
