<?php

namespace TusharKhan\DbErrorExplainer\Drivers;

use PDO;
use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Introspection\IntrospectorInterface;
use TusharKhan\DbErrorExplainer\Parsing\MessageParserInterface;
use TusharKhan\DbErrorExplainer\Rules\Rule;

// Everything that differs between databases: rules, message parsing, schema lookups and code names.
interface DriverInterface
{
    // Matches PDO::ATTR_DRIVER_NAME, e.g. "mysql".
    public function name(): string;

    public function parser(): MessageParserInterface;

    /** @return list<Rule> */
    public function rules(): array;

    public function introspector(PDO $pdo): IntrospectorInterface;

    // The code shown to users: the driver code for MySQL, the SQLSTATE for PostgreSQL.
    public function errorCode(NormalizedException $error): string;

    public function describeError(NormalizedException $error): string;

    // Type for errors no rule covers, or null when unknown.
    public function fallbackErrorType(NormalizedException $error): ?string;
}
