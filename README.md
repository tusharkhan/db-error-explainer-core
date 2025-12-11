# DB Error Explainer Core

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A framework-agnostic PHP library for analyzing and explaining database errors with actionable insights.

## Features

- 🔍 **Intelligent Error Analysis**: Automatically classifies MySQL/PDO errors
- 📝 **Human-Readable Explanations**: Converts cryptic database errors into understandable messages
- 🛠️ **Migration Suggestions**: Generates Laravel Blueprint-based migration fixes
- 🔒 **Safe Introspection**: Optional read-only schema analysis
- 📊 **Structured Output**: Returns `Diagnosis` objects for programmatic use
- 🪵 **PSR-3 Logging**: Optional logging support via `LoggerAdapter`

## Requirements

- PHP ^8.2
- PDO extension
- PSR-3 Logger (optional)

## Installation

```bash
composer require tusharkhan/db-error-explainer-core
```

## Usage

[See the documentation](USAGE.md) for usage examples.

## Components

- **Explainer**: Main entry point
- **ExceptionNormalizer**: Extracts canonical data from exceptions
- **ErrorClassifier**: Selects candidate rules based on error codes
- **RuleEngine**: Executes rule matching using PHP array rules
- **Introspector**: Performs safe INFORMATION_SCHEMA queries
- **MigrationSuggester**: Builds Blueprint migration stubs
- **LoggerAdapter**: Wraps PSR-3 logging (optional)
- **Diagnosis**: Structured output object

## Rule Definition

Rules are defined as PHP arrays:

```php
[
    'id' => 'fk_missing_parent',
    'error_code' => [1452],
    'message_regex' => '/foreign key constraint fails/',
    'extract' => function($normalized) {
        return ['table' => $normalized->table];
    },
    'explanation' => 'Foreign key violation: parent row missing.',
    'migration' => function($ctx) {
        return "Schema::table('{$ctx['table']}', fn (Blueprint \$t) => \$t->foreign('user_id')->references('id')->on('users'));";
    },
]
```

## Laravel Integration

For Laravel applications, use the companion package:

```bash
composer require tusharkhan/db-error-explainer-laravel
```

## License

MIT License. See [LICENSE](LICENSE) for details.

## Credits

Developed by [Tushar Khan](https://github.com/tusharkhan)

