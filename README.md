# DB Error Explainer Core

Framework-agnostic PHP library that explains database exceptions in plain language and suggests a fix. Works with any PDO connection to **MySQL / MariaDB, PostgreSQL or SQLite**.

For Laravel, use [`tusharkhan/db-error-explainer-laravel`](../laravel), which wires this up for you.

## Requirements

- PHP 8.4+
- `ext-pdo` with the driver for your database

## Installation

```bash
composer require tusharkhan/db-error-explainer-core
```

## Usage

```php
use TusharKhan\DbErrorExplainer\Explainer;

$explainer = new Explainer($pdo);

try {
    $pdo->exec("INSERT INTO orders (user_id) VALUES (999)");
} catch (PDOException $e) {
    $diagnosis = $explainer->explain($e);

    echo $diagnosis->format();
}
```

`explain()` never throws. If analysis itself fails you get a diagnosis with `errorType` `analysis_failed` and the original message.

The database is detected from the PDO connection (`PDO::ATTR_DRIVER_NAME`). When there is no working connection, for example because connecting failed, pass `null` and name the driver:

```php
$explainer = new Explainer(null, ['driver' => 'mysql']);   // mysql, mariadb, pgsql or sqlite
```

## The diagnosis

| Property | Example |
|---|---|
| `errorType` | `fk_missing_parent`, the rule id, shared across databases |
| `errorCode` | `1452` on MySQL, the SQLSTATE (`23503`) on PostgreSQL, `19` on SQLite |
| `sqlState` | `23000` |
| `explanation` | Plain-language explanation with your table / column names |
| `migrationSuggestion['migration_suggestion']` | SQL fix, or `''` |
| `migrationSuggestion['other_suggestions']` | Tip (often Laravel-specific), or `''` |
| `context` | Extracted names and, with introspection, `table_exists`, `table_columns`, `foreign_keys`, `unique_constraints`, `column_type` |
| `originalMessage` | The database's message |
| `metadata` | `rule_id` (`none` when no rule matched), `driver`, `introspection_enabled`, `timestamp` |

Output helpers: `format()` (readable text), `toArray()`, `toJson()`.

## Options

```php
$explainer = new Explainer($pdo, [
    'driver' => null,                  // null = detect; or 'mysql', 'mariadb', 'pgsql', 'sqlite', or a DriverInterface
    'allow_introspection' => true,     // read-only schema lookups for more specific explanations
    'redact_sensitive_data' => true,   // hide values of keys like password, token, api_key
    'custom_rules' => [],              // see below
], $psrLogger);                        // optional PSR-3 logger: every diagnosis is logged at info level
```

Introspection only runs read-only queries (`information_schema`, `pg_catalog` or SQLite `PRAGMA`s). Turn it off if you don't want the extra queries, e.g. `'allow_introspection' => getenv('APP_ENV') !== 'production'`.

## Custom rules

Rules are arrays, checked after the built-in ones for the active database:

```php
$explainer = new Explainer($pdo, [
    'custom_rules' => [
        [
            'id' => 'orders_status_check',
            'error_code' => [3819],                       // driver codes, and/or
            'sqlstate' => ['HY000'],                      // SQLSTATEs
            'message_regex' => '/orders_status_check/',   // prefers this rule when several match
            'require_message' => true,                    // only match when the regex matches too
            'extract' => fn($normalized) => ['status' => 'unknown'],
            'explanation' => 'Invalid order status "{status}" for table {table}.',
            'migration' => fn($ctx) => [
                'migration_suggestion' => '',
                'other_suggestions' => 'Use one of the OrderStatus enum values.',
            ],
        ],
    ],
]);

$explainer->addCustomRule([...]);   // or later
```

- `{placeholders}` in `explanation` (and in a string `migration`) are filled from the context: what `extract` returns plus `table`, `column`, `error_code` and `error_description`.
- `migration` can be a string template or a callable returning a string or `['migration_suggestion' => ..., 'other_suggestions' => ...]`.
- Rules are validated when loaded. A typo in a key, a missing `id`, nothing to match on or an invalid regex throws an `InvalidArgumentException` naming the rule.

## Adding a database

Implement `TusharKhan\DbErrorExplainer\Drivers\DriverInterface` (rules, message parser, introspector, error descriptions) and pass an instance as the `driver` option. The built-in drivers in `src/Drivers/` are the reference.

## License

MIT. See [LICENSE](../../LICENSE).
