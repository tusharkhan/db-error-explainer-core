<?php

namespace TusharKhan\DbErrorExplainer\Classification;

use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Drivers\DriverInterface;
use TusharKhan\DbErrorExplainer\Rules\Rule;

/**
 * Error Classifier
 * Classifies database errors and selects matching rules
 */
class ErrorClassifier
{
    /** @var list<Rule> */
    private array $rules;

    /** @param array<Rule|array> $rules */
    public function __construct(array $rules, private ?DriverInterface $driver = null)
    {
        $this->rules = array_values(array_map(Rule::from(...), $rules));
    }

    /** @return list<Rule> */
    public function findMatchingRules(NormalizedException $normalized): array
    {
        return array_values(array_filter($this->rules, fn(Rule $rule) => $rule->matches($normalized)));
    }

    public function getBestMatch(NormalizedException $normalized): ?Rule
    {
        $matches = $this->findMatchingRules($normalized);

        // Several rules can share a code; prefer the one whose message_regex fits.
        return array_find($matches, fn(Rule $rule) => $rule->matchesMessage($normalized->message))
            ?? $matches[0] ?? null;
    }

    // Used when no rule fully matched: any rule listing this code, then the driver's own grouping.
    public function classifyErrorType(NormalizedException $error): string
    {
        $rule = array_find($this->rules, fn(Rule $rule) => $rule->handlesCode($error->code));

        return $rule->id ?? $this->driver?->fallbackErrorType($error) ?? 'unknown_error';
    }
}
