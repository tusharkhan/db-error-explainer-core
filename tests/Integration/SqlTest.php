<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Explainer;
use TusharKhan\DbErrorExplainer\Lib\Helpers;

class SqlTest extends TestCase
{
    public function test_sql_generation(){
       $pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', 'password');
        // $pdo = new \PDO('sqlite::memory:');
        $explainer = new Explainer($pdo);

        try {
            // create database
//            $pdo->exec("CREATE DATABASE test");
//            $pdo->exec("USE test");
            $pdo->exec("CREATE TABLE test.orders2 (order_id INT PRIMARY KEY,customer_id INT,FOREIGN KEY (customer_id) REFERENCES customers(id))");
        } catch (\PDOException $e) {
            $diagnosis = $explainer->explain($e);
            Helpers::dd($diagnosis);
            $this->assertEquals("42S01", $diagnosis->sqlState, "Error code should be HY000");
            $this->assertEquals(1050, $diagnosis->errorCode, "Error code should be 1007");
        }
    }
}