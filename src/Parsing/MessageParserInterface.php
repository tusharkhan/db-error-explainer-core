<?php

namespace TusharKhan\DbErrorExplainer\Parsing;

// Each database words its errors differently, so each driver brings its own parser.
interface MessageParserInterface
{
    public function table(string $message): ?string;

    public function column(string $message): ?string;

    public function database(string $message): ?string;

    public function constraintDetails(string $message): array;
}
