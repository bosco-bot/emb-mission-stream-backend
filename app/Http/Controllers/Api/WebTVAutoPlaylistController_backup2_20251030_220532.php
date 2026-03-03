<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebTVPlaylist;
use App\Services\WebTVAutoPlaylistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebTVAutoPlaylistController extends Controller
{
    private WebTVAutoPlaylistService $autoPlaylistService;
    
    public function __construct()
    {
        $this->autoPlaylistService = new WebTVAutoPlaylistService();
    }
    
    /**
     * Démarrer la playlist automatique pour une playlist WebTV
     */
    public function startAutoPlaylist(Request $request, WebTVPlaylist $webTVPlaylist): JsonResponse
    {
        try {
            $result = $this->autoPlaylistService->startAutoPlaylist($webTVPlaylist);
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result
            ], $result['success'] ? 200 : 400);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du démarrage de la playlist automatique: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Surveiller et gérer le basculement automatique
     */
    public function monitorAutoSwitch(): JsonResponse
    {
        try {
            $result = $this->autoPlaylistService->monitorAutoSwitch();
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result
            ], $result['success'] ? 200 : 400);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la surveillance du basculement: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtenir le statut actuel de la playlist automatique
     */
    public function getAutoPlaylistStatus(): JsonResponse
    {
        try {
            $result = $this->autoPlaylistService->getAutoPlaylistStatus();
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result
            ], $result['success'] ? 200 : 400);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du statut: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtenir l'URL de lecture actuelle (live ou VoD)
     */
    public function getCurrentPlaybackUrl(): JsonResponse
    {
        try {
            $result = $this->autoPlaylistService->getCurrentPlaybackUrl();
            
            // ✅ CORRECTION: Éviter double imbrication des données
            if (isset($result['data'])) {
                return response()->json($result, $result['success'] ? 200 : 400);
            }
            
            // Structure plate
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result
            ], $result['success'] ? 200 : 400);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'URL de lecture: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Arrêter la diffusion (live ET playlist)
     */
    public function stopAutoPlaylist(): JsonResponse
    {
        try {
            Log::info("🛑 Arrêt de la diffusion demandé");
            
            // Version simplifiée - pas d'appel à checkLiveStatus pour éviter l'erreur
            // Mettre le système en pause directement
            $pauseResult = $this->autoPlaylistService->pauseSystem();
            
            if ($pauseResult['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Diffusion arrêtée avec succès - Système en pause',
                    'status' => 'paused'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de la mise en pause: ' . $pauseResult['message']
                ], 500);
            }
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur arrêt diffusion: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'arrêt de la diffusion: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reprendre la diffusion (système automatique)
     */
    public function resumeAutoPlaylist(): JsonResponse
    {
        try {
            Log::info("▶️ Reprise de la diffusion demandée");
            
            // Reprendre le système automatique
            $this->autoPlaylistService->resumeSystem();
            
            // Vérifier le statut après reprise
            $status = $this->autoPlaylistService->getAutoPlaylistStatus();
            
            return response()->json([
                'success' => true,
                'message' => 'Diffusion reprise avec succès - Système automatique activé',
                'status' => 'resumed',
                'current_mode' => $status['data']['mode'] ?? 'unknown'
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur reprise diffusion: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la reprise de la diffusion: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les paramètres de connexion OBS
     */
    public function getOBSConnectionParams(): JsonResponse
    {
        try {
            Log::info("📡 Récupération des paramètres de connexion OBS");
            
            $obsParams = [
                'success' => true,
                'message' => 'Paramètres de connexion OBS récupérés avec succès',
                'data' => [
                    'server_url' => 'rtmp://15.235.86.98:1935/LiveApp',
                    'stream_key' => 'R9rzvVzMPvCClU6s1562847982308692',
                    'username' => 'obs_user',
                    'password' => 'EmbMission!Secure#789',
                    'server_name' => 'Ant Media Server',
                    'protocol' => 'RTMP',
                    'port' => 1935,
                    'application' => 'LiveApp',
                    'authentication_required' => true,
                    'bitrate_recommended' => '2500-3000 kbps',
                    'resolution_recommended' => '1280x720',
                    'fps_recommended' => '30',
                    'encoder_recommended' => 'x264 (software) ou NVENC (hardware)',
                    'keyframe_interval' => '2 seconds',
                    'audio_bitrate' => '128 kbps',
                    'video_codec' => 'H.264',
                    'audio_codec' => 'AAC',
                    'profile' => 'main',
                    'preset' => 'veryfast',
                    'tune' => 'zerolatency',
                    'instructions' => [
                        '1. Ouvrir OBS Studio',
                        '2. Aller dans Paramètres > Diffusion',
                        '3. Sélectionner "Service personnalisé"',
                        '4. URL du serveur: rtmp://15.235.86.98:1935/LiveApp',
                        '5. Clé de diffusion: R9rzvVzMPvCClU6s1562847982308692',
                        '6. Nom d\'utilisateur: obs_user',
                        '7. Mot de passe: EmbMission!Secure#789',
                        '8. Cliquer sur "OK" puis "Démarrer la diffusion"'
                    ]
                ]
            ];
            
            return response()->json($obsParams);
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur paramètres OBS: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres OBS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir l'URL du flux public M3U8 (version finale)
     */
    public function getPublicStreamUrl(): JsonResponse
    {
        try {
            Log::info("📡 Récupération de l'URL du flux public M3U8");
            
            // Étape 1: Vérifier le statut général
            $status = $this->autoPlaylistService->getAutoPlaylistStatus();
            
            // Vérification de sécurité - la structure est directement dans $status
            if (!isset($status['mode'])) {
                Log::error("❌ Structure de statut invalide", ['status' => $status]);
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de structure de données',
                    'data' => ['url' => null, 'mode' => 'error']
                ], 500);
            }
            
            if ($status['mode'] === 'paused') {
                return response()->json([
                    'success' => false,
                    'message' => 'Système en pause - Aucun flux disponible',
                    'data' => [
                        'url' => null,
                        'mode' => 'paused',
                        'format' => null,
                        'protocol' => null
                    ]
                ]);
            }
            
            if ($status['mode'] === 'live') {
                // Mode live - utiliser les données du statut
                $streamId = $status['live_stream'] ?? null;
                
                if (!$streamId) {
                    Log::error("❌ Stream ID manquant pour le live", ['status' => $status]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Stream ID manquant pour le live',
                        'data' => ['url' => null, 'mode' => 'error']
                    ], 500);
                }
                
                $m3u8Url = "https://tv.embmission.com/webtv-live/streams/" . $streamId . ".m3u8";
                
                return response()->json([
                    'success' => true,
                    'message' => 'Flux live disponible',
                    'data' => [
                        'url' => $m3u8Url,
                        'mode' => 'live',
                        'stream_id' => $streamId,
                        'format' => 'HLS (M3U8)',
                        'protocol' => 'HTTPS',
                        'player_url' => "https://tv.embmission.com/webtv-live/play.html?name=" . $streamId,
                        'iframe_url' => "https://tv.embmission.com/webtv-live/play.html?name=" . $streamId
                    ]
                ]);
            }
            
            // Mode VoD - utiliser l'API current-url existante
            $currentUrl = $this->autoPlaylistService->getCurrentPlaybackUrl();

            // Vérification de sécurité pour VoD
            if (!$currentUrl['success'] || !isset($currentUrl['url'])) {
                Log::error("❌ URL VoD manquante", ['currentUrl' => $currentUrl]);
                return response()->json([
                    'success' => false,
                    'message' => 'URL VoD manquante',
                    'data' => ['url' => null, 'mode' => 'error']
                ], 500);
            }

            // Convertir l'URL HTTP en HTTPS sécurisée
            $vodUrl = $currentUrl['url'];
            // Remplacer http://15.235.86.98:5080/LiveApp/streams/ par https://tv.embmission.com/webtv-live/streams/
            $secureUrl = str_replace('http://15.235.86.98:5080/LiveApp/streams/', 'https://tv.embmission.com/webtv-live/streams/', $vodUrl);

            return response()->json([
                'success' => true,
                'message' => 'Flux VoD disponible',
                'data' => [
                    'url' => $secureUrl,
                    'mode' => 'vod',
                    'format' => 'MP4 Direct',
                    'protocol' => 'HTTPS',
                    'player_url' => $secureUrl,
                    'iframe_url' => $secureUrl
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur récupération URL flux public: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'URL du flux: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir le prochain élément VoD
     */
    public function getNextVodItem(): JsonResponse
    {
        try {
            $result = $this->autoPlaylistService->getNextVodItem();
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur récupération prochain VoD: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du prochain VoD: ' . $e->getMessage()
            ], 500);
        }
    }

}
