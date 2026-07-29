<?php

namespace HorizonsPlus\CollectorLaravel\Http\Controllers;

use HorizonsPlus\CollectorLaravel\Events\DigestCancelled;
use HorizonsPlus\CollectorLaravel\Events\DigestReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DigestWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        $event = $payload['event'] ?? null;

        if (! isset($payload['digest']['id'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 422);
        }

        if ($event === 'digest.sent') {
            event(new DigestReceived($payload));

            return response()->json(['status' => 'ok']);
        }

        if ($event === 'digest.cancelled') {
            event(new DigestCancelled($payload));

            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 422);
    }
}
