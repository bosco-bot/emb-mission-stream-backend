<?php

namespace App\Services;

use App\Models\WebTVPlaylist;
use App\Models\WebTVPlaylistItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\FFmpegHLSService;

class WebTVAutoPlaylistService
{
    private AntMediaVoDService $vodService;
    private FFmpegHLSService $hlsService;
    
    public function __construct()
    {
        $this->vodService = new AntMediaVoDService();
        $this->hlsService = new FFmpegHLSService();
    }
    
    /**
     * Démarrer la playlist automatique WebTV
     */
    public function startAutoPlaylist(WebTVPlaylist $playlist): array
    {
        try {
            Log::info("🎬 Démarrage de la playlist automatique WebTV", [
                'playlist_id' => $playlist->id,
                'playlist_name' => $playlist->name
            ]);
            
            // Vérifier s'il y a un live en cours
            $liveStatus = $this->checkLiveStatus();
            
            if ($liveStatus['is_live']) {
                // Live en cours - pas de VoD automatiques
                Log::info("📺 Live en cours - VoD automatiques désactivés", [
                    'live_stream' => $liveStatus['stream_id']
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Live en cours - VoD automatiques désactivés',
                    'mode' => 'live',
                    'live_stream' => $liveStatus['stream_id']
                ];
                    } else {
                // Pas de live - démarrer les VoD automatiques
                Log::info("🎥 Pas de live - Démarrage des VoD automatiques");
                
                $vodResult = $this->startVodPlaylist($playlist);
                
                return [
                'success' => true,
                    'message' => 'VoD automatiques démarrés',
                'mode' => 'vod',
                    'vod_result' => $vodResult
                ];
            }
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur démarrage playlist automatique: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors du démarrage de la playlist automatique: ' . $e->getMessage()
            ];
        }
    }
    
    /** TTL du cache live status (évite de surcharger Ant Media et accélère current-url). */
    private const LIVE_STATUS_CACHE_TTL_SECONDS = 2;

    /**
     * Vérifier s'il y a un live en cours (cache 2s + timeout 3s pour éviter lenteur API current-url).
     */
    public function checkLiveStatus(): array
    {
        $cacheKey = 'webtv_live_status';
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && array_key_exists('is_live', $cached)) {
            return $cached;
        }
        try {
            // Timeout court pour ne pas bloquer la page watch (évite "tens of seconds").
            $response = Http::withBasicAuth(
                config('app.webtv_stream_username'),
                config('app.webtv_stream_password')
            )->timeout(3)->connectTimeout(2)->get('http://localhost:5080/LiveApp/rest/v2/broadcasts/list/0/10');
            
            if ($response->successful()) {
                $streams = $response->json();
                
                // Chercher un stream live actif
                foreach ($streams as $stream) {
                    if (($stream['type'] === 'live' || $stream['type'] === 'liveStream') && $stream['status'] === 'broadcasting') {
                        $result = [
                            'is_live' => true,
                            'stream_id' => $stream['streamId'],
                            'stream_name' => $stream['name']
                        ];
                        Cache::put($cacheKey, $result, now()->addSeconds(self::LIVE_STATUS_CACHE_TTL_SECONDS));
                        return $result;
                    }
                }
            }
            $result = [
                'is_live' => false,
                'stream_id' => '',
                'stream_name' => ''
            ];
            Cache::put($cacheKey, $result, now()->addSeconds(self::LIVE_STATUS_CACHE_TTL_SECONDS));
            return $result;

        } catch (\Exception $e) {
            Log::error("❌ Erreur vérification live status: " . $e->getMessage());
            $result = [
                'is_live' => false,
                'stream_id' => '',
                'stream_name' => ''
            ];
            Cache::put($cacheKey, $result, now()->addSeconds(self::LIVE_STATUS_CACHE_TTL_SECONDS));
            return $result;
        }
    }
    
    /**
     * Démarrer la playlist VoD automatique
     */
    private function startVodPlaylist(WebTVPlaylist $playlist): array
    {
        try {
            $vodItems = $playlist->items()->where('sync_status', 'synced')->get();
            
            if ($vodItems->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Aucun VoD disponible pour la playlist'
                ];
            }
            
            // Créer une playlist de lecture automatique
            $playlistData = [];
            foreach ($vodItems as $item) {
                $vodInfo = $this->vodService->checkVoD($item);
                if ($vodInfo['success']) {
                    $playlistData[] = [
                        'title' => $item->title,
                        'url' => $vodInfo['direct_url'],
                        'duration' => $item->duration,
                        'order' => $item->order
                    ];
                }
            }
            
            // Trier par ordre
            usort($playlistData, function($a, $b) {
                return $a['order'] <=> $b['order'];
            });
            
            Log::info("🎥 Playlist VoD automatique créée", [
                'playlist_id' => $playlist->id,
                'items_count' => count($playlistData)
            ]);

            return [
                'success' => true,
                'message' => 'Playlist VoD automatique créée',
                'playlist_data' => $playlistData,
                'items_count' => count($playlistData)
            ];
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur création playlist VoD: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erreur lors de la création de la playlist VoD: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Surveiller et gérer le basculement automatique
     */
    public function monitorAutoSwitch(): array
    {
        try {
            $liveStatus = $this->checkLiveStatus();
            
            if ($liveStatus['is_live']) {
                // Live en cours - arrêter les VoD automatiques
                Log::info("📺 Live détecté - Arrêt des VoD automatiques");

                        return [
                    'success' => true,
                    'message' => 'Live détecté - VoD automatiques arrêtés',
                    'mode' => 'live',
                    'live_stream' => $liveStatus['stream_id']
                ];
                } else {
                // Pas de live - maintenir les VoD automatiques
                Log::info("🎥 Pas de live - Maintien des VoD automatiques");
                            
                            return [
                    'success' => true,
                    'message' => 'Pas de live - VoD automatiques maintenus',
                    'mode' => 'vod'
                ];
            }
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur surveillance basculement: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la surveillance du basculement: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtenir le statut actuel de la playlist automatique
     */
    public function getAutoPlaylistStatus(): array
    {
        try {
            $liveStatus = $this->checkLiveStatus();

            return [
                'success' => true,
                'message' => $liveStatus['is_live'] ? 'Live en cours' : 'VoD actif',
                'is_live' => $liveStatus['is_live'],
                'mode' => $liveStatus['is_live'] ? 'live' : 'vod',
                'live_stream' => $liveStatus['stream_id'] ?: 0,
                'live_name' => $liveStatus['stream_name'] ?: ''
            ];
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur statut playlist automatique: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération du statut: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Position de sync globale pour le flux unifié (unified.m3u8).
     * Utilisée par UnifiedStreamController (M3U) et getCurrentPlaybackContext.
     */
    public function getCurrentSyncPosition(): array
    {
        try {
            $activePlaylist = WebTVPlaylist::where('is_active', true)->first();
            if (!$activePlaylist) {
                    return [
                    'success' => false,
                    'message' => 'Aucune playlist active',
                    'current_item' => ['item_id' => null, 'current_time' => 0.0],
                ];
            }
            $cacheKey = 'webtv_unified_sync_position_' . $activePlaylist->id;
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['item_id'])) {
            return [
                'success' => true,
                    'current_item' => [
                        'item_id' => (int) $cached['item_id'],
                        'current_time' => (float) ($cached['current_time'] ?? 0),
                    ],
                ];
            }
            $firstItem = $activePlaylist->items()
                ->where('sync_status', 'synced')
                ->orderBy('order')
                ->first();
            if (!$firstItem) {
                return [
                    'success' => false,
                    'message' => 'Aucun item dans la playlist',
                    'current_item' => ['item_id' => null, 'current_time' => 0.0],
                ];
            }
            $default = ['item_id' => $firstItem->id, 'current_time' => 0.0];
            Cache::put($cacheKey, $default, now()->addHours(1));
            return ['success' => true, 'current_item' => $default];
        } catch (\Exception $e) {
            Log::error('getCurrentSyncPosition: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'current_item' => ['item_id' => null, 'current_time' => 0.0],
            ];
        }
    }
    
    /**
     * Contexte de lecture (live ou VoD) pour le flux unifié.
     * Utilisé par UnifiedStreamController::getUnifiedHLS et UnifiedHlsBuilder::build.
     * Cache playback_context_v2_* invalidé par AntMediaWebhookController.
     */
    public function getCurrentPlaybackContext(): array
    {
        $cacheKey = 'playback_context_v2_' . floor(time() / 2);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['mode'])) {
            return $cached;
        }
        try {
            if (Cache::get('webtv_system_paused')) {
                $context = ['success' => true, 'mode' => 'paused'];
                Cache::put($cacheKey, $context, now()->addSeconds(5));
                return $context;
            }
            $liveStatus = $this->checkLiveStatus();
            if ($liveStatus['is_live'] && !empty($liveStatus['stream_id'])) {
                $context = [
                    'success' => true,
                    'mode' => 'live',
                    'live' => [
                        'stream_id' => $liveStatus['stream_id'],
                        'stream_name' => $liveStatus['stream_name'] ?? '',
                        'source_url' => 'http://localhost:5080/LiveApp/play.html?name=' . $liveStatus['stream_id'],
                    ],
                ];
                Cache::put($cacheKey, $context, now()->addSeconds(5));
                return $context;
            }
            $activePlaylist = WebTVPlaylist::where('is_active', true)->first();
            if (!$activePlaylist) {
                $context = ['success' => false, 'message' => 'Aucune playlist active', 'mode' => 'vod'];
                Cache::put($cacheKey, $context, now()->addSeconds(5));
                return $context;
            }
            $sync = $this->getCurrentSyncPosition();
            $currentItemId = $sync['current_item']['item_id'] ?? null;
            $currentTime = (float) ($sync['current_item']['current_time'] ?? 0.0);
            $items = $activePlaylist->items()
                ->where('sync_status', 'synced')
                ->orderBy('order')
                ->get();
            $sequence = [];
            foreach ($items as $item) {
                if ($item->ant_media_item_id) {
                    $sequence[] = [
                        'item_id' => $item->id,
                        'title' => $item->title ?? '',
                        'ant_media_item_id' => $item->ant_media_item_id,
                        'duration' => (float) ($item->duration ?? 0),
                    ];
                }
            }
            if (empty($sequence)) {
                $context = ['success' => false, 'message' => 'Aucun item VoD synchronisé', 'mode' => 'vod'];
                Cache::put($cacheKey, $context, now()->addSeconds(5));
                return $context;
            }
            if ($currentItemId === null) {
                $currentItemId = $sequence[0]['item_id'];
                $currentTime = 0.0;
            }
            $context = [
                    'success' => true,
                'mode' => 'vod',
                'sequence' => ['items' => $sequence],
                'current_item' => ['item_id' => $currentItemId, 'current_time' => $currentTime],
                'playlist' => ['is_loop' => (bool) $activePlaylist->is_loop],
            ];
            Cache::put($cacheKey, $context, now()->addSeconds(5));
            return $context;
        } catch (\Exception $e) {
            Log::error('getCurrentPlaybackContext: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'mode' => 'vod'];
        }
    }

    /**
     * Mettre le système en pause (flux unifié en mode paused).
     */
    public function pauseSystem(): array
    {
        try {
            Cache::put('webtv_system_paused', true, now()->addHours(24));
            Log::info('⏸️ Système WebTV mis en pause');
            return ['success' => true, 'message' => 'Système en pause'];
        } catch (\Exception $e) {
            Log::error('pauseSystem: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Reprendre le système (sortie du mode paused).
     */
    public function resumeSystem(): void
    {
        Cache::forget('webtv_system_paused');
        Log::info('▶️ Système WebTV repris');
    }

    /**
     * Snapshot du statut en temps réel (SSE / WatchStreamController).
     */
    public function getRealtimeStatusSnapshot(): array
    {
        $urlData = $this->getCurrentPlaybackUrl();
        $status = $this->getAutoPlaylistStatus();
        return array_merge([
            'success' => $urlData['success'] ?? true,
            'mode' => $status['mode'] ?? ($urlData['mode'] ?? 'vod'),
            'url' => $urlData['url'] ?? null,
            'stream_id' => $urlData['stream_id'] ?? null,
            'stream_name' => $urlData['stream_name'] ?? null,
            'is_live' => $status['is_live'] ?? false,
        ], $urlData);
    }

    /** TTL cache pour getCurrentPlaybackUrl (aligné sur playback_context, réduit charge et latence page watch). */
    private const CURRENT_PLAYBACK_URL_CACHE_TTL_SECONDS = 2;

    /**
     * Obtenir l'URL de lecture actuelle (live ou VoD). Cache 2s pour réactivité de la page watch.
     */
    public function getCurrentPlaybackUrl(): array
    {
        $cacheKey = 'webtv_current_playback_url_' . floor(time() / self::CURRENT_PLAYBACK_URL_CACHE_TTL_SECONDS);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['success'])) {
            return $cached;
        }
        try {
            $liveStatus = $this->checkLiveStatus();
            
            if ($liveStatus['is_live']) {
                // Mode LIVE - Inchangé
                $liveUrl = "http://15.235.86.98:5080/LiveApp/play.html?name=" . $liveStatus['stream_id'];
                
                $result = [
                    'success' => true,
                    'message' => 'Live stream available',
                    'mode' => 'live',
                    'url' => $liveUrl,
                    'stream_id' => $liveStatus['stream_id'],
                    'stream_name' => $liveStatus['stream_name']
                ];
                Cache::put($cacheKey, $result, now()->addSeconds(self::CURRENT_PLAYBACK_URL_CACHE_TTL_SECONDS + 1));
                return $result;
            } else {
                // Mode VoD : utiliser le flux unifié (unified.m3u8) déjà généré par UnifiedHlsBuilder.
                // Ce flux contient les segments VoD quand il n'y a pas de live — pas besoin de FFmpegHLSService.
                $unifiedStreamUrl = 'https://tv.embmission.com/hls/streams/unified.m3u8';

                $result = [
                    'success' => true,
                    'message' => 'Stream unifié disponible (VoD)',
                    'mode' => 'vod',
                    'url' => $unifiedStreamUrl,
                    'format' => 'HLS (M3U8)',
                    'continuous' => true,
                ];

                // Enrichir avec item_id, current_time, duration pour la page watch (éviter fausse détection fin de vidéo / freeze)
                $context = $this->getCurrentPlaybackContext();
                if (($context['mode'] ?? '') === 'vod' && !empty($context['current_item']) && !empty($context['sequence']['items'])) {
                    $currentId = $context['current_item']['item_id'] ?? null;
                    $currentTime = (float) ($context['current_item']['current_time'] ?? 0);
                    $duration = 0;
                    foreach ($context['sequence']['items'] as $item) {
                        if (($item['item_id'] ?? null) === $currentId) {
                            $duration = (float) ($item['duration'] ?? 0);
                            break;
                        }
                    }
                    $result['item_id'] = $currentId;
                    $result['current_time'] = $currentTime;
                    $result['duration'] = $duration;
                }

                Cache::put($cacheKey, $result, now()->addSeconds(self::CURRENT_PLAYBACK_URL_CACHE_TTL_SECONDS + 1));
                return $result;
            }
        } catch (\Exception $e) {
            Log::error("❌ Erreur récupération URL de lecture: " . $e->getMessage());
            $err = [
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'URL de lecture: ' . $e->getMessage()
            ];
            Cache::put($cacheKey, $err, now()->addSeconds(5));
            return $err;
        }
    }
    
    /**
     * Obtenir le prochain élément VoD de la playlist active
     * Utilise le Cache pour gérer la position par URL de lecture actuelle
     */
    public function getNextVodItem(): array
    {
        try {
            // Récupérer l'URL actuellement en lecture
            $currentUrlData = $this->getCurrentPlaybackUrl();
            
            if (!$currentUrlData['success']) {
                return [
                    'success' => false,
                    'message' => 'Aucune source de lecture disponible'
                ];
            }
            
            // Récupérer la playlist active
            $activePlaylist = WebTVPlaylist::where('is_active', true)->first();
            
            if (!$activePlaylist) {
            return [
                'success' => false,
                    'message' => 'Aucune playlist active'
                ];
            }
            
            // Récupérer les items de la playlist
            $vodItems = $activePlaylist->items()
            ->where('sync_status', 'synced')
            ->orderBy('order')
            ->get();

            if ($vodItems->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Aucun VoD disponible dans la playlist'
                ];
            }
            
            // Utiliser session_id pour une position par utilisateur
            $sessionId = session()->getId();
            $cacheKey = 'webtv_position_' . $activePlaylist->id . '_' . $sessionId;
            $cachePlaylistKey = 'webtv_playlist_shuffle_' . $activePlaylist->id;
            
            // Récupérer ou initialiser la position
            $currentPosition = Cache::get($cacheKey, 0);
            $shuffledOrder = Cache::get($cachePlaylistKey, []);
            
            // Si c'est une nouvelle playlist ou pas de shuffle encore, créer l'ordre
            if (empty($shuffledOrder) && $activePlaylist->shuffle_enabled) {
                $shuffledOrder = range(0, $vodItems->count() - 1);
                shuffle($shuffledOrder);
                Cache::put($cachePlaylistKey, $shuffledOrder, now()->addDays(7));
            }
            
            // ✅ Validation: Si le nombre d'items a changé, régénérer le shuffle
            $currentItemCount = $vodItems->count();
            if (!empty($shuffledOrder) && count($shuffledOrder) !== $currentItemCount && $activePlaylist->shuffle_enabled) {
                Log::info("⚠️ Nombre d'items playlist changé, régénération shuffle", [
                    'playlist_id' => $activePlaylist->id,
                    'ancien_count' => count($shuffledOrder),
                    'nouveau_count' => $currentItemCount
                ]);
                $shuffledOrder = range(0, $currentItemCount - 1);
                shuffle($shuffledOrder);
                Cache::put($cachePlaylistKey, $shuffledOrder, now()->addDays(7));
            }
            
            // Gérer l'ordre selon shuffle_enabled
            if ($activePlaylist->shuffle_enabled && !empty($shuffledOrder)) {
                // Utiliser la position dans l'ordre mélangé
                if ($currentPosition < count($shuffledOrder)) {
                    $itemIndex = $shuffledOrder[$currentPosition];
                    $nextItem = $vodItems[$itemIndex];
                } else {
                    // Fin de la playlist mélangée
                    if ($activePlaylist->is_loop) {
                        // Recommencer avec un nouveau shuffle
                        $shuffledOrder = range(0, $vodItems->count() - 1);
                        shuffle($shuffledOrder);
                        Cache::put($cachePlaylistKey, $shuffledOrder, now()->addDays(7));
                        $currentPosition = 0;
                        $itemIndex = $shuffledOrder[0];
                        $nextItem = $vodItems[$itemIndex];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Fin de la playlist'
                        ];
                    }
                }
            } else {
                // Ordre normal
                if ($currentPosition < $vodItems->count()) {
                    $nextItem = $vodItems[$currentPosition];
                } else {
                    // Fin de la playlist
                    if ($activePlaylist->is_loop) {
                        // Recommencer depuis le début
                        $currentPosition = 0;
                        $nextItem = $vodItems[0];
                    } else {
                return [
                            'success' => false,
                            'message' => 'Fin de la playlist'
                        ];
                    }
                }
            }
            
            // Avancer à la prochaine position
            $currentPosition++;
            Cache::put($cacheKey, $currentPosition, now()->addHours(1));
            
            // Récupérer l'URL VoD
            $vodInfo = $this->vodService->checkVoD($nextItem);
            
            if (!$vodInfo['success']) {
        return [
                    'success' => false,
                    'message' => 'Erreur lors de la récupération du VoD: ' . ($vodInfo['message'] ?? 'Unknown error')
                ];
            }
            
            Log::info('🎥 Prochaine vidéo VoD récupérée', [
                'playlist_id' => $activePlaylist->id,
                'position' => $currentPosition - 1,
                'item_title' => $nextItem->title,
                'shuffle_enabled' => $activePlaylist->shuffle_enabled,
                'is_loop' => $activePlaylist->is_loop
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'url' => $vodInfo['direct_url'],
                    'title' => $nextItem->title,
                    'duration' => $nextItem->duration
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('❌ Erreur récupération prochain VoD: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération du prochain VoD: ' . $e->getMessage()
            ];
        }
    }
}






