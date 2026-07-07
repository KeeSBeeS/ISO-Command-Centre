<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->hasRole('system-administrator')) {
            return $next($request);
        }

        if (!$user->hasPermission($permission)) {
            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
