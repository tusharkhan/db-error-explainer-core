<?php

namespace TusharKhan\DbErrorExplainer\Drivers;

use PDO;
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

    public function describeError(int $code): string;

    // Type for codes no rule covers, or null when unknown.
    public function fallbackErrorType(int $code): ?string;
}
