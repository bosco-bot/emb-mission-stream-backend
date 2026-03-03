<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Contrôleur WebRadio - Proxy vers AzuraCast
 * 
 * Ce contrôleur fait office de proxy entre le Flutter Web (HTTPS)
 * et AzuraCast (HTTP) pour résoudre les problèmes de CORS et Mixed Content.
 */
class WebRadioController extends Controller
{
    private string $azuracastBaseUrl;
    private string $azuracastApiKey;
    private string $azuracastStationId;

    public function __construct()
    {
        $this->azuracastBaseUrl = rtrim(env('AZURACAST_BASE_URL', 'http://15.235.86.98:8080'), '/');
        $this->azuracastApiKey = env('AZURACAST_API_KEY', '');
        $this->azuracastStationId = env('AZURACAST_STATION_ID', '1');
    }

    /**
     * GET /api/webradio/current-stream
     * 
     * Récupère les informations du flux en cours de lecture (nowplaying)
     * Proxy vers AzuraCast: GET /api/nowplaying/{stationId}
     */
    public function getCurrentStream(): JsonResponse
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->azuracastBaseUrl}/api/nowplaying/{$this->azuracastStationId}");

            if ($response->successful()) {
                $data = $response->json();
                
                // 🔧 CORRECTION: Remplacer l'URL du flux audio
                if (isset($data["station"]["listen_url"])) {
                    $data["station"]["listen_url"] = str_replace(
                        ["https://radio.embmission.com:8000/radio.mp3", "http://15.235.86.98:8000/radio.mp3"],
                        "https://radio.embmission.com/stream",
                        $data["station"]["listen_url"]
                    );
                }
                // Corriger aussi les URLs dans les mounts
                if (isset($data["station"]["mounts"])) {
                    foreach ($data["station"]["mounts"] as &$mount) {
                        if (isset($mount["url"])) {
                            $mount["url"] = str_replace(
                                ["https://radio.embmission.com:8000/radio.mp3", "http://15.235.86.98:8000/radio.mp3"],
                                "https://radio.embmission.com/stream",
                                $mount["url"]
                            );
                        }
                    }
                }
                
                // Extraire les informations pertinentes
                $nowPlaying = $data['now_playing'] ?? null;
                $station = $data['station'] ?? null;
                $listeners = $data['listeners'] ?? [];

                return response()->json([
                    'success' => true,
                    'message' => 'Flux récupéré avec succès',
                    'data' => [
                        'now_playing' => $nowPlaying,
                        'station' => $station,
                        'listeners' => $listeners,
                        'is_online' => $data['is_online'] ?? false,
                        'live' => $data['live'] ?? [],
                    ]
                ]);
            }

            Log::error('Erreur AzuraCast getCurrentStream', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du flux',
                'error' => 'AzuraCast API error'
            ], $response->status());

        } catch (Exception $e) {
            Log::error('Exception getCurrentStream: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur de connexion à AzuraCast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/webradio/track-history
     * 
     * Récupère l'historique des pistes jouées
     * Proxy vers AzuraCast: GET /api/nowplaying/{stationId} (section song_history)
     */
    public function getTrackHistory(): JsonResponse
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->azuracastBaseUrl}/api/nowplaying/{$this->azuracastStationId}");

            if ($response->successful()) {
                $data = $response->json();

                // Extraire l'historique des chansons
                $songHistory = $data['song_history'] ?? [];

                return response()->json([
                    'success' => true,
                    'message' => 'Historique récupéré avec succès',
                    'data' => $songHistory
                ]);
            }

            Log::error('Erreur AzuraCast getTrackHistory', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique',
                'error' => 'AzuraCast API error'
            ], $response->status());

        } catch (Exception $e) {
            Log::error('Exception getTrackHistory: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur de connexion à AzuraCast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/webradio/control
     * 
     * Contrôle la diffusion (start, stop, restart)
     * Proxy vers AzuraCast: POST /api/station/{stationId}/backend/{action}
     */
    public function controlBroadcast(): JsonResponse
    {
        try {
            $action = request()->input('action', 'restart');
            
            // 🔧 CORRECTION: Utiliser les bons endpoints AzuraCast backend
            $endpoint = match($action) {
                'start' => "/api/station/{$this->azuracastStationId}/backend/start",
                'stop' => "/api/station/{$this->azuracastStationId}/backend/stop",
                'restart' => "/api/station/{$this->azuracastStationId}/backend/restart",
                default => "/api/station/{$this->azuracastStationId}/backend/restart"
            };

            $response = Http::timeout(30)
                ->withHeaders(['X-API-Key' => $this->azuracastApiKey])
                ->post("{$this->azuracastBaseUrl}{$endpoint}");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => "Action '$action' effectuée avec succès",
                    'data' => $response->json()
                ]);
            }

            Log::error('Erreur AzuraCast controlBroadcast', [
                'action' => $action,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return response()->json([
                'success' => false,
                'message' => "Erreur lors de l'action '$action'",
                'error' => 'AzuraCast API error'
            ], $response->status());

        } catch (Exception $e) {
            Log::error('Exception controlBroadcast: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur de contrôle de la diffusion',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
