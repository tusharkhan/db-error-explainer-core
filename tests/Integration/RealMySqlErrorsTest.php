<?php

namespace Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use TusharKhan\DbErrorExplainer\Explainer;
use TusharKhan\DbErrorExplainer\Lib\RuleIds;

// Triggers real MySQL errors so rules are checked against actual messages and SQLSTATEs.
class RealMySqlErrorsTest extends DatabaseTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = $this->pdo();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('DROP TABLE IF EXISTS rt_child, rt_parent, rt_items');
        $this->db->exec('CREATE TABLE rt_parent (id BIGINT UNSIGNED PRIMARY KEY)');
        $this->db->exec('CREATE TABLE rt_child (id INT PRIMARY KEY, parent_id BIGINT UNSIGNED, FOREIGN KEY (parent_id) REFERENCES rt_parent(id))');
        $this->db->exec('CREATE TABLE rt_items (id INT PRIMARY KEY, name VARCHAR(5) NOT NULL, email VARCHAR(50) UNIQUE, age INT, made DATETIME, cat INT)');
        $this->db->exec("INSERT INTO rt_parent VALUES (1)");
        $this->db->exec("INSERT INTO rt_child VALUES (1, 1)");
        $this->db->exec("INSERT INTO rt_items (id, name, email) VALUES (1, 'a', 'a@b.c')");
    }

    protected function tearDown(): void
    {
        $this->db->exec('DROP TABLE IF EXISTS rt_child, rt_parent, rt_items');
    }

    public static function queryErrors(): array
    {
        return [
            'duplicate entry' => ["INSERT INTO rt_items (id, name, email) VALUES (2, 'b', 'a@b.c')", RuleIds::DUPLICATE_ENTRY],
            'fk missing parent' => ['INSERT INTO rt_child VALUES (2, 99)', RuleIds::FK_MISSING_PARENT],
            'fk delete parent' => ['DELETE FROM rt_parent WHERE id = 1', RuleIds::FK_CANNOT_DELETE_PARENT],
            'null column' => ['INSERT INTO rt_items (id, name) VALUES (3, NULL)', RuleIds::NULL_CONSTRAINT_VIOLATION],
            'no default' => ['INSERT INTO rt_items (id) VALUES (4)', RuleIds::NO_DEFAULT_FOR_FIELD],
            'unknown column' => ['SELECT nope FROM rt_items', RuleIds::UNKNOWN_COLUMN],
            'table not found' => ['SELECT * FROM rt_missing', RuleIds::TABLE_NOT_FOUND],
            'data too long' => ["INSERT INTO rt_items (id, name) VALUES (5, 'abcdefgh')", RuleIds::DATA_TOO_LONG],
            'table exists' => ['CREATE TABLE rt_items (id INT)', RuleIds::TABLE_ALREADY_EXISTS],
            'ambiguous column' => ['SELECT id FROM rt_parent JOIN rt_child ON rt_child.parent_id = rt_parent.id', RuleIds::AMBIGUOUS_COLUMN],
            'syntax error' => ['SELEC 1', RuleIds::SYNTAX_ERROR],
            'incorrect integer' => ["INSERT INTO rt_items (id, name, age) VALUES (6, 'x', 'abc')", RuleIds::INCORRECT_VALUE],
            'incorrect datetime' => ["INSERT INTO rt_items (id, name, made) VALUES (7, 'x', '2024-13-45')", RuleIds::INCORRECT_VALUE],
            'only full group by' => ['SELECT id, cat FROM rt_items GROUP BY cat', RuleIds::ONLY_FULL_GROUP_BY],
            'duplicate column' => ['ALTER TABLE rt_items ADD COLUMN age INT', RuleIds::DUPLICATE_COLUMN],
            'key too long' => ['CREATE TABLE rt_long (s VARCHAR(1000) CHARACTER SET utf8mb4, INDEX (s(1000)))', RuleIds::KEY_TOO_LONG],
            'cant drop' => ['ALTER TABLE rt_items DROP COLUMN nope', RuleIds::CANT_DROP_COLUMN_OR_KEY],
            'fk incompatible' => ['CREATE TABLE rt_bad (id INT PRIMARY KEY, parent_id INT, FOREIGN KEY (parent_id) REFERENCES rt_parent(id))', RuleIds::FK_INCOMPATIBLE_COLUMNS],
            'fk parent missing' => ['CREATE TABLE rt_bad (id INT PRIMARY KEY, x_id INT, FOREIGN KEY (x_id) REFERENCES rt_nope(id))', RuleIds::FK_PARENT_TABLE_MISSING],
        ];
    }

    #[DataProvider('queryErrors')]
    public function test_query_error_is_explained(string $sql, string $expectedRule)
    {
        $this->assertRuleFor(fn() => $this->db->query($sql)->fetchAll(), $expectedRule);
    }

    public function test_server_gone_away()
    {
        $conn = $this->connect();
        $id = $conn->query('SELECT CONNECTION_ID()')->fetchColumn();
        $this->db->exec("KILL {$id}");

        $this->assertRuleFor(fn() => $conn->query('SELECT 1'), RuleIds::SERVER_GONE_AWAY);
    }

    public function test_access_denied_on_connect()
    {
        $this->assertRuleFor(fn() => new PDO(getenv('DB_DSN'), getenv('DB_USER'), 'wrong-password'), RuleIds::ACCESS_DENIED);
    }

    public function test_unknown_database_on_connect()
    {
        $dsn = preg_replace('/dbname=[^;]+/', 'dbname=rt_no_such_db', getenv('DB_DSN'));

        $this->assertRuleFor(fn() => new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS')), RuleIds::UNKNOWN_DATABASE);
    }

    public function test_connection_refused()
    {
        $this->assertRuleFor(fn() => new PDO('mysql:host=127.0.0.1;port=1;dbname=test', 'root', ''), RuleIds::CONNECTION_REFUSED);
    }

    private function assertRuleFor(callable $trigger, string $expectedRule): void
    {
        try {
            $trigger();
            $this->fail('Expected a PDOException');
        } catch (PDOException $e) {
            $diagnosis = new Explainer($this->db)->explain($e);

            $this->assertSame($expectedRule, $diagnosis->metadata['rule_id'] ?? null, $e->getMessage());
            $this->assertNotSame('analysis_failed', $diagnosis->errorType);
        }
    }

    private function connect(): PDO
    {
        return new PDO(getenv('DB_DSN'), getenv('DB_USER'), getenv('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
}
