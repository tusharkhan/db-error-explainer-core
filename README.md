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
        'id' => RuleIds::FK_MISSING_PARENT,
        'error_code' => [MySqlErrorCodes::ER_NO_REFERENCED_ROW_2],
        'sqlstate' => [MySqlStates::CONSTANT_VIOLATION],
        'message_regex' => '/foreign key constraint fails.*Cannot add or update/i',
        'extract' => function($normalized) {
            preg_match('/CONSTRAINT `([^`]+)`/', $normalized->message, $constraint);
            preg_match('/FOREIGN KEY \(`([^`]+)`\)/', $normalized->message, $column);
            preg_match('/REFERENCES `([^`]+)`/', $normalized->message, $parentTable);
            preg_match('/\(`([^`]+)`\)/', $normalized->message, $parentColumn);

            return [
                'table' => $normalized->table ?? 'unknown',
                'column' => $column[1] ?? 'unknown',
                'parent_table' => $parentTable[1] ?? 'unknown',
                'constraint' => $constraint[1] ?? null,
                'parent_column' => $parentColumn[1] ?? null
            ];
        },
        'explanation' => 'Foreign key constraint violation: The value you are trying to insert or update in column "{column}" does not exist in the parent table "{parent_table}". You must first insert the referenced record in the parent table.',
        'migration' => function($ctx) {
            return (new \TusharKhan\DbErrorExplainer\Migration\MigrationSuggester(RuleIds::FK_MISSING_PARENT, $ctx))->suggest();
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

