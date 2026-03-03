<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AntMediaService;
use App\Models\WebTVStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebTVController extends Controller
{
    protected AntMediaService $antMediaService;

    public function __construct(AntMediaService $antMediaService)
    {
        $this->antMediaService = $antMediaService;
    }

    public function getCurrentStream(): JsonResponse
    {
        try {
            if (!$this->antMediaService->checkConnection()) {
                return response()->json([
                    "success" => false,
                    "message" => "Impossible de se connecter à Ant Media Server.",
                    "data" => null
                ], 503);
            }

            $mainStream = WebTVStream::main()->first();

            if (!$mainStream) {
                $antMediaStream = $this->antMediaService->getMainWebTVStream();
                
                if ($antMediaStream) {
                    $stream = WebTVStream::firstOrCreate(
                        ["ant_media_stream_id" => $antMediaStream["streamId"]],
                        [
                            "title" => $antMediaStream["name"] ?? "Stream WebTV",
                            "description" => $antMediaStream["description"] ?? "Diffusion en direct",
                            "status" => $antMediaStream["status"] === "broadcasting" ? "live" : "offline",
                        ]
                    );

                    $stream->syncWithAntMedia($antMediaStream);
                    $mainStream = $stream;
                }
            }

            if (!$mainStream) {
                return response()->json([
                    "success" => false,
                    "message" => "Aucun stream WebTV actif trouvé.",
                    "data" => null
                ], 404);
            }

            if ($mainStream->ant_media_stream_id) {
                $currentStatus = $this->antMediaService->getStreamStatus($mainStream->ant_media_stream_id);
                if ($currentStatus !== $mainStream->status) {
                    $mainStream->updateStatus($currentStatus);
                }
            }

            return response()->json([
                "success" => true,
                "message" => "Stream WebTV récupéré avec succès.",
                "data" => $mainStream->toApiArray()
            ]);

        } catch (\Exception $e) {
            Log::error("WebTVController getCurrentStream: " . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de la récupération du stream WebTV.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function checkConnection(): JsonResponse
    {
        try {
            $isConnected = $this->antMediaService->checkConnection();

            return response()->json([
                "success" => true,
                "message" => $isConnected ? "Connexion établie." : "Connexion échouée.",
                "data" => [
                    "connected" => $isConnected,
                    "ant_media_url" => "https://tv.embmission.com"
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("WebTVController checkConnection: " . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de la vérification de la connexion.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function getStreams(Request $request): JsonResponse
    {
        try {
            $query = WebTVStream::query();

            if ($request->has("status")) {
                $query->where("status", $request->input("status"));
            }

            if ($request->has("featured")) {
                $query->where("is_featured", $request->boolean("featured"));
            }

            $perPage = $request->input("per_page", 10);
            $streams = $query->orderBy("updated_at", "desc")->paginate($perPage);

            return response()->json([
                "success" => true,
                "message" => "Streams récupérés avec succès.",
                "data" => $streams->items(),
                "pagination" => [
                    "current_page" => $streams->currentPage(),
                    "per_page" => $streams->perPage(),
                    "total" => $streams->total(),
                    "last_page" => $streams->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("WebTVController getStreams: " . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de la récupération des streams.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function getRecentBroadcasts(Request $request): JsonResponse
    {
        try {
            $limit = $request->input("limit", 3);
            
            // Essayer d'abord la DB
            $streams = WebTVStream::orderBy("created_at", "desc")
                ->limit($limit)
                ->get();
            
            // Si la DB est vide, récupérer depuis Ant Media
            if ($streams->isEmpty()) {
                try {
                    $response = \Illuminate\Support\Facades\Http::withBasicAuth(
                        config('app.webtv_stream_username'),
                        config('app.webtv_stream_password')
                    )->get('http://localhost:5080/LiveApp/rest/v2/broadcasts/list/0/20');
                    
                    if ($response->successful()) {
                        $antMediaStreams = $response->json();
                        $streams = collect($antMediaStreams)
                            ->filter(function ($stream) {
                                // Filtrer uniquement les streams réels (pas les playlists)
                                return isset($stream['type']) 
                                    && in_array($stream['type'], ['live', 'liveStream'])
                                    && isset($stream['status'])
                                    && $stream['status'] === 'broadcasting';
                            })
                            ->take($limit)
                            ->map(function ($stream) {
                                return [
                                    "id" => $stream['streamId'] ?? '',
                                    "title" => $stream['name'] ?? 'Stream WebTV',
                                    "status" => "live",
                                    "icon" => "record",
                                    "time_ago" => $this->calculateTimeAgo($stream['startTime'] ?? 0),
                                    "start_time" => isset($stream['startTime']) && $stream['startTime'] > 0 
                                        ? \Carbon\Carbon::createFromTimestampMs($stream['startTime'])->toISOString()
                                        : null,
                                    "thumbnail_url" => null,
                                    "viewer_count" => $stream['hlsViewerCount'] ?? 0,
                                    "duration" => $this->formatDuration($stream['duration'] ?? 0),
                                ];
                            });
                    }
                } catch (\Exception $e) {
                    Log::warning("Erreur récupération Ant Media pour recent-broadcasts: " . $e->getMessage());
                }
            } else {
                // Mapper depuis la DB
                $streams = $streams->map(function ($stream) {
                    return [
                        "id" => $stream->id,
                        "title" => $stream->title,
                        "status" => $stream->status,
                        "icon" => $this->getStatusIcon($stream->status),
                        "time_ago" => $stream->created_at->diffForHumans(),
                        "start_time" => $stream->start_time?->toISOString(),
                        "thumbnail_url" => $stream->thumbnail_url,
                    ];
                });
            }

            return response()->json([
                "success" => true,
                "data" => $streams->values()->all(),
                "message" => "Diffusions récentes récupérées avec succès"
            ]);

        } catch (\Exception $e) {
            Log::error("WebTVController getRecentBroadcasts: " . $e->getMessage());

            return response()->json([
                "success" => false,
                "message" => "Erreur lors de la récupération des diffusions récentes",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    private function getStatusIcon(string $status): string
    {
        return match($status) {
            "live" => "record",
            "finished" => "play",
            "scheduled" => "calendar",
            default => "play"
        };
    }

    private function calculateTimeAgo(int $timestampMs): string
    {
        if ($timestampMs === 0) {
            return "À l'instant";
        }
        
        try {
            $startTime = \Carbon\Carbon::createFromTimestampMs($timestampMs);
            return $startTime->diffForHumans();
        } catch (\Exception $e) {
            return "Il y a quelques instants";
        }
    }

    private function formatDuration(int $durationMs): string
    {
        if ($durationMs === 0) {
            return "En direct";
        }
        
        $seconds = intval($durationMs / 1000);
        $hours = intval($seconds / 3600);
        $minutes = intval(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf("%dh %dm", $hours, $minutes);
        } elseif ($minutes > 0) {
            return sprintf("%dm %ds", $minutes, $secs);
        } else {
            return sprintf("%ds", $secs);
        }
    }
    
}
