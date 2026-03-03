<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebTVAutoPlaylistService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WatchStreamController extends Controller
{
    private const TICK_INTERVAL = 1;
    private const HEARTBEAT_EVERY = 15;
    private const MAX_ITERATIONS = 1800;

    private WebTVAutoPlaylistService $playlist;

    public function __construct(WebTVAutoPlaylistService $playlist)
    {
        $this->playlist = $playlist;
    }

    public function stream(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        return response()->stream(function () {
            @set_time_limit(0);
            $lastPayload = null;
            $tick = 0;

            while (!connection_aborted() && $tick < self::MAX_ITERATIONS) {
                $snapshot = $this->playlist->getRealtimeStatusSnapshot();
                $currentPayload = json_encode($snapshot);

                if ($currentPayload !== $lastPayload) {
                    echo "event: status\n";
                    echo 'data: ' . $currentPayload . "\n\n";
                    $lastPayload = $currentPayload;
                } elseif ($tick % self::HEARTBEAT_EVERY === 0) {
                    echo "event: heartbeat\n";
                    echo 'data: ' . json_encode(['t' => time()]) . "\n\n";
                }

                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
                $tick++;
                sleep(self::TICK_INTERVAL);
            }

            echo "event: end\n";
            echo 'data: ' . json_encode(['reason' => 'stream_timeout']) . "\n\n";
            if (ob_get_level() > 0) {
                @ob_flush();
            }
            flush();
        }, Response::HTTP_OK, $headers);
    }
}
