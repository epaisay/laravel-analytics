<?php

namespace Epaisay\Analytics\Helpers;

use Epaisay\Analytics\Models\Analytic;
use Epaisay\Analytics\Models\View;
use Epaisay\Analytics\Models\Period;
use Epaisay\Analytics\Services\GeolocationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use hisorange\BrowserDetect\Parser as Browser;

class AnalyticsHelper
{
    private static $processedRequests = [];
    private static $geolocationService;
    private static $browserParser;

    // Define routes and user types for system-wide analytics
    private static $systemAnalyticsRoutes = [
        'home' => ['seller', 'office'],
        'analytics.index' => ['seller', 'office', 'admin'],
        'dashboard' => ['seller', 'office', 'admin'],
    ];

    /**
     * Get or generate a consistent UUID for a virtual page.
     */
    public static function getVirtualPageUuid(string $slug): string
    {
        $path = 'virtual_pages.json';

        // Load the map if it exists, otherwise start with empty array
        $map = Storage::exists($path)
            ? json_decode(Storage::get($path), true)
            : [];

        // Return existing UUID if found
        if (isset($map[$slug])) {
            return $map[$slug];
        }

        // Otherwise generate and save a new one
        $uuid = Uuid::uuid4()->toString();
        $map[$slug] = $uuid;

        Storage::put($path, json_encode($map, JSON_PRETTY_PRINT));

        return $uuid;
    }

    /**
     * Get geolocation service instance
     */
    private static function getGeolocationService(): GeolocationService
    {
        if (!self::$geolocationService) {
            self::$geolocationService = new GeolocationService();
        }
        return self::$geolocationService;
    }

    /**
     * Get browser parser instance
     */
    private static function getBrowserParser(): Browser
    {
        if (!self::$browserParser) {
            self::$browserParser = new Browser();
        }
        return self::$browserParser;
    }

    /**
     * Get or create analytics record for a model - SEPARATE RECORDS PER USER/VISITOR
     */
    private static function getOrCreateAnalyticsRecord(Model $model, string $action, string $requestPath, string $ipAddress): Analytic
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();
        $userId = Auth::check() ? Auth::id() : null;
        $sessionId = session()->getId();

        // Create separate analytics record for each user/visitor combination
        $analytic = Analytic::where([
            'analyticable_type' => $modelClass,
            'analyticable_id' => $modelId,
            'user_id' => $userId,
            'visitor_token' => $userId ? null : $sessionId, // For guests, use session ID as visitor token
        ])->first();

        if (!$analytic) {
            $analytic = Analytic::create([
                'id' => Uuid::uuid4()->toString(),
                'analyticable_type' => $modelClass,
                'analyticable_id' => $modelId,
                'user_id' => $userId,
                'visitor_token' => $userId ? null : $sessionId,
                'session_id' => $sessionId,
                'ip_address' => $ipAddress,
                'action_type' => $action,
                'request_path' => $requestPath,
                'created_by' => $userId,
                'last_activity_at' => now(),
                // Initialize all counts to 0
                'views_count' => 0,
                'unique_viewers' => 1, // Always 1 since this is per user/visitor
                'user_views' => $userId ? 0 : 0, // Will be set properly in update method
                'public_views' => $userId ? 0 : 0, // Will be set properly in update method
                'bot_views' => 0,
                'human_views' => 0,
                'impressions_count' => 0,
                'likes_count' => 0,
                'shares_count' => 0,
                'votes_count' => 0,
                'follows_count' => 0,
                'replies_count' => 0,
                'complaints_count' => 0,
                'bookmarks_count' => 0,
                'clicks_count' => 0,
                'comments_count' => 0,
                'messages_count' => 0,
                'chats_count' => 0,
                'contacts_count' => 0,
                'wishlists_count' => 0,
                'listings_count' => 0,
                'subscriptions_count' => 0,
                'users_count' => 0,
                'sellers_count' => 0,
                'cartitems_count' => 0,
                'checkouts_count' => 0,
                'payments_count' => 0,
                'orders_count' => 0,
                'brands_count' => 0,
                'shops_count' => 0,
                'articles_count' => 0,
                'posts_count' => 0,
                'video_count' => 0,
                'click_through_rate' => 0,
                'trend_score' => 0,
                'reaction_counts' => 0,
                'contributors_count' => 0,
            ]);
        } else {
            // Update IP address, action type, and request path for existing record
            $analytic->update([
                'ip_address' => $ipAddress,
                'action_type' => $action,
                'request_path' => $requestPath,
                'session_id' => $sessionId,
                'last_activity_at' => now(),
            ]);
        }

        return $analytic;
    }

    /**
     * Track controller action with specific model instance
     */
    public static function trackControllerAction(Model $model, string $action): ?Analytic
    {
        try {
            $modelClass = get_class($model);
            $modelId = $model->getKey();
            
            $signature = self::getRequestSignature($modelClass, $modelId, $action);
            if (isset(self::$processedRequests[$signature])) {
                return null;
            }
            self::$processedRequests[$signature] = true;

            $userId = Auth::check() ? Auth::id() : null;
            $sessionId = session()->getId();
            $ip = Request::ip();
            $requestPath = Request::path();

            // Get geolocation data
            $geolocationData = self::getGeolocationData($ip);
            
            // Get browser and device details
            $browserDetails = self::parseUserAgentWithBrowserDetect();

            // Get or create analytics record with IP, action, and request path
            $analytic = self::getOrCreateAnalyticsRecord($model, $action, $requestPath, $ip);

            // Track the view - this will handle duplicates internally
            $view = self::trackView($model, array_merge([
                'user_id' => $userId,
                'visitor_token' => $sessionId,
                'ip_address' => $ip,
                'session_id' => $sessionId,
                'visited_at' => now(),
                'method' => Request::method(),
                'request' => json_encode(Request::all()),
                'url' => Request::fullUrl(),
                'referer' => Request::header('referer'),
                'page_url' => $requestPath,
                'languages' => implode(',', Request::getLanguages() ?? []),
                'useragent' => Request::userAgent(),
                'headers' => json_encode(Request::header()),
                'device' => $browserDetails['device'],
                'device_type' => $browserDetails['device_type'],
                'platform' => $browserDetails['platform'],
                'os' => $browserDetails['os'],
                'browser' => $browserDetails['browser'],
                'browser_version' => $browserDetails['browser_version'],
                'is_robot' => $browserDetails['is_robot'],
                'robot_name' => $browserDetails['robot_name'],
                'robot_category' => $browserDetails['robot_category'],
                'action_type' => $action,
            ], $geolocationData));

            // Update analytics counts - SEPARATE RECORDS PER USER/VISITOR
            $analytic = self::updateAnalyticsCounts($model, $view, $action, $requestPath, $ip);

            // Update periods table
            self::updatePeriods($analytic, 'views_count');

            // Conditionally update system-wide analytics for specific routes and user types
            self::conditionallyUpdateSystemAnalytics($model);

            return $analytic;

        } catch (\Exception $e) {
            Log::error("Analytics creation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update analytics counts - SEPARATE RECORDS PER USER/VISITOR
     */
    private static function updateAnalyticsCounts(Model $model, ?View $view, string $action, string $requestPath, string $ipAddress): Analytic
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();
        $userId = Auth::check() ? Auth::id() : null;
        $sessionId = session()->getId();

        // Get base analytics record for this specific user/visitor
        $analytic = self::getOrCreateAnalyticsRecord($model, $action, $requestPath, $ipAddress);

        // Use database transaction to ensure data consistency
        DB::transaction(function () use ($analytic, $userId, $view, $action, $requestPath, $ipAddress) {
            
            // Always increment views_count for this user/visitor
            $analytic->increment('views_count');

            // unique_viewers is always 1 since this record is per user/visitor
            $analytic->update(['unique_viewers' => 1]);

            // Update user vs public views
            if ($userId) {
                $analytic->increment('user_views');
            } else {
                $analytic->increment('public_views');
            }

            // Update bot vs human views
            if ($view && $view->is_robot) {
                $analytic->increment('bot_views');
            } else {
                $analytic->increment('human_views');
            }

            // Update IP address, action type, and request path
            $analytic->update([
                'ip_address' => $ipAddress,
                'action_type' => $action,
                'request_path' => $requestPath,
                'last_activity_at' => now(),
            ]);
        });

        // Refresh the analytic model to get updated values
        $analytic->refresh();

        // Update derived metrics
        self::updateDerivedMetrics($analytic);

        return $analytic;
    }

    /**
     * Get total analytics for a model across all users/visitors
     */
    public static function getTotalAnalyticsForModel(Model $model): array
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        return Analytic::where('analyticable_type', $modelClass)
            ->where('analyticable_id', $modelId)
            ->selectRaw('
                SUM(views_count) as total_views,
                COUNT(*) as total_unique_viewers, -- Each record represents one unique viewer
                SUM(user_views) as total_user_views,
                SUM(public_views) as total_public_views,
                SUM(bot_views) as total_bot_views,
                SUM(human_views) as total_human_views,
                SUM(likes_count) as total_likes,
                SUM(shares_count) as total_shares,
                SUM(comments_count) as total_comments
            ')
            ->first()
            ->toArray();
    }

    /**
     * Update periods table for time-based analytics
     */
    private static function updatePeriods(Analytic $analytic, string $analyticType): void
    {
        try {
            $now = now();
            $userId = Auth::check() ? Auth::id() : null;

            // Get current value from analytics
            $currentValue = $analytic->{$analyticType} ?? 0;

            // Update for different granularities
            $granularities = ['daily', 'weekly', 'monthly', 'yearly'];
            
            foreach ($granularities as $granularity) {
                $periodDates = self::getPeriodDates($now, $granularity);
                
                // Find or create period record
                $period = Period::firstOrCreate(
                    [
                        'analytic_id' => $analytic->id,
                        'analytic_type' => $analyticType,
                        'period_granularity' => $granularity,
                        'period_start_date' => $periodDates['start'],
                    ],
                    [
                        'id' => Uuid::uuid4()->toString(),
                        'period_end_date' => $periodDates['end'],
                        'value' => 0,
                        'previous_value' => 0,
                        'created_by' => $userId,
                    ]
                );

                // Update the period value
                $period->update([
                    'value' => $currentValue,
                    'updated_at' => $now,
                    'updated_by' => $userId,
                ]);

                // Calculate growth rate if we have previous value
                self::calculateGrowthRate($period);
            }

        } catch (\Exception $e) {
            Log::error("Error updating periods table: " . $e->getMessage());
        }
    }

    /**
     * Get period dates based on granularity
     */
    private static function getPeriodDates(Carbon $date, string $granularity): array
    {
        return match($granularity) {
            'daily' => [
                'start' => $date->copy()->startOfDay()->toDateString(),
                'end' => $date->copy()->endOfDay()->toDateString(),
            ],
            'weekly' => [
                'start' => $date->copy()->startOfWeek()->toDateString(),
                'end' => $date->copy()->endOfWeek()->toDateString(),
            ],
            'monthly' => [
                'start' => $date->copy()->startOfMonth()->toDateString(),
                'end' => $date->copy()->endOfMonth()->toDateString(),
            ],
            'yearly' => [
                'start' => $date->copy()->startOfYear()->toDateString(),
                'end' => $date->copy()->endOfYear()->toDateString(),
            ],
            default => [
                'start' => $date->copy()->startOfDay()->toDateString(),
                'end' => $date->copy()->endOfDay()->toDateString(),
            ],
        };
    }

    /**
     * Calculate growth rate for period
     */
    private static function calculateGrowthRate(Period $period): void
    {
        try {
            // Get previous period value
            $previousPeriod = Period::where('analytic_id', $period->analytic_id)
                ->where('analytic_type', $period->analytic_type)
                ->where('period_granularity', $period->period_granularity)
                ->where('period_start_date', '<', $period->period_start_date)
                ->orderBy('period_start_date', 'desc')
                ->first();

            if ($previousPeriod && $previousPeriod->value > 0) {
                $growthRate = (($period->value - $previousPeriod->value) / $previousPeriod->value) * 100;
                
                $period->update([
                    'growth_rate' => round($growthRate, 2),
                    'previous_value' => $previousPeriod->value,
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Error calculating growth rate: " . $e->getMessage());
        }
    }

    /**
     * Track a view for a model - PREVENT DUPLICATES with session/user based checking
     */
    public static function trackView(Model $model, array $viewData = []): ?View
    {
        try {
            $modelClass = get_class($model);
            $modelId = $model->getKey();
            $userId = $viewData['user_id'] ?? null;
            $visitorToken = $viewData['visitor_token'] ?? null;
            $sessionId = $viewData['session_id'] ?? null;
            $ipAddress = $viewData['ip_address'] ?? null;
            $actionType = $viewData['action_type'] ?? 'view';
            $requestPath = $viewData['page_url'] ?? null;

            // Get analytics record first
            $analytic = self::getOrCreateAnalyticsRecord($model, $actionType, $requestPath, $ipAddress);

            // STRICT DUPLICATE PREVENTION - Check for existing view using unique constraints
            $existingViewQuery = View::where('viewable_type', $modelClass)
                ->where('viewable_id', $modelId)
                ->where('action_type', $actionType)
                ->where('request_path', $requestPath);

            if ($userId) {
                // For logged-in users: check by user_id
                $existingView = $existingViewQuery->where('user_id', $userId)->first();
            } else {
                // For guests: check by visitor_token
                $existingView = $existingViewQuery->where('visitor_token', $visitorToken)->first();
            }

            if ($existingView) {
                // UPDATE EXISTING VIEW - don't create new record
                $updateData = [
                    'session_id' => $sessionId ?? $existingView->session_id,
                    'ip_address' => $ipAddress ?? $existingView->ip_address,
                    'visited_at' => now(),
                    'method' => $viewData['method'] ?? $existingView->method,
                    'url' => $viewData['url'] ?? $existingView->url,
                    'referer' => $viewData['referer'] ?? $existingView->referer,
                    'page_url' => $viewData['page_url'] ?? $existingView->page_url,
                    'useragent' => $viewData['useragent'] ?? $existingView->useragent,
                    'device' => $viewData['device'] ?? $existingView->device,
                    'device_type' => $viewData['device_type'] ?? $existingView->device_type,
                    'platform' => $viewData['platform'] ?? $existingView->platform,
                    'os' => $viewData['os'] ?? $existingView->os,
                    'browser' => $viewData['browser'] ?? $existingView->browser,
                    'browser_version' => $viewData['browser_version'] ?? $existingView->browser_version,
                    'is_robot' => $viewData['is_robot'] ?? $existingView->is_robot,
                    'robot_name' => $viewData['robot_name'] ?? $existingView->robot_name,
                    'robot_category' => $viewData['robot_category'] ?? $existingView->robot_category,
                    'action_type' => $actionType,
                    'request_path' => $requestPath,
                    'updated_at' => now(),
                    'updated_by' => $userId,
                ];

                // Update user_id if it's being set and different
                if ($userId && $existingView->user_id !== $userId) {
                    $updateData['user_id'] = $userId;
                }

                // Update visitor_token to ensure consistency
                if ($visitorToken && $existingView->visitor_token !== $visitorToken) {
                    $updateData['visitor_token'] = $visitorToken;
                }

                // Update geolocation data if not already set
                if (!self::viewHasGeolocation($existingView) && isset($viewData['country'])) {
                    $updateData = array_merge($updateData, [
                        'country' => $viewData['country'] ?? null,
                        'country_code' => $viewData['country_code'] ?? null,
                        'region' => $viewData['region'] ?? null,
                        'region_name' => $viewData['region_name'] ?? null,
                        'city' => $viewData['city'] ?? null,
                        'zip' => $viewData['zip'] ?? null,
                        'lat' => $viewData['lat'] ?? null,
                        'lon' => $viewData['lon'] ?? null,
                        'timezone' => $viewData['timezone'] ?? null,
                        'isp' => $viewData['isp'] ?? null,
                        'org' => $viewData['org'] ?? null,
                        'as_name' => $viewData['as_name'] ?? null,
                    ]);
                }

                $existingView->update($updateData);
                return $existingView;
            }

            // CREATE NEW VIEW only if no existing view found
            $viewAttributes = [
                'id' => Uuid::uuid4()->toString(),
                'viewable_type' => $modelClass,
                'viewable_id' => $modelId,
                'analytic_id' => $analytic->id,
                'user_id' => $userId,
                'visitor_token' => $visitorToken,
                'ip_address' => $ipAddress,
                'session_id' => $sessionId,
                'visited_at' => $viewData['visited_at'] ?? now(),
                'action_type' => $actionType,
                'request_path' => $requestPath,
                'method' => $viewData['method'] ?? null,
                'request' => $viewData['request'] ?? null,
                'url' => $viewData['url'] ?? null,
                'referer' => $viewData['referer'] ?? null,
                'page_url' => $viewData['page_url'] ?? null,
                'languages' => $viewData['languages'] ?? null,
                'useragent' => $viewData['useragent'] ?? null,
                'headers' => $viewData['headers'] ?? null,
                'device' => $viewData['device'] ?? null,
                'device_type' => $viewData['device_type'] ?? null,
                'platform' => $viewData['platform'] ?? null,
                'os' => $viewData['os'] ?? null,
                'browser' => $viewData['browser'] ?? null,
                'browser_version' => $viewData['browser_version'] ?? null,
                'is_robot' => $viewData['is_robot'] ?? false,
                'robot_name' => $viewData['robot_name'] ?? null,
                'robot_category' => $viewData['robot_category'] ?? null,
                'view_status' => true,
                'created_by' => $userId,
                'country' => $viewData['country'] ?? null,
                'country_code' => $viewData['country_code'] ?? null,
                'region' => $viewData['region'] ?? null,
                'region_name' => $viewData['region_name'] ?? null,
                'city' => $viewData['city'] ?? null,
                'zip' => $viewData['zip'] ?? null,
                'lat' => $viewData['lat'] ?? null,
                'lon' => $viewData['lon'] ?? null,
                'timezone' => $viewData['timezone'] ?? null,
                'isp' => $viewData['isp'] ?? null,
                'org' => $viewData['org'] ?? null,
                'as_name' => $viewData['as_name'] ?? null,
            ];

            $view = View::create($viewAttributes);
            return $view;

        } catch (\Exception $e) {
            Log::error("View tracking failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Conditionally update system-wide analytics based on route and user role
     */
    private static function conditionallyUpdateSystemAnalytics(Model $model): void
    {
        try {
            $currentRoute = Request::route() ? Request::route()->getName() : null;
            
            if (!$currentRoute || !isset(self::$systemAnalyticsRoutes[$currentRoute])) {
                return;
            }

            $user = Auth::user();
            if (!$user) {
                return;
            }

            $allowedRoles = self::$systemAnalyticsRoutes[$currentRoute];
            $shouldUpdate = false;

            foreach ($allowedRoles as $role) {
                if ($user->hasRole($role)) {
                    $shouldUpdate = true;
                    break;
                }
            }

            if ($shouldUpdate) {
                self::updateSystemWideAnalytics($model);
            }

        } catch (\Exception $e) {
            Log::error("Conditional system analytics update failed: " . $e->getMessage());
        }
    }

    /**
     * Update system-wide analytics counts
     */
    private static function updateSystemWideAnalytics(Model $model): void
    {
        try {
            $analytic = self::getOrCreateAnalyticsRecord($model, 'system', Request::path(), Request::ip());
            
            // These would be customized based on your application's models
            $updateData = [
                'users_count' => 0,
                'sellers_count' => 0,
                'posts_count' => 0,
                'articles_count' => 0,
            ];

            // Example: Update users count if User model exists
            if (class_exists('App\Models\User')) {
                $updateData['users_count'] = \App\Models\User::where('user_status', 1)->count();
            }

            // Example: Update sellers count if you have a seller role
            if (class_exists('App\Models\User') && method_exists('App\Models\User', 'scopeRole')) {
                $updateData['sellers_count'] = \App\Models\User::role('seller')->count();
            }

            // Example: Update posts count if Post model exists
            if (class_exists('App\Models\Post')) {
                $updateData['posts_count'] = \App\Models\Post::count();
            }

            // Example: Update articles count if Article model exists
            if (class_exists('App\Models\Article')) {
                $updateData['articles_count'] = \App\Models\Article::count();
            }

            $analytic->update($updateData);

        } catch (\Exception $e) {
            Log::error("Error updating system-wide analytics: " . $e->getMessage());
        }
    }

    /**
     * Update derived metrics for analytics
     */
    public static function updateDerivedMetrics(Analytic $analytic): void
    {
        try {
            $totalInteractions = $analytic->likes_count + $analytic->shares_count + $analytic->comments_count;
            $engagementRate = $analytic->views_count > 0 ? ($totalInteractions / $analytic->views_count) * 100 : 0;
            
            $clickThroughRate = $analytic->impressions_count > 0 ? 
                ($analytic->clicks_count / $analytic->impressions_count) * 100 : 0;

            $analytic->update([
                'click_through_rate' => round($clickThroughRate, 2),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating derived metrics: " . $e->getMessage());
        }
    }

    /**
     * Parse user agent for browser and device details
     */
    private static function parseUserAgentWithBrowserDetect(): array
    {
        $browser = self::getBrowserParser();
        $userAgent = Request::userAgent();
        
        if ($userAgent) {
            $browser->parse($userAgent);
        }

        $device = 'Desktop';
        if ($browser->isTablet()) {
            $device = 'Tablet';
        } elseif ($browser->isMobile()) {
            $device = 'Mobile';
        }

        $botName = self::detectBotName($userAgent);
        $isRobot = !empty($botName);
        $robotCategory = $isRobot ? self::categorizeBot($botName) : null;
        
        if ($isRobot) {
            $device = 'Bot';
        }

        $platform = $browser->platformName();
        $os = $browser->platformVersion() ? $browser->platformName() . ' ' . $browser->platformVersion() : $browser->platformName();

        return [
            'device' => $device,
            'device_type' => $browser->deviceType() ?: 'Unknown',
            'platform' => $platform ?: 'Unknown',
            'os' => $os ?: 'Unknown',
            'browser' => $browser->browserName() ?: 'Unknown',
            'browser_version' => $browser->browserVersion() ?: 'Unknown',
            'is_robot' => $isRobot,
            'robot_name' => $botName,
            'robot_category' => $robotCategory,
        ];
    }

    /**
     * Detect bot name from user agent
     */
    private static function detectBotName(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        $botPatterns = [
            'Googlebot' => 'Googlebot',
            'Googlebot-Image' => 'Googlebot-Image',
            'Googlebot-News' => 'Googlebot-News',
            'Googlebot-Video' => 'Googlebot-Video',
            'Bingbot' => 'Bingbot',
            'Slurp' => 'Yahoo! Slurp',
            'DuckDuckBot' => 'DuckDuckBot',
            'Baiduspider' => 'Baiduspider',
            'YandexBot' => 'YandexBot',
            'Sogou' => 'Sogou',
            'Exabot' => 'Exabot',
            'facebot' => 'Facebook External Hit',
            'facebookexternalhit' => 'Facebook External Hit',
            'Twitterbot' => 'Twitterbot',
            'rogerbot' => 'Rogerbot',
            'LinkedInBot' => 'LinkedInBot',
            'Embedly' => 'Embedly',
            'Quora Link Preview' => 'Quora Link Preview',
            'outbrain' => 'Outbrain',
            'Pinterest' => 'Pinterest',
            'Slackbot' => 'Slackbot',
            'TelegramBot' => 'TelegramBot',
            'Discordbot' => 'Discordbot',
            'WhatsApp' => 'WhatsApp',
            'Redditbot' => 'Redditbot',
            'Applebot' => 'Applebot',
            'SemrushBot' => 'SemrushBot',
            'AhrefsBot' => 'AhrefsBot',
            'MJ12bot' => 'MJ12bot',
            'DotBot' => 'DotBot',
            'Seekport' => 'Seekport',
            'CCBot' => 'Common Crawl Bot',
            'GPTBot' => 'OpenAI GPTBot',
            'ChatGPT-User' => 'OpenAI ChatGPT',
            'ClaudeBot' => 'Anthropic ClaudeBot',
        ];

        foreach ($botPatterns as $pattern => $botName) {
            if (stripos($userAgent, $pattern) !== false) {
                return $botName;
            }
        }

        $genericBotPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'checker', 'fetcher', 'monitor'
        ];

        foreach ($genericBotPatterns as $pattern) {
            if (preg_match('/\b' . $pattern . '\b/i', $userAgent)) {
                return ucfirst($pattern);
            }
        }

        return null;
    }

    /**
     * Categorize bots for better analytics
     */
    private static function categorizeBot(?string $botName): ?string
    {
        if (!$botName) return null;

        $searchBots = ['Googlebot', 'Bingbot', 'Yahoo! Slurp', 'DuckDuckBot', 'Baiduspider', 'YandexBot', 'Sogou'];
        $socialBots = ['Facebook External Hit', 'Twitterbot', 'LinkedInBot', 'Pinterest', 'Slackbot', 'TelegramBot', 'Discordbot', 'WhatsApp', 'Redditbot'];
        $aiBots = ['OpenAI GPTBot', 'OpenAI ChatGPT', 'Anthropic ClaudeBot'];
        $seoBots = ['SemrushBot', 'AhrefsBot', 'MJ12bot', 'DotBot', 'Seekport', 'Rogerbot'];
        $monitoringBots = ['CCBot', 'Exabot', 'Embedly'];

        if (in_array($botName, $searchBots)) return 'Search Engine';
        if (in_array($botName, $socialBots)) return 'Social Media';
        if (in_array($botName, $aiBots)) return 'AI Assistant';
        if (in_array($botName, $seoBots)) return 'SEO Tool';
        if (in_array($botName, $monitoringBots)) return 'Monitoring Tool';
        
        return 'Other Bot';
    }

    /**
     * Get geolocation data for IP
     */
    private static function getGeolocationData(string $ip): array
    {
        try {
            $geolocationService = self::getGeolocationService();
            $locationData = $geolocationService->getLocationData($ip);
            
            return $locationData ?: [];
        } catch (\Exception $e) {
            Log::error("Geolocation lookup failed for IP {$ip}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add a new route for system analytics tracking
     */
    public static function addSystemAnalyticsRoute(string $routeName, array $allowedRoles): void
    {
        self::$systemAnalyticsRoutes[$routeName] = $allowedRoles;
    }

    /**
     * Get all registered system analytics routes
     */
    public static function getSystemAnalyticsRoutes(): array
    {
        return self::$systemAnalyticsRoutes;
    }

    /**
     * Get browser and platform analytics with bot detection
     */
    public static function getBrowserAnalytics(Model $model): array
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        $browserStats = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->whereNotNull('browser')
            ->selectRaw('
                browser,
                browser_version,
                COUNT(*) as views,
                COUNT(DISTINCT visitor_token) as unique_visitors
            ')
            ->groupBy('browser', 'browser_version')
            ->orderBy('views', 'desc')
            ->get();

        $platformStats = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->whereNotNull('platform')
            ->selectRaw('
                platform,
                os,
                COUNT(*) as views,
                COUNT(DISTINCT visitor_token) as unique_visitors
            ')
            ->groupBy('platform', 'os')
            ->orderBy('views', 'desc')
            ->get();

        $deviceStats = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->whereNotNull('device')
            ->selectRaw('
                device,
                device_type,
                COUNT(*) as views,
                COUNT(DISTINCT visitor_token) as unique_visitors
            ')
            ->groupBy('device', 'device_type')
            ->orderBy('views', 'desc')
            ->get();

        $botStats = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->where('is_robot', true)
            ->selectRaw('
                robot_name,
                robot_category,
                COUNT(*) as views,
                COUNT(DISTINCT visitor_token) as unique_visitors
            ')
            ->groupBy('robot_name', 'robot_category')
            ->orderBy('views', 'desc')
            ->get();

        return [
            'browsers' => $browserStats,
            'platforms' => $platformStats,
            'devices' => $deviceStats,
            'bots' => $botStats,
            'total_bots' => $botStats->sum('views'),
        ];
    }

    /**
     * Get bot analytics summary
     */
    public static function getBotAnalytics(Model $model): array
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        $botStats = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->selectRaw('
                SUM(CASE WHEN is_robot = true THEN 1 ELSE 0 END) as total_bot_views,
                SUM(CASE WHEN is_robot = false THEN 1 ELSE 0 END) as total_human_views,
                COUNT(DISTINCT CASE WHEN is_robot = true THEN visitor_token END) as unique_bots,
                COUNT(DISTINCT CASE WHEN is_robot = false THEN visitor_token END) as unique_humans
            ')
            ->first();

        $botCategories = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->where('is_robot', true)
            ->whereNotNull('robot_category')
            ->selectRaw('
                robot_category,
                COUNT(*) as views,
                COUNT(DISTINCT visitor_token) as unique_bots
            ')
            ->groupBy('robot_category')
            ->orderBy('views', 'desc')
            ->get();

        $topBots = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->where('is_robot', true)
            ->whereNotNull('robot_name')
            ->selectRaw('
                robot_name,
                robot_category,
                COUNT(*) as views,
                COUNT(DISTINCT visitor_token) as unique_bots
            ')
            ->groupBy('robot_name', 'robot_category')
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get();

        return [
            'total_bot_views' => $botStats->total_bot_views ?? 0,
            'total_human_views' => $botStats->total_human_views ?? 0,
            'unique_bots' => $botStats->unique_bots ?? 0,
            'unique_humans' => $botStats->unique_humans ?? 0,
            'bot_ratio' => $botStats->total_bot_views > 0 ? 
                round(($botStats->total_bot_views / ($botStats->total_bot_views + $botStats->total_human_views)) * 100, 2) : 0,
            'bot_categories' => $botCategories,
            'top_bots' => $topBots,
        ];
    }

    /**
     * Get device type analytics
     */
    public static function getDeviceTypeAnalytics(Model $model): array
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        $deviceTypeStats = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->whereNotNull('device_type')
            ->selectRaw('
                device_type,
                device,
                COUNT(*) as views,
                COUNT(DISTINCT visitor_token) as unique_visitors
            ')
            ->groupBy('device_type', 'device')
            ->orderBy('views', 'desc')
            ->get();

        return [
            'device_types' => $deviceTypeStats,
            'total_device_types' => $deviceTypeStats->count(),
        ];
    }

    /**
     * Get geolocation analytics for a model
     */
    public static function getGeolocationAnalytics(Model $model): array
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        $countryStats = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->whereNotNull('country')
            ->selectRaw('
                country,
                country_code,
                COUNT(*) as views,
                COUNT(DISTINCT visitor_token) as unique_visitors
            ')
            ->groupBy('country', 'country_code')
            ->orderBy('views', 'desc')
            ->get();

        $cityStats = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->whereNotNull('city')
            ->selectRaw('
                city,
                region,
                country,
                COUNT(*) as views,
                COUNT(DISTINCT visitor_token) as unique_visitors
            ')
            ->groupBy('city', 'region', 'country')
            ->orderBy('views', 'desc')
            ->limit(50)
            ->get();

        return [
            'countries' => $countryStats,
            'cities' => $cityStats,
            'total_countries' => $countryStats->count(),
            'total_cities' => $cityStats->count(),
        ];
    }

    /**
     * Get time-based analytics
     */
    public static function getTimeBasedAnalytics(Model $model, string $period = 'daily'): array
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        $format = match($period) {
            'hourly' => '%Y-%m-%d %H:00:00',
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $timeStats = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->selectRaw('
                DATE_FORMAT(visited_at, ?) as time_period,
                COUNT(*) as views,
                COUNT(DISTINCT visitor_token) as unique_visitors
            ', [$format])
            ->groupBy('time_period')
            ->orderBy('time_period')
            ->get();

        return [
            'period' => $period,
            'data' => $timeStats,
            'total_views' => $timeStats->sum('views'),
            'total_unique_visitors' => $timeStats->sum('unique_visitors'),
        ];
    }

    /**
     * Utility methods
     */
    private static function getRequestSignature(string $modelClass, $modelId, string $action): string
    {
        return md5(implode('|', [
            $modelClass,
            $modelId,
            $action,
            Request::fullUrl(),
            session()->getId(),
            Auth::id() ?? 'guest'
        ]));
    }

    public static function clearProcessedRequests(): void
    {
        self::$processedRequests = [];
    }

    /**
     * Check if view has geolocation data
     */
    public static function viewHasGeolocation(View $view): bool
    {
        return !empty($view->country) && !empty($view->country_code);
    }

    /**
     * Force update all analytics for a model
     */
    public static function forceUpdateAllAnalytics(Model $model): void
    {
        try {
            $analytic = self::getOrCreateAnalyticsRecord($model, 'force_update', Request::path(), Request::ip());
            
            self::updateSystemWideAnalytics($model);
            
            // Update periods for all metrics
            $metrics = ['views_count', 'unique_viewers', 'user_views', 'public_views', 'bot_views', 'human_views'];
            foreach ($metrics as $metric) {
                self::updatePeriods($analytic, $metric);
            }
            
            Log::info("Forced analytics update for " . get_class($model));
        } catch (\Exception $e) {
            Log::error("Error forcing analytics update: " . $e->getMessage());
        }
    }

    /**
     * Track general controller action when no specific model instance is available
     */
    public static function trackGeneralControllerAction(string $modelClass, string $action): ?Analytic
    {
        try {
            $defaultModel = $modelClass::first();
            if (!$defaultModel) {
                return null;
            }
            return self::trackControllerAction($defaultModel, $action);
        } catch (\Exception $e) {
            Log::error("General controller tracking failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Increment specific metric count
     */
    public static function incrementMetric(Model $model, string $metric): void
    {
        try {
            $analytic = self::getOrCreateAnalyticsRecord($model, 'increment', Request::path(), Request::ip());
            $analytic->increment($metric);
            
            // Update periods for this metric
            self::updatePeriods($analytic, $metric);
        } catch (\Exception $e) {
            Log::error("Error incrementing metric {$metric}: " . $e->getMessage());
        }
    }

    /**
     * Decrement specific metric count
     */
    public static function decrementMetric(Model $model, string $metric): void
    {
        try {
            $analytic = self::getOrCreateAnalyticsRecord($model, 'decrement', Request::path(), Request::ip());
            $analytic->decrement($metric);
            
            // Update periods for this metric
            self::updatePeriods($analytic, $metric);
        } catch (\Exception $e) {
            Log::error("Error decrementing metric {$metric}: " . $e->getMessage());
        }
    }

    /**
     * Get comprehensive analytics summary for a model
     */
    public static function getAnalyticsSummary(Model $model): array
    {
        $totalAnalytics = self::getTotalAnalyticsForModel($model);
        
        return [
            'views' => [
                'total' => $totalAnalytics['total_views'] ?? 0,
                'unique' => $totalAnalytics['total_unique_viewers'] ?? 0,
                'users' => $totalAnalytics['total_user_views'] ?? 0,
                'public' => $totalAnalytics['total_public_views'] ?? 0,
                'bots' => $totalAnalytics['total_bot_views'] ?? 0,
                'humans' => $totalAnalytics['total_human_views'] ?? 0,
            ],
            'engagement' => [
                'likes' => $totalAnalytics['total_likes'] ?? 0,
                'shares' => $totalAnalytics['total_shares'] ?? 0,
                'comments' => $totalAnalytics['total_comments'] ?? 0,
            ]
        ];
    }

    /**
     * Clean up old analytics data
     */
    public static function cleanupOldData(int $days = 30): void
    {
        try {
            $cutoffDate = now()->subDays($days);
            
            // Delete old views
            $deletedViews = View::where('created_at', '<', $cutoffDate)->delete();
            
            // Delete old periods that are no longer needed
            $deletedPeriods = Period::where('created_at', '<', $cutoffDate)->delete();
            
            Log::info("Analytics cleanup completed: {$deletedViews} views and {$deletedPeriods} periods deleted older than {$days} days.");
        } catch (\Exception $e) {
            Log::error("Error cleaning up old analytics data: " . $e->getMessage());
        }
    }

    /**
     * Update method for backward compatibility
     */
    public static function update(Model $model, array $data = [], ?string $action = null, ?string $userId = null, ?string $sessionId = null, ?string $ip = null): Analytic
    {
        return self::trackControllerAction($model, $action ?? 'update');
    }

    /**
     * Get aggregated analytics
     */
    public static function getAggregatedAnalytics(Model $model): ?Analytic
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        return Analytic::where('analyticable_type', $modelClass)
            ->where('analyticable_id', $modelId)
            ->whereNull('user_id')
            ->first();
    }

    /**
     * Aggregate engagement data
     */
    public static function aggregateEngagementData(Model $model): void
    {
        // This would be implemented based on your specific aggregation needs
        self::forceUpdateAllAnalytics($model);
    }

    /**
     * Get real-time engagement stats
     */
    public static function getRealtimeEngagementStats(Model $model): array
    {
        return self::getAnalyticsSummary($model);
    }

    /**
     * Get period analytics
     */
    public static function getPeriodAnalytics(Model $model, string $granularity = 'daily', ?string $metric = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $analytic = self::getAggregatedAnalytics($model);
        
        if (!$analytic) {
            return [];
        }

        $query = Period::where('analytic_id', $analytic->id)
            ->where('period_granularity', $granularity);

        if ($metric) {
            $query->where('analytic_type', $metric);
        }

        if ($startDate) {
            $query->where('period_start_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('period_start_date', '<=', $endDate);
        }

        return $query->orderBy('period_start_date')->get()->toArray();
    }

    /**
     * Get view stats
     */
    public static function getViewStats(Model $model): array
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        $totalViews = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->count();

        $uniqueViewers = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->distinct('visitor_token')
            ->count('visitor_token');

        $userViews = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->whereNotNull('user_id')
            ->count();

        $publicViews = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->whereNull('user_id')
            ->count();

        return [
            'total_views' => $totalViews,
            'unique_viewers' => $uniqueViewers,
            'user_views' => $userViews,
            'public_views' => $publicViews,
        ];
    }

    /**
     * Get unique viewer stats
     */
    public static function getUniqueViewerStats(Model $model): array
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        $uniqueLoggedIn = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $uniqueSessions = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->distinct('session_id')
            ->count('session_id');

        $uniqueIps = View::where('viewable_type', $modelClass)
            ->where('viewable_id', $modelId)
            ->where('view_status', true)
            ->distinct('ip_address')
            ->count('ip_address');

        return [
            'unique_logged_in_users' => $uniqueLoggedIn,
            'unique_sessions' => $uniqueSessions,
            'unique_ips' => $uniqueIps,
        ];
    }
}