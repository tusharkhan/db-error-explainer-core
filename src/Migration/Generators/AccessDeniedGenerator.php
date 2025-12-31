<?php

namespace TusharKhan\DbErrorExplainer\Migration\Generators;

use TusharKhan\DbErrorExplainer\Lib\RuleIds;
use TusharKhan\DbErrorExplainer\Migration\Generators\AbstractMigrationGenerator;

class AccessDeniedGenerator extends AbstractMigrationGenerator
{
    protected array $supportedRuleIds = [
        RuleIds::ACCESS_DENIED
    ];

    /**
     * @inheritDoc
     */
    public function generate(array $context): string
    {
        $message = $this->sqlComment('Grant access to user:');
        $message .= "GRANT ALL PRIVILEGES ON {$context['user']}.* TO '{$context['user']}'@'%';";
        return $message;
    }
}