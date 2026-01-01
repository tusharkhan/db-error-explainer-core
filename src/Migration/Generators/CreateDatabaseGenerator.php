<?php

namespace TusharKhan\DbErrorExplainer\Migration\Generators;

use TusharKhan\DbErrorExplainer\Lib\RuleIds;

class CreateDatabaseGenerator extends AbstractMigrationGenerator
{
    protected array $supportedRuleIds = [
        RuleIds::UNKNOWN_DATABASE
    ];
    /**
     * @inheritDoc
     */
    public function generate(array $context): string
    {
        $database = $context['database'] ?? 'your_database';
        return "-- Create the database:\nCREATE DATABASE `$database`;";
    }
}