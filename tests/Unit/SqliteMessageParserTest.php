<?php

namespace Unit;

use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Drivers\Sqlite\SqliteMessageParser;

// Messages are copied from real SQLite 3.46 errors as PDO reports them.
class SqliteMessageParserTest extends TestCase
{
    private SqliteMessageParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SqliteMessageParser();
    }

    public function test_unique_on_one_column()
    {
        $message = 'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: users.email';

        $this->assertSame('users', $this->parser->table($message));
        $this->assertSame('email', $this->parser->column($message));
        $this->assertSame('UNIQUE', $this->parser->constraintType($message));
    }

    public function test_composite_unique_lists_every_column()
    {
        $message = 'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: items.a, items.b';

        $this->assertSame(['a', 'b'], $this->parser->constraintColumns($message));
    }

    public function test_check_constraint_name_only_when_named()
    {
        $named = 'SQLSTATE[23000]: Integrity constraint violation: 19 CHECK constraint failed: v_positive';
        $unnamed = 'SQLSTATE[23000]: Integrity constraint violation: 19 CHECK constraint failed: age >= 0';

        $this->assertSame('v_positive', $this->parser->constraintDetails($named)['constraint_name']);
        $this->assertNull($this->parser->constraintDetails($unnamed)['constraint_name']);
    }

    public function test_schema_errors()
    {
        $this->assertSame('posts', $this->parser->table('SQLSTATE[HY000]: General error: 1 no such table: main.posts'));
        $this->assertSame('nope', $this->parser->column('SQLSTATE[HY000]: General error: 1 no such column: "nope"'));
        $this->assertSame('items', $this->parser->table('SQLSTATE[HY000]: General error: 1 table items has no column named nope'));
        $this->assertSame('nope', $this->parser->column('SQLSTATE[HY000]: General error: 1 table items has no column named nope'));
    }
}
