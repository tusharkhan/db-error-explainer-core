<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TusharKhan\DbErrorExplainer\Explainer;
use PDO;
use PDOException;

// Example 1: Basic usage with foreign key error
echo "=== Example 1: Foreign Key Violation ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $explainer = new Explainer($pdo);

    // This will fail with foreign key constraint
    $pdo->exec("INSERT INTO child_table (parent_id) VALUES (999)");

} catch (PDOException $e) {
    $diagnosis = $explainer->explain($e);
    
    echo "Error Type: " . $diagnosis->errorType . "\n";
    echo "Explanation:\n" . $diagnosis->explanation . "\n\n";
    
    if ($diagnosis->migrationSuggestion) {
        echo "Suggested Fix:\n" . $diagnosis->migrationSuggestion . "\n\n";
    }
    
    echo "Full JSON:\n";
    echo $diagnosis->toJson(JSON_PRETTY_PRINT) . "\n\n";
}

// Example 2: Duplicate entry error
echo "=== Example 2: Duplicate Entry ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $explainer = new Explainer($pdo);

    // This will fail with duplicate entry
    $pdo->exec("INSERT INTO users (email) VALUES ('duplicate@example.com')");
    $pdo->exec("INSERT INTO users (email) VALUES ('duplicate@example.com')");

} catch (PDOException $e) {
    $diagnosis = $explainer->explain($e);
    
    echo "Error Type: " . $diagnosis->errorType . "\n";
    echo "Explanation:\n" . $diagnosis->explanation . "\n\n";
    echo "Context:\n";
    print_r($diagnosis->context);
}

// Example 3: With custom configuration
echo "=== Example 3: With Custom Configuration ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $explainer = new Explainer($pdo, [
        'allow_introspection' => true,  // Enable schema introspection
        'redact_sensitive_data' => true, // Redact sensitive data
    ]);

    // Table doesn't exist error
    $pdo->query("SELECT * FROM non_existent_table");

} catch (PDOException $e) {
    $diagnosis = $explainer->explain($e);
    
    echo $diagnosis->format() . "\n";
}

// Example 4: With custom rules
echo "=== Example 4: With Custom Rules ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $explainer = new Explainer($pdo, [
        'custom_rules' => [
            [
                'id' => 'custom_timeout',
                'error_code' => [2006],
                'message_regex' => '/server has gone away/i',
                'extract' => function($normalized) {
                    return [];
                },
                'explanation' => 'The MySQL server connection was lost. This usually happens due to:\n' .
                                '1. Long-running queries exceeding wait_timeout\n' .
                                '2. Server restart during operation\n' .
                                '3. Network issues',
                'migration' => function($ctx) {
                    return '-- Check server logs and increase wait_timeout if needed\n' .
                           '-- SET GLOBAL wait_timeout = 28800;';
                },
            ]
        ]
    ]);

    echo "Loaded " . $explainer->getRulesCount() . " rules (including custom)\n";

} catch (PDOException $e) {
    $diagnosis = $explainer->explain($e);
    echo $diagnosis->toJson(JSON_PRETTY_PRINT);
}

// Example 5: Syntax error
echo "=== Example 5: SQL Syntax Error ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $explainer = new Explainer($pdo);

    // Invalid SQL syntax
    $pdo->query("SELCT * FROM users");

} catch (PDOException $e) {
    $diagnosis = $explainer->explain($e);
    
    echo "Error Type: " . $diagnosis->errorType . "\n";
    echo "Explanation:\n" . $diagnosis->explanation . "\n\n";
}

// Example 6: Without introspection (faster, less context)
echo "=== Example 6: Introspection Disabled ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $explainer = new Explainer($pdo, [
        'allow_introspection' => false, // Disable for performance
    ]);

    $pdo->exec("INSERT INTO users (id) VALUES (1)");
    $pdo->exec("INSERT INTO users (id) VALUES (1)"); // Duplicate

} catch (PDOException $e) {
    $diagnosis = $explainer->explain($e);
    
    echo "Metadata:\n";
    print_r($diagnosis->metadata);
    echo "\nExplanation: " . $diagnosis->explanation . "\n";
}
