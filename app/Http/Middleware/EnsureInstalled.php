<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('install') || $request->is('install/*')) {
            return $next($request);
        }

        if (!$this->installed()) {
            return redirect()->route('install.index');
        }

        return $next($request);
    }

    private function installed(): bool
    {
        try {
            return file_exists(storage_path('app/isoadmin_installed.lock'))
                && Schema::hasTable('users')
                && Schema::hasTable('roles')
                && Schema::hasTable('permissions');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
