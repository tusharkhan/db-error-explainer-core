<?php

namespace TusharKhan\DbErrorExplainer\Drivers\Sqlite;

use PDO;
use TusharKhan\DbErrorExplainer\Drivers\DriverInterface;
use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Introspection\IntrospectorInterface;
use TusharKhan\DbErrorExplainer\Lib\SqliteErrorCodes;
use TusharKhan\DbErrorExplainer\Parsing\MessageParserInterface;
use TusharKhan\DbErrorExplainer\Rules\Rule;

final class SqliteDriver implements DriverInterface
{
    /** @var list<Rule>|null */
    private ?array $rules = null;

    public function name(): string
    {
        return 'sqlite';
    }

    public function parser(): MessageParserInterface
    {
        return new SqliteMessageParser();
    }

    public function rules(): array
    {
        return $this->rules ??= array_map(Rule::fromArray(...), require __DIR__ . '/rules.php');
    }

    public function introspector(PDO $pdo): IntrospectorInterface
    {
        return new SqliteIntrospector($pdo);
    }

    public function errorCode(NormalizedException $error): string
    {
        return (string) $error->code;
    }

    public function describeError(NormalizedException $error): string
    {
        return SqliteErrorCodes::getDescription($error->code);
    }

    public function fallbackErrorType(NormalizedException $error): ?string
    {
        return match ($error->code) {
            SqliteErrorCodes::SQLITE_CONSTRAINT => 'constraint_violation',
            SqliteErrorCodes::SQLITE_BUSY, SqliteErrorCodes::SQLITE_LOCKED => 'database_locked',
            SqliteErrorCodes::SQLITE_CORRUPT => 'database_corrupt',
            SqliteErrorCodes::SQLITE_FULL => 'disk_full',
            default => null,
        };
    }
}
