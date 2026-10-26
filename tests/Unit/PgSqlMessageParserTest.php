<?php

namespace Unit;

use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Drivers\PgSql\PgSqlMessageParser;

// Messages are copied from real PostgreSQL 17 errors.
class PgSqlMessageParserTest extends TestCase
{
    private PgSqlMessageParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PgSqlMessageParser();
    }

    public function test_unique_violation()
    {
        $message = "SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint \"users_email_key\"\nDETAIL:  Key (email)=(a@b.c) already exists.";

        $this->assertSame('email', $this->parser->column($message));
        $this->assertSame(['columns' => 'email', 'value' => 'a@b.c'], $this->parser->keyValue($message));
        $this->assertSame('users_email_key', $this->parser->constraintDetails($message)['constraint_name']);
        $this->assertSame('UNIQUE', $this->parser->constraintDetails($message)['constraint_type']);
    }

    public function test_foreign_key_insert()
    {
        $message = "SQLSTATE[23503]: Foreign key violation: 7 ERROR:  insert or update on table \"posts\" violates foreign key constraint \"posts_user_id_fkey\"\nDETAIL:  Key (user_id)=(99) is not present in table \"users\".";
        $details = $this->parser->constraintDetails($message);

        $this->assertSame('posts', $this->parser->table($message));
        $this->assertSame('user_id', $this->parser->column($message));
        $this->assertSame('FOREIGN KEY', $details['constraint_type']);
        $this->assertSame('users', $details['referenced_table']);
    }

    public function test_not_null()
    {
        $message = "SQLSTATE[23502]: Not null violation: 7 ERROR:  null value in column \"name\" of relation \"items\" violates not-null constraint\nDETAIL:  Failing row contains (3, null).";

        $this->assertSame('items', $this->parser->table($message));
        $this->assertSame('name', $this->parser->column($message));
    }

    public function test_schema_qualified_and_grouping_names_are_stripped()
    {
        $this->assertSame('orders', $this->parser->table('ERROR:  relation "public.orders" does not exist'));
        $this->assertSame('id', $this->parser->column('ERROR:  column "p_t.id" must appear in the GROUP BY clause'));
    }

    public function test_database_and_hint()
    {
        $this->assertSame('shop', $this->parser->database('FATAL:  database "shop" does not exist'));
        $this->assertSame(
            'Perhaps you meant to reference the column "users.name".',
            $this->parser->hint("ERROR:  column \"nme\" does not exist\nHINT:  Perhaps you meant to reference the column \"users.name\".")
        );
    }
}
