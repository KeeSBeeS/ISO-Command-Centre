<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user
            && Schema::hasColumn('users', 'must_change_password')
            && $user->must_change_password
            && !$request->routeIs('password.*')
            && !$request->routeIs('logout')) {
            return redirect()
                ->route('password.edit')
                ->with('warning', 'You must change your temporary password before using ISO Admin.');
        }

        return $next($request);
    }
}
