<?php

namespace HorizonsPlus\CollectorLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMonitoringToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('horizons-collector.token');

        if (! $token || $request->bearerToken() !== $token) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
