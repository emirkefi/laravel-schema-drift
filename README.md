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
3. It compares the two schemas and outputs a beautiful terminal table highlighting any missing tables, untracked columns, nullability mismatches, and index discrepancies.

## Requirements

- PHP 8.2 or higher
- Laravel 11.0, 12.0, or 13.0
- SQLite PHP extension enabled (for the shadow database)

## Installation

You can install the package via composer. Since this is a developer tool, it is highly recommended to install it as a `dev` dependency:

```bash
composer require emirkefi/laravel-schema-drift --dev