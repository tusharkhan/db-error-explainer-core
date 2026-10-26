<?php

namespace TusharKhan\DbErrorExplainer\Rules;

use Closure;
use InvalidArgumentException;
use TusharKhan\DbErrorExplainer\DTO\NormalizedException;

final readonly class Rule
{
    private const KEYS = ['id', 'error_code', 'sqlstate', 'message_regex', 'require_message', 'extract', 'explanation', 'migration'];

    public function __construct(
        public string $id,
        public array $errorCodes = [],
        public array $sqlStates = [],
        public ?string $messageRegex = null,
        public ?Closure $extract = null,
        public ?string $explanation = null,
        public Closure|string|null $migration = null,
        // Only match when message_regex matches too; for SQLSTATEs shared by unrelated errors (08006, HY000)
        public bool $requireMessage = false,
    ) {}

    // Builds a rule from the array format used by driver rule files and custom_rules; fails loudly on typos.
    public static function fromArray(array $definition): self
    {
        $id = $definition['id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new InvalidArgumentException('Rule is missing a string "id".');
        }

        $unknown = array_diff(array_keys($definition), self::KEYS);
        if ($unknown !== []) {
            throw new InvalidArgumentException("Rule \"{$id}\" has unknown key(s): " . implode(', ', $unknown));
        }

        if (!isset($definition['error_code']) && !isset($definition['sqlstate'])) {
            throw new InvalidArgumentException("Rule \"{$id}\" needs an error_code or a sqlstate.");
        }

        $regex = $definition['message_regex'] ?? null;
        if ($regex !== null && (!is_string($regex) || @preg_match($regex, '') === false)) {
            throw new InvalidArgumentException("Rule \"{$id}\" has an invalid message_regex.");
        }

        $extract = $definition['extract'] ?? null;
        if ($extract !== null && !is_callable($extract)) {
            throw new InvalidArgumentException("Rule \"{$id}\": extract must be callable.");
        }

        $explanation = $definition['explanation'] ?? null;
        if ($explanation !== null && !is_string($explanation)) {
            throw new InvalidArgumentException("Rule \"{$id}\": explanation must be a string.");
        }

        $requireMessage = $definition['require_message'] ?? false;
        if (!is_bool($requireMessage) || ($requireMessage && $regex === null)) {
            throw new InvalidArgumentException("Rule \"{$id}\": require_message must be a bool and needs a message_regex.");
        }

        // A string migration is a template, not a function name
        $migration = $definition['migration'] ?? null;
        if ($migration !== null && !is_string($migration)) {
            if (!is_callable($migration)) {
                throw new InvalidArgumentException("Rule \"{$id}\": migration must be a string or callable.");
            }
            $migration = Closure::fromCallable($migration);
        }

        return new self(
            id: $id,
            errorCodes: (array) ($definition['error_code'] ?? []),
            sqlStates: (array) ($definition['sqlstate'] ?? []),
            messageRegex: $regex,
            extract: $extract === null ? null : Closure::fromCallable($extract),
            explanation: $explanation,
            migration: $migration,
            requireMessage: $requireMessage,
        );
    }

    public static function from(self|array $rule): self
    {
        return $rule instanceof self ? $rule : self::fromArray($rule);
    }

    public function matches(NormalizedException $normalized): bool
    {
        return ($this->sqlStates === [] || in_array($normalized->sqlState, $this->sqlStates))
            && ($this->errorCodes === [] || in_array($normalized->code, $this->errorCodes))
            && (!$this->requireMessage || $this->matchesMessage($normalized->message));
    }

    public function matchesMessage(string $message): bool
    {
        return $this->messageRegex !== null && preg_match($this->messageRegex, $message) === 1;
    }

    public function handlesCode(int $code): bool
    {
        return in_array($code, $this->errorCodes);
    }
}
