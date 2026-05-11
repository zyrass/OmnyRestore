<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    | By default, Horizon will be available under the /horizon URI. Change
    | this to restrict Horizon to a specific domain.
    */

    'domain' => env('HORIZON_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    | The URI path where Horizon will be available from.
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    | The Redis connection to use for the Horizon queues and metrics storage.
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    | The prefix added to all Horizon keys stored in Redis.
    | Prevents collisions if multiple apps share the same Redis instance.
    */

    'prefix' => env('HORIZON_PREFIX', 'omnyrestore_horizon:'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    | Horizon will use these middleware for the Horizon dashboard routes.
    | By default, the HorizonServiceProvider restricts access to authenticated
    | admin users via the 'viewHorizon' Gate.
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    | Send alert if any queue's wait time exceeds this number of seconds.
    | Useful for detecting worker saturation on high-volume periods.
    | Note: Alerting requires Horizon + Slack notification configuration.
    */

    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    | How long (in minutes) to keep historical job records in Redis.
    | Recent: completed successfully in the last N minutes
    | Pending: currently in the queue
    | Failed:  retained for inspection before manual retry
    */

    'trim' => [
        'recent'         => 60,      // 1 hour
        'pending'        => 60,
        'recent_failed'  => 10080,   // 7 days — time to investigate and retry
        'failed'         => 10080,
        'monitored'      => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    | When set to false, Horizon gracefully waits for the current job to
    | finish before terminating. Recommended for long-running jobs.
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    | Maximum memory a single worker process can use before Horizon restarts it.
    | Set higher for ZIP generation (large photo batches can use ~400MB).
    */

    'memory_limit' => 512,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Supervisor Configuration
    |--------------------------------------------------------------------------
    | Supervisors manage groups of queue workers.
    | We define separate supervisors per queue to control resource allocation.
    |
    | Queues (by priority):
    |   default        → General purpose (emails, notifications)
    |   zip-generation → CPU/memory intensive (ZIP creation) — fewer workers
    |
    | Balancing strategies:
    |   'simple'   → Fixed worker count (predictable resources)
    |   'auto'     → Auto-scales workers based on queue depth
    |   'false'    → No balancing (use minProcesses exactly)
    */

    'defaults' => [
        'supervisor-1' => [
            'connection'   => 'redis',
            'queue'        => ['default'],
            'balance'      => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 10,
            'maxTime'      => 0,
            'maxJobs'      => 0,
            'memory'       => 256,
            'tries'        => 1,
            'timeout'      => 60,
            'nice'         => 0,
        ],
    ],

    'environments' => [

        'production' => [
            // General purpose workers: emails, notifications, signed URLs
            'supervisor-default' => [
                'connection'   => 'redis',
                'queue'        => ['default'],
                'balance'      => 'auto',
                'maxProcesses' => 5,
                'minProcesses' => 1,
                'memory'       => 256,
                'tries'        => 3,
                'timeout'      => 90,
                'nice'         => 0,
            ],

            // ZIP generation: resource-intensive, fewer concurrent workers
            'supervisor-zip' => [
                'connection'   => 'redis',
                'queue'        => ['zip-generation'],
                'balance'      => 'simple',
                'maxProcesses' => 2,  // Limit to 2 concurrent ZIP jobs (memory protection)
                'minProcesses' => 1,
                'memory'       => 512,
                'tries'        => 3,
                'timeout'      => 600, // 10 minutes max per ZIP job
                'nice'         => 10,  // Lower priority than web requests
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection'   => 'redis',
                'queue'        => ['default', 'zip-generation'],
                'balance'      => 'simple',
                'maxProcesses' => 3,
                'minProcesses' => 1,
                'memory'       => 512,
                'tries'        => 1,
                'timeout'      => 600,
                'nice'         => 0,
            ],
        ],
    ],

];
