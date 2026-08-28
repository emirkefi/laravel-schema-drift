<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shadow Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Schema Drift uses an in-memory SQLite database to simulate
    | migrations. If your migrations use MySQL/PostgreSQL-specific syntax,
    | raw SQL, spatial/GIS indexes, or stored procedures, you can specify
    | a custom testing connection here (e.g. 'mysql_testing' or 'pgsql_testing').
    |
    */
    'shadow_connection' => env('SCHEMA_DRIFT_SHADOW_CONNECTION', null),

    /*
    | When using a custom shadow connection, whether to run migrate:fresh
    | to ensure the shadow database starts with a clean slate before diffing.
    */
    'fresh_shadow' => env('SCHEMA_DRIFT_FRESH_SHADOW', true),

    /*
    |--------------------------------------------------------------------------
    | Ignored Tables
    |--------------------------------------------------------------------------
    |
    | Ignore system, framework, or vendor tables from drift analysis.
    | Wildcards are supported (e.g., 'pma__*').
    |
    */
    'ignore_tables' => [
        // Default Laravel tables
        'migrations',
        'failed_jobs',
        'job_batches',
        'sessions',
        'cache',
        'cache_locks',
        'password_reset_tokens',
        
        // phpMyAdmin
        'pma__*',

        // Legacy Project Tables
        'attachments',
        'attendance',
        'audit_log',
        'avisservice',
        'categorieservice',
        'commande',
        'commande_item',
        'conversation_members',
        'conversations',
        'demandeservice',
        'department',
        'doctrine_migration_versions',
        'employe',
        'employee',
        'entreprise',
        'friend_requests',
        'location_cache',
        'messages',
        'messenger_messages',
        'objectif',
        'panier',
        'payment',
        'phase',
        'produit',
        'produits',
        'projet',
        'service',
        'settings',
        'user_connection',
        'utilisateur',
    ],

    /*
    |--------------------------------------------------------------------------
    | Strictness Checks
    |--------------------------------------------------------------------------
    |
    | Configure which schema properties to compare during drift detection.
    |
    */
    'check_indexes' => true,
    'check_foreign_keys' => true,
    'check_types' => true,
    'check_defaults' => true,
];