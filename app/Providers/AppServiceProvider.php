<?php

namespace App\Providers;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The dashboard compliance widget links to employee_compliance.index.
        // On installs where the compliance module routes are missing (for
        // example after a partial update replaced routes/web.php), rendering
        // the dashboard throws RouteNotFoundException and blocks login. The
        // safety-net route must be registered after all route files have
        // loaded, hence the booted callback.
        $this->app->booted(function () {
            // Resolving the URL generator binds the 'routes' container
            // instance, so the rebinding below fires when a compiled route
            // cache replaces the collection after this callback has run.
            $this->app->make('url');

            $this->registerEmployeeComplianceFallbackRoute();

            $this->app->rebinding('routes', function () {
                $this->registerEmployeeComplianceFallbackRoute();
            });
        });
    }

    protected function registerEmployeeComplianceFallbackRoute(): void
    {
        $routes = Route::getRoutes();

        // The collection's name lookup table can be stale at this point, so
        // inspect each route's name directly.
        foreach ($routes as $route) {
            if ($route->getName() === 'employee_compliance.index') {
                return;
            }
        }

        // The name must be part of the action array so it is indexed when the
        // route is added; a fluent ->name() call afterwards is not picked up
        // when a compiled route cache is active, because
        // CompiledRouteCollection::refreshNameLookups() is a no-op.
        Route::get('/employee-compliance', [
            'as' => 'employee_compliance.index',
            'middleware' => [
                'web',
                EnsureInstalled::class,
                'auth',
                ForcePasswordChange::class,
                CheckPermission::class . ':employee_compliance.view',
            ],
            'uses' => \App\Http\Controllers\EmployeeComplianceFallbackController::class . '@index',
        ]);
    }
}
