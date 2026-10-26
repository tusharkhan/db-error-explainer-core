<?php

namespace Support;

use PDOException;

final class FakePdoException
{
    // Builds a PDOException the way PDO does, with errorInfo = [SQLSTATE, driver code, message].
    public static function make(string $message, ?array $errorInfo = null, int|string $code = 0): PDOException
    {
        $e = new PDOException($message, (int) $code);
        if ($errorInfo !== null) {
            $e->errorInfo = $errorInfo;
        }

        return $e;
    }
}
