<?php

namespace Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Explainer;
use TusharKhan\DbErrorExplainer\Lib\RuleIds;

// Triggers real SQLite errors; needs no server, just pdo_sqlite.
class RealSqliteErrorsTest extends TestCase
{
    private PDO $db;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->exec('PRAGMA foreign_keys = ON');
        $this->db->exec('CREATE TABLE parent (id INTEGER PRIMARY KEY)');
        $this->db->exec('CREATE TABLE child (id INTEGER PRIMARY KEY, parent_id INTEGER REFERENCES parent(id))');
        $this->db->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT UNIQUE, age INTEGER CHECK (age >= 0))');
        $this->db->exec('CREATE TABLE typed (n INTEGER) STRICT');
        $this->db->exec('INSERT INTO parent VALUES (1)');
        $this->db->exec('INSERT INTO child VALUES (1, 1)');
        $this->db->exec("INSERT INTO items (id, name, email) VALUES (1, 'a', 'a@b.c')");
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->files, 'file_exists'));
    }

    public static function queryErrors(): array
    {
        return [
            'duplicate entry' => ["INSERT INTO items (id, name, email) VALUES (2, 'b', 'a@b.c')", RuleIds::DUPLICATE_ENTRY],
            'duplicate primary key' => ["INSERT INTO items (id, name) VALUES (1, 'b')", RuleIds::DUPLICATE_ENTRY],
            'fk missing parent' => ['INSERT INTO child VALUES (2, 99)', RuleIds::FOREIGN_KEY_VIOLATION],
            'fk delete parent' => ['DELETE FROM parent WHERE id = 1', RuleIds::FOREIGN_KEY_VIOLATION],
            'not null' => ['INSERT INTO items (id, name) VALUES (3, NULL)', RuleIds::NULL_CONSTRAINT_VIOLATION],
            'check' => ["INSERT INTO items (id, name, age) VALUES (4, 'x', -1)", RuleIds::CHECK_VIOLATION],
            'strict type' => ["INSERT INTO typed VALUES ('abc')", RuleIds::INCORRECT_VALUE],
            'integer primary key type' => ["INSERT INTO items (id, name) VALUES ('abc', 'x')", RuleIds::INCORRECT_VALUE],
            'unknown column' => ['SELECT nope FROM items', RuleIds::UNKNOWN_COLUMN],
            'unknown column on insert' => ['INSERT INTO items (nope) VALUES (1)', RuleIds::UNKNOWN_COLUMN],
            'table not found' => ['SELECT * FROM missing', RuleIds::TABLE_NOT_FOUND],
            'table exists' => ['CREATE TABLE items (id INT)', RuleIds::TABLE_ALREADY_EXISTS],
            'duplicate column' => ['ALTER TABLE items ADD COLUMN age INT', RuleIds::DUPLICATE_COLUMN],
            'ambiguous column' => ['SELECT id FROM parent JOIN child ON child.parent_id = parent.id', RuleIds::AMBIGUOUS_COLUMN],
            'syntax error' => ['SELEC 1', RuleIds::SYNTAX_ERROR],
            'incomplete input' => ['SELECT * FROM items WHERE', RuleIds::SYNTAX_ERROR],
            'wrong value count' => ['INSERT INTO items (id, name) VALUES (6)', RuleIds::WRONG_VALUE_COUNT],
            'drop missing index' => ['DROP INDEX nope_idx', RuleIds::CANT_DROP_COLUMN_OR_KEY],
            'unknown function' => ['SELECT nope_func(1)', RuleIds::FUNCTION_NOT_DEFINED],
        ];
    }

    #[DataProvider('queryErrors')]
    public function test_query_error_is_explained(string $sql, string $expectedRule)
    {
        $this->assertRuleFor(fn() => $this->db->query($sql)->fetchAll(), $expectedRule);
    }

    public function test_duplicate_names_table_and_column()
    {
        $diagnosis = $this->assertRuleFor(fn() => $this->db->exec("INSERT INTO items (id, name, email) VALUES (2, 'b', 'a@b.c')"), RuleIds::DUPLICATE_ENTRY);

        $this->assertSame('items', $diagnosis->context['table']);
        $this->assertSame('email', $diagnosis->context['columns']);
        $this->assertTrue($diagnosis->context['table_exists']);
        $this->assertSame(['id', 'name', 'email', 'age'], $diagnosis->context['table_columns']);
    }

    public function test_readonly_database()
    {
        $file = $this->file();
        $readonly = new PDO("sqlite:file:{$file}?mode=ro", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $this->assertRuleFor(fn() => $readonly->exec('INSERT INTO t VALUES (1)'), RuleIds::READONLY_DATABASE, $readonly);
    }

    public function test_locked_database()
    {
        $file = $this->file();
        $writer = new PDO("sqlite:{$file}", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $other = new PDO("sqlite:{$file}", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 0]);
        $writer->beginTransaction();
        $writer->exec('INSERT INTO t VALUES (1)');

        try {
            $this->assertRuleFor(fn() => $other->exec('INSERT INTO t VALUES (2)'), RuleIds::LOCK_WAIT_TIMEOUT, $other);
        } finally {
            $writer->rollBack();
        }
    }

    public function test_cannot_open_database()
    {
        $this->assertRuleFor(fn() => new PDO('sqlite:/nonexistent/dir/db.sqlite'), RuleIds::CANNOT_OPEN_DATABASE);
    }

    private function assertRuleFor(callable $trigger, string $expectedRule, ?PDO $connection = null): \TusharKhan\DbErrorExplainer\DTO\Diagnosis
    {
        try {
            $trigger();
        } catch (PDOException $e) {
            $diagnosis = new Explainer($connection ?? $this->db)->explain($e);

            $this->assertSame($expectedRule, $diagnosis->metadata['rule_id'] ?? null, $e->getMessage());
            $this->assertSame('sqlite', $diagnosis->metadata['driver']);
            $this->assertDoesNotMatchRegularExpression('/\{\w+\}/', $diagnosis->explanation, 'Unfilled placeholder');

            return $diagnosis;
        }

        $this->fail('Expected a PDOException');
    }

    // A database file with one table, removed in tearDown
    private function file(): string
    {
        $this->files[] = $file = tempnam(sys_get_temp_dir(), 'dbx');
        new PDO("sqlite:{$file}")->exec('CREATE TABLE t (x)');

        return $file;
    }
}
