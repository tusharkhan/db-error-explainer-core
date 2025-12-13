<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Normalizer\ExceptionNormalizer;

class NormalizeTest extends TestCase
{
    public function test_fakePDO()
    {
        $ex = $this->fakePdoException(
            "MySQL Error 1050: SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'orders2' already exists",
            ['42S01', 1050, "Table 'orders2' already exists"],
            1050
        );

        $this->assertInstanceOf(\PDOException::class, $ex);
        $this->assertSame("MySQL Error 1050: SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'orders2' already exists", $ex->getMessage());
        $this->assertSame(['42S01', 1050, "Table 'orders2' already exists"], $ex->errorInfo);
        $this->assertSame(1050, $ex->getCode());
    }

    public function test_test_error_normalize()
    {
        $exception = $this->fakePdoException(
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

    private function fakePdoException(string $message, ?array $errorInfo = null, $code = 0): \PDOException
    {
        $e = new \PDOException($message, (int) $code);
        if ($errorInfo !== null) {
            $e->errorInfo = $errorInfo;
        }
        return $e;
    }
}