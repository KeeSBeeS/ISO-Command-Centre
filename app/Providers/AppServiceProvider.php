<?php

namespace App\Providers;

use App\Http\Controllers\ModuleFallbackController;
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
        // Views from overlay update packages (dashboard compliance widget,
        // system settings navigation, ...) link to named routes that are not
        // always present in routes/web.php, because a changed-files-only
        // update can replace the routes file with a version built without
        // those modules. A missing route name throws RouteNotFoundException
        // while rendering, which blocks login. Safety-net routes for known
        // module pages are registered after all route files have loaded, and
        // a resolver turns any other missing name into a link to a notice
        // page instead of an exception.
        $this->app->booted(function () {
            // Resolving the URL generator binds the 'routes' container
            // instance, so the rebinding below fires when a compiled route
            // cache replaces the collection after this callback has run.
            $url = $this->app->make('url');

            $this->registerModuleFallbackRoutes();

            $this->app->rebinding('routes', function () {
                $this->registerModuleFallbackRoutes();
            });

            if (method_exists($url, 'resolveMissingNamedRoutesUsing')) {
                $url->resolveMissingNamedRoutesUsing(
                    fn ($name) => $url->to('/module-unavailable') . '?missing_route=' . urlencode($name)
                );
            }
        });
    }

    protected function registerModuleFallbackRoutes(): void
    {
        $this->registerFallbackRoute('employee_compliance.index', '/employee-compliance', 'employee_compliance', 'employee_compliance.view');
        $this->registerFallbackRoute('platform_updates.index', '/platform-updates', 'platform_updates', 'platform_updates.view');
        $this->registerFallbackRoute('module_unavailable.notice', '/module-unavailable', null, null, 'notice');
    }

    protected function registerFallbackRoute(string $name, string $uri, ?string $module, ?string $permission, string $action = 'index'): void
    {
        // The collection's name lookup table can be stale at this point, so
        // inspect each route's name directly.
        foreach (Route::getRoutes() as $route) {
            if ($route->getName() === $name) {
                return;
            }
        }

        $middleware = ['web', EnsureInstalled::class, 'auth', ForcePasswordChange::class];

        if ($permission !== null) {
            $middleware[] = CheckPermission::class . ':' . $permission;
        }

        // The name must be part of the action array so it is indexed when the
        // route is added; a fluent ->name() call afterwards is not picked up
        // when a compiled route cache is active, because
        // CompiledRouteCollection::refreshNameLookups() is a no-op.
        $route = Route::get($uri, [
            'as' => $name,
            'middleware' => $middleware,
            'uses' => ModuleFallbackController::class . '@' . $action,
        ]);

        if ($module !== null) {
            $route->defaults('fallbackModule', $module);
        }
    }
}
