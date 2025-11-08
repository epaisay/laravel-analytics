<?php

namespace Epaisay\Analytics\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Epaisay\Analytics\Models\Analytic trackControllerAction(\Illuminate\Database\Eloquent\Model $model, string $action)
 * @method static \Epaisay\Analytics\Models\View trackView(\Illuminate\Database\Eloquent\Model $model, array $viewData = [])
 * @method static \Epaisay\Analytics\Models\Analytic trackGeneralControllerAction(string $modelClass, string $action)
 * @method static void incrementMetric(\Illuminate\Database\Eloquent\Model $model, string $metric)
 * @method static void decrementMetric(\Illuminate\Database\Eloquent\Model $model, string $metric)
 * @method static array getTotalAnalyticsForModel(\Illuminate\Database\Eloquent\Model $model)
 * @method static array getAnalyticsSummary(\Illuminate\Database\Eloquent\Model $model)
 * @method static array getBrowserAnalytics(\Illuminate\Database\Eloquent\Model $model)
 * @method static array getBotAnalytics(\Illuminate\Database\Eloquent\Model $model)
 * @method static array getDeviceTypeAnalytics(\Illuminate\Database\Eloquent\Model $model)
 * @method static array getGeolocationAnalytics(\Illuminate\Database\Eloquent\Model $model)
 * @method static array getTimeBasedAnalytics(\Illuminate\Database\Eloquent\Model $model, string $period = 'daily')
 * @method static void forceUpdateAllAnalytics(\Illuminate\Database\Eloquent\Model $model)
 * @method static void cleanupOldData(int $days = 30)
 * @method static string getVirtualPageUuid(string $slug)
 * @method static void addSystemAnalyticsRoute(string $routeName, array $allowedRoles)
 * @method static array getSystemAnalyticsRoutes()
 * @method static void clearProcessedRequests()
 * @method static \Epaisay\Analytics\Models\Analytic update(\Illuminate\Database\Eloquent\Model $model, array $data = [], ?string $action = null, ?string $userId = null, ?string $sessionId = null, ?string $ip = null)
 * @method static \Epaisay\Analytics\Models\Analytic getAggregatedAnalytics(\Illuminate\Database\Eloquent\Model $model)
 * @method static void aggregateEngagementData(\Illuminate\Database\Eloquent\Model $model)
 * @method static array getRealtimeEngagementStats(\Illuminate\Database\Eloquent\Model $model)
 * @method static array getPeriodAnalytics(\Illuminate\Database\Eloquent\Model $model, string $granularity = 'daily', ?string $metric = null, ?string $startDate = null, ?string $endDate = null)
 * @method static array getViewStats(\Illuminate\Database\Eloquent\Model $model)
 * @method static array getUniqueViewerStats(\Illuminate\Database\Eloquent\Model $model)
 * @method static void updateDerivedMetrics(\Epaisay\Analytics\Models\Analytic $analytic)
 *
 * @see \Epaisay\Analytics\Helpers\AnalyticsHelper
 */
class Analytics extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'analytics';
    }
}