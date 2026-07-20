<?php

namespace App\Http\Controllers;

class EmployeeComplianceFallbackController extends Controller
{
    /**
     * Safety-net handler for employee_compliance.index. Defers to the v2.8.0
     * compliance module controller when its file is installed, otherwise
     * shows a notice page instead of an error.
     */
    public function index()
    {
        $controller = 'App\\Http\\Controllers\\EmployeeComplianceController';

        if (class_exists($controller) && method_exists($controller, 'index')) {
            return app()->call([app($controller), 'index']);
        }

        return view('employee_compliance.fallback');
    }
}
