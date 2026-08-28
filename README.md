# Laravel Schema Drift Detector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/emirkefi/laravel-schema-drift.svg?style=flat-square)](https://packagist.org/packages/emirkefi/laravel-schema-drift)
[![Total Downloads](https://img.shields.io/packagist/dt/emirkefi/laravel-schema-drift.svg?style=flat-square)](https://packagist.org/packages/emirkefi/laravel-schema-drift)
[![License](https://img.shields.io/packagist/l/emirkefi/laravel-schema-drift.svg?style=flat-square)](https://packagist.org/packages/emirkefi/laravel-schema-drift)

A powerful, zero-config Artisan command to detect schema drift between your live database and your Laravel migration files. 

Ever wonder if someone manually tweaked a database column directly in production without writing a migration? Or if a legacy table is sitting in your database completely untracked? This package catches those discrepancies instantly.

## How It Works

Behind the scenes, the package uses a clever "shadow database" approach:
1. It takes a snapshot of your live database schema.
2. It spins up a temporary, in-memory SQLite database and runs all your migration files.
3. It compares the two schemas and outputs a terminal table highlighting missing tables, untracked columns, nullability mismatches, type drift, default value drift, and index discrepancies.

## Features

- **Zero-Config Drift Detection**: Compare live databases directly against migration files.
- **Cross-Database Type Normalization Engine**: SQLite shadow databases use loose type affinity. Our built-in `TypeNormalizer` accurately maps dialect-specific column types across **MySQL**, **PostgreSQL**, **SQLite**, and **SQL Server** to canonical types (`integer`, `bigint`, `boolean`, `decimal`, `string`, `datetime`, `json`, `binary`), eliminating false-positive type mismatches (e.g. MySQL `TINYINT(1)` vs SQLite boolean/integer).
- **Smart Default Value Normalization**: Strips dialect-specific default wrappers (such as Postgres casts `'val'::character varying`, SQL Server `((0))`, MySQL bit literals `b'1'`, and boolean string variants) to ensure accurate default comparisons.
- **Fine-Grained Strictness Checks**: Enable or disable checks for indexes, foreign keys, column types, and defaults.
- **Ignore Patterns**: Exclude vendor, framework, or legacy tables with wildcard support (e.g. `pma__*`).

## Requirements

- PHP 8.2 or higher
- Laravel 11.0, 12.0, or 13.0
- SQLite PHP extension enabled (for the shadow database)

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

Run the drift check against your default database connection:

```bash
php artisan schema:drift
```

Check a specific database connection or migration directory:

```bash
php artisan schema:drift --connection=mysql --path=database/migrations
```

## Configuration

In `config/schema-drift.php`, you can customize strictness checks and ignored tables:

```php
return [
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