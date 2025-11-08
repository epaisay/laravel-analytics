<?php

namespace Epaisay\Analytics\Console\Commands;

use Illuminate\Console\Command;
use Epaisay\Analytics\Services\AnalyticsAggregationService;

class AggregateAnalytics extends Command
{
    protected $signature = 'analytics:aggregate 
                            {model? : The model class to aggregate analytics for}
                            {--recent : Aggregate only recent activities (last 24 hours)}
                            {--cleanup : Clean up old analytics data}
                            {--orphaned : Clean up orphaned analytics records}';

    protected $description = 'Aggregate analytics data from individual tables';

    public function handle()
    {
        $service = new AnalyticsAggregationService();

        // Handle cleanup options
        if ($this->option('cleanup')) {
            $this->cleanupOldData($service);
            return;
        }

        if ($this->option('orphaned')) {
            $this->cleanupOrphaned($service);
            return;
        }

        if ($this->option('recent')) {
            $service->aggregateRecentActivities();
            $this->info('✅ Recent activities aggregated successfully.');
            return;
        }

        $model = $this->argument('model');

        if ($model) {
            if (!class_exists($model)) {
                $this->error("❌ Model class '{$model}' does not exist.");
                return;
            }

            $service->aggregateForModelType($model);
            $this->info("✅ Analytics aggregated for: {$model}");
        } else {
            // Aggregate for common models that might use analytics
            $models = $this->getAnalyticsModels();
            
            foreach ($models as $modelClass) {
                if (class_exists($modelClass)) {
                    $this->info("🔄 Aggregating analytics for: {$modelClass}");
                    $service->aggregateForModelType($modelClass);
                }
            }

            $this->info('✅ Analytics aggregated for all models.');
        }
    }

    /**
     * Get models that might use analytics
     */
    private function getAnalyticsModels(): array
    {
        return [
            'App\Models\Post',
            'App\Models\Article',
            'App\Models\Page',
            'App\Models\Product',
            'App\Models\User',
            'App\Models\Video',
            'App\Models\Comment',
        ];
    }

    /**
     * Clean up old analytics data
     */
    private function cleanupOldData(AnalyticsAggregationService $service): void
    {
        $this->info('🧹 Cleaning up old analytics data...');
        
        // Clean up old periods
        $service->cleanupOldPeriods();
        
        // Clean up old views and analytics data
        $retentionDays = config('analytics.retention.views', 365);
        $cutoffDate = now()->subDays($retentionDays);
        
        $deletedViews = \Epaisay\Analytics\Models\View::where('created_at', '<', $cutoffDate)->delete();
        $deletedAnalytics = \Epaisay\Analytics\Models\Analytic::where('created_at', '<', $cutoffDate)->delete();
        
        $this->info("✅ Cleaned up {$deletedViews} old views and {$deletedAnalytics} old analytics records.");
    }

    /**
     * Clean up orphaned analytics records
     */
    private function cleanupOrphaned(AnalyticsAggregationService $service): void
    {
        $this->info('🧹 Cleaning up orphaned analytics records...');
        $service->cleanupOrphanedAnalytics();
        $this->info('✅ Orphaned analytics records cleaned up.');
    }
}