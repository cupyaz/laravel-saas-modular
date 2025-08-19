<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile Performance Optimization Settings
    |--------------------------------------------------------------------------
    |
    | These settings control the behavior of mobile performance optimizations
    | throughout the application. Adjust based on your application's needs.
    |
    */

    'optimization' => [
        'enabled' => env('MOBILE_OPTIMIZATION_ENABLED', true),
        
        // Global optimization level
        'level' => env('MOBILE_OPTIMIZATION_LEVEL', 'auto'), // auto, light, moderate, aggressive
        
        // Response size threshold for optimization (in bytes)
        'size_threshold' => env('MOBILE_OPTIMIZATION_SIZE_THRESHOLD', 1024),
        
        // Optimization timeout (in milliseconds)
        'timeout' => env('MOBILE_OPTIMIZATION_TIMEOUT', 5000),
    ],

    'caching' => [
        'enabled' => env('MOBILE_CACHING_ENABLED', true),
        
        // Default cache TTL values (in seconds)
        'ttl' => [
            'slow-2g' => env('MOBILE_CACHE_TTL_SLOW_2G', 86400), // 24 hours
            '2g' => env('MOBILE_CACHE_TTL_2G', 43200),           // 12 hours
            '3g' => env('MOBILE_CACHE_TTL_3G', 7200),            // 2 hours
            '4g' => env('MOBILE_CACHE_TTL_4G', 3600),            // 1 hour
            '5g' => env('MOBILE_CACHE_TTL_5G', 1800),            // 30 minutes
            'default' => env('MOBILE_CACHE_TTL_DEFAULT', 3600),   // 1 hour
        ],

        // Cache prefix for mobile-specific data
        'prefix' => env('MOBILE_CACHE_PREFIX', 'mobile:'),
        
        // Maximum cache size per user (in MB)
        'max_size_per_user' => env('MOBILE_CACHE_MAX_SIZE_PER_USER', 50),
    ],

    'preload' => [
        'enabled' => env('MOBILE_PRELOAD_ENABLED', true),
        
        // Critical resources to preload for mobile users
        'critical_resources' => [
            'user_profile',
            'navigation_menu',
            'critical_notifications',
            'tenant_settings',
        ],
        
        // Maximum items to preload per resource
        'max_items_per_resource' => env('MOBILE_PRELOAD_MAX_ITEMS', 10),
        
        // Preload timeout (in seconds)
        'timeout' => env('MOBILE_PRELOAD_TIMEOUT', 30),
    ],

    'lazy_load' => [
        'enabled' => env('MOBILE_LAZY_LOAD_ENABLED', true),
        
        // Default pagination limits for mobile
        'pagination_limits' => [
            'slow-2g' => 5,
            '2g' => 5,
            '3g' => 10,
            '4g' => 15,
            '5g' => 20,
            'default' => 10,
        ],
        
        // Lazy load threshold (number of items before lazy loading kicks in)
        'threshold' => env('MOBILE_LAZY_LOAD_THRESHOLD', 20),
    ],

    'images' => [
        'optimization_enabled' => env('MOBILE_IMAGE_OPTIMIZATION_ENABLED', true),
        
        // Image quality settings based on connection
        'quality' => [
            'slow-2g' => 30,
            '2g' => 40,
            '3g' => 60,
            '4g' => 80,
            '5g' => 90,
            'default' => 70,
        ],
        
        // Image width limits based on connection
        'width_limits' => [
            'slow-2g' => 200,
            '2g' => 300,
            '3g' => 500,
            '4g' => 800,
            '5g' => 1200,
            'default' => 600,
        ],
        
        // Preferred formats (in order of preference)
        'preferred_formats' => ['webp', 'jpeg', 'png'],
        
        // CDN settings for image optimization
        'cdn' => [
            'enabled' => env('MOBILE_IMAGE_CDN_ENABLED', false),
            'base_url' => env('MOBILE_IMAGE_CDN_BASE_URL'),
            'optimization_params' => [
                'auto_format' => true,
                'auto_compress' => true,
                'progressive' => true,
            ],
        ],
    ],

    'offline' => [
        'enabled' => env('MOBILE_OFFLINE_ENABLED', true),
        
        // Pages available offline
        'offline_pages' => [
            '/dashboard',
            '/profile',
            '/notifications',
        ],
        
        // Cache strategy for offline mode
        'cache_strategy' => env('MOBILE_OFFLINE_CACHE_STRATEGY', 'cache_first'),
        
        // Sync interval for background sync (in minutes)
        'sync_interval' => env('MOBILE_OFFLINE_SYNC_INTERVAL', 15),
        
        // Maximum offline cache size (in MB)
        'max_cache_size' => env('MOBILE_OFFLINE_MAX_CACHE_SIZE', 100),
    ],

    'performance' => [
        'monitoring_enabled' => env('MOBILE_PERFORMANCE_MONITORING_ENABLED', true),
        
        // Performance thresholds (in milliseconds)
        'thresholds' => [
            'response_time' => [
                'good' => 500,
                'needs_improvement' => 1000,
                'poor' => 2000,
            ],
            'first_contentful_paint' => [
                'good' => 1800,
                'needs_improvement' => 3000,
                'poor' => 5000,
            ],
            'largest_contentful_paint' => [
                'good' => 2500,
                'needs_improvement' => 4000,
                'poor' => 6000,
            ],
        ],
        
        // Metrics collection settings
        'metrics' => [
            'sample_rate' => env('MOBILE_METRICS_SAMPLE_RATE', 0.1), // 10% sampling
            'batch_size' => env('MOBILE_METRICS_BATCH_SIZE', 50),
            'flush_interval' => env('MOBILE_METRICS_FLUSH_INTERVAL', 60), // seconds
        ],
        
        // Performance budget (in KB)
        'budget' => [
            'total_size' => env('MOBILE_PERFORMANCE_BUDGET_TOTAL', 1024), // 1MB
            'js_size' => env('MOBILE_PERFORMANCE_BUDGET_JS', 300),         // 300KB
            'css_size' => env('MOBILE_PERFORMANCE_BUDGET_CSS', 100),       // 100KB
            'image_size' => env('MOBILE_PERFORMANCE_BUDGET_IMAGES', 500),  // 500KB
        ],
    ],

    'network' => [
        'retry' => [
            'enabled' => env('MOBILE_NETWORK_RETRY_ENABLED', true),
            'max_attempts' => env('MOBILE_NETWORK_RETRY_MAX_ATTEMPTS', 3),
            'base_delay' => env('MOBILE_NETWORK_RETRY_BASE_DELAY', 1000), // milliseconds
            'max_delay' => env('MOBILE_NETWORK_RETRY_MAX_DELAY', 10000),   // milliseconds
        ],
        
        'timeout' => [
            'slow-2g' => 30000, // 30 seconds
            '2g' => 20000,      // 20 seconds
            '3g' => 15000,      // 15 seconds
            '4g' => 10000,      // 10 seconds
            '5g' => 5000,       // 5 seconds
            'default' => 15000, // 15 seconds
        ],
    ],

    'compression' => [
        'enabled' => env('MOBILE_COMPRESSION_ENABLED', true),
        
        // Compression algorithms (in order of preference)
        'algorithms' => ['br', 'gzip', 'deflate'],
        
        // Minimum size for compression (in bytes)
        'min_size' => env('MOBILE_COMPRESSION_MIN_SIZE', 1024),
        
        // Compression level (1-9, higher = better compression but slower)
        'level' => env('MOBILE_COMPRESSION_LEVEL', 6),
    ],

    'logging' => [
        'enabled' => env('MOBILE_LOGGING_ENABLED', true),
        
        // Log channel for mobile performance
        'channel' => env('MOBILE_LOG_CHANNEL', 'mobile_performance'),
        
        // Log level
        'level' => env('MOBILE_LOG_LEVEL', 'info'),
        
        // Log performance metrics
        'log_metrics' => env('MOBILE_LOG_METRICS', true),
        
        // Log optimization details
        'log_optimizations' => env('MOBILE_LOG_OPTIMIZATIONS', false),
    ],

    'testing' => [
        // Enable test endpoints
        'test_endpoints_enabled' => env('MOBILE_TEST_ENDPOINTS_ENABLED', env('APP_DEBUG', false)),
        
        // Test data size limits (in KB)
        'test_data_size_limit' => env('MOBILE_TEST_DATA_SIZE_LIMIT', 1024),
        
        // Test timeout (in seconds)
        'test_timeout' => env('MOBILE_TEST_TIMEOUT', 30),
    ],

    'security' => [
        // Rate limiting for mobile endpoints (per minute)
        'rate_limit' => [
            'preload' => env('MOBILE_RATE_LIMIT_PRELOAD', 60),
            'lazy_load' => env('MOBILE_RATE_LIMIT_LAZY_LOAD', 120),
            'metrics' => env('MOBILE_RATE_LIMIT_METRICS', 300),
        ],
        
        // IP whitelist for performance testing
        'test_ip_whitelist' => explode(',', env('MOBILE_TEST_IP_WHITELIST', '')),
        
        // User agent validation
        'validate_user_agent' => env('MOBILE_VALIDATE_USER_AGENT', true),
    ],
];