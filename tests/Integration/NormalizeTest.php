<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Normalizer\ExceptionNormalizer;

class NormalizeTest extends TestCase
{
    public function test_fakePDO()
    {
        $ex = $this->fakePdoException(
            'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry',
            ['23000', 1062, 'Duplicate entry'],
            1062
        );

        $this->assertInstanceOf(\PDOException::class, $ex);
        $this->assertSame('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry', $ex->getMessage());
        $this->assertSame(['23000', 1062, 'Duplicate entry'], $ex->errorInfo);
    }

    public function test_test_error_normalize()
    {
        $exception = $this->fakePdoException('SQLSTATE[HY000]: General error: 1 near "DATABASE": syntax error', ['HY000', 1007], 1007);
        $normalizer = new ExceptionNormalizer();
        $normalized = $normalizer->normalize($exception);
        $this->assertSame('SQLSTATE[HY000]: General error: 1 near "DATABASE": syntax error', $normalized->message);
        $this->assertSame(1007, $normalized->code);
        $this->assertSame('HY000', $normalized->sqlState);
        $this->assertSame('000', $normalized->stateSubClass);
        $this->assertSame('HY', $normalized->stateClass);
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