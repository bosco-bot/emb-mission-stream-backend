<?php

namespace HorizonsPlus\CollectorLaravel\Http\Controllers;

use HorizonsPlus\CollectorLaravel\Events\DigestReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DigestWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (($payload['event'] ?? null) !== 'digest.sent' || ! isset($payload['digest'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 422);
        }

        event(new DigestReceived($payload));

        return response()->json(['status' => 'ok']);
    }
}
