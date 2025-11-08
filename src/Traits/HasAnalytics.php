<?php

namespace Epaisay\Analytics\Traits;

use Epaisay\Analytics\Models\Analytic;
use Epaisay\Analytics\Models\View;
use Epaisay\Analytics\Models\Period;
use Epaisay\Analytics\Helpers\AnalyticsHelper;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

trait HasAnalytics
{
    /**
     * Polymorphic relation to Analytic
     */
    public function analytics(): MorphMany
    {
        return $this->morphMany(Analytic::class, 'analyticable');
    }

    /**
     * Polymorphic relation to View
     */
    public function views(): MorphMany
    {
        return $this->morphMany(View::class, 'viewable');
    }

    /**
     * Update or create analytics for this model instance - WITH LOOP PREVENTION
     */
    public function updateAnalytics(
        array $data = [], 
        ?string $action = null,
        ?string $userId = null, 
        ?string $sessionId = null, 
        ?string $ip = null
    ): Analytic {
        // Prevent analytics on Analytic model itself to avoid loops
        if (get_class($this) === Analytic::class) {
            return new Analytic(); // Return empty instance
        }

        return AnalyticsHelper::update($this, $data, $action, $userId, $sessionId, $ip);
    }

    /**
     * Track a view for this model
     */
    public function trackView(array $viewData = []): ?View
    {
        if (get_class($this) === Analytic::class) {
            return null;
        }

        return AnalyticsHelper::trackView($this, $viewData);
    }

    /**
     * Track a specific metric
     */
    public function trackMetric(string $metric, string $action, int $amount = 1): ?Analytic
    {
        if (get_class($this) === Analytic::class) {
            return null;
        }

        return AnalyticsHelper::incrementMetric($this, $metric, $action, $amount);
    }

    /**
     * Get aggregated analytics with data from ALL individual tables
     */
    public function getAggregatedAnalytics(): ?Analytic
    {
        return AnalyticsHelper::getAggregatedAnalytics($this);
    }

    /**
     * Aggregate engagement data from ALL individual tables
     */
    public function aggregateEngagementData(): void
    {
        AnalyticsHelper::aggregateEngagementData($this);
    }

    /**
     * Get real-time engagement statistics from ALL tables
     */
    public function getRealtimeEngagementStats(): array
    {
        return AnalyticsHelper::getRealtimeEngagementStats($this);
    }

    /**
     * Get period-based analytics
     */
    public function getPeriodAnalytics(string $granularity = 'daily', ?string $metric = null, ?string $startDate = null, ?string $endDate = null): array
    {
        return AnalyticsHelper::getPeriodAnalytics($this, $granularity, $metric, $startDate, $endDate);
    }

    /**
     * Real-time metrics from individual tables
     */
    public function getTotalLikes(): int
    {
        // This method assumes you have a Like model in your application
        // You can customize this based on your actual like system
        if (class_exists('App\Models\Like')) {
            return \App\Models\Like::where('likeable_type', get_class($this))
                ->where('likeable_id', $this->getKey())
                ->where('like_status', 1)
                ->count();
        }
        
        return $this->analytics()->sum('likes_count');
    }

    public function getTotalImpressions(): int
    {
        // This method assumes you have an Impression model in your application
        if (class_exists('App\Models\Impression')) {
            return \App\Models\Impression::where('impressionable_type', get_class($this))
                ->where('impressionable_id', $this->getKey())
                ->where('impression_status', 1)
                ->where('action_type', 'view')
                ->count();
        }
        
        return $this->analytics()->sum('impressions_count');
    }

    public function getTotalClicks(): int
    {
        // This method assumes you have an Impression model in your application
        if (class_exists('App\Models\Impression')) {
            return \App\Models\Impression::where('impressionable_type', get_class($this))
                ->where('impressionable_id', $this->getKey())
                ->where('impression_status', 1)
                ->where('action_type', 'click')
                ->count();
        }
        
        return $this->analytics()->sum('clicks_count');
    }

    public function getTotalReplies(): int
    {
        // This method assumes you have a Reply model in your application
        if (class_exists('App\Models\Reply')) {
            return \App\Models\Reply::where('replyable_type', get_class($this))
                ->where('replyable_id', $this->getKey())
                ->where('reply_status', 1)
                ->count();
        }
        
        return $this->analytics()->sum('replies_count');
    }

    public function getTotalVotes(): int
    {
        // This method assumes you have a Vote model in your application
        if (class_exists('App\Models\Vote')) {
            return \App\Models\Vote::where('voteable_type', get_class($this))
                ->where('voteable_id', $this->getKey())
                ->where('vote_status', 1)
                ->count();
        }
        
        return $this->analytics()->sum('votes_count');
    }

    public function getTotalComplaints(): int
    {
        // This method assumes you have a Complaint model in your application
        if (class_exists('App\Models\Complaint')) {
            return \App\Models\Complaint::where('complainable_type', get_class($this))
                ->where('complainable_id', $this->getKey())
                ->where('complaint_status', 1)
                ->count();
        }
        
        return $this->analytics()->sum('complaints_count');
    }

    public function getTotalBookmarks(): int
    {
        // This method assumes you have a Bookmark model in your application
        if (class_exists('App\Models\Bookmark')) {
            return \App\Models\Bookmark::where('bookmarkable_type', get_class($this))
                ->where('bookmarkable_id', $this->getKey())
                ->where('bookmark_status', 1)
                ->count();
        }
        
        return $this->analytics()->sum('bookmarks_count');
    }

    public function getTotalShares(): int
    {
        // This method assumes you have a Share model in your application
        if (class_exists('App\Models\Share')) {
            return \App\Models\Share::where('shareable_type', get_class($this))
                ->where('shareable_id', $this->getKey())
                ->where('share_status', 1)
                ->count();
        }
        
        return $this->analytics()->sum('shares_count');
    }

    public function getTotalFollows(): int
    {
        // This method assumes you have a Follow model in your application
        if (class_exists('App\Models\Follow')) {
            return \App\Models\Follow::where('followable_type', get_class($this))
                ->where('followable_id', $this->getKey())
                ->where('follow_status', 1)
                ->count();
        }
        
        return $this->analytics()->sum('follows_count');
    }

    /**
     * View statistics using the new views table
     */
    public function getViewStats(): array
    {
        return AnalyticsHelper::getViewStats($this);
    }

    public function getTotalViews(): int
    {
        return View::where('viewable_type', get_class($this))
            ->where('viewable_id', $this->getKey())
            ->where('view_status', 1)
            ->count();
    }

    public function getUniqueViewersCount(): int
    {
        return View::where('viewable_type', get_class($this))
            ->where('viewable_id', $this->getKey())
            ->where('view_status', 1)
            ->distinct('visitor_token')
            ->count('visitor_token');
    }

    public function getUserViewsCount(): int
    {
        return View::where('viewable_type', get_class($this))
            ->where('viewable_id', $this->getKey())
            ->where('view_status', 1)
            ->whereNotNull('user_id')
            ->count();
    }

    public function getPublicViewsCount(): int
    {
        return View::where('viewable_type', get_class($this))
            ->where('viewable_id', $this->getKey())
            ->where('view_status', 1)
            ->whereNull('user_id')
            ->count();
    }

    /**
     * Unique viewer statistics using the new views table
     */
    public function getUniqueViewerStats(): array
    {
        return AnalyticsHelper::getUniqueViewerStats($this);
    }

    public function getUniqueLoggedInUsersCount(): int
    {
        return View::where('viewable_type', get_class($this))
            ->where('viewable_id', $this->getKey())
            ->where('view_status', 1)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');
    }

    public function getUniqueSessionsCount(): int
    {
        return View::where('viewable_type', get_class($this))
            ->where('viewable_id', $this->getKey())
            ->where('view_status', 1)
            ->distinct('session_id')
            ->count('session_id');
    }

    public function getUniqueIpsCount(): int
    {
        return View::where('viewable_type', get_class($this))
            ->where('viewable_id', $this->getKey())
            ->where('view_status', 1)
            ->distinct('ip_address')
            ->count('ip_address');
    }

    /**
     * Metric trackers
     */
    public function trackImpression(string $action = 'impression'): ?Analytic
    {
        return $this->trackMetric('impressions_count', $action);
    }

    public function trackLike(string $action = 'like'): ?Analytic
    {
        $analytic = $this->trackMetric('likes_count', $action);
        if ($analytic) {
            AnalyticsHelper::updateDerivedMetrics($analytic);
        }
        return $analytic;
    }

    public function trackShare(string $action = 'share'): ?Analytic
    {
        $analytic = $this->trackMetric('shares_count', $action);
        if ($analytic) {
            AnalyticsHelper::updateDerivedMetrics($analytic);
        }
        return $analytic;
    }

    public function trackClick(string $action = 'click'): ?Analytic
    {
        $analytic = $this->trackMetric('clicks_count', $action);
        if ($analytic) {
            AnalyticsHelper::updateDerivedMetrics($analytic);
        }
        return $analytic;
    }

    public function trackFollow(string $action = 'follow'): ?Analytic
    {
        $analytic = $this->trackMetric('follows_count', $action);
        if ($analytic) {
            AnalyticsHelper::updateDerivedMetrics($analytic);
        }
        return $analytic;
    }

    public function trackBookmark(string $action = 'bookmark'): ?Analytic
    {
        return $this->trackMetric('bookmarks_count', $action);
    }

    public function trackReply(string $action = 'reply'): ?Analytic
    {
        return $this->trackMetric('replies_count', $action);
    }

    public function trackVote(string $action = 'vote'): ?Analytic
    {
        return $this->trackMetric('votes_count', $action);
    }

    public function trackComplaint(string $action = 'complaint'): ?Analytic
    {
        return $this->trackMetric('complaints_count', $action);
    }

    /**
     * Calculated engagement metrics
     */
    public function getTotalEngagementScore(): float
    {
        return $this->analytics()
            ->get()
            ->sum(function ($analytic) {
                $weights = config('analytics.engagement_weights', [
                    'views' => 0.15,
                    'likes' => 0.25,
                    'shares' => 0.20,
                    'clicks' => 0.15,
                    'replies' => 0.10,
                    'follows' => 0.10,
                    'bookmarks' => 0.05,
                ]);

                return (
                    ($analytic->views_count * $weights['views']) +
                    ($analytic->likes_count * $weights['likes']) +
                    ($analytic->shares_count * $weights['shares']) +
                    ($analytic->clicks_count * $weights['clicks']) +
                    ($analytic->replies_count * $weights['replies']) +
                    ($analytic->follows_count * $weights['follows']) +
                    ($analytic->bookmarks_count * $weights['bookmarks'])
                );
            });
    }

    public function getClickThroughRate(): float
    {
        $stats = $this->getRealtimeEngagementStats();
        return $stats['impressions_count'] > 0
            ? ($stats['clicks_count'] / $stats['impressions_count']) * 100
            : 0.0;
    }

    public function getEngagementRate(): float
    {
        $stats = $this->getRealtimeEngagementStats();
        return $stats['impressions_count'] > 0
            ? (($stats['likes_count'] + $stats['shares_count'] + $stats['replies_count']) / $stats['impressions_count']) * 100
            : 0.0;
    }

    public function getPopularityScore(): float
    {
        $stats = $this->getRealtimeEngagementStats();
        return (
            ($stats['likes_count'] * 0.3) +
            ($stats['shares_count'] * 0.25) +
            ($stats['follows_count'] * 0.2) +
            ($stats['replies_count'] * 0.15) +
            ($stats['bookmarks_count'] * 0.1)
        );
    }

    /**
     * User-specific interaction checks
     */
    public function isLikedByUser(?string $userId = null): bool
    {
        $userId ??= auth()->id();
        
        if (class_exists('App\Models\Like')) {
            return $userId
                ? \App\Models\Like::where('likeable_type', get_class($this))
                    ->where('likeable_id', $this->getKey())
                    ->where('user_id', $userId)
                    ->where('like_status', 1)
                    ->exists()
                : false;
        }
        
        return false;
    }

    public function isBookmarkedByUser(?string $userId = null): bool
    {
        $userId ??= auth()->id();
        
        if (class_exists('App\Models\Bookmark')) {
            return $userId
                ? \App\Models\Bookmark::where('bookmarkable_type', get_class($this))
                    ->where('bookmarkable_id', $this->getKey())
                    ->where('user_id', $userId)
                    ->where('bookmark_status', 1)
                    ->exists()
                : false;
        }
        
        return false;
    }

    public function isFollowedByUser(?string $userId = null): bool
    {
        $userId ??= auth()->id();
        
        if (class_exists('App\Models\Follow')) {
            return $userId
                ? \App\Models\Follow::where('followable_type', get_class($this))
                    ->where('followable_id', $this->getKey())
                    ->where('user_id', $userId)
                    ->where('follow_status', 1)
                    ->exists()
                : false;
        }
        
        return false;
    }

    public function isComplainedByUser(?string $userId = null): bool
    {
        $userId ??= auth()->id();
        
        if (class_exists('App\Models\Complaint')) {
            return $userId
                ? \App\Models\Complaint::where('complainable_type', get_class($this))
                    ->where('complainable_id', $this->getKey())
                    ->where('user_id', $userId)
                    ->where('complaint_status', 1)
                    ->exists()
                : false;
        }
        
        return false;
    }

    public function isViewedByUser(?string $userId = null): bool
    {
        $userId ??= auth()->id();
        return $userId
            ? View::where('viewable_type', get_class($this))
                ->where('viewable_id', $this->getKey())
                ->where('user_id', $userId)
                ->where('view_status', 1)
                ->exists()
            : false;
    }

    /**
     * Top engagers
     */
    public function getTopEngagers(int $limit = 10): array
    {
        if (class_exists('App\Models\Like')) {
            return \App\Models\Like::where('likeable_type', get_class($this))
                ->where('likeable_id', $this->getKey())
                ->where('like_status', 1)
                ->with('user')
                ->select('user_id', DB::raw('COUNT(*) as interaction_count'))
                ->groupBy('user_id')
                ->orderByDesc('interaction_count')
                ->limit($limit)
                ->get()
                ->toArray();
        }
        
        return [];
    }

    /**
     * Analytics timeline using periods table
     */
    public function getAnalyticsTimeline(string $period = '7days', string $metric = 'views_count'): array
    {
        $endDate = now();
        $startDate = match ($period) {
            '30days' => now()->subDays(30),
            '90days' => now()->subDays(90),
            '1year'  => now()->subYear(),
            default  => now()->subDays(7),
        };

        // Get the main analytics record for this model
        $analytic = $this->analytics()->first();
        
        if (!$analytic) {
            return [];
        }

        return Period::where('analytic_id', $analytic->id)
            ->where('analytic_type', $metric)
            ->where('period_granularity', 'daily')
            ->whereBetween('period_start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->selectRaw('period_start_date as date, value')
            ->orderBy('period_start_date')
            ->get()
            ->toArray();
    }

    /**
     * Tracked controller actions
     */
    public static function getTrackedActions(): array
    {
        return array_merge([
            'show', 
            'index',
            'view',
            'display',
        ], static::customTrackedActions());
    }

    /**
     * Add custom tracked actions
     * Override this method in your model to add custom actions
     */
    protected static function customTrackedActions(): array
    {
        return [];
    }

    protected static function bootHasAnalytics(): void
    {
        static::created(fn() => null);
        static::updated(fn() => null);
    }

    /**
     * Get formatted view count for this model (1.5k, 2.3M, etc.)
     */
    public function getViewCount(): string
    {
        $views = $this->getTotalViews();

        if ($views >= 1000000000000) { // 1 Trillion
            return number_format($views / 1000000000000, 2) . 'T';
        } elseif ($views >= 1000000000) { // 1 Billion
            return number_format($views / 1000000000, 2) . 'B';
        } elseif ($views >= 1000000) { // 1 Million
            return number_format($views / 1000000, 2) . 'M';
        } elseif ($views >= 1000) { // 1 Thousand
            return number_format($views / 1000, 2) . 'K';
        } else {
            return (string) $views;
        }
    }

    /**
     * Get raw view count (if ever needed)
     */
    public function getRawViewCount(): int
    {
        return $this->getTotalViews();
    }
}