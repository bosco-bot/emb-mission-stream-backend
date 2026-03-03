<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class RadioStreamController extends Controller
{
    private string $baseUrl;
    private string $apiKey;
    private string $stationId;

    public function __construct()
    {
        // Validation des variables d'environnement
        $this->baseUrl = rtrim(env('AZURACAST_BASE_URL', 'http://localhost:8080'), '/');
        $this->apiKey = env('AZURACAST_API_KEY', '');
        $this->stationId = env('AZURACAST_STATION_ID', '1');

        if (empty($this->apiKey)) {
            Log::error('AZURACAST_API_KEY non configurée');
        }
    }

    /**
     * Obtenir les informations de diffusion pour Flutter Web
     * Retourne: Port, Point de montage, Lien M3U
     */
    public function getBroadcastInfo(): JsonResponse
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey
            ])->timeout(10)->get("{$this->baseUrl}/api/station/{$this->stationId}");

            if ($response->successful()) {
                $stationData = $response->json();
                
                // Extraction sécurisée des informations de diffusion
                $mounts = $stationData['mounts'] ?? [];
                $firstMount = $mounts[0] ?? [];
                
                // URLs publiques via Nginx (au lieu des URLs AzuraCast internes)
                $broadcastInfo = [
                    'port' => 8005,  // Port en dur
                    'mount_point' => '/',  // Mount point en dur
                    'm3u_url' => 'https://radio.embmission.com/playlist.m3u',
                    'listen_url' => 'https://radio.embmission.com/listen',
                    'public_player_url' => 'https://radio.embmission.com/player',
                    'stream_url' => 'https://radio.embmission.com/stream',
                    'bitrate' => $firstMount['bitrate'] ?? 192,
                    'format' => $firstMount['format'] ?? 'mp3',
                    'station_name' => $stationData['name'] ?? 'Radio EMB Mission'
                ];

                return response()->json([
                    'success' => true,
                    'message' => 'Informations de diffusion récupérées avec succès',
                    'data' => $broadcastInfo
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des informations de diffusion',
                'error' => $response->body(),
                'status_code' => $response->status()
            ], $response->status());

        } catch (Exception $e) {
            Log::error('Erreur getBroadcastInfo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur de connexion au serveur AzuraCast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les informations complètes de la station
     */
    public function getStationInfo(): JsonResponse
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey
            ])->timeout(10)->get("{$this->baseUrl}/api/station/{$this->stationId}");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Informations de la station récupérées avec succès',
                    'data' => $response->json()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des informations de la station',
                'error' => $response->body(),
                'status_code' => $response->status()
            ], $response->status());

        } catch (Exception $e) {
            Log::error('Erreur getStationInfo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur de connexion au serveur AzuraCast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir le statut de la station
     */
    public function getStationStatus(): JsonResponse
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey
            ])->timeout(10)->get("{$this->baseUrl}/api/station/{$this->stationId}/status");

            if ($response->successful()) {
                $statusData = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => 'Statut de la station récupéré avec succès',
                    'data' => $statusData
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du statut',
                'error' => $response->body(),
                'status_code' => $response->status()
            ], $response->status());

        } catch (Exception $e) {
            Log::error('Erreur getStationStatus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur de connexion au serveur AzuraCast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Contrôler la diffusion
     */
    public function controlStream(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|in:play,stop,restart,next'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $action = $request->input('action');
            
            // Mapping des actions vers les endpoints AzuraCast
            $endpointMap = [
                'play' => 'start',
                'stop' => 'stop', 
                'restart' => 'restart',
                'next' => 'nextsong'
            ];

            $endpoint = $endpointMap[$action] ?? $action;

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey
            ])->timeout(15)->post("{$this->baseUrl}/api/station/{$this->stationId}/backend/{$endpoint}");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => "Action '{$action}' exécutée avec succès",
                    'data' => $response->json()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "Erreur lors de l'exécution de l'action '{$action}'",
                'error' => $response->body(),
                'status_code' => $response->status()
            ], $response->status());

        } catch (Exception $e) {
            Log::error('Erreur controlStream: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur de connexion au serveur AzuraCast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les auditeurs en temps réel
     */
    public function getListeners(): JsonResponse
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey
            ])->timeout(10)->get("{$this->baseUrl}/api/station/{$this->stationId}/listeners");

            if ($response->successful()) {
                $listenersData = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => 'Données des auditeurs récupérées avec succès',
                    'data' => $listenersData
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des auditeurs',
                'error' => $response->body(),
                'status_code' => $response->status()
            ], $response->status());

        } catch (Exception $e) {
            Log::error('Erreur getListeners: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur de connexion au serveur AzuraCast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir la piste actuellement en lecture
     */
    public function getCurrentTrack(): JsonResponse
    {
        // Données par défaut à retourner en cas d'erreur
        $defaultData = [
            "title" => "Titre inconnu",
            "artist" => "",
            "artwork_url" => null,
            "duration" => 0,
            "elapsed" => 0,
            "remaining" => 0,
            "is_live" => false,
            "listeners" => ["current" => 0, "unique" => 0],
            "station_name" => "Station Radio"
        ];

        try {
            $response = Http::withHeaders([
                "X-API-Key" => $this->apiKey
            ])->timeout(5)->get("{$this->baseUrl}/api/nowplaying/{$this->stationId}"); // Timeout réduit à 5s

            if ($response->successful()) {
                $data = $response->json();
                
                // Extraction sécurisée des données now playing
                $nowPlaying = $data["now_playing"] ?? [];
                $currentSong = $nowPlaying["song"] ?? [];
                $liveInfo = $data["live"] ?? [];
                
                $trackInfo = [
                    "title" => $currentSong["title"] ?? "Titre inconnu",
                    "artist" => $currentSong["artist"] ?? "Artiste inconnu",
                    "artwork_url" => $currentSong["art"] ?? null,
                    "duration" => $currentSong["duration"] ?? 0,
                    "elapsed" => $nowPlaying["elapsed"] ?? 0,
                    "remaining" => $nowPlaying["remaining"] ?? 0,
                    "is_live" => $liveInfo["is_live"] ?? false,
                    "listeners" => $data["listeners"] ?? ["current" => 0, "unique" => 0],
                    "station_name" => $data["station"]["name"] ?? "Station Radio"
                ];

                return response()->json([
                    "success" => true,
                    "message" => "Piste actuelle récupérée avec succès",
                    "data" => $trackInfo
                ]);
            }

            // Si AzuraCast retourne une erreur (ex: en cours de redémarrage)
            // Retourner des données par défaut au lieu d'une erreur 500
            Log::warning('AzuraCast API non disponible (peut-être en cours de redémarrage)', [
                'status' => $response->status(),
                'url' => "{$this->baseUrl}/api/nowplaying/{$this->stationId}"
            ]);

            return response()->json([
                "success" => false,
                "message" => "Titre non disponible (AzuraCast peut être en cours de redémarrage)",
                "data" => $defaultData
            ], 200); // Retourner 200 pour ne pas casser le frontend

        } catch (Exception $e) {
            Log::error('Erreur getCurrentTrack: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Retourner une réponse avec succès=false mais avec des données par défaut
            // pour éviter de casser le frontend
            return response()->json([
                "success" => false,
                "message" => "Titre non disponible",
                "data" => [
                    "title" => "Titre inconnu",
                    "artist" => "",
                    "artwork_url" => null,
                    "duration" => 0,
                    "elapsed" => 0,
                    "remaining" => 0,
                    "is_live" => false,
                    "listeners" => ["current" => 0, "unique" => 0],
                    "station_name" => "Station Radio"
                ]
            ], 200); // Retourner 200 pour ne pas casser le frontend
        }
    }

    /**
     * Vérifier la connexion à AzuraCast
     */
    public function checkConnection(): JsonResponse
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey
            ])->timeout(5)->get("{$this->baseUrl}/api/status");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Connexion AzuraCast établie',
                    'data' => [
                        'connected' => true,
                        'version' => $response->json()['version'] ?? 'Inconnue'
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur de connexion à AzuraCast',
                'data' => ['connected' => false]
            ], 200); // Toujours 200 pour les checks de connexion

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de se connecter à AzuraCast',
                'error' => $e->getMessage(),
                'data' => ['connected' => false]
            ], 200);
        }
    }
}
