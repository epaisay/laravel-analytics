<?php

namespace Epaisay\Analytics\Observers;

use Illuminate\Database\Eloquent\Model;
use Epaisay\Analytics\Traits\HasAnalytics;
use Illuminate\Support\Facades\Log;

class AnalyticsObserver
{
    private static $processing = false;

    /**
     * Handle model creation - PREVENT LOOPS
     */
    public function created(Model $model)
    {
        if (self::$processing) {
            return;
        }

        if (get_class($model) === \Epaisay\Analytics\Models\Analytic::class) {
            return;
        }

        if (in_array(HasAnalytics::class, class_uses($model))) {
            self::$processing = true;

            try {
                $model->updateAnalytics([], 'created');
            } catch (\Exception $e) {
                Log::error("Created analytics error: " . $e->getMessage());
            } finally {
                self::$processing = false;
            }
        }
    }

    /**
     * Handle model updates - PREVENT LOOPS
     */
    public function updated(Model $model)
    {
        if (self::$processing) {
            return;
        }

        if (get_class($model) === \Epaisay\Analytics\Models\Analytic::class) {
            return;
        }

        if (in_array(HasAnalytics::class, class_uses($model))) {
            self::$processing = true;

            try {
                $analytics = $model->analytics()->latest()->first();
                $trendScore = 0;

                if ($analytics) {
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
                        ($analytics->views_count * $weights['views']) +
                        ($analytics->likes_count * $weights['likes']) +
                        ($analytics->shares_count * $weights['shares']) +
                        ($analytics->clicks_count * $weights['clicks']) +
                        ($analytics->replies_count * $weights['replies']) +
                        ($analytics->follows_count * $weights['follows']) +
                        ($analytics->bookmarks_count * $weights['bookmarks'])
                    );
                }

                $model->updateAnalytics([
                    'last_activity_at' => now(),
                    'trend_score' => $trendScore,
                ], 'updated');
            } catch (\Exception $e) {
                Log::error("Updated analytics error: " . $e->getMessage());
            } finally {
                self::$processing = false;
            }
        }
    }

    /**
     * Handle model deletion - PREVENT LOOPS
     */
    public function deleted(Model $model)
    {
        if (self::$processing) {
            return;
        }

        if (get_class($model) === \Epaisay\Analytics\Models\Analytic::class) {
            return;
        }

        if (in_array(HasAnalytics::class, class_uses($model))) {
            self::$processing = true;

            try {
                if (method_exists($model, 'trashed') && $model->trashed()) {
                    // Soft delete
                    $model->analytics()->update(['analytics_status' => 0]);
                    $model->updateAnalytics([], 'soft_deleted');
                } else {
                    // Hard delete - cascade will handle views and periods deletion
                    $model->updateAnalytics([], 'deleted');
                }
            } catch (\Exception $e) {
                Log::error("Deleted analytics error: " . $e->getMessage());
            } finally {
                self::$processing = false;
            }
        }
    }

    /**
     * Handle model restoration - PREVENT LOOPS
     */
    public function restored(Model $model)
    {
        if (self::$processing) {
            return;
        }

        if (get_class($model) === \Epaisay\Analytics\Models\Analytic::class) {
            return;
        }

        if (in_array(HasAnalytics::class, class_uses($model))) {
            self::$processing = true;

            try {
                $model->analytics()->update(['analytics_status' => 1]);
                $model->updateAnalytics([], 'restored');
            } catch (\Exception $e) {
                Log::error("Restored analytics error: " . $e->getMessage());
            } finally {
                self::$processing = false;
            }
        }
    }

    /**
     * Handle model force deletion - PREVENT LOOPS
     */
    public function forceDeleted(Model $model)
    {
        if (self::$processing) {
            return;
        }

        if (get_class($model) === \Epaisay\Analytics\Models\Analytic::class) {
            return;
        }

        if (in_array(HasAnalytics::class, class_uses($model))) {
            self::$processing = true;

            try {
                // Cascade deletion will handle views and periods
                $model->analytics()->delete();
            } catch (\Exception $e) {
                Log::error("Force deletion analytics error: " . $e->getMessage());
            } finally {
                self::$processing = false;
            }
        }
    }
}