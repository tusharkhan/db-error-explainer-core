<?php

namespace Unit;

use TusharKhan\DbErrorExplainer\Drivers\MySql\MySqlDriver;
use TusharKhan\DbErrorExplainer\Drivers\PgSql\PgSqlDriver;
use TusharKhan\DbErrorExplainer\Drivers\Sqlite\SqliteDriver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Rules\Rule;
use TusharKhan\DbErrorExplainer\Rules\RuleEngine;

class RuleTest extends TestCase
{
    public function test_default_rules_are_all_valid()
    {
        $this->assertContainsOnlyInstancesOf(Rule::class, new MySqlDriver()->rules());
    }

    public function test_every_default_rule_has_an_explanation()
    {
        $rules = [...new MySqlDriver()->rules(), ...new PgSqlDriver()->rules(), ...new SqliteDriver()->rules()];
        $missing = array_filter($rules, fn(Rule $rule) => $rule->explanation === null);

        $this->assertSame([], array_map(fn(Rule $rule) => $rule->id, array_values($missing)));
    }

    public static function invalidRules(): array
    {
        return [
            'missing id' => [['error_code' => [1062]], 'missing a string "id"'],
            'typo in key' => [['id' => 'r', 'error_code' => [1062], 'explaination' => 'x'], 'unknown key(s): explaination'],
            'nothing to match on' => [['id' => 'r'], 'needs an error_code or a sqlstate'],
            'bad regex' => [['id' => 'r', 'error_code' => [1], 'message_regex' => 'no delimiters'], 'invalid message_regex'],
            'extract not callable' => [['id' => 'r', 'error_code' => [1], 'extract' => 42], 'extract must be callable'],
            'migration wrong type' => [['id' => 'r', 'error_code' => [1], 'migration' => 42], 'migration must be a string or callable'],
        ];
    }

    #[DataProvider('invalidRules')]
    public function test_invalid_rule_is_rejected(array $definition, string $message)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        Rule::fromArray($definition);
    }

    public function test_string_migration_is_kept_as_template()
    {
        // "date" is also a PHP function name; it must not be called
        $this->assertSame('date', Rule::fromArray(['id' => 'r', 'error_code' => [1], 'migration' => 'date'])->migration);
    }

    public function test_matches_on_code_and_sqlstate()
    {
        $rule = Rule::fromArray(['id' => 'r', 'error_code' => [1062], 'sqlstate' => ['23000']]);

        $this->assertTrue($rule->matches(new NormalizedException(message: '', code: 1062, sqlState: '23000')));
        $this->assertFalse($rule->matches(new NormalizedException(message: '', code: 1062, sqlState: 'HY000')));
        $this->assertFalse($rule->matches(new NormalizedException(message: '', code: 1048, sqlState: '23000')));
    }

    public function test_rule_without_explanation_uses_error_description()
    {
        $explanation = new RuleEngine()->generateExplanation(new Rule('r'), ['error_description' => 'Deadlock found when trying to get lock']);

        $this->assertSame('Deadlock found when trying to get lock.', $explanation);
    }
}
