<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ModuleFallbackController extends Controller
{
    /**
     * Candidate controllers per module, tried in order when a safety-net
     * route is hit. Module files come from overlay update packages and may
     * or may not be installed on a given server, so dispatch has to be
     * resolved at request time.
     */
    protected array $modules = [
        'employee_compliance' => [
            'title' => 'Employee Compliance',
            'controllers' => [
                'App\\Http\\Controllers\\EmployeeComplianceController',
            ],
        ],
        'platform_updates' => [
            'title' => 'Platform Updates',
            'controllers' => [
                'App\\Http\\Controllers\\PlatformUpdateController',
                'App\\Http\\Controllers\\PlatformUpdatesController',
                'App\\Http\\Controllers\\SystemUpdateController',
                'App\\Http\\Controllers\\UpdateManagerController',
            ],
        ],
    ];

    public function index(Request $request, ?string $fallbackModule = null)
    {
        $module = $this->modules[$fallbackModule] ?? null;

        foreach ($module['controllers'] ?? [] as $class) {
            if (class_exists($class) && method_exists($class, 'index')) {
                return app()->call([app($class), 'index']);
            }
        }

        return view('system.module_unavailable', [
            'moduleTitle' => $module['title'] ?? 'Module',
            'missingRoute' => null,
        ]);
    }

    public function notice(Request $request)
    {
        return view('system.module_unavailable', [
            'moduleTitle' => 'Module',
            'missingRoute' => $request->query('missing_route'),
        ]);
    }
}
