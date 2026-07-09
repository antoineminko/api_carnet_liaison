<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Production frontend
        'https://carnet-liaison.vercel.app',
        // Vercel preview deployments (dynamic URLs)
        // Local development
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:3000',
    ],

    // Allow all Vercel preview URLs (e.g. carnet-liaison-git-main-xxx.vercel.app)
    'allowed_origins_patterns' => [
        '#^https://carnet-liaison.*\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400, // Cache preflight for 24h to reduce OPTIONS requests

    'supports_credentials' => false,

];
