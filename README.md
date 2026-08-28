# Laravel Schema Drift Detector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/emirkefi/laravel-schema-drift.svg?style=flat-square)](https://packagist.org/packages/emirkefi/laravel-schema-drift)
[![Total Downloads](https://img.shields.io/packagist/dt/emirkefi/laravel-schema-drift.svg?style=flat-square)](https://packagist.org/packages/emirkefi/laravel-schema-drift)
[![License](https://img.shields.io/packagist/l/emirkefi/laravel-schema-drift.svg?style=flat-square)](https://packagist.org/packages/emirkefi/laravel-schema-drift)

A powerful, zero-config Artisan command to detect schema drift between your live database and your Laravel migration files. 

Ever wonder if someone manually tweaked a database column directly in production without writing a migration? Or if a legacy table is sitting in your database completely untracked? This package catches those discrepancies instantly.

## How It Works

Behind the scenes, the package uses a clever "shadow database" approach:
1. It takes a snapshot of your live database schema.
2. It spins up a temporary in-memory SQLite database (or connects to your configured shadow database) and runs all your migration files.
3. It compares the two schemas and outputs a terminal table highlighting missing tables, untracked columns, nullability mismatches, type drift, default value drift, and index discrepancies.

## Features

- 🚀 **Zero-Config Drift Detection**: Compare live databases directly against migration files.
- 🔄 **Cross-Database Type Normalization Engine**: SQLite shadow databases use loose type affinity. Our built-in `TypeNormalizer` accurately maps dialect-specific column types across **MySQL**, **PostgreSQL**, **SQLite**, and **SQL Server** to canonical types (`integer`, `bigint`, `boolean`, `decimal`, `string`, `datetime`, `json`, `binary`), eliminating false-positive type mismatches (e.g. MySQL `TINYINT(1)` vs SQLite boolean/integer).
- 🏷️ **Smart Default Value Normalization**: Strips dialect-specific default wrappers (such as Postgres casts `'val'::character varying`, SQL Server `((0))`, MySQL bit literals `b'1'`, and boolean string variants) to ensure accurate default comparisons.
- 🗄️ **Custom Shadow Connections**: Have migrations containing raw SQL statements, full-text indexes, GIS/spatial types, or stored procedures that fail on SQLite? Pass a real shadow connection (e.g. `--shadow-connection=mysql_testing`) to run migrations against a dedicated test database.
- 🛡️ **Built-in Safety Guardrails**: Prevents accidentally running shadow migrations against your target live/production connection.
- 🎯 **Fine-Grained Strictness Checks**: Enable or disable checks for indexes, foreign keys, column types, and defaults.
- 🔍 **Ignore Patterns**: Exclude vendor, framework, or legacy tables with wildcard support (e.g. `pma__*`).

## Requirements

- PHP 8.2 or higher
- Laravel 11.0, 12.0, or 13.0
- SQLite PHP extension enabled (when using the default in-memory shadow database)

## Installation

You can install the package via Composer as a `dev` dependency:

```bash
composer require emirkefi/laravel-schema-drift --dev
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=schema-drift-config
```

## Usage

### Basic Usage (In-Memory SQLite Shadow DB)
Run the drift check against your default database connection:

```bash
php artisan schema:drift
```

### Custom Live Connection or Migration Path
Check a specific database connection or custom migration directory:

```bash
php artisan schema:drift --connection=mysql --path=database/migrations
```

### Custom Shadow Connection (MySQL / PostgreSQL / SQL Server)
When migrations contain engine-specific SQL or spatial indexes that SQLite doesn't support, supply a dedicated test database connection:

```bash
php artisan schema:drift --connection=mysql --shadow-connection=mysql_testing --fresh-shadow
```

## Configuration

In `config/schema-drift.php`, you can customize shadow connections, strictness checks, and ignored tables:

```php
return [
    /*
    | Shadow Database Connection
    | Set to null for default in-memory SQLite, or specify a test connection name
    */
    'shadow_connection' => env('SCHEMA_DRIFT_SHADOW_CONNECTION', null),
    'fresh_shadow' => env('SCHEMA_DRIFT_FRESH_SHADOW', true),

    /*
    | Ignore system or vendor tables from drift analysis
    */
    'ignore_tables' => [
        'migrations',
        'failed_jobs',
        'job_batches',
        'sessions',
        'cache',
        'cache_locks',
        'password_reset_tokens',
        'pma__*',
    ],

    /*
    | Strictness checks
    */
    'check_indexes' => true,
    'check_foreign_keys' => true,
    'check_types' => true,
    'check_defaults' => true,
];
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.