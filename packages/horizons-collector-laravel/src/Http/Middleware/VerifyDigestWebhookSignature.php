<?php

namespace HorizonsPlus\CollectorLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDigestWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('horizons-collector.digest_webhook_secret');

        if (! $secret) {
            return response()->json(['status' => 'error', 'message' => 'Webhook secret not configured'], 503);
        }

        $signature = (string) $request->header('X-Horizons-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
