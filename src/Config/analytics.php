<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the analytics package.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Enable Analytics Tracking
    |--------------------------------------------------------------------------
    |
    | This option controls whether analytics tracking is enabled.
    |
    */
    'enabled' => env('ANALYTICS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Track Bots
    |--------------------------------------------------------------------------
    |
    | This option controls whether to track bot traffic.
    |
    */
    'track_bots' => env('ANALYTICS_TRACK_BOTS', false),

    /*
    |--------------------------------------------------------------------------
    | Geolocation Service
    |--------------------------------------------------------------------------
    |
    | This option controls the geolocation service provider.
    |
    */
    'geolocation' => [
        'enabled' => env('ANALYTICS_GEOLOCATION_ENABLED', true),
        'cache_ttl' => env('ANALYTICS_GEOLOCATION_CACHE_TTL', 30), // days
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | This option controls how long to keep analytics data.
    |
    */
    'retention' => [
        'views' => env('ANALYTICS_RETENTION_VIEWS', 365), // days
        'analytics' => env('ANALYTICS_RETENTION_ANALYTICS', 730), // days
        'periods' => env('ANALYTICS_RETENTION_PERIODS', 1095), // days
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregation Settings
    |--------------------------------------------------------------------------
    |
    | This option controls analytics data aggregation.
    |
    */
    'aggregation' => [
        'enabled' => env('ANALYTICS_AGGREGATION_ENABLED', true),
        'batch_size' => env('ANALYTICS_AGGREGATION_BATCH_SIZE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Tracked Actions
    |--------------------------------------------------------------------------
    |
    | These actions will be automatically tracked by the middleware.
    |
    */
    'tracked_actions' => [
        'show',
        'index',
        'view',
        'display',
    ],

    /*
    |--------------------------------------------------------------------------
    | System Analytics Routes
    |--------------------------------------------------------------------------
    |
    | These routes will trigger system-wide analytics updates.
    |
    */
    'system_routes' => [
        'home' => ['seller', 'office'],
        'analytics.index' => ['seller', 'office', 'admin'],
        'dashboard' => ['seller', 'office', 'admin'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bot Detection
    |--------------------------------------------------------------------------
    |
    | Configuration for bot detection and categorization.
    |
    */
    'bot_detection' => [
        'enabled' => true,
        'categories' => [
            'search_engine' => ['Googlebot', 'Bingbot', 'Yahoo! Slurp', 'DuckDuckBot', 'Baiduspider'],
            'social_media' => ['Facebook External Hit', 'Twitterbot', 'LinkedInBot', 'Pinterest'],
            'ai_assistant' => ['OpenAI GPTBot', 'OpenAI ChatGPT', 'Anthropic ClaudeBot'],
            'seo_tool' => ['SemrushBot', 'AhrefsBot', 'MJ12bot'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Engagement Scoring
    |--------------------------------------------------------------------------
    |
    | Weights for calculating engagement scores.
    |
    */
    'engagement_weights' => [
        'views' => 0.15,
        'likes' => 0.25,
        'shares' => 0.20,
        'clicks' => 0.15,
        'replies' => 0.10,
        'follows' => 0.10,
        'bookmarks' => 0.05,
    ],
];