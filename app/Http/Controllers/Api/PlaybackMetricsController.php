<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PlaybackMetricsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'nullable|string|max:64',
            'started_at' => 'nullable|integer',
            'stalled_count' => 'nullable|integer|min:0',
            'waiting_count' => 'nullable|integer|min:0',
            'freeze_recoveries' => 'nullable|integer|min:0',
            'video_errors' => 'nullable|integer|min:0',
            'hls_errors_fatal' => 'nullable|integer|min:0',
            'hls_errors_non_fatal' => 'nullable|integer|min:0',
            'total_buffering_ms' => 'nullable|integer|min:0',
            'player_type' => 'nullable|string|max:32',
            'user_agent' => 'nullable|string|max:512',
            'platform' => 'nullable|string|max:32',
            'mode' => 'nullable|string|max:16',
            'current_url' => 'nullable|string|max:512',
            'flush' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Invalid payload'], 422);
        }

        $data = $validator->validated();
        $data['ip'] = $request->ip();
        $data['received_at'] = now()->toIso8601String();

        Log::channel('playback_metrics')->info('playback_metrics', $data);

        return response()->json(['success' => true], 200)
            ->header('Cache-Control', 'no-store');
    }
}
