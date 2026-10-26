<?php

namespace TusharKhan\DbErrorExplainer\Drivers\PgSql;

use PDO;
use TusharKhan\DbErrorExplainer\Drivers\DriverInterface;
use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Introspection\IntrospectorInterface;
use TusharKhan\DbErrorExplainer\Lib\PgSqlStates;
use TusharKhan\DbErrorExplainer\Parsing\MessageParserInterface;
use TusharKhan\DbErrorExplainer\Rules\Rule;

final class PgSqlDriver implements DriverInterface
{
    /** @var list<Rule>|null */
    private ?array $rules = null;

    public function name(): string
    {
        return 'pgsql';
    }

    public function parser(): MessageParserInterface
    {
        return new PgSqlMessageParser();
    }

    public function rules(): array
    {
        return $this->rules ??= array_map(Rule::fromArray(...), require __DIR__ . '/rules.php');
    }

    public function introspector(PDO $pdo): IntrospectorInterface
    {
        return new PgSqlIntrospector($pdo);
    }

    // PostgreSQL's driver code is always 7; the SQLSTATE is what identifies the error.
    public function errorCode(NormalizedException $error): string
    {
        return $error->sqlState ?? (string) $error->code;
    }

    public function describeError(NormalizedException $error): string
    {
        return str_replace('_', ' ', ucfirst($this->fallbackErrorType($error) ?? 'database error'));
    }

    public function fallbackErrorType(NormalizedException $error): ?string
    {
        return $error->sqlState !== null ? PgSqlStates::className($error->sqlState) : null;
    }
}
