# RuleIds Constants

This document describes the `RuleIds` class which centralizes all rule identifiers used in the DB Error Explainer.

## Overview

The `RuleIds` class is a **final class** that contains all rule identifiers as public constants. This approach provides:

- **Type Safety**: Constants prevent typos and ensure consistency
- **IDE Support**: Autocomplete helps discover available rule IDs
- **Maintainability**: Changes to rule IDs only need to be made in one place
- **Documentation**: All rule IDs are organized in logical groups

## Usage

```php
use TusharKhan\DbErrorExplainer\Rules\RuleIds;

// Use in default_rules.php
[
    'id' => RuleIds::TABLE_ALREADY_EXISTS,
    'error_code' => [MySqlErrorCodes::ER_TABLE_EXISTS_ERROR],
    // ... rest of rule definition
]

// Check if a rule ID exists
if (RuleIds::exists('table_already_exists')) {
    // ...
}

// Get all rule IDs
$allRuleIds = RuleIds::all();
```

## Rule ID Categories

### Table and Database Structure Errors
- `TABLE_ALREADY_EXISTS`
- `NO_DATABASE_SELECTED`
- `CANT_CREATE_DATABASE`
- `CANT_DROP_DATABASE`
- `UNKNOWN_DATABASE`
- `UNKNOWN_TABLE`
- `TABLE_NOT_FOUND`
- `NON_UNIQUE_TABLE`
- And more...

### File and I/O Errors
- `CANT_CREATE_FILE`
- `CANT_OPEN_FILE`
- `FILE_NOT_FOUND`
- `CANT_READ_DIR`
- `IO_READ_ERROR`
- `IO_WRITE_ERROR`

### Key and Index Errors
- `DUPLICATE_KEY`
- `DUPLICATE_KEY_NAME`
- `DUPLICATE_ENTRY`
- `UNKNOWN_KEY_CACHE`

### Column Errors
- `AMBIGUOUS_COLUMN`
- `UNKNOWN_COLUMN`
- `WRONG_FIELD_SPEC`
- `INVALID_USE_OF_NULL`
- `NULL_CONSTRAINT_VIOLATION`

### Access Control and Permissions
- `ACCESS_DENIED`
- `TABLE_ACCESS_DENIED`
- `COLUMN_ACCESS_DENIED`
- `NONEXISTING_GRANT`
- And more...

### Query Syntax and Execution Errors
- `EMPTY_QUERY`
- `SYNTAX_ERROR`
- `SYNTAX_ERROR_GENERAL`
- `WRONG_VALUE_COUNT`
- `REGEXP_ERROR`

### Foreign Key and Constraint Errors
- `FK_MISSING_PARENT`
- `FK_CANNOT_DELETE_PARENT`

### Network and Communication Errors
- `NET_PACKET_TOO_LARGE`
- `NET_READ_ERROR`
- `NET_WRITE_INTERRUPTED`
- And more...

### Storage Engine Errors
- `UNKNOWN_STORAGE_ENGINE`
- `INNODB_FULLTEXT_LIMIT`
- `TABLESPACE_MISSING`
- And more...

## Methods

### `RuleIds::all(): array`
Returns an array of all rule ID values.

```php
$allIds = RuleIds::all();
// ['table_already_exists', 'no_database_selected', ...]
```

### `RuleIds::exists(string $ruleId): bool`
Checks if a rule ID exists in the constants.

```php
if (RuleIds::exists('table_already_exists')) {
    echo "Rule exists!";
}
```

## Implementation Details

The class is marked as `final` to prevent inheritance and uses a private constructor to prevent instantiation. This enforces the class to be used only for its constants.

```php
final class RuleIds
{
    public const TABLE_ALREADY_EXISTS = 'table_already_exists';
    // ... more constants
    
    private function __construct()
    {
        // Prevent instantiation
    }
}
```

## Benefits

1. **Refactoring Safety**: If you need to rename a rule ID, change it in one place
2. **Discovery**: IDEs can show all available constants with autocomplete
3. **Validation**: Helper methods like `exists()` make validation easy
4. **Documentation**: The class serves as living documentation of all rule IDs
5. **Consistency**: Prevents string literal typos throughout the codebase

## Migration from String Literals

Old way:
```php
[
    'id' => 'table_already_exists',
    // ...
]
```

New way:
```php
[
    'id' => RuleIds::TABLE_ALREADY_EXISTS,
    // ...
]
```

## Branch Information

This refactoring was implemented on the `refactor/extract-migration-class` branch as part of improving code structure and maintainability.

