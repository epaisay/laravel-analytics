<?php

namespace Epaisay\Analytics\Services;

use Epaisay\Analytics\Models\Analytic;
use Epaisay\Analytics\Models\View;
use Epaisay\Analytics\Models\Period;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsAggregationService
{
    /**
     * Aggregate all engagement data for a specific model instance across all users/visitors
     */
    public function aggregateForModel(string $modelClass, string $modelId): void
    {
        try {
            Log::info("🔄 Aggregating analytics for: {$modelClass}::{$modelId}");

            // Get aggregated data from individual user/visitor analytics records
            $aggregatedData = $this->getAggregatedDataFromAnalytics($modelClass, $modelId);

            // Update the base analytics record (without user_id) with aggregated totals
            $this->updateBaseAnalyticsRecord($modelClass, $modelId, $aggregatedData);

            Log::info("✅ Aggregation completed for: {$modelClass}::{$modelId}");

        } catch (\Exception $e) {
            Log::error("💥 Aggregation failed for {$modelClass}::{$modelId}: " . $e->getMessage());
        }
    }

    /**
     * Aggregate all engagement data for all models of a specific type
     */
    public function aggregateForModelType(string $modelClass): void
    {
        try {
            Log::info("🔄 Aggregating analytics for all: {$modelClass}");

            // Get all model instances that have analytics records
            $modelIds = Analytic::where('analyticable_type', $modelClass)
                ->distinct()
                ->pluck('analyticable_id')
                ->toArray();

            foreach ($modelIds as $modelId) {
                $this->aggregateForModel($modelClass, $modelId);
            }

            Log::info("✅ Aggregation completed for all: {$modelClass}");

        } catch (\Exception $e) {
            Log::error("💥 Bulk aggregation failed for {$modelClass}: " . $e->getMessage());
        }
    }

    /**
     * Get aggregated data from all individual user/visitor analytics records
     */
    private function getAggregatedDataFromAnalytics(string $modelClass, string $modelId): array
    {
        return Analytic::where('analyticable_type', $modelClass)
            ->where('analyticable_id', $modelId)
            ->selectRaw('
                SUM(views_count) as total_views_count,
                COUNT(*) as total_unique_viewers,
                SUM(user_views) as total_user_views,
                SUM(public_views) as total_public_views,
                SUM(bot_views) as total_bot_views,
                SUM(human_views) as total_human_views,
                SUM(impressions_count) as total_impressions_count,
                SUM(likes_count) as total_likes_count,
                SUM(replies_count) as total_replies_count,
                SUM(votes_count) as total_votes_count,
                SUM(complaints_count) as total_complaints_count,
                SUM(bookmarks_count) as total_bookmarks_count,
                SUM(shares_count) as total_shares_count,
                SUM(follows_count) as total_follows_count,
                SUM(clicks_count) as total_clicks_count,
                SUM(comments_count) as total_comments_count,
                SUM(messages_count) as total_messages_count,
                SUM(chats_count) as total_chats_count,
                SUM(contacts_count) as total_contacts_count,
                SUM(wishlists_count) as total_wishlists_count,
                SUM(listings_count) as total_listings_count,
                MAX(last_activity_at) as last_activity_at
            ')
            ->first()
            ->toArray();
    }

    /**
     * Update base analytics record with aggregated totals
     */
    private function updateBaseAnalyticsRecord(string $modelClass, string $modelId, array $aggregatedData): Analytic
    {
        // Find or create base analytics record (without user_id)
        $baseAnalytic = Analytic::where('analyticable_type', $modelClass)
            ->where('analyticable_id', $modelId)
            ->whereNull('user_id')
            ->first();

        if (!$baseAnalytic) {
            // Create base analytics record
            $baseAnalytic = Analytic::create([
                'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
                'analyticable_type' => $modelClass,
                'analyticable_id' => $modelId,
                'user_id' => null,
                'visitor_token' => null,
                'session_id' => null,
                'ip_address' => null,
                'action_type' => 'aggregated',
                'request_path' => null,
                'last_activity_at' => $aggregatedData['last_activity_at'] ?? now(),
                'created_by' => null,
            ]);
        }

        // Prepare update data
        $updateData = [
            'views_count' => $aggregatedData['total_views_count'] ?? 0,
            'unique_viewers' => $aggregatedData['total_unique_viewers'] ?? 0,
            'user_views' => $aggregatedData['total_user_views'] ?? 0,
            'public_views' => $aggregatedData['total_public_views'] ?? 0,
            'bot_views' => $aggregatedData['total_bot_views'] ?? 0,
            'human_views' => $aggregatedData['total_human_views'] ?? 0,
            'impressions_count' => $aggregatedData['total_impressions_count'] ?? 0,
            'likes_count' => $aggregatedData['total_likes_count'] ?? 0,
            'replies_count' => $aggregatedData['total_replies_count'] ?? 0,
            'votes_count' => $aggregatedData['total_votes_count'] ?? 0,
            'complaints_count' => $aggregatedData['total_complaints_count'] ?? 0,
            'bookmarks_count' => $aggregatedData['total_bookmarks_count'] ?? 0,
            'shares_count' => $aggregatedData['total_shares_count'] ?? 0,
            'follows_count' => $aggregatedData['total_follows_count'] ?? 0,
            'clicks_count' => $aggregatedData['total_clicks_count'] ?? 0,
            'comments_count' => $aggregatedData['total_comments_count'] ?? 0,
            'messages_count' => $aggregatedData['total_messages_count'] ?? 0,
            'chats_count' => $aggregatedData['total_chats_count'] ?? 0,
            'contacts_count' => $aggregatedData['total_contacts_count'] ?? 0,
            'wishlists_count' => $aggregatedData['total_wishlists_count'] ?? 0,
            'listings_count' => $aggregatedData['total_listings_count'] ?? 0,
            'last_activity_at' => $aggregatedData['last_activity_at'] ?? now(),
        ];

        // Update base analytics record
        $baseAnalytic->update($updateData);

        // Update derived metrics
        $this->updateDerivedMetrics($baseAnalytic);

        // Update periods
        $this->updatePeriods($baseAnalytic);

        return $baseAnalytic;
    }

    /**
     * Update derived metrics (CTR, trend score, etc.)
     */
    private function updateDerivedMetrics(Analytic $analytic): void
    {
        // Calculate click_through_rate
        if ($analytic->impressions_count > 0) {
            $ctr = ($analytic->clicks_count / $analytic->impressions_count) * 100;
            $analytic->click_through_rate = round($ctr, 2);
        } else {
            $analytic->click_through_rate = 0;
        }

        // Calculate trend_score based on engagement
        $weights = config('analytics.engagement_weights', [
            'views' => 0.15,
            'likes' => 0.25,
            'shares' => 0.20,
            'clicks' => 0.15,
            'replies' => 0.10,
            'follows' => 0.10,
            'bookmarks' => 0.05,
        ]);

        $trendScore = (
            ($analytic->views_count * $weights['views']) +
            ($analytic->likes_count * $weights['likes']) +
            ($analytic->shares_count * $weights['shares']) +
            ($analytic->clicks_count * $weights['clicks']) +
            ($analytic->replies_count * $weights['replies']) +
            ($analytic->follows_count * $weights['follows']) +
            ($analytic->bookmarks_count * $weights['bookmarks'])
        );

        $analytic->trend_score = round($trendScore, 2);
        
        // Calculate total reactions
        $analytic->reaction_counts = (
            $analytic->likes_count +
            $analytic->replies_count +
            $analytic->votes_count +
            $analytic->shares_count
        );

        // Calculate contributors count (users who interacted)
        $contributorsCount = Analytic::where('analyticable_type', $analytic->analyticable_type)
            ->where('analyticable_id', $analytic->analyticable_id)
            ->where(function($query) {
                $query->where('likes_count', '>', 0)
                    ->orWhere('shares_count', '>', 0)
                    ->orWhere('comments_count', '>', 0)
                    ->orWhere('replies_count', '>', 0)
                    ->orWhere('votes_count', '>', 0);
            })
            ->count();

        $analytic->contributors_count = $contributorsCount;

        $analytic->save();
    }

    /**
     * Update periods for analytic
     */
    private function updatePeriods(Analytic $analytic): void
    {
        $metrics = [
            'views_count',
            'likes_count',
            'shares_count',
            'clicks_count',
            'impressions_count',
            'unique_viewers'
        ];

        foreach ($metrics as $metric) {
            $this->updatePeriodForMetric($analytic, $metric, 'daily');
            $this->updatePeriodForMetric($analytic, $metric, 'weekly');
            $this->updatePeriodForMetric($analytic, $metric, 'monthly');
            $this->updatePeriodForMetric($analytic, $metric, 'yearly');
        }
    }

    /**
     * Update period for specific metric and granularity
     */
    private function updatePeriodForMetric(Analytic $analytic, string $metric, string $granularity): void
    {
        $periodData = $this->calculatePeriodData($analytic, $metric, $granularity);
        
        if ($periodData) {
            Period::updateOrCreate(
                [
                    'analytic_id' => $analytic->id,
                    'analytic_type' => $metric,
                    'period_granularity' => $granularity,
                    'period_start_date' => $periodData['start_date'],
                ],
                [
                    'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
                    'period_end_date' => $periodData['end_date'],
                    'value' => $periodData['value'],
                    'growth_rate' => $periodData['growth_rate'],
                    'previous_value' => $periodData['previous_value'],
                    'created_by' => null,
                ]
            );
        }
    }

    /**
     * Calculate period data for metric
     */
    private function calculatePeriodData(Analytic $analytic, string $metric, string $granularity): ?array
    {
        $now = Carbon::now();
        
        switch ($granularity) {
            case 'daily':
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
                $previousStart = $startDate->copy()->subDay();
                break;
                
            case 'weekly':
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
                $previousStart = $startDate->copy()->subWeek();
                break;
                
            case 'monthly':
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                $previousStart = $startDate->copy()->subMonth();
                break;
                
            case 'yearly':
                $startDate = $now->copy()->startOfYear();
                $endDate = $now->copy()->endOfYear();
                $previousStart = $startDate->copy()->subYear();
                break;
                
            default:
                return null;
        }

        // Current value from analytics
        $currentValue = $analytic->{$metric} ?? 0;
        
        // Get previous period value
        $previousValue = $this->getPreviousPeriodValue($analytic, $metric, $previousStart, $granularity);
        
        // Calculate growth rate
        $growthRate = $previousValue > 0 
            ? (($currentValue - $previousValue) / $previousValue) * 100 
            : ($currentValue > 0 ? 100 : 0);

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'value' => $currentValue,
            'growth_rate' => round($growthRate, 2),
            'previous_value' => $previousValue,
        ];
    }

    /**
     * Get previous period value
     */
    private function getPreviousPeriodValue(Analytic $analytic, string $metric, Carbon $previousStart, string $granularity): int
    {
        // Try to get from periods table first
        $previousPeriod = Period::where('analytic_id', $analytic->id)
            ->where('analytic_type', $metric)
            ->where('period_granularity', $granularity)
            ->where('period_start_date', '<', $previousStart->format('Y-m-d'))
            ->orderBy('period_start_date', 'desc')
            ->first();

        if ($previousPeriod) {
            return $previousPeriod->value;
        }

        // Fallback: calculate based on historical data from individual user analytics
        return $this->calculateHistoricalValue($analytic, $metric, $previousStart);
    }

    /**
     * Calculate historical value for previous period from individual user analytics
     */
    private function calculateHistoricalValue(Analytic $analytic, string $metric, Carbon $periodStart): int
    {
        $modelClass = $analytic->analyticable_type;
        $modelId = $analytic->analyticable_id;

        // Sum the metric from individual user analytics records created before the period start
        return Analytic::where('analyticable_type', $modelClass)
            ->where('analyticable_id', $modelId)
            ->where('created_at', '<', $periodStart)
            ->sum($metric) ?? 0;
    }

    /**
     * Run aggregation for recent activities (last 24 hours)
     */
    public function aggregateRecentActivities(): void
    {
        try {
            Log::info("🔄 Aggregating recent activities (last 24 hours)");

            $yesterday = Carbon::now()->subDay();

            // Get models with recent analytics activity
            $recentModels = Analytic::where('last_activity_at', '>=', $yesterday)
                ->orWhere('created_at', '>=', $yesterday)
                ->select(['analyticable_type', 'analyticable_id'])
                ->distinct()
                ->get();

            foreach ($recentModels as $model) {
                $this->aggregateForModel($model->analyticable_type, $model->analyticable_id);
            }

            Log::info("✅ Recent activities aggregation completed");

        } catch (\Exception $e) {
            Log::error("💥 Recent activities aggregation failed: " . $e->getMessage());
        }
    }

    /**
     * Get analytics summary for a model
     */
    public function getAnalyticsSummary(string $modelClass, string $modelId): array
    {
        $baseAnalytic = Analytic::where('analyticable_type', $modelClass)
            ->where('analyticable_id', $modelId)
            ->whereNull('user_id')
            ->first();

        if (!$baseAnalytic) {
            return [];
        }

        return [
            'views' => [
                'total' => $baseAnalytic->views_count,
                'unique' => $baseAnalytic->unique_viewers,
                'users' => $baseAnalytic->user_views,
                'public' => $baseAnalytic->public_views,
                'bots' => $baseAnalytic->bot_views,
                'humans' => $baseAnalytic->human_views,
            ],
            'engagement' => [
                'likes' => $baseAnalytic->likes_count,
                'shares' => $baseAnalytic->shares_count,
                'comments' => $baseAnalytic->comments_count,
                'replies' => $baseAnalytic->replies_count,
                'bookmarks' => $baseAnalytic->bookmarks_count,
                'click_through_rate' => $baseAnalytic->click_through_rate,
            ],
            'performance' => [
                'trend_score' => $baseAnalytic->trend_score,
                'contributors' => $baseAnalytic->contributors_count,
                'last_activity' => $baseAnalytic->last_activity_at,
            ]
        ];
    }

    /**
     * Clean up old period data
     */
    public function cleanupOldPeriods(int $keepMonths = 12): void
    {
        try {
            $cutoffDate = Carbon::now()->subMonths($keepMonths);

            $deletedCount = Period::where('period_start_date', '<', $cutoffDate->format('Y-m-d'))
                ->delete();

            Log::info("🧹 Cleaned up {$deletedCount} old period records older than {$keepMonths} months");
        } catch (\Exception $e) {
            Log::error("💥 Period cleanup failed: " . $e->getMessage());
        }
    }

    /**
     * Clean up orphaned analytics records
     */
    public function cleanupOrphanedAnalytics(): void
    {
        try {
            // Find analytics records where the related model no longer exists
            $orphanedCount = 0;
            
            $modelTypes = Analytic::distinct()->pluck('analyticable_type');
            
            foreach ($modelTypes as $modelType) {
                if (class_exists($modelType)) {
                    $existingIds = $modelType::pluck('id')->toArray();
                    
                    $deleted = Analytic::where('analyticable_type', $modelType)
                        ->whereNotIn('analyticable_id', $existingIds)
                        ->delete();
                    
                    $orphanedCount += $deleted;
                } else {
                    // Model class doesn't exist anymore
                    $deleted = Analytic::where('analyticable_type', $modelType)->delete();
                    $orphanedCount += $deleted;
                }
            }

            Log::info("🧹 Cleaned up {$orphanedCount} orphaned analytics records");
        } catch (\Exception $e) {
            Log::error("💥 Orphaned analytics cleanup failed: " . $e->getMessage());
        }
    }
}