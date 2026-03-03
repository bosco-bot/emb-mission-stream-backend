<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebTVAutoPlaylistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WatchController extends Controller
{
    private WebTVAutoPlaylistService $playlist;

    public function __construct(WebTVAutoPlaylistService $playlist)
    {
        $this->playlist = $playlist;
    }

    public function playback(Request $request): JsonResponse
    {
        if (filter_var($request->query('initial') ?? $request->query('fresh'), FILTER_VALIDATE_BOOLEAN)) {
            Cache::forget('live_status_check');
            Cache::forget('live_status_last_confirmed');
            $this->playlist->checkLiveStatus();
        }
        $data = $this->playlist->getCurrentPlaybackUrl(
            filter_var($request->query('emit_event'), FILTER_VALIDATE_BOOLEAN),
            $request->query('snapshot_source') ?? $request->query('source') ?? 'watch'
        );
        $ok = $data['success'] ?? true;
        return response()->json([
            'success' => $ok,
            'message' => $data['message'] ?? 'ok',
            'data' => $data,
        ], $ok ? 200 : 400)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function config(): JsonResponse
    {
        $base = rtrim(config('app.url', 'https://tv.embmission.com'), '/');
        return response()->json([
            'success' => true,
            'data' => [
                'playback_url' => $base . '/api/watch/playback',
                'sse_url' => $base . '/api/watch/stream',
                'ws_channel' => 'webtv-stream-status',
                'unified_stream_url' => $base . '/hls/streams/unified.m3u8',
            ],
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
