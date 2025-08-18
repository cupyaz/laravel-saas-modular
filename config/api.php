<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the API framework.
    |
    */

    'version' => env('API_VERSION', '1.0'),
    
    'supported_versions' => ['1.0', '1.1', '2.0'],
    
    'deprecated_versions' => [],
    
    'rate_limits' => [
        'free' => [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
            'requests_per_day' => 10000,
        ],
        'basic' => [
            'requests_per_minute' => 200,
            'requests_per_hour' => 5000,
            'requests_per_day' => 50000,
        ],
        'pro' => [
            'requests_per_minute' => 500,
            'requests_per_hour' => 15000,
            'requests_per_day' => 150000,
        ],
        'enterprise' => [
            'requests_per_minute' => 1000,
            'requests_per_hour' => 50000,
            'requests_per_day' => 500000,
        ],
    ],
    
    'documentation' => [
        'title' => 'Laravel SaaS Platform API',
        'description' => 'Comprehensive REST API for multi-tenant SaaS platform',
        'contact' => [
            'name' => 'API Support',
            'email' => 'api-support@example.com',
            'url' => 'https://example.com/support',
        ],
        'license' => [
            'name' => 'MIT',
            'url' => 'https://opensource.org/licenses/MIT',
        ],
    ],
];