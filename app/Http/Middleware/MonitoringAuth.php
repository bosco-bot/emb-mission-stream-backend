<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MonitoringAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('monitoring_authenticated') === true) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('system-monitoring/status')) {
            return response()->json([
                'success' => false,
                'error' => 'Non authentifié',
            ], 401);
        }

        return redirect()->route('system-monitoring.login');
    }
}
