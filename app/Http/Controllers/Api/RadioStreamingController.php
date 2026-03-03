<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class RadioStreamingController extends Controller
{
    private string $baseUrl;
    private string $apiKey;
    private string $stationId;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('AZURACAST_BASE_URL', 'http://localhost:8080'), '/');
        $this->apiKey = env('AZURACAST_API_KEY', '');
        $this->stationId = env('AZURACAST_STATION_ID', '1');
    }

    /**
     * Obtenir les paramètres de streaming pour la configuration des logiciels
     */
    public function getStreamingSettings(): JsonResponse
    {
        try {
            // Valeurs par défaut
            $defaults = [
                'server_address' => 'radio.embmission.com',
                'port' => 8005,
                'mount_point' => '/',
                'username' => 'dj_mixxx',
                'password' => 'DjMixxx2025!Radio',
                'station_name' => 'Radio EMB Mission',
                'description' => 'WebRadio EMB Mission',
                'bitrate' => 192,
                'format' => 'mp3',
                'frontend' => 'icecast',
                'backend' => 'liquidsoap',
                'm3u_url' => 'https://radio.embmission.com/playlist.m3u',
                'listen_url' => 'https://radio.embmission.com/listen',
                'stream_url' => 'https://radio.embmission.com/stream',
                'public_player_url' => 'https://radio.embmission.com/player'
            ];

            // Récupérer les données depuis AzuraCast
            $azuracastData = null;
            try {
                $response = Http::withHeaders([
                    'X-API-Key' => $this->apiKey
                ])->timeout(10)->get("{$this->baseUrl}/api/station/{$this->stationId}");

                if ($response->successful()) {
                    $azuracastData = $response->json();
                    Log::info('Données AzuraCast récupérées avec succès');
                } else {
                    Log::warning('Erreur AzuraCast API: ' . $response->status() . ' - ' . $response->body());
                }
            } catch (Exception $e) {
                Log::warning('Impossible de récupérer les données AzuraCast: ' . $e->getMessage());
            }

            // Construire les paramètres de streaming
            $streamingSettings = $defaults;

            if ($azuracastData) {
                // Informations de la station
                $streamingSettings['station_name'] = $azuracastData["name"] ?? $defaults['station_name'];
                $streamingSettings['description'] = $azuracastData["description"] ?? $defaults['description'];
                $streamingSettings['frontend'] = $azuracastData["frontend"] ?? $defaults['frontend'];
                $streamingSettings['backend'] = $azuracastData["backend"] ?? $defaults['backend'];

                // URLs publiques (on garde les URLs HTTPS publiques)
                if (isset($azuracastData["playlist_m3u_url"])) {
                    $azuracastM3U = $azuracastData["playlist_m3u_url"];
                    // Remplacer l'URL AzuraCast par notre URL publique simplifiée
                    $streamingSettings['m3u_url'] = 'https://radio.embmission.com/playlist.m3u';
                }
                if (isset($azuracastData["public_player_url"])) {
                    $azuracastPlayer = $azuracastData["public_player_url"];
                    // Remplacer l'URL AzuraCast par notre URL publique simplifiée
                    $streamingSettings['public_player_url'] = 'https://radio.embmission.com/player';
                }

                // Informations des mounts
                if (isset($azuracastData["mounts"]) && count($azuracastData["mounts"]) > 0) {
                    $defaultMount = null;
                    foreach ($azuracastData["mounts"] as $mount) {
                        if (isset($mount["is_default"]) && $mount["is_default"]) {
                            $defaultMount = $mount;
                            break;
                        }
                    }
                    if (!$defaultMount) {
                        $defaultMount = $azuracastData["mounts"][0];
                    }

                    if ($defaultMount) {
                        $streamingSettings['mount_point'] = $defaultMount["path"] ?? $defaults['mount_point'];
                        $streamingSettings['bitrate'] = $defaultMount["bitrate"] ?? $defaults['bitrate'];
                        $streamingSettings['format'] = $defaultMount["format"] ?? $defaults['format'];
                    }
                }

                // Statut
                $streamingSettings['is_streaming'] = $azuracastData["is_streaming"] ?? false;
                $streamingSettings['is_public'] = $azuracastData["is_public"] ?? true;
                $streamingSettings['requests_enabled'] = $azuracastData["requests_enabled"] ?? false;
            }

            // Ajouter des métadonnées
            $streamingSettings['last_updated'] = now()->toISOString();
            $streamingSettings['source'] = $azuracastData ? 'azuracast_api' : 'defaults';
            $streamingSettings['azuracast_connected'] = $azuracastData !== null;
            // Ajouter les paramètres spécifiques pour Mixxx (Live DJ)
            $streamingSettings['dj_settings'] = [
                'server_address' => 'radio.embmission.com',
                'port' => 8005,
                'mount_point' => '/',
                'username' => 'dj_mixxx',
                'password' => 'DjMixxx2025!Radio',
                'type' => 'Icecast 2',
                'description' => 'Paramètres pour la diffusion live via Mixxx ou autre logiciel DJ'
            ];

            return response()->json([
                'success' => true,
                'message' => $azuracastData ? 'Paramètres récupérés depuis AzuraCast avec succès' : 'Paramètres par défaut (AzuraCast non accessible)',
                'data' => $streamingSettings
            ]);

        } catch (Exception $e) {
            Log::error('Erreur getStreamingSettings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Générer une nouvelle clé de diffusion
     */
    public function generateNewKey(): JsonResponse
    {
        try {
            $newPassword = bin2hex(random_bytes(16));
            
            return response()->json([
                'success' => true,
                'message' => 'Nouvelle clé de diffusion générée avec succès',
                'data' => [
                    'new_password' => $newPassword,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération de la nouvelle clé',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tester la connexion de streaming
     */
    public function testConnection(): JsonResponse
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey
            ])->timeout(5)->get("{$this->baseUrl}/api/status");

            $isConnected = $response->successful();
            
            return response()->json([
                'success' => true,
                'message' => $isConnected ? 'Connexion AzuraCast établie' : 'Impossible de se connecter à AzuraCast',
                'data' => [
                    'connected' => $isConnected,
                    'server_status' => $response->status(),
                    'server_url' => $this->baseUrl
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du test de connexion',
                'error' => $e->getMessage(),
                'data' => ['connected' => false]
            ], 500);
        }
    }
}
