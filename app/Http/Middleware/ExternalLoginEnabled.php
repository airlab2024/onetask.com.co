<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ExternalLoginEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('oauth/*', 'onetask/oauth/*')) {
            abort_unless(config('filament-socialite.enabled'), 404);
        }
        return $next($request);
    }
}
