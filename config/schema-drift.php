<?php

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
    ],

    /*
    | Strictness checks
    */
    'check_indexes' => true,
    'check_foreign_keys' => true,
    'check_types' => true,
];