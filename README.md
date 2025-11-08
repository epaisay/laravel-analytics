Laravel Analytics Package

A comprehensive analytics package for Laravel applications with detailed tracking, geolocation, and engagement metrics.

Features

📊 Comprehensive Tracking: Track views, likes, shares, clicks, and more

🌍 Geolocation: Automatic IP to location conversion with multiple providers

🤖 Bot Detection: Identify and categorize bot traffic

📱 Device Detection: Track devices, browsers, and platforms

⏱️ Time-based Analytics: Daily, weekly, monthly, and yearly period tracking

🔄 Real-time Aggregation: Automatic data aggregation from individual interactions

🎯 Engagement Scoring: Calculate engagement rates and trend scores

🛡️ Duplicate Prevention: Prevent duplicate tracking with unique constraints

📈 Performance Optimized: Comprehensive indexing for fast queries

Requirements

PHP 8.1 or higher

Laravel 11.0 or higher

MySQL, PostgreSQL, or SQLite

Installation

1. Install via Composer

composer require epaisay/laravel-analytics


2. Run the Install Command

php artisan analytics:install --migrate


3. Manual Installation (Optional)

If you prefer manual installation:

Publish configuration:

php artisan vendor:publish --tag=analytics-config


Publish migrations:

php artisan vendor:publish --tag=analytics-migrations


Run migrations:

php artisan migrate


4. Register middleware in app/Http/Kernel.php

protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \Epaisay\Analytics\Middleware\TrackAnalytics::class,
    ],
];


Usage

1. Add Trait to Your Models

Add the HasAnalytics trait to any model you want to track:

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Epaisay\Analytics\Traits\HasAnalytics;

class Post extends Model
{
    use HasAnalytics;

    // Your model code...

    /**
     * Add custom tracked actions.
     * These controller methods will be automatically tracked.
     */
    protected static function customTrackedActions(): array
    {
        return [
            'showPost',
            // Add more controller method names here
        ];
    }
}


2. Define Tracked Actions

The package automatically tracks these default controller actions:

show

index

view

display

Add your own custom actions by overriding the customTrackedActions method in your model, as shown above.

3. Using the Analytics Facade

use Epaisay\Analytics\Facades\Analytics;

// Track a custom action
Analytics::trackControllerAction($post, 'customAction');

// Track a view manually
Analytics::trackView($post, [
    'ip_address' => request()->ip(),
    'useragent' => request()->userAgent(),
]);

// Increment a specific metric
Analytics::incrementMetric($post, 'likes_count');

// Get analytics summary
$summary = Analytics::getAnalyticsSummary($post);

// Get browser analytics
$browserStats = Analytics::getBrowserAnalytics($post);

// Get geolocation analytics
$locationStats = Analytics::getGeolocationAnalytics($post);


4. Using in Controllers

Your controller methods will be automatically tracked if they're in the getTrackedActions() list:

public function show(Post $post)
{
    // This action is automatically tracked (if 'show' is in the list)
    return view('posts.show', compact('post'));
}

public function showAgency(Post $post)
{
    // This custom action will be tracked if added to customTrackedActions
    return view('posts.agency', compact('post'));
}


5. Accessing Analytics Data

$post = Post::find(1);

// Get total views
$views = $post->getTotalViews();

// Get unique viewers
$uniqueViewers = $post->getUniqueViewersCount();

// Get engagement stats
$engagement = $post->getRealtimeEngagementStats();

// Get formatted view count (1.5K, 2.3M, etc.)
$formattedViews = $post->getViewCount();

// Check if user has interacted
$isLiked = $post->isLikedByUser(auth()->id());
$isBookmarked = $post->isBookmarkedByUser(auth()->id());

// Get analytics timeline
$timeline = $post->getAnalyticsTimeline('7days', 'views_count');


Configuration

After installation, configure the package in config/analytics.php:

return [
    'enabled' => env('ANALYTICS_ENABLED', true),
    
    'geolocation' => [
        'enabled' => env('ANALYTICS_GEOLOCATION_ENABLED', true),
        'cache_ttl' => env('ANALYTICS_GEOLOCATION_CACHE_TTL', 30), // in minutes
    ],
    
    'tracked_actions' => [
        'show', 'index', 'view', 'display',
    ],
    
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


Commands

Aggregate Analytics Data

# Aggregate all models
php artisan analytics:aggregate

# Aggregate specific model
php artisan analytics:aggregate "App\Models\Post"

# Aggregate recent activities only
php artisan analytics:aggregate --recent

# Clean up old data
php artisan analytics:aggregate --cleanup

# Clean up orphaned records
php artisan analytics:aggregate --orphaned


Install Package

# Full installation with migrations
php artisan analytics:install --migrate

# Force overwrite existing files
php artisan analytics:install --force

# With demo data seeding
php artisan analytics:install --migrate --seed


Database Schema

The package creates three main tables:

analytics

Tracks engagement metrics per user/visitor per model

Polymorphic relationship to any model

Comprehensive metrics for views, likes, shares, etc.

views

Detailed view tracking with geolocation

Device and browser information

Bot detection and categorization

periods

Time-based aggregation (daily, weekly, monthly, yearly)

Growth rate calculations

Historical trend analysis

Advanced Usage

Custom Metric Tracking

// Track custom metrics directly on the model
$post->trackLike();
$post->trackShare();
$post->trackBookmark();
$post->trackFollow();

// Or use the facade
Analytics::incrementMetric($post, 'custom_metric_count');


Bot Detection Customization

The package automatically detects and categorizes bots. You can customize bot categories in the config/analytics.php configuration file.

Geolocation Service

use Epaisay\Analytics\Services\GeolocationService;

$service = app(GeolocationService::class);
$location = $service->getLocationData('8.8.8.8');

// Test the service
$status = $service->getServiceStatus();


System-wide Analytics

Track system-wide metrics for specific routes:

use Epaisay\Analytics\Facades\Analytics;

Analytics::addSystemAnalyticsRoute('admin.dashboard', ['admin', 'super-admin']);


Testing

# Run tests
composer test

# Run with coverage
composer test-coverage


Security

IP addresses are hashed for privacy.

Bot traffic can be filtered.

Private IP ranges get development location data.

Comprehensive audit trails.

Contributing

Please see CONTRIBUTING.md for details.

License

The MIT License (MIT). Please see LICENSE.md for more information.

Support

For support and questions, please open an issue on GitHub.

Changelog

Please see CHANGELOG.md for details on what has changed.