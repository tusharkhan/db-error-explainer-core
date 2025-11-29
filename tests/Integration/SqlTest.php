<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use TusharKhan\DbErrorExplainer\Explainer;
use TusharKhan\DbErrorExplainer\Lib\Helpers;

class SqlTest extends TestCase
{
    public function test_sql_generation(){
//        $pdo = new \PDO('mysql::memory:');
        $pdo = new \PDO('sqlite::memory:');
        $explainer = new Explainer($pdo);

        try {
            // create database
            $pdo->exec("CREATE DATABASE test");
            $pdo->exec("CREATE TABLE orders (order_id INT PRIMARY KEY,customer_id INT,FOREIGN KEY (customer_id) REFERENCES customers(id))");
        } catch (\PDOException $e) {
            Helpers::dd($e->getMessage(), $e->getCode(), $e->errorInfo);
            $diagnosis = $explainer->explain($e);
            $this->assertTrue(true);
            var_dump($diagnosis->toArray());
            exit();
        }
    }
}