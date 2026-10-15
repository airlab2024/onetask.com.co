<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ExternalLoginEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('oauth/*', 'onetask/oauth/*')) {
            $provider = $request->route('provider');
            abort_unless(is_string($provider) && \App\Support\ExternalLoginConfiguration::social($provider), 404);
        }
        return $next($request);
    }
}
