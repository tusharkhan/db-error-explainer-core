<?php

namespace TusharKhan\DbErrorExplainer\Migration\Generators;

use TusharKhan\DbErrorExplainer\Lib\RuleIds;

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
        $user = $context['user'] ?? 'your_user';
        $host = $context['host'] ?? '%';
        $database = $context['database'] ?? 'your_database';

        // 1045 is usually a wrong password, so check credentials before granting anything
        $message = $this->sqlComment('Check DB_USERNAME / DB_PASSWORD first. If they are correct, reset the password:');
        $message .= "ALTER USER '{$user}'@'{$host}' IDENTIFIED BY 'new_password';\n";
        $message .= $this->sqlComment('Or grant the user access to the database:');
        $message .= "GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$user}'@'{$host}';";
        return $message;
    }
}