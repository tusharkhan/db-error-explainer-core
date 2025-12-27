<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Lib\Helpers;
use TusharKhan\DbErrorExplainer\Normalizer\ExceptionNormalizer;

class NormalizeTest extends TestCase
{
    public function test_fakePDO()
    {
        $ex = Helpers::fakePdoException(
            "MySQL Error 1050: SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'orders2' already exists",
            ['42S01', 1050, "Table 'orders2' already exists"],
            1050
        );

        $this->assertInstanceOf(\PDOException::class, $ex);
        $this->assertSame("MySQL Error 1050: SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'orders2' already exists", $ex->getMessage());
        $this->assertSame(['42S01', 1050, "Table 'orders2' already exists"], $ex->errorInfo);
        $this->assertSame(1050, $ex->getCode());
    }

    public function test_error_normalize()
    {
        $exception = Helpers::fakePdoException(
            "MySQL Error 1050: SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'orders2' already exists",
            ['42S01', 1050, "Table 'orders2' already exists"],
            1050
        );
        $normalizer = new ExceptionNormalizer();
        $normalized = $normalizer->normalize($exception);
        $this->assertSame("MySQL Error 1050: SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'orders2' already exists", $normalized->message);
        $this->assertSame(1050, $normalized->code);
        $this->assertSame('42S01', $normalized->sqlState);
        $this->assertSame('S01', $normalized->stateSubClass);
        $this->assertSame('42', $normalized->stateClass);
    }

    public function test_table_not_exist()
    {
        $exception = Helpers::fakePdoException(
            "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'orders.orders' doesn't exist",
            ['42S02', 1146, "Table 'orders.orders' doesn't exist"],
            1146
        );

        $normalizer = new ExceptionNormalizer();
        $normalized = $normalizer->normalize($exception);
        $this->assertSame("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'orders.orders' doesn't exist", $normalized->message);
        $this->assertSame(1146, $normalized->code);
        $this->assertSame('42S02', $normalized->sqlState);
        $this->assertSame('S02', $normalized->stateSubClass);
        $this->assertSame('42', $normalized->stateClass);
    }

    public function test_column_not_found()
    {
        $exception = Helpers::fakePdoException(
            "Error 1054: Unknown column 'user_email' in 'field list'",
            ['42S22', 1054, "Unknown column 'user_email' in 'field list'"],
            1054
        );

        $normalizer = new ExceptionNormalizer();
        $normalized = $normalizer->normalize($exception);
        $this->assertSame("Error 1054: Unknown column 'user_email' in 'field list'", $normalized->message);
        $this->assertSame(1054, $normalized->code);
        $this->assertSame('42S22', $normalized->sqlState);
        $this->assertSame('S22', $normalized->stateSubClass);
        $this->assertSame('42', $normalized->stateClass);
        print_r($normalized);
    }
}