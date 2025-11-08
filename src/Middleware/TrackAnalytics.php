<?php

namespace Epaisay\Analytics\Middleware;

use Closure;
use Illuminate\Http\Request;
use Epaisay\Analytics\Helpers\AnalyticsHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class TrackAnalytics
{
    /**
     * Handle incoming request
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $this->trackAnalytics($request, $response);
        return $response;
    }

    /**
     * Main analytics tracking
     */
    private function trackAnalytics(Request $request, $response): void
    {
        try {
            // Skip if analytics is disabled
            if (!config('analytics.enabled', true)) {
                return;
            }

            // Only track successful GET requests
            if (!$request->isMethod('get') || $response->getStatusCode() !== 200) {
                return;
            }

            $route = $request->route();
            if (!$route) {
                return;
            }

            $controller = $route->getController();
            $action = $route->getActionMethod();

            if (!$controller || !$action) {
                return;
            }

            $controllerClass = get_class($controller);
            $modelClass = $this->findModelForController($controllerClass);

            if ($modelClass && $this->shouldTrackAction($modelClass, $action)) {
                $modelInstance = $this->getModelInstanceFromRoute($modelClass, $route);

                if ($modelInstance) {
                    AnalyticsHelper::trackControllerAction($modelInstance, $action);
                } else {
                    AnalyticsHelper::trackGeneralControllerAction($modelClass, $action);
                }
            }
        } catch (\Exception $e) {
            Log::error("Analytics middleware error: " . $e->getMessage());
        }
    }

    /**
     * Find model for controller
     */
    private function findModelForController(string $controllerClass): ?string
    {
        $controllerName = class_basename($controllerClass);
        $modelName = str_replace('Controller', '', $controllerName);

        $possibleModels = [
            "App\\Models\\{$modelName}",
            "App\\{$modelName}",
        ];

        foreach ($possibleModels as $modelClass) {
            if (class_exists($modelClass) && $this->modelUsesAnalyticsTrait($modelClass)) {
                return $modelClass;
            }
        }

        return null;
    }

    /**
     * Get model instance from route parameters
     */
    private function getModelInstanceFromRoute(string $modelClass, $route): ?Model
    {
        try {
            $parameters = $route->parameters();
            $possibleIdKeys = ['id', 'uuid', 'slug', 'user', 'post', 'article', 'model'];

            foreach ($possibleIdKeys as $key) {
                if (isset($parameters[$key]) && $parameters[$key]) {
                    $param = $parameters[$key];

                    if ($param instanceof Model) {
                        return $param;
                    }

                    if (is_string($param) || is_int($param)) {
                        // Try to find by ID first
                        $modelInstance = $modelClass::find($param);
                        if ($modelInstance) {
                            return $modelInstance;
                        }

                        // Try to find by slug if it's a string
                        if (is_string($param) && method_exists($modelClass, 'where')) {
                            $modelInstance = $modelClass::where('slug', $param)->first();
                            if ($modelInstance) {
                                return $modelInstance;
                            }
                        }
                    }
                }
            }

            // For index methods, return the first instance or create a dummy
            if ($route->getActionMethod() === 'index') {
                return $modelClass::first() ?? new $modelClass;
            }
        } catch (\Exception $e) {
            Log::error("Error getting model instance: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Check if model uses HasAnalytics trait
     */
    private function modelUsesAnalyticsTrait(string $modelClass): bool
    {
        try {
            $traits = class_uses($modelClass);
            return $traits && in_array(\Epaisay\Analytics\Traits\HasAnalytics::class, $traits);
        } catch (\Exception $e) {
            Log::error("Error checking traits: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if action should be tracked
     */
    private function shouldTrackAction(string $modelClass, string $action): bool
    {
        try {
            $reflection = new \ReflectionClass($modelClass);

            if ($reflection->hasMethod('getTrackedActions')) {
                $method = $reflection->getMethod('getTrackedActions');
                $trackedActions = $method->invoke(null);
                return in_array($action, $trackedActions);
            }
        } catch (\Exception $e) {
            Log::error("Error getting tracked actions: " . $e->getMessage());
        }

        // Fallback to default tracked actions from config
        return in_array($action, config('analytics.tracked_actions', []));
    }
}