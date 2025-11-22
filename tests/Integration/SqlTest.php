<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Explainer;

class SqlTest extends TestCase
{
    public function test_sql_generation(){
        $this->assertTrue(true);

//        $pdo = new \PDO('mysql::memory:');
        $pdo = new \PDO('sqlite::memory:');
        $explainer = new Explainer($pdo);

        try {
            $pdo->exec("INSERT INTO users (id) VALUES (1)");
        } catch (\PDOException $e) {
            $diagnosis = $explainer->explain($e);
            var_dump($diagnosis);
        }
    }
}