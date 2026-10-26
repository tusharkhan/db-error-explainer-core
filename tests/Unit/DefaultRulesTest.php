<?php

namespace Unit;

use TusharKhan\DbErrorExplainer\Drivers\MySql\MySqlDriver;
use TusharKhan\DbErrorExplainer\Drivers\PgSql\PgSqlDriver;
use TusharKhan\DbErrorExplainer\Drivers\Sqlite\SqliteDriver;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\DTO\Diagnosis;
use TusharKhan\DbErrorExplainer\Explainer;
use Support\FakePdoException;
use TusharKhan\DbErrorExplainer\Rules\Rule;
use TusharKhan\DbErrorExplainer\Rules\RuleEngine;

class DefaultRulesTest extends TestCase
{
    public static function rulesProvider(): array
    {
        $cases = [];
        foreach ([new MySqlDriver(), new PgSqlDriver(), new SqliteDriver()] as $driver) {
            foreach ($driver->rules() as $rule) {
                // PostgreSQL rules have no codes; its driver code is always 7
                $cases["{$driver->name()}: {$rule->id}"] = [$driver->name(), $rule->errorCodes ?: [7], $rule->sqlStates ?: ['HY000']];
            }
        }

        return $cases;
    }

    #[DataProvider('rulesProvider')]
    public function test_every_default_rule_produces_a_diagnosis(string $driver, array $codes, array $sqlStates)
    {
        $explainer = new Explainer(new PDO('sqlite::memory:'), ['driver' => $driver, 'allow_introspection' => false]);

        $diagnosis = $explainer->explain(FakePdoException::make(
            "SQLSTATE[{$sqlStates[0]}]: test error",
            [$sqlStates[0], $codes[0], 'test error'],
            $codes[0]
        ));

        $this->assertNotSame('analysis_failed', $diagnosis->errorType, $diagnosis->explanation);
        $this->assertIsString($diagnosis->migrationSuggestion['migration_suggestion']);
        $this->assertIsString($diagnosis->migrationSuggestion['other_suggestions']);
    }

    public function test_string_migration_is_normalized()
    {
        $this->assertSame(
            ['migration_suggestion' => 'USE db;', 'other_suggestions' => ''],
            new RuleEngine()->generateMigration(new Rule('r', migration: fn() => 'USE db;'), [])
        );
    }

    public function test_missing_migration_is_normalized()
    {
        $this->assertSame(
            ['migration_suggestion' => '', 'other_suggestions' => ''],
            new RuleEngine()->generateMigration(new Rule('r'), [])
        );
    }

    public function test_foreign_server_name_is_extracted()
    {
        $explainer = new Explainer(new PDO('sqlite::memory:'), ['driver' => 'mysql', 'allow_introspection' => false]);
        $message = 'The foreign server, fed1, you are trying to create already exists.';

        $diagnosis = $explainer->explain(FakePdoException::make($message, ['HY000', 1476, $message], 1476));

        $this->assertSame('DROP SERVER `fed1`;', $diagnosis->migrationSuggestion['migration_suggestion']);
    }

    public function test_format_handles_array_values()
    {
        $diagnosis = new Diagnosis(
            errorCode: '1062',
            errorType: 'duplicate_entry',
            explanation: 'Duplicate entry',
            migrationSuggestion: ['migration_suggestion' => 'ALTER TABLE x;', 'other_suggestions' => ''],
            context: ['table' => 'users', 'constraint' => ['name' => 'users_email_unique']],
        );

        $output = $diagnosis->format();

        $this->assertStringContainsString("Suggested Fix:\nALTER TABLE x;", $output);
        $this->assertStringContainsString('constraint: {"name":"users_email_unique"}', $output);
        $this->assertStringNotContainsString('Array', $output);
    }
}
