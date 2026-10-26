<?php

namespace Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use TusharKhan\DbErrorExplainer\Explainer;
use TusharKhan\DbErrorExplainer\Lib\RuleIds;

// Triggers real PostgreSQL errors so rules are checked against actual messages and SQLSTATEs.
class RealPostgresErrorsTest extends DatabaseTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = $this->pgsql();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('DROP TABLE IF EXISTS rt_child, rt_parent, rt_items, rt_bad CASCADE');
        $this->db->exec('CREATE TABLE rt_parent (id BIGINT PRIMARY KEY)');
        $this->db->exec('CREATE TABLE rt_child (id INT PRIMARY KEY, parent_id BIGINT REFERENCES rt_parent(id))');
        $this->db->exec('CREATE TABLE rt_items (id INT PRIMARY KEY, name VARCHAR(5) NOT NULL, email VARCHAR(50) UNIQUE, age INT CHECK (age >= 0), made TIMESTAMP, cat INT)');
        $this->db->exec('INSERT INTO rt_parent VALUES (1)');
        $this->db->exec('INSERT INTO rt_child VALUES (1, 1)');
        $this->db->exec("INSERT INTO rt_items (id, name, email) VALUES (1, 'a', 'a@b.c')");
    }

    protected function tearDown(): void
    {
        $this->db->exec('DROP TABLE IF EXISTS rt_child, rt_parent, rt_items, rt_bad CASCADE');
    }

    public static function queryErrors(): array
    {
        return [
            'duplicate entry' => ["INSERT INTO rt_items (id, name, email) VALUES (2, 'b', 'a@b.c')", RuleIds::DUPLICATE_ENTRY, '23505'],
            'fk missing parent' => ['INSERT INTO rt_child VALUES (2, 99)', RuleIds::FK_MISSING_PARENT, '23503'],
            'fk delete parent' => ['DELETE FROM rt_parent WHERE id = 1', RuleIds::FK_CANNOT_DELETE_PARENT, '23503'],
            'not null' => ['INSERT INTO rt_items (id, name) VALUES (3, NULL)', RuleIds::NULL_CONSTRAINT_VIOLATION, '23502'],
            'check' => ["INSERT INTO rt_items (id, name, age) VALUES (4, 'x', -1)", RuleIds::CHECK_VIOLATION, '23514'],
            'unknown column' => ['SELECT nope FROM rt_items', RuleIds::UNKNOWN_COLUMN, '42703'],
            'drop unknown column' => ['ALTER TABLE rt_items DROP COLUMN nope', RuleIds::UNKNOWN_COLUMN, '42703'],
            'table not found' => ['SELECT * FROM rt_missing', RuleIds::TABLE_NOT_FOUND, '42P01'],
            'table exists' => ['CREATE TABLE rt_items (id INT)', RuleIds::TABLE_ALREADY_EXISTS, '42P07'],
            'duplicate column' => ['ALTER TABLE rt_items ADD COLUMN age INT', RuleIds::DUPLICATE_COLUMN, '42701'],
            'ambiguous column' => ['SELECT id FROM rt_parent JOIN rt_child ON rt_child.parent_id = rt_parent.id', RuleIds::AMBIGUOUS_COLUMN, '42702'],
            'syntax error' => ['SELEC 1', RuleIds::SYNTAX_ERROR, '42601'],
            'data too long' => ["INSERT INTO rt_items (id, name) VALUES (5, 'abcdefgh')", RuleIds::DATA_TOO_LONG, '22001'],
            'out of range' => ["INSERT INTO rt_items (id, name, age) VALUES (6, 'x', 99999999999)", RuleIds::OUT_OF_RANGE, '22003'],
            'incorrect integer' => ["INSERT INTO rt_items (id, name, age) VALUES (7, 'x', 'abc')", RuleIds::INCORRECT_VALUE, '22P02'],
            'incorrect date' => ["INSERT INTO rt_items (id, name, made) VALUES (8, 'x', '2024-13-45')", RuleIds::INCORRECT_VALUE, '22008'],
            'group by' => ['SELECT id, cat FROM rt_items GROUP BY cat', RuleIds::ONLY_FULL_GROUP_BY, '42803'],
            'fk incompatible' => ['CREATE TABLE rt_bad (id INT PRIMARY KEY, parent_id TEXT REFERENCES rt_parent(id))', RuleIds::FK_INCOMPATIBLE_COLUMNS, '42804'],
            'drop missing index' => ['DROP INDEX rt_nope_idx', RuleIds::CANT_DROP_COLUMN_OR_KEY, '42704'],
            'unknown function' => ['SELECT nope_func(1)', RuleIds::FUNCTION_NOT_DEFINED, '42883'],
            'division by zero' => ['SELECT 1/0', RuleIds::DIVISION_BY_ZERO, '22012'],
        ];
    }

    #[DataProvider('queryErrors')]
    public function test_query_error_is_explained(string $sql, string $expectedRule, string $sqlState)
    {
        $this->assertRuleFor(fn() => $this->db->query($sql)->fetchAll(), $expectedRule, $sqlState);
    }

    public function test_lock_timeout()
    {
        $other = $this->connect();
        $other->beginTransaction();
        $other->exec('UPDATE rt_items SET age = 1 WHERE id = 1');

        try {
            $this->db->exec("SET lock_timeout = '200ms'");
            $this->assertRuleFor(fn() => $this->db->exec('UPDATE rt_items SET age = 2 WHERE id = 1'), RuleIds::LOCK_WAIT_TIMEOUT, '55P03');
        } finally {
            $other->rollBack();
            $this->db->exec('RESET lock_timeout');
        }
    }

    public function test_statement_timeout()
    {
        $conn = $this->connect();
        $conn->exec("SET statement_timeout = '100ms'");

        $this->assertRuleFor(fn() => $conn->query('SELECT pg_sleep(1)'), RuleIds::QUERY_TIMEOUT, '57014');
    }

    public function test_server_closed_connection()
    {
        $conn = $this->connect();
        $pid = $conn->query('SELECT pg_backend_pid()')->fetchColumn();
        $this->db->query("SELECT pg_terminate_backend({$pid})");
        usleep(200_000);

        $this->assertRuleFor(fn() => $conn->query('SELECT 1'), RuleIds::SERVER_GONE_AWAY);
    }

    public function test_wrong_password()
    {
        $this->assertRuleFor(fn() => new PDO(getenv('PG_DSN'), getenv('PG_USER'), 'wrong-password'), RuleIds::ACCESS_DENIED, '08006');
    }

    public function test_unknown_database()
    {
        $dsn = preg_replace('/dbname=[^;]+/', 'dbname=rt_no_such_db', getenv('PG_DSN'));

        $this->assertRuleFor(fn() => new PDO($dsn, getenv('PG_USER'), getenv('PG_PASS')), RuleIds::UNKNOWN_DATABASE, '08006');
    }

    public function test_connection_refused()
    {
        $this->assertRuleFor(fn() => new PDO('pgsql:host=127.0.0.1;port=1;dbname=test', 'postgres', 'x'), RuleIds::CONNECTION_REFUSED, '08006');
    }

    public function test_introspection_adds_schema_details()
    {
        try {
            $this->db->exec("INSERT INTO rt_items (id, name, email) VALUES (2, 'b', 'a@b.c')");
            $this->fail('Expected a PDOException');
        } catch (PDOException $e) {
            $context = new Explainer($this->db)->explain($e)->context;

            $this->assertTrue($context['table_exists']);
            $this->assertSame(['id', 'name', 'email', 'age', 'made', 'cat'], $context['table_columns']);
            $this->assertContains('rt_items_email_key', array_column($context['unique_constraints'], 'INDEX_NAME'));
        }
    }

    private function assertRuleFor(callable $trigger, string $expectedRule, ?string $sqlState = null): void
    {
        try {
            $trigger();
            $this->fail('Expected a PDOException');
        } catch (PDOException $e) {
            $diagnosis = new Explainer($this->db)->explain($e);

            $this->assertSame($expectedRule, $diagnosis->metadata['rule_id'] ?? null, $e->getMessage());
            $this->assertSame('pgsql', $diagnosis->metadata['driver']);
            $this->assertDoesNotMatchRegularExpression('/\{\w+\}/', $diagnosis->explanation, 'Unfilled placeholder');
            if ($sqlState !== null) {
                $this->assertSame($sqlState, $diagnosis->errorCode);
            }
        }
    }

    private function connect(): PDO
    {
        return new PDO(getenv('PG_DSN'), getenv('PG_USER'), getenv('PG_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
}
