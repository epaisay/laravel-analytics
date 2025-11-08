<?php

namespace Epaisay\Analytics\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallAnalytics extends Command
{
    protected $signature = 'analytics:install 
                            {--force : Overwrite existing files}
                            {--migrate : Run migrations after installation}
                            {--seed : Seed demo data}';

    protected $description = 'Install the Laravel Analytics package';

    public function handle()
    {
        $this->info('🚀 Installing Laravel Analytics Package...');

        // Publish configuration
        $this->call('vendor:publish', [
            '--tag' => 'analytics-config',
            '--force' => $this->option('force')
        ]);

        // Publish migrations
        $this->call('vendor:publish', [
            '--tag' => 'analytics-migrations',
            '--force' => $this->option('force')
        ]);

        // Run migrations if requested
        if ($this->option('migrate')) {
            $this->call('migrate');
            $this->info('✅ Database migrations executed.');
        }

        // Create example model and controller if they don't exist
        $this->createExampleFiles();

        // Display installation summary
        $this->displayInstallationSummary();

        $this->info('🎉 Laravel Analytics package installed successfully!');
    }

    /**
     * Create example files for the user
     */
    private function createExampleFiles(): void
    {
        $this->createExampleModel();
        $this->createExampleController();
        $this->createExampleMiddlewareRegistration();
    }

    /**
     * Create example Post model with analytics trait
     */
    private function createExampleModel(): void
    {
        $modelPath = app_path('Models/Post.php');
        
        if (!File::exists($modelPath)) {
            $stub = File::get(__DIR__ . '/../../../stubs/Post.stub');
            File::put($modelPath, $stub);
            $this->info('✅ Example Post model created.');
        } else {
            $this->warn('⚠️  Post model already exists. Skipping creation.');
        }
    }

    /**
     * Create example PostController with analytics tracking
     */
    private function createExampleController(): void
    {
        $controllerPath = app_path('Http/Controllers/PostController.php');
        
        if (!File::exists($controllerPath)) {
            $stub = File::get(__DIR__ . '/../../../stubs/PostController.stub');
            File::put($controllerPath, $stub);
            $this->info('✅ Example PostController created.');
        } else {
            $this->warn('⚠️  PostController already exists. Skipping creation.');
        }
    }

    /**
     * Add middleware registration to route service provider
     */
    private function createExampleMiddlewareRegistration(): void
    {
        $routeServiceProviderPath = app_path('Providers/RouteServiceProvider.php');
        
        if (File::exists($routeServiceProviderPath)) {
            $content = File::get($routeServiceProviderPath);
            
            // Check if middleware is already registered
            if (!str_contains($content, 'track.analytics')) {
                $search = "protected function mapWebRoutes()\n    {";
                $replace = "protected function mapWebRoutes()\n    {\n        Route::middleware(['web', 'track.analytics'])";
                
                $content = str_replace($search, $replace, $content);
                File::put($routeServiceProviderPath, $content);
                $this->info('✅ Analytics middleware registered in RouteServiceProvider.');
            } else {
                $this->warn('⚠️  Analytics middleware already registered. Skipping.');
            }
        }
    }

    /**
     * Display installation summary
     */
    private function displayInstallationSummary(): void
    {
        $this->info(PHP_EOL . '📊 Installation Summary:');
        $this->line('----------------------------------------');
        $this->line('✅ Configuration published to config/analytics.php');
        $this->line('✅ Database migrations published');
        
        if ($this->option('migrate')) {
            $this->line('✅ Database migrations executed');
        } else {
            $this->line('⚠️  Run migrations: php artisan migrate');
        }
        
        $this->line('✅ Example files created');
        $this->line('✅ Analytics middleware registered');
        
        $this->info(PHP_EOL . '🎯 Next Steps:');
        $this->line('1. Add the HasAnalytics trait to your models:');
        $this->line('   use Epaisay\Analytics\Traits\HasAnalytics;');
        $this->line('2. Add custom tracked actions in your models:');
        $this->line('   protected static function customTrackedActions(): array');
        $this->line('   {');
        $this->line('       return [\'showAgency\', \'showStore\'];');
        $this->line('   }');
        $this->line('3. Run analytics aggregation: php artisan analytics:aggregate');
        $this->line('4. Check your analytics data!');
        
        $this->info(PHP_EOL . '📖 Documentation: https://github.com/epaisay/laravel-analytics');
    }
}