<?php

return [
    /*
    | Ignore system or vendor tables from drift analysis
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
    | Strictness checks
    */
    'check_indexes' => true,
    'check_foreign_keys' => true,
    'check_types' => true,
    'check_defaults' => true,
];