<?php

namespace Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Parsing\MessageParser;
use TusharKhan\DbErrorExplainer\Parsing\SqlConstraintParser;

class TestExtractAllConstraints extends TestCase
{
    #[DataProvider('allConstraintsErrorProvider')]
    public function test_extract_all_constraints_from_sql_errors($errorMessage, $expectedResult)
    {
        $result = new MessageParser()->constraintDetails($errorMessage);

        $this->assertEquals($expectedResult['constraint_type'], $result['constraint_type'], 'Constraint type mismatch');

        if (isset($expectedResult['constraint_name'])) {
            $this->assertEquals($expectedResult['constraint_name'], $result['constraint_name'], 'Constraint name mismatch');
        }
        if (isset($expectedResult['table'])) {
            $this->assertEquals($expectedResult['table'], $result['table'], 'Table mismatch');
        }
        if (isset($expectedResult['column'])) {
            $this->assertEquals($expectedResult['column'], $result['column'], 'Column mismatch');
        }
        if (isset($expectedResult['referenced_table'])) {
            $this->assertEquals($expectedResult['referenced_table'], $result['referenced_table'], 'Referenced table mismatch');
        }
        if (isset($expectedResult['referenced_column'])) {
            $this->assertEquals($expectedResult['referenced_column'], $result['referenced_column'], 'Referenced column mismatch');
        }
    }

    public static function allConstraintsErrorProvider()
    {
        return [
            // MySQL Foreign Key - Full details
            [
                'SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`testdb`.`orders`, CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`))',
                [
                    'constraint_type' => 'FOREIGN KEY',
                    'constraint_name' => 'fk_user_id',
                    'table' => 'orders',
                    'column' => 'user_id',
                    'referenced_table' => 'users',
                    'referenced_column' => 'id',
                ]
            ],

            // MySQL Foreign Key with ON DELETE/UPDATE
            [
                'Cannot add or update a child row: a foreign key constraint fails (CONSTRAINT `fk_posts_users` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE)',
                [
                    'constraint_type' => 'FOREIGN KEY',
                    'constraint_name' => 'fk_posts_users',
                    'column' => 'author_id',
                    'referenced_table' => 'users',
                    'referenced_column' => 'id',
                ]
            ],

            // MySQL Unique Constraint
            [
                'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'test@test.com\' for key \'users.email_unique\'',
                [
                    'constraint_type' => 'UNIQUE',
                    'constraint_name' => 'users.email_unique',
                ]
            ],

            // MySQL Primary Key
            [
                'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'1\' for key \'PRIMARY\'',
                [
                    'constraint_type' => 'PRIMARY KEY',
                    'constraint_name' => 'PRIMARY',
                ]
            ],

            // MySQL NOT NULL
            [
                'SQLSTATE[23000]: Integrity constraint violation: 1048 Column \'email\' cannot be null',
                [
                    'constraint_type' => 'NOT NULL',
                    'column' => 'email',
                ]
            ],

            // PostgreSQL Foreign Key
            [
                'SQLSTATE[23503]: Foreign key violation: 7 ERROR: insert or update on table "orders" violates foreign key constraint "orders_user_id_fkey"',
                [
                    'constraint_type' => 'FOREIGN KEY',
                    'constraint_name' => 'orders_user_id_fkey',
                    'table' => 'orders',
                ]
            ],

            // PostgreSQL Unique
            [
                'SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "users_email_key"',
                [
                    'constraint_type' => 'UNIQUE',
                    'constraint_name' => 'users_email_key',
                ]
            ],

            // PostgreSQL Check
            [
                'ERROR: new row for relation "products" violates check constraint "products_price_check"',
                [
                    'constraint_type' => 'CHECK',
                    'constraint_name' => 'products_price_check',
                    'table' => 'products',
                ]
            ],

            // PostgreSQL NOT NULL
            [
                'ERROR: null value in column "email" violates not-null constraint',
                [
                    'constraint_type' => 'NOT NULL',
                    'column' => 'email',
                ]
            ],

            // SQLite UNIQUE
            [
                'UNIQUE constraint failed: users.email',
                [
                    'constraint_type' => 'UNIQUE',
                    'constraint_name' => 'users.email',
                ]
            ],

            // SQLite NOT NULL
            [
                'NOT NULL constraint failed: users.email',
                [
                    'constraint_type' => 'NOT NULL',
                    'constraint_name' => 'users.email',
                ]
            ],

            // SQLite CHECK
            [
                'CHECK constraint failed: positive_price',
                [
                    'constraint_type' => 'CHECK',
                    'constraint_name' => 'positive_price',
                ]
            ],

            // Oracle Unique
            [
                'ORA-00001: unique constraint (HR.USERS_EMAIL_UK) violated',
                [
                    'constraint_type' => 'UNIQUE',
                    'constraint_name' => 'HR.USERS_EMAIL_UK',
                ]
            ],

            // Oracle Foreign Key - now detected via "parent key not found" pattern
            [
                'ORA-02291: integrity constraint (HR.ORDERS_USER_FK) violated - parent key not found',
                [
                    'constraint_type' => 'FOREIGN KEY',
                    'constraint_name' => 'HR.ORDERS_USER_FK',
                ]
            ],

            // Oracle Check
            [
                'ORA-02290: check constraint (HR.SALARY_CHECK) violated',
                [
                    'constraint_type' => 'CHECK',
                    'constraint_name' => 'HR.SALARY_CHECK',
                ]
            ],

            // SQL Server Unique
            [
                'Violation of UNIQUE KEY constraint \'UQ_Email\'. Cannot insert duplicate key.',
                [
                    'constraint_type' => 'UNIQUE',
                    'constraint_name' => 'UQ_Email',
                ]
            ],

            // SQL Server Primary Key
            [
                'Violation of PRIMARY KEY constraint \'PK_Users\'. Cannot insert duplicate key.',
                [
                    'constraint_type' => 'PRIMARY KEY',
                    'constraint_name' => 'PK_Users',
                ]
            ],

            // SQL Server Foreign Key
            [
                'The INSERT statement conflicted with the FOREIGN KEY constraint "FK_Orders_Users". The conflict occurred in database "MyDB", table "dbo.Users", column \'id\'.',
                [
                    'constraint_type' => 'FOREIGN KEY',
                    'constraint_name' => 'FK_Orders_Users',
                ]
            ],

            // SQL Server Check
            [
                'The INSERT statement conflicted with the CHECK constraint "CK_Age". The conflict occurred in database "MyDB", table "dbo.Users", column \'age\'.',
                [
                    'constraint_type' => 'CHECK',
                    'constraint_name' => 'CK_Age',
                ]
            ],
        ];
    }

    #[DataProvider('detectConstraintTypeProvider')]
    public function test_detect_constraint_type($errorMessage, $expectedType)
    {
        $type = new MessageParser()->constraintType($errorMessage);
        $this->assertEquals($expectedType, $type);
    }

    public static function detectConstraintTypeProvider()
    {
        return [
            // FOREIGN KEY detection
            ['a foreign key constraint fails', 'FOREIGN KEY'],
            ['violates foreign key constraint', 'FOREIGN KEY'],
            ['FOREIGN KEY (`user_id`) REFERENCES `users`', 'FOREIGN KEY'],
            ['referential integrity constraint violation', 'FOREIGN KEY'],
            ['constraint FK_users_orders failed', 'FOREIGN KEY'],

            // PRIMARY KEY detection
            ['Duplicate entry \'1\' for key \'PRIMARY\'', 'PRIMARY KEY'],
            ['Violation of PRIMARY KEY constraint', 'PRIMARY KEY'],
            ['PRIMARY KEY constraint failed', 'PRIMARY KEY'],
            ['constraint PK_users violated', 'PRIMARY KEY'],

            // UNIQUE detection
            ['UNIQUE constraint failed: users.email', 'UNIQUE'],
            ['violates unique constraint "users_email_key"', 'UNIQUE'],
            ['Violation of UNIQUE KEY constraint', 'UNIQUE'],
            ['duplicate key value violates unique constraint', 'UNIQUE'],
            ['Duplicate entry \'test\' for key \'email_unique\'', 'UNIQUE'],
            ['constraint UK_email violated', 'UNIQUE'],

            // CHECK detection
            ['CHECK constraint failed: positive_price', 'CHECK'],
            ['violates check constraint', 'CHECK'],
            ['constraint CK_age violated', 'CHECK'],
            ['CHK_status constraint failed', 'CHECK'],

            // NOT NULL detection
            ['NOT NULL constraint failed: users.email', 'NOT NULL'],
            ['Column \'email\' cannot be null', 'NOT NULL'],
            ['null value in column "email"', 'NOT NULL'],
            ['cannot insert NULL into column', 'NOT NULL'],
            ['violates not-null constraint', 'NOT NULL'],
            ['Field \'email\' doesn\'t have a default value', 'NOT NULL'],

            // NOT NULL detection (same as default for doesn't have a default value)
            ['Field \'email\' doesn\'t have a default value', 'NOT NULL'],

            // DEFAULT detection (more specific patterns)
            ['no default value for column', 'DEFAULT'],
            ['default value for column email is invalid', 'DEFAULT'],
        ];
    }

    #[DataProvider('foreignKeyInfoProvider')]
    public function test_extract_foreign_key_info($errorMessage, $expectedTable, $expectedColumn, $expectedOnDelete, $expectedOnUpdate)
    {
        $result = new MessageParser()->foreignKeyInfo($errorMessage);

        $this->assertEquals($expectedTable, $result['referenced_table'], 'Referenced table mismatch');
        $this->assertEquals($expectedColumn, $result['referenced_column'], 'Referenced column mismatch');
        $this->assertEquals($expectedOnDelete, $result['on_delete'], 'ON DELETE mismatch');
        $this->assertEquals($expectedOnUpdate, $result['on_update'], 'ON UPDATE mismatch');
    }

    public static function foreignKeyInfoProvider()
    {
        return [
            // MySQL - Basic
            [
                'FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)',
                'users', 'id', null, null
            ],

            // MySQL - With ON DELETE CASCADE
            [
                'FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE',
                'orders', 'id', 'CASCADE', null
            ],

            // MySQL - With ON UPDATE CASCADE
            [
                'FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE',
                'categories', 'id', null, 'CASCADE'
            ],

            // MySQL - Full options
            [
                'FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
                'posts', 'id', 'SET NULL', 'CASCADE'
            ],

            // Without quotes
            [
                'FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT',
                'users', 'id', 'RESTRICT', 'RESTRICT'
            ],

            // PostgreSQL style
            [
                'REFERENCES "products" ("product_id") ON DELETE NO ACTION ON UPDATE NO ACTION',
                'products', 'product_id', 'NO ACTION', 'NO ACTION'
            ],
        ];
    }

    #[DataProvider('constraintsFromSQLProvider')]
    public function test_extract_constraints_from_sql($sql, $expectedCount, $expectedTypes)
    {
        $constraints = new SqlConstraintParser()->fromSql($sql);

        $this->assertCount($expectedCount, $constraints, 'Constraint count mismatch');

        $actualTypes = array_map(fn($c) => $c['constraint_type'], $constraints);
        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $actualTypes, "Expected constraint type '$type' not found");
        }
    }

    public static function constraintsFromSQLProvider()
    {
        return [
            // Simple table with PRIMARY KEY
            [
                'CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255))',
                0, // PRIMARY KEY without parentheses won't match
                []
            ],

            // Table with explicit PRIMARY KEY
            [
                'CREATE TABLE users (id INT, email VARCHAR(255), PRIMARY KEY (id))',
                1,
                ['PRIMARY KEY']
            ],

            // Table with UNIQUE constraint
            [
                'CREATE TABLE users (id INT, email VARCHAR(255), UNIQUE (email))',
                1,
                ['UNIQUE']
            ],

            // Table with FOREIGN KEY
            [
                'CREATE TABLE orders (id INT, user_id INT, FOREIGN KEY (user_id) REFERENCES users (id))',
                1,
                ['FOREIGN KEY']
            ],

            // Table with CHECK constraint
            [
                'CREATE TABLE products (id INT, price DECIMAL(10,2), CHECK (price > 0))',
                1,
                ['CHECK']
            ],

            // Table with multiple constraints
            [
                'CREATE TABLE orders (
                    id INT, 
                    user_id INT, 
                    status VARCHAR(20),
                    PRIMARY KEY (id),
                    UNIQUE (user_id, status),
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                    CHECK (status IN (\'pending\', \'shipped\', \'delivered\'))
                )',
                4,
                ['PRIMARY KEY', 'UNIQUE', 'FOREIGN KEY', 'CHECK']
            ],

            // Named constraints
            [
                'CREATE TABLE posts (
                    id INT,
                    user_id INT,
                    CONSTRAINT pk_posts PRIMARY KEY (id),
                    CONSTRAINT fk_posts_users FOREIGN KEY (user_id) REFERENCES users (id)
                )',
                2,
                ['PRIMARY KEY', 'FOREIGN KEY']
            ],
        ];
    }

    #[DataProvider('violatingValueProvider')]
    public function test_extract_violating_value($errorMessage, $expectedValue)
    {
        $value = new MessageParser()->violatingValue($errorMessage);
        $this->assertEquals($expectedValue, $value);
    }

    public static function violatingValueProvider()
    {
        return [
            // MySQL duplicate entry
            ['Duplicate entry \'test@example.com\' for key \'email_unique\'', 'test@example.com'],
            ['Duplicate entry "john_doe" for key \'username\'', 'john_doe'],

            // MySQL out of range
            ['Out of range value \'999999\' for column \'age\'', '999999'],

            // MySQL incorrect value
            ['Incorrect value: \'abc\' for column \'price\'', 'abc'],

            // PostgreSQL out of range
            ['value "12345678901234567890" is out of range for type integer', '12345678901234567890'],

            // PostgreSQL enum - note: actual PostgreSQL doesn't include value in simple format
            ['invalid input value for enum status: "unknown"', null],

            // SQL Server with value
            ['value = \'test_value\'', 'test_value'],

            // No value
            ['Column \'email\' cannot be null', null],
            ['NOT NULL constraint failed: users.email', null],
        ];
    }

    #[DataProvider('constraintViolationSummaryProvider')]
    public function test_get_constraint_violation_summary($errorMessage, $expectedType, $expectedName, $hasDetails)
    {
        $summary = new MessageParser()->violationSummary($errorMessage);

        $this->assertEquals($expectedType, $summary['type'], 'Type mismatch');
        $this->assertEquals($expectedName, $summary['name'], 'Name mismatch');

        if ($hasDetails) {
            $this->assertNotEmpty($summary['details'], 'Details should not be empty');
        }
    }

    public static function constraintViolationSummaryProvider()
    {
        return [
            // MySQL Foreign Key
            [
                'SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`testdb`.`orders`, CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`))',
                'FOREIGN KEY',
                'fk_user_id',
                true
            ],

            // MySQL Unique
            [
                'Duplicate entry \'test@test.com\' for key \'users.email_unique\'',
                'UNIQUE',
                'users.email_unique',
                true
            ],

            // PostgreSQL NOT NULL
            [
                'ERROR: null value in column "email" violates not-null constraint',
                'NOT NULL',
                null,
                true
            ],

            // SQLite CHECK
            [
                'CHECK constraint failed: positive_price',
                'CHECK',
                'positive_price',
                true
            ],
        ];
    }
}

