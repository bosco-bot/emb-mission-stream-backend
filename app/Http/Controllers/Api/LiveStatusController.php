<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebTVAutoPlaylistService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiveStatusController extends Controller
{
    /**
     * Diffuse un flux SSE avec le statut live/VOD en quasi temps réel.
     */
    public function stream(WebTVAutoPlaylistService $autoPlaylistService): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        return response()->stream(function () use ($autoPlaylistService) {
            @set_time_limit(0);

            $lastPayload = null;
            $tick = 0;
            $heartbeatEvery = 10;
            $maxIterations = 900; // ~30 minutes à raison d'un tick / 2s

            while (!connection_aborted() && $tick < $maxIterations) {
                $snapshot = $autoPlaylistService->getRealtimeStatusSnapshot();
                $currentPayload = json_encode($snapshot);

                if ($currentPayload !== $lastPayload) {
                    echo "event: status\n";
                    echo 'data: ' . $currentPayload . "\n\n";
                    $lastPayload = $currentPayload;
                } elseif ($tick % $heartbeatEvery === 0) {
                    $heartbeat = json_encode(['timestamp' => time()]);
                    echo "event: heartbeat\n";
                    echo 'data: ' . $heartbeat . "\n\n";
                }

                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();

                $tick++;
                sleep(2);
            }

            echo "event: end\n";
            echo 'data: {"reason":"stream_timeout"}' . "\n\n";
            if (ob_get_level() > 0) {
                @ob_flush();
            }
            flush();
        }, Response::HTTP_OK, $headers);
    }
}

















