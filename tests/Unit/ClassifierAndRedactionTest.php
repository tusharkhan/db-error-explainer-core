<?php

namespace Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Classification\ErrorClassifier;
use TusharKhan\DbErrorExplainer\DTO\NormalizedException;
use TusharKhan\DbErrorExplainer\Explainer;
use Support\FakePdoException;

class ClassifierAndRedactionTest extends TestCase
{
    public function test_message_regex_breaks_ties_between_rules_with_same_code()
    {
        $classifier = new ErrorClassifier([
            ['id' => 'first', 'error_code' => [1364], 'message_regex' => '/cannot be null/'],
            ['id' => 'second', 'error_code' => [1364], 'message_regex' => '/doesn\'t have a default value/'],
        ]);

        $rule = $classifier->getBestMatch(new NormalizedException(
            message: "Field 'email' doesn't have a default value",
            code: 1364
        ));

        $this->assertSame('second', $rule->id);
    }

    public function test_first_rule_wins_when_no_regex_matches()
    {
        $classifier = new ErrorClassifier([
            ['id' => 'first', 'error_code' => [1364], 'message_regex' => '/nope/'],
            ['id' => 'second', 'error_code' => [1364]],
        ]);

        $rule = $classifier->getBestMatch(new NormalizedException(message: 'anything', code: 1364));

        $this->assertSame('first', $rule->id);
    }

    public function test_duplicate_entry_key_is_not_redacted()
    {
        $explainer = new Explainer(new PDO('sqlite::memory:'), ['driver' => 'mysql', 'allow_introspection' => false]);
        $message = "Duplicate entry 'a@b.c' for key 'users.users_email_unique'";

        $diagnosis = $explainer->explain(FakePdoException::make($message, ['23000', 1062, $message], 1062));

        $this->assertStringNotContainsString('REDACTED', json_encode($diagnosis->context));
    }

    public function test_password_is_redacted()
    {
        $explainer = new Explainer(new PDO('sqlite::memory:'), [
            'driver' => 'mysql',
            'allow_introspection' => false,
            'custom_rules' => [[
                'id' => 'custom',
                'error_code' => [9999],
                'extract' => fn() => ['password' => 'hunter2', 'db' => ['api_key' => 'abc']],
            ]],
        ]);

        $diagnosis = $explainer->explain(FakePdoException::make('boom', ['HY000', 9999, 'boom'], 9999));

        $this->assertSame('***REDACTED***', $diagnosis->context['password']);
        $this->assertSame('***REDACTED***', $diagnosis->context['db']['api_key']);
    }
}
