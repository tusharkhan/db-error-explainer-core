# Core Package Usage Guide

## Installation

```bash
composer require tushar-khan/db-error-explainer-core
```

## Basic Usage

```php
<?php

use TusharKhan\DbErrorExplainer\Explainer;
use PDO;
use PDOException;

// Create PDO connection
$pdo = new PDO('mysql:host=localhost;dbname=myapp', 'user', 'password');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create explainer instance
$explainer = new Explainer($pdo);

try {
    // Execute your query
    $pdo->exec("INSERT INTO orders (user_id) VALUES (999)");

} catch (PDOException $e) {
    // Get human-readable explanation
    $diagnosis = $explainer->explain($e);

    echo $diagnosis->explanation;
    echo $diagnosis->migrationSuggestion;
}
```

## Configuration Options

```php
$explainer = new Explainer($pdo, [
    // Enable/disable database introspection (default: true)
    'allow_introspection' => true,

    // Redact sensitive data in logs (default: true)
    'redact_sensitive_data' => true,

    // Add custom error rules
    'custom_rules' => [
        [
            'id' => 'my_custom_rule',
            'error_code' => [1234],
            'message_regex' => '/custom pattern/i',
            'extract' => function($normalized) {
                return ['key' => 'value'];
            },
            'explanation' => 'Custom explanation with {key}',
            'migration' => function($ctx) {
                return "-- Fix SQL here";
            },
        ]
    ],
]);
```

## Output Formats

### 1. Formatted Text

```php
$diagnosis = $explainer->explain($exception);
echo $diagnosis->format();
```

Output:

```
[ERROR 1452] foreign_key_violation

The operation failed because it violates a foreign key constraint on table 'orders'.
Column 'user_id' references a non-existent value in the parent table.

Suggested Fix:
ALTER TABLE `orders` DROP FOREIGN KEY `fk_orders_user_id`;
```

### 2. JSON

```php
echo $diagnosis->toJson(JSON_PRETTY_PRINT);
```

Output:

```json
{
  "error_code": "1452",
  "error_type": "foreign_key_violation",
  "explanation": "The operation failed...",
  "migration_suggestion": "Schema::table...",
  "context": {
    "table": "orders",
    "column": "user_id"
  },
  "sql_state": "23000",
  "original_message": "SQLSTATE[23000]: Cannot add...",
  "metadata": {
    "rule_id": "foreign_key_violation",
    "introspection_enabled": true,
    "timestamp": "2024-01-15 10:30:00"
  }
}
```

### 3. Array

```php
$data = $diagnosis->toArray();
print_r($data);
```

## Adding Custom Rules

```php
$explainer->addCustomRule([
    'id' => 'custom_business_logic',
    'error_code' => [1644], // SIGNAL error
    'message_regex' => '/Account suspended/i',
    'extract' => function($normalized) {
        preg_match('/account_id: (\d+)/i', $normalized->message, $matches);
        return ['account_id' => $matches[1] ?? null];
    },
    'explanation' => 'Account {account_id} is suspended. Contact support to reactivate.',
    'migration' => function($ctx) {
        return "-- UPDATE accounts SET status = 'active' WHERE id = {$ctx['account_id']};";
    },
]);
```

## With PSR-3 Logger

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('db-errors');
$logger->pushHandler(new StreamHandler('/var/log/db-errors.log'));

$explainer = new Explainer($pdo, [], $logger);

// All diagnoses will be automatically logged
try {
    $pdo->exec("...");
} catch (PDOException $e) {
    $diagnosis = $explainer->explain($e); // Logged automatically
}
```

## Introspection Examples

When introspection is enabled, the explainer queries `INFORMATION_SCHEMA` to gather context:

```php
$explainer = new Explainer($pdo, ['allow_introspection' => true]);

try {
    $pdo->exec("INSERT INTO orders (user_id) VALUES (999)");
} catch (PDOException $e) {
    $diagnosis = $explainer->explain($e);

    // Context includes introspected data
    print_r($diagnosis->context);
    /*
    Array (
        [table] => orders
        [column] => user_id
        [constraint] => fk_orders_user_id
        [table_exists] => true
        [foreign_keys] => Array (
            [0] => Array (
                [CONSTRAINT_NAME] => fk_orders_user_id
                [COLUMN_NAME] => user_id
                [REFERENCED_TABLE_NAME] => users
                [REFERENCED_COLUMN_NAME] => id
            )
        )
        [table_columns] => Array (
            [0] => id
            [1] => user_id
            [2] => total
            [3] => created_at
        )
    )
    */
}
```

## Performance Considerations

### Disable Introspection for High-Traffic Apps

```php
// Faster analysis, less context
$explainer = new Explainer($pdo, [
    'allow_introspection' => false,
]);
```

### Use in Production

```php
// Enable in development/staging only
$explainer = new Explainer($pdo, [
    'allow_introspection' => getenv('APP_ENV') !== 'production',
]);
```

## Error Handling

```php
try {
    $diagnosis = $explainer->explain($exception);
} catch (Throwable $e) {
    // Explainer failed to analyze - fallback
    echo "Analysis failed: " . $e->getMessage();
    echo "Original error: " . $exception->getMessage();
}
```

## API Reference

### Explainer Methods

- `__construct(PDO $pdo, array $config = [], ?LoggerInterface $logger = null)` - Create explainer
- `explain(Throwable $exception): Diagnosis` - Analyze exception
- `addCustomRule(array $rule): void` - Add custom rule
- `getConfig(): array` - Get current config
- `getRulesCount(): int` - Get total rules count

### Diagnosis Properties

- `errorCode: string` - MySQL error code (e.g., "1452")
- `errorType: string` - Classification (e.g., "foreign_key_violation")
- `explanation: string` - Human-readable explanation
- `migrationSuggestion: ?string` - Suggested fix code
- `context: array` - Extracted context data
- `sqlState: ?string` - SQLSTATE code
- `originalMessage: string` - Original error message
- `metadata: array` - Additional metadata

### Diagnosis Methods

- `toArray(): array` - Convert to array
- `toJson(int $flags = 0): string` - Convert to JSON
- `format(): string` - Format as readable text
