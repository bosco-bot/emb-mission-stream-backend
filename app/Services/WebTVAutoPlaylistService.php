<?php

namespace App\Services;

use App\Events\StreamStatusChanged;
use App\Models\WebTVPlaylist;
use App\Models\WebTVPlaylistItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class WebTVAutoPlaylistService
{
    private AntMediaVoDService $vodService;
    private string $antMediaStreamsPath;
    
    public function __construct()
    {
        $this->vodService = new AntMediaVoDService();
        $this->antMediaStreamsPath = '/usr/local/antmedia/webapps/LiveApp/streams';
    }
    
    /**
     * Obtenir l'URL de lecture actuelle (live ou VoD).
     * @see apply_advance_sync_position.php (script correctif serveur)
     *
     * @param  bool        $forceBroadcast   Forcer l'émission d'un événement WebSocket (snapshot)
     * @param  string|null $snapshotSource   Source (debug) du snapshot forcé
     */
    public function getCurrentPlaybackUrl(bool $forceBroadcast = false, ?string $snapshotSource = null): array
    {
        try {
            // ✅ Cache pour éviter les recalculs répétés (2 secondes pour rester réactif)
            $cacheKey = 'webtv_current_url';
            $cached = Cache::get($cacheKey);
            
            // Vérifier rapidement le statut live pour détecter les changements de mode
            $liveStatus = $this->checkLiveStatus();
            $currentMode = $liveStatus['is_live'] ? 'live' : 'vod';
            
            // Détecter les changements de mode pour émettre l'Event WebSocket
            $previousMode = $cached['data']['mode'] ?? null;
            $modeChanged = ($previousMode !== null && $previousMode !== $currentMode);
            
            if ($cached && isset($cached['cached_at']) && $cached['cached_at'] >= (time() - 2)) {
                // Vérifier si le mode a changé - si oui, invalider le cache
                $cachedMode = $cached['data']['mode'] ?? null;
                if ($cachedMode === $currentMode) {
                    // Même mode - utiliser le cache (mise à jour du sync_timestamp)
                    $cached['data']['sync_timestamp'] = time();
                    return $cached['data'];
                } else {
                    // Mode a changé - invalider le cache et recalculer
                    Cache::forget($cacheKey);
                }
            }

            if ($this->isSystemPaused()) {
                $result = [
                    'success' => true,
                    'message' => 'Système en pause',
                    'mode' => 'paused',
                    'url' => null,
                    'stream_url' => null,
                    'stream_id' => null,
                    'stream_name' => null,
                    'sync_timestamp' => time(),
                ];
                Cache::put($cacheKey, ['data' => $result, 'cached_at' => time()], 5);

                if ($forceBroadcast && $this->shouldBroadcastSnapshot('paused')) {
                    $payload = $this->appendSnapshotMetadata($result, true, $snapshotSource);
                    event(new StreamStatusChanged($payload));
                    Log::info('📡 Event WebSocket snapshot (paused)', [
                        'snapshot_forced' => true,
                        'snapshot_source' => $snapshotSource,
                    ]);
                }

                return $result;
            }

            // $liveStatus déjà récupéré plus haut pour la vérification du cache
            if ($liveStatus['is_live']) {
                // ✅ Retourner l'URL HLS unifiée (compatible VLC et navigateur)
                $liveUrl = "https://tv.embmission.com/hls/streams/unified.m3u8";
                
                $result = [
                    'success' => true,
                    'message' => 'Live stream available',
                    'mode' => 'live',
                    'url' => $liveUrl,
                    'stream_url' => $liveUrl,
                    'stream_id' => $liveStatus['stream_id'],
                    'stream_name' => $liveStatus['stream_name'],
                    'sync_timestamp' => time(),
                ];
                // Cache de 2 secondes pour le live (réactif mais efficace)
                Cache::put($cacheKey, ['data' => $result, 'cached_at' => time()], 5);
                
                $forceSnapshot = false;
                if ($forceBroadcast && !$modeChanged && $this->shouldBroadcastSnapshot('live')) {
                    $forceSnapshot = true;
                }
                
                // ✅ Émettre l'Event WebSocket si le mode a changé ou snapshot forcé
                // Vérifier une dernière fois que le manifest est vraiment accessible avant d'émettre
                if ($modeChanged || $forceSnapshot) {
                    $streamId = $liveStatus['stream_id'];
                    // Double vérification : s'assurer que le manifest est vraiment prêt avant d'émettre l'événement
                    if ($this->isHlsManifestReady($streamId)) {
                        $payload = $this->appendSnapshotMetadata($result, $forceSnapshot, $snapshotSource);
                        event(new StreamStatusChanged($payload));
                        Log::info($forceSnapshot ? '📡 Event WebSocket snapshot (live)' : '📡 Event WebSocket émis: Live activé', [
                            'stream_id' => $streamId,
                            'previous_mode' => $previousMode,
                            'snapshot_forced' => $forceSnapshot,
                            'snapshot_source' => $snapshotSource,
                        ]);
                    } else {
                        Log::info('⏳ Manifest HLS pas encore prêt - Event WebSocket différé', [
                            'stream_id' => $streamId,
                            'previous_mode' => $previousMode,
                            'snapshot_forced' => $forceSnapshot,
                        ]);
                        // Ne pas émettre l'événement si le manifest n'est pas prêt
                        // Le polling détectera le Live une fois le manifest prêt
                    }
                }
                
                return $result;
            }

            $context = $this->getCurrentPlaybackContext();
            if (($context['success'] ?? false) !== true || ($context['mode'] ?? 'vod') !== 'vod') {
                return [
                    'success' => false,
                    'message' => $context['message'] ?? 'Aucune source de lecture disponible'
                ];
            }

            $currentItem = $context['current_item'] ?? null;
            if (!$currentItem || empty($currentItem['stream_url'])) {
                Log::warning("⚠️ Contexte VoD sans stream_url", ['context' => $context]);
                return [
                    'success' => false,
                    'message' => 'VoD disponible mais URL manquante'
                ];
            }

            $playlist = $context['playlist'] ?? [];
            $sequenceItems = $context['sequence']['items'] ?? [];

            $currentItemId = $currentItem['item_id'] ?? null;
            $nextItems = [];

            if ($currentItemId && !empty($sequenceItems)) {
                $sequenceCount = count($sequenceItems);
                for ($i = 0; $i < $sequenceCount; $i++) {
                    if (($sequenceItems[$i]['item_id'] ?? null) === $currentItemId) {
                        $lookAhead = 0;
                        $index = $i + 1;
                        while ($lookAhead < 3 && $sequenceCount > 0) {
                            if ($index >= $sequenceCount) {
                                $index = 0;
                            }

                            if ($index === $i) {
                                break;
                            }

                            $candidate = $sequenceItems[$index];
                            $nextItems[] = [
                                'item_id' => $candidate['item_id'] ?? null,
                                'title' => $candidate['title'] ?? null,
                                'stream_url' => $candidate['stream_url'] ?? null,
                                'duration' => $candidate['duration'] ?? null,
                                'start_time' => $candidate['start_time'] ?? null,
                                'end_time' => $candidate['end_time'] ?? null,
                            ];

                            $lookAhead++;
                            $index++;
                        }
                        break;
                    }
                }
            }

            $syncTimestamp = $context['context_timestamp'] ?? time();

            $result = [
                'success' => true,
                'message' => 'VoD available',
                'mode' => 'vod',
                'url' => 'https://tv.embmission.com/hls/streams/unified.m3u8',
                'stream_url' => 'https://tv.embmission.com/hls/streams/unified.m3u8',
                'playlist_id' => $playlist['id'] ?? null,
                'playlist_name' => $playlist['name'] ?? null,
                'item_title' => $currentItem['title'] ?? null,
                'item_id' => $currentItem['item_id'] ?? null,
                'ant_media_item_id' => $currentItem['ant_media_item_id'] ?? null,
                'duration' => $currentItem['duration'] ?? null,
                'current_time' => $currentItem['current_time'] ?? 0.0,
                'is_finished' => $currentItem['is_finished'] ?? false,
                'sync_timestamp' => $syncTimestamp,
                'playlist' => $playlist,
                'next_items' => $nextItems,
                'sequence' => $sequenceItems,
            ];
            
            // Cache de 2 secondes pour le VoD (réactif mais efficace)
            Cache::put($cacheKey, ['data' => $result, 'cached_at' => time()], 5);
            
            $forceSnapshot = false;
            if ($forceBroadcast && !$modeChanged && $this->shouldBroadcastSnapshot('vod')) {
                $forceSnapshot = true;
            }

            // ✅ Émettre l'Event WebSocket si le mode a changé ou snapshot forcé
            if ($modeChanged || $forceSnapshot) {
                $payload = $this->appendSnapshotMetadata($result, $forceSnapshot, $snapshotSource);
                event(new StreamStatusChanged($payload));
                Log::info($forceSnapshot ? '📡 Event WebSocket snapshot (VoD)' : '📡 Event WebSocket émis: VoD activé', [
                    'item_id' => $currentItem['item_id'] ?? null,
                    'previous_mode' => $previousMode,
                    'snapshot_forced' => $forceSnapshot,
                    'snapshot_source' => $snapshotSource,
                ]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur récupération URL de lecture: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'URL de lecture: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Contexte complet pour le builder HLS unifié.
     */
    public function getCurrentPlaybackContext(): array
    {
        try {
            if ($this->isSystemPaused()) {
                return [
                    'success' => true,
                    'mode' => 'paused',
                    'context_timestamp' => time(),
                    'playlist' => null,
                    'current_item' => null,
                    'sequence' => [
                        'items' => [],
                    ],
                ];
            }

            $liveStatus = $this->checkLiveStatus();

            if ($liveStatus['is_live']) {
                return [
                    'success' => true,
                    'mode' => 'live',
                    'live' => [
                        'stream_id' => $liveStatus['stream_id'],
                        'stream_name' => $liveStatus['stream_name'],
                        'source_url' => 'https://tv.embmission.com/hls/streams/live_transcoded.m3u8',
                    ],
                    'context_timestamp' => time(),
                ];
            }

            $playlist = WebTVPlaylist::where('is_active', true)->first();
            if (!$playlist) {
                return [
                    'success' => false,
                    'message' => 'Aucune playlist active',
                ];
            }

            $sequence = $this->buildVodSequence($playlist);
            if (empty($sequence)) {
                return [
                    'success' => false,
                    'message' => 'Aucun VoD synchronisé dans la playlist',
                ];
            }

            $totalDuration = array_sum(array_column($sequence, 'duration'));
            if ($totalDuration <= 0) {
                return [
                    'success' => false,
                    'message' => 'Durées des éléments invalides',
                ];
            }

            $now = time();
            $referenceDate = $playlist->updated_at ?? $playlist->created_at;
            $timelineStart = $referenceDate ? $referenceDate->getTimestamp() : $now;
            $elapsed = max(0, $now - $timelineStart);
            $loopEnabled = (bool) ($playlist->is_loop ?? false);

            $cycleIndex = 0;
            $elapsedInCycle = $elapsed;
            $isFinished = false;

            if ($loopEnabled) {
                if ($totalDuration > 0) {
                    $cycleIndex = intdiv((int) $elapsed, (int) $totalDuration);
                    $elapsedInCycle = fmod($elapsed, $totalDuration);
                }
            } else {
                if ($elapsed >= $totalDuration) {
                    $elapsedInCycle = $totalDuration;
                    $isFinished = true;
                }
            }

            if (!empty($sequence) && ($playlist->shuffle_enabled ?? false) && $totalDuration > 0) {
                $sequence = $this->shuffleSequence($playlist->id, $sequence, $cycleIndex);
            } else {
                $sequence = array_values($sequence);
            }

            $timedSequence = $this->applySequenceTiming($sequence);
            $sequence = $timedSequence['sequence'];
            $totalDuration = $timedSequence['total_duration'];

            if ($totalDuration <= 0 || empty($sequence)) {
                return [
                    'success' => false,
                    'message' => 'Impossible de déterminer la durée totale de la playlist',
                ];
            }

            if (!$loopEnabled && $elapsed >= $totalDuration) {
                $isFinished = true;
                $elapsedInCycle = $totalDuration;
            } elseif ($loopEnabled && $totalDuration > 0) {
                $elapsedInCycle = fmod($elapsed, $totalDuration);
            }

            $currentItem = null;
            $currentTime = 0.0;

            if ($isFinished && !$loopEnabled) {
                $currentItem = end($sequence) ?: null;
                if ($currentItem) {
                    $currentTime = $currentItem['duration'];
                }
            } else {
                foreach ($sequence as $item) {
                    if ($elapsedInCycle >= $item['start_time'] && $elapsedInCycle < $item['end_time']) {
                        $currentItem = $item;
                        $currentTime = $elapsedInCycle - $item['start_time'];
                        break;
                    }
                }

                if (!$currentItem && !empty($sequence)) {
                    $currentItem = end($sequence) ?: null;
                    if ($currentItem) {
                        $currentTime = max(0.0, min(
                            $currentItem['duration'],
                            $elapsedInCycle - ($currentItem['start_time'] ?? 0.0)
                        ));
                    }
                }
            }

            if (!$currentItem) {
                return [
                    'success' => false,
                    'message' => 'Impossible de déterminer l’élément courant',
                ];
            }

            $currentItemPayload = [
                'item_id' => $currentItem['item_id'],
                'title' => $currentItem['title'],
                'ant_media_item_id' => $currentItem['ant_media_item_id'],
                'stream_url' => $currentItem['stream_url'],
                'duration' => $currentItem['duration'],
                'current_time' => max(0.0, min($currentItem['duration'], $currentTime)),
                'is_finished' => $isFinished && !$loopEnabled,
            ];

            $sequencePayload = array_map(static function (array $item) {
                return [
                    'item_id' => $item['item_id'],
                    'title' => $item['title'],
                    'ant_media_item_id' => $item['ant_media_item_id'],
                    'stream_url' => $item['stream_url'],
                    'duration' => $item['duration'],
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                ];
            }, $sequence);

            return [
                'success' => true,
                'mode' => 'vod',
                'context_timestamp' => $now,
                'playlist' => [
                    'id' => $playlist->id,
                    'name' => $playlist->name,
                    'shuffle_enabled' => (bool) ($playlist->shuffle_enabled ?? false),
                    'is_loop' => $loopEnabled,
                    'total_duration' => $totalDuration,
                ],
                'current_item' => $currentItemPayload,
                'sequence' => [
                    'items' => $sequencePayload,
                ],
            ];
        } catch (\Exception $e) {
            Log::error("❌ Erreur getCurrentPlaybackContext: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ];
        }
    }

    private function getSyncPositionFilePath(int $playlistId): string
    {
        return storage_path('app/webtv_sync_position_' . $playlistId . '.json');
    }

    private function readSyncPositionFromFile(int $playlistId): ?array
    {
        $path = $this->getSyncPositionFilePath($playlistId);
        if (!File::isFile($path)) {
            return null;
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }
        $data = @json_decode($content, true);
        if (!is_array($data) || !isset($data['item_id'])) {
            return null;
        }
        return $data;
    }

    private function writeSyncPositionToFile(int $playlistId, array $position): void
    {
        $path = $this->getSyncPositionFilePath($playlistId);
        $dir = dirname($path);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        @file_put_contents($path, json_encode($position, JSON_PRETTY_PRINT));
    }

    /**
     * Position de sync globale pour le flux unifié (unified.m3u8).
     * Utilisée par UnifiedStreamController (M3U) et commande webtv:advance-sync-position.
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
            if (!is_array($cached) || !isset($cached['item_id'])) {
                $cached = $this->readSyncPositionFromFile($activePlaylist->id);
            }
            if (is_array($cached) && isset($cached['item_id'])) {
                Cache::put($cacheKey, $cached, now()->addHours(1));
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
            $default = ['item_id' => $firstItem->id, 'current_time' => 0.0, 'updated_at' => time()];
            Cache::put($cacheKey, $default, now()->addHours(1));
            $this->writeSyncPositionToFile($activePlaylist->id, $default);
            return [
                'success' => true,
                'current_item' => ['item_id' => $default['item_id'], 'current_time' => $default['current_time']],
            ];
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
     * Avance la position de lecture du flux unifié selon le temps écoulé.
     * Appelée par la commande webtv:advance-sync-position (planifiée chaque minute).
     */
    public function advanceSyncPosition(): array
    {
        try {
            if (Cache::get('webtv_system_paused')) {
                return ['success' => true, 'message' => 'Système en pause'];
            }
            if ($this->checkLiveStatus()['is_live']) {
                return ['success' => true, 'message' => 'Mode live'];
            }

            $activePlaylist = WebTVPlaylist::where('is_active', true)->first();
            if (!$activePlaylist) {
                return ['success' => false, 'message' => 'Aucune playlist active'];
            }

            $cacheKey = 'webtv_unified_sync_position_' . $activePlaylist->id;
            $cached = Cache::get($cacheKey);
            if (!is_array($cached) || !isset($cached['item_id'])) {
                $cached = $this->readSyncPositionFromFile($activePlaylist->id);
            }

            $items = $activePlaylist->items()
                ->where('sync_status', 'synced')
                ->orderBy('order')
                ->get();

            $sequence = [];
            foreach ($items as $item) {
                if ($item->ant_media_item_id && ($item->duration ?? 0) > 0) {
                    $sequence[] = [
                        'item_id' => $item->id,
                        'duration' => (float) $item->duration,
                    ];
                }
            }
            if (empty($sequence)) {
                return ['success' => false, 'message' => 'Aucun item VoD'];
            }

            $now = time();
            $updatedAt = $cached['updated_at'] ?? ($now - 60);
            $elapsed = max(0, $now - $updatedAt);

            $currentItemId = isset($cached['item_id']) ? (int) $cached['item_id'] : $sequence[0]['item_id'];
            $currentTime = (float) ($cached['current_time'] ?? 0);

            $index = 0;
            foreach ($sequence as $i => $seg) {
                if ($seg['item_id'] === $currentItemId) {
                    $index = $i;
                    break;
                }
            }

            $currentTime += $elapsed;
            $isLoop = (bool) $activePlaylist->is_loop;

            while ($currentTime >= $sequence[$index]['duration']) {
                $currentTime -= $sequence[$index]['duration'];
                $index++;
                if ($index >= count($sequence)) {
                    if ($isLoop) {
                        $index = 0;
                    } else {
                        $index = count($sequence) - 1;
                        $currentTime = $sequence[$index]['duration'];
                        break;
                    }
                }
            }

            $newItemId = $sequence[$index]['item_id'];
            $newTime = min($currentTime, $sequence[$index]['duration']);

            $newPosition = [
                'item_id' => $newItemId,
                'current_time' => $newTime,
                'updated_at' => $now,
            ];
            Cache::put($cacheKey, $newPosition, now()->addHours(1));
            $this->writeSyncPositionToFile($activePlaylist->id, $newPosition);

            return ['success' => true, 'message' => 'Position mise à jour', 'item_id' => $newItemId, 'current_time' => $newTime];
        } catch (\Exception $e) {
            Log::error('advanceSyncPosition: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Vérifier le statut du live avec cache pour réduire les appels
     */
    public function checkLiveStatus(): array
    {
        // 🎯 Cache de 5 secondes pour réduire drastiquement les requêtes vers Ant Media (au lieu de 2s)
        // Avec 5 spectateurs, cela réduit de 2.5 requêtes/sec à 0.2 requêtes/sec par spectateur
        $cached = Cache::get('live_status_check');
        if ($cached && isset($cached['cached_at']) && $cached['cached_at'] >= (time() - 5)) {
            return $cached;
        }

        // ✅ Verrou pour éviter que plusieurs connexions SSE fassent la même requête simultanément
        $lock = Cache::lock('live_status_probe', 10); // Verrou de 10 secondes max (augmenté)
        if (!$lock->get()) {
            // Si le verrou est pris, attendre un peu et retourner le cache existant
            usleep(200000); // 200ms (augmenté)
            $cached = Cache::get('live_status_check');
            if ($cached && isset($cached['cached_at'])) {
                return $cached;
            }
            // Si toujours pas de cache, attendre encore un peu
            usleep(300000); // 300ms supplémentaire
            $cached = Cache::get('live_status_check');
            if ($cached && isset($cached['cached_at'])) {
                return $cached;
            }
        }

        try {
            $status = $this->probeLiveStatus();
            
            // ✅ HYSTÉRÉSIS : Une fois le live détecté, on le garde pendant 10 secondes même si la vérification échoue temporairement
            // MAIS : Si le manifest HLS est arrêté (pas de mise à jour depuis 10s), on ignore l'hystérésis
            $lastLiveStatus = Cache::get('live_status_last_confirmed');
            $manifestStopped = false;
            
            // Vérifier si le manifest est vraiment arrêté (pas juste une erreur temporaire)
            if ($lastLiveStatus && isset($lastLiveStatus['is_live']) && $lastLiveStatus['is_live'] === true) {
                $streamId = $lastLiveStatus['stream_id'] ?? null;
                if ($streamId) {
                    // ✅ OPTIMISATION : Vérifier d'abord si le fichier HLS existe vraiment et est récent
                    $hlsPath = '/usr/local/antmedia/webapps/LiveApp/streams/' . $streamId . '.m3u8';
                    if (!file_exists($hlsPath) || !is_readable($hlsPath)) {
                        // Fichier HLS n'existe pas = live vraiment arrêté
                        $manifestStopped = true;
                        Log::info('⏹️ Manifest HLS inexistant - Live vraiment arrêté, ignorer l\'hystérésis', [
                            'stream_id' => $streamId,
                        ]);
                    } else {
                        $fileAge = time() - filemtime($hlsPath);
                        // ✅ OPTIMISÉ : Si le fichier n'a pas été modifié depuis plus de 2 secondes, le stream est vraiment arrêté
                        // (Segments HLS de 2s → 2s sans mise à jour = vraiment arrêté)
                        if ($fileAge > 2) {
                            $manifestStopped = true;
                            Log::info('⏹️ Manifest HLS arrêté (non mis à jour depuis ' . $fileAge . 's) - Ignorer l\'hystérésis', [
                                'stream_id' => $streamId,
                                'file_age' => $fileAge,
                            ]);
                        } else {
                            // Vérifier aussi le cache du manifest pour détecter les cas où le fichier existe mais le contenu ne change plus
                            $manifestContentCacheKey = "live_manifest_content_{$streamId}";
                            $previousManifest = Cache::get($manifestContentCacheKey);
                            if ($previousManifest) {
                                $previousTime = $previousManifest['timestamp'] ?? 0;
                                $timeSinceLastUpdate = time() - $previousTime;
                                // ✅ OPTIMISÉ : Si le manifest n'a pas été mis à jour depuis plus de 2 secondes, le stream est vraiment arrêté
                                if ($timeSinceLastUpdate > 2) {
                                    $manifestStopped = true;
                                    Log::info('⏹️ Manifest HLS arrêté (contenu non mis à jour depuis ' . $timeSinceLastUpdate . 's) - Ignorer l\'hystérésis', [
                                        'stream_id' => $streamId,
                                        'time_since_last_update' => $timeSinceLastUpdate,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
            
            if ($lastLiveStatus && isset($lastLiveStatus['is_live']) && $lastLiveStatus['is_live'] === true) {
                $lastConfirmedAt = $lastLiveStatus['confirmed_at'] ?? 0;
                $timeSinceLastConfirm = time() - $lastConfirmedAt;
                
                // Si le manifest est vraiment arrêté, ignorer l'hystérésis et déclarer le live comme arrêté
                if ($manifestStopped) {
                    Log::info('⏹️ Live arrêté (manifest HLS arrêté) - Ignorer l\'hystérésis', [
                        'stream_id' => $lastLiveStatus['stream_id'] ?? null,
                    ]);
                    Cache::forget('live_status_last_confirmed');
                }
                // Si le live était confirmé il y a moins de 10 secondes et que la nouvelle vérification échoue (mais manifest toujours actif)
                // ✅ OPTIMISATION : Appliquer l'hystérésis SEULEMENT si le manifest est toujours actif (pas vraiment arrêté)
                elseif ($timeSinceLastConfirm < 10 && ($status['is_live'] ?? false) === false && !$manifestStopped) {
                    Log::info('🔄 Live toujours actif (hystérésis), maintien du statut', [
                        'time_since_confirm' => $timeSinceLastConfirm,
                        'last_stream_id' => $lastLiveStatus['stream_id'] ?? null,
                    ]);
                    // Retourner le dernier statut confirmé au lieu du nouveau statut négatif
                    $status = $lastLiveStatus;
                } else {
                    // Le live est confirmé depuis plus de 10 secondes OU la nouvelle vérification confirme le live
                    if (($status['is_live'] ?? false) === true) {
                        Cache::put('live_status_last_confirmed', array_merge($status, ['confirmed_at' => time()]), 30);
                    } else {
                        // Le live est vraiment arrêté, effacer la confirmation
                        Cache::forget('live_status_last_confirmed');
                    }
                }
            } else {
                // Première détection du live ou pas de statut précédent
                if (($status['is_live'] ?? false) === true) {
                    Cache::put('live_status_last_confirmed', array_merge($status, ['confirmed_at' => time()]), 30);
                }
            }
            
            // ✅ OPTIMISÉ : Cache de 5s pour réduire drastiquement les requêtes vers Ant Media
            Cache::put('live_status_check', $status, 5);

            return $status;
        } finally {
            // Libérer le verrou dans tous les cas (succès ou erreur)
            if (isset($lock)) {
                $lock->release();
            }
        }
    }

    private function probeLiveStatus(): array
    {
        try {
            $candidateStreams = [
                [
                    'id' => 'live_transcoded',
                    'name' => 'EMB WebTV Live (transcodé)',
                ],
                [
                    'id' => 'R9rzvVzMPvCClU6s1562847982308692',
                    'name' => 'EMB WebTV Stream',
                ],
            ];

            // ✅ OPTIMISATION : Vérifier les streams en parallèle pour réduire le délai de détection
            // ✅ Timeout réduit à 1.5s pour éviter les blocages et l'accumulation de connexions CLOSE-WAIT
            // ✅ Connection timeout réduit pour fermer rapidement les connexions inactives
            $responses = Http::pool(function ($pool) use ($candidateStreams) {
                $poolRequests = [];
                foreach ($candidateStreams as $index => $candidate) {
                    $streamId = $candidate['id'];
                    $antMediaUrl = "http://localhost:5080/LiveApp/rest/v2/broadcasts/{$streamId}";
                    $poolRequests["stream_{$index}"] = $pool->timeout(1.5)
                        ->connectTimeout(1) // Timeout de connexion de 1 seconde
                        ->withBasicAuth('admin', 'Emb2024!')
                        ->withOptions([
                            'curl' => [
                                CURLOPT_TCP_NODELAY => true,
                                CURLOPT_FRESH_CONNECT => true, // Force nouvelle connexion
                                CURLOPT_FORBID_REUSE => true,  // Interdit la réutilisation
                            ],
                            'headers' => [
                                'Connection' => 'close', // Force la fermeture après requête
                            ],
                        ])
                        ->get($antMediaUrl);
                }
                return $poolRequests;
            });

            // Traiter les réponses dans l'ordre de priorité (live_transcoded en premier)
            foreach ($candidateStreams as $index => $candidate) {
                $streamId = $candidate['id'];
                $responseKey = "stream_{$index}";
                $response = $responses[$responseKey] ?? null;

                if ($response && $response->successful()) {
                    $stream = $response->json();

                    Log::info('🔎 Vérification stream', [
                        'stream_id' => $streamId,
                        'http_status' => $response->status(),
                        'stream_status' => $stream['status'] ?? null,
                        'zombi' => $stream['zombi'] ?? null,
                    ]);

                    // ✅ Accepter le stream même s'il est marqué "zombi" si le statut est "broadcasting"
                    // Un stream peut être marqué zombi mais toujours actif (problème connu d'Ant Media Server)
                    if (isset($stream['status']) && $stream['status'] === 'broadcasting') {
                        // Vérifier le manifest HLS - si accessible, considérer le live comme prêt
                        if (!$this->isHlsManifestReady($streamId)) {
                            Log::info('⏳ Manifest HLS pas encore prêt, attente...', [
                                'stream_id' => $streamId,
                                'zombi' => $stream['zombi'] ?? false,
                            ]);
                            continue;
                        }

                        Log::info('✅ Live détecté et manifest HLS validé', [
                            'stream_id' => $stream['streamId'] ?? $streamId,
                            'zombi' => $stream['zombi'] ?? false,
                            'status' => $stream['status'],
                        ]);

                        return [
                            'is_live' => true,
                            'stream_id' => $stream['streamId'] ?? $streamId,
                            'stream_name' => $stream['name'] ?? $candidate['name'],
                            'cached_at' => time(),
                        ];
                    }
                } else {
                    $httpStatus = $response ? $response->status() : 'timeout/error';
                    Log::warning('⚠️ Vérification stream échouée', [
                        'stream_id' => $streamId,
                        'http_status' => $httpStatus,
                    ]);
                    
                    // ✅ Si l'API retourne 404 mais que le fichier HLS existe, considérer le live comme actif
                    // (l'API peut être temporairement indisponible mais le stream continue)
                    $hlsPath = '/usr/local/antmedia/webapps/LiveApp/streams/' . $streamId . '.m3u8';
                    if (file_exists($hlsPath) && is_readable($hlsPath)) {
                        $fileAge = time() - filemtime($hlsPath);
                        // Si le fichier a été modifié il y a moins de 30 secondes, considérer le live comme actif
                        if ($fileAge < 30) {
                            Log::info('✅ Live détecté via fichier HLS (API indisponible)', [
                                'stream_id' => $streamId,
                                'file_age' => $fileAge,
                                'http_status' => $httpStatus,
                            ]);
                            
                            return [
                                'is_live' => true,
                                'stream_id' => $streamId,
                                'stream_name' => $candidate['name'],
                                'cached_at' => time(),
                            ];
                        }
                    }
                }
            }

            return [
                'is_live' => false,
                'stream_id' => null,
                'stream_name' => null,
                'cached_at' => time(),
            ];

        } catch (\Exception $e) {
            Log::error("❌ Erreur vérification live: " . $e->getMessage());
            return [
                'is_live' => false,
                'stream_id' => null,
                'stream_name' => null,
                'cached_at' => time(),
            ];
        }
    }

    private function isHlsManifestReady(string $streamId, int $minSegments = 4): bool
    {
        $confirmationCacheKey = "live_manifest_ready_confirm_{$streamId}";
        $manifestContentCacheKey = "live_manifest_content_{$streamId}";

        try {

            // ✅ Utiliser l'URL correcte selon le streamId
            // Pour live_transcoded, utiliser /hls/streams/ (comme le player)
            // Pour les autres streams, utiliser /webtv-live/streams/
            if ($streamId === 'live_transcoded') {
                $manifestUrl = "https://tv.embmission.com/hls/streams/{$streamId}.m3u8";
            } else {
                $manifestUrl = "https://tv.embmission.com/webtv-live/streams/{$streamId}.m3u8";
            }
            $manifestUrlWithBypass = $manifestUrl . (str_contains($manifestUrl, '?') ? '&' : '?') . 'nocache=' . time();
            $response = Http::timeout(2)
                ->withOptions([
                    'curl' => [
                        CURLOPT_FRESH_CONNECT => true,
                        CURLOPT_FORBID_REUSE => true,
                    ],
                    'headers' => [
                        'Connection' => 'close',
                    ],
                ])
                ->get($manifestUrlWithBypass);

            if (!$response->successful()) {
                Cache::forget($confirmationCacheKey);
                return false;
            }

            $body = $response->body();
            if (empty($body)) {
                Cache::forget($confirmationCacheKey);
                return false;
            }

            $segmentCount = substr_count($body, '#EXTINF');
            if ($segmentCount < $minSegments) {
                Cache::forget($confirmationCacheKey);
                return false;
            }

            // ✅ Vérifier que le premier segment listé existe vraiment sur le serveur
            // Cela évite les 404 quand hls.js essaie de charger un segment qui n'existe pas encore
            $lines = explode("\n", $body);
            $firstSegmentUrl = null;
            
            foreach ($lines as $line) {
                $line = trim($line);
                // Ignorer les lignes vides, commentaires et tags
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                // C'est une URL de segment
                if (strpos($line, '.ts') !== false || strpos($line, '.m4s') !== false) {
                    $firstSegmentUrl = $line;
                    break;
                }
            }

            if ($firstSegmentUrl === null) {
                // Pas de segment trouvé dans le manifest
                Cache::forget($confirmationCacheKey);
                return false;
            }

            // Normaliser l'URL du segment (peut être relative ou absolue)
            if (!str_starts_with($firstSegmentUrl, 'http')) {
                if (str_starts_with($firstSegmentUrl, '/')) {
                    $firstSegmentUrl = 'https://tv.embmission.com' . $firstSegmentUrl;
                } else {
                    // Segment relatif : construire l'URL complète
                    $manifestBase = dirname($manifestUrl);
                    $firstSegmentUrl = $manifestBase . '/' . $firstSegmentUrl;
                }
            }

            // Vérifier que le segment existe vraiment (HEAD request rapide)
            // ✅ Tolérance : Vérifier jusqu'à 5 segments (les segments peuvent être en rotation dans un flux live)
            $segmentUrls = [$firstSegmentUrl];
            
            // Extraire plusieurs segments supplémentaires du manifest pour avoir des alternatives
            $segmentIndex = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                if ((strpos($line, '.ts') !== false || strpos($line, '.m4s') !== false) && $segmentIndex < 5) {
                    $segmentUrl = $line;
                    if (!str_starts_with($segmentUrl, 'http')) {
                        if (str_starts_with($segmentUrl, '/')) {
                            $segmentUrl = 'https://tv.embmission.com' . $segmentUrl;
                        } else {
                            // Segment relatif : construire l'URL complète basée sur le manifest
                            $manifestBase = dirname($manifestUrl);
                            $segmentUrl = $manifestBase . '/' . $segmentUrl;
                        }
                    }
                    if (!in_array($segmentUrl, $segmentUrls)) {
                        $segmentUrls[] = $segmentUrl;
                        $segmentIndex++;
                    }
                }
            }
            
            // ✅ Vérifier qu'au moins 1 segment est accessible (tolérance accrue pour les flux live)
            // Pour un flux live, les segments peuvent être en rotation, donc on accepte 1 segment valide
            $availableSegments = [];
            $checkedUrls = [];
            $maxChecks = min(3, count($segmentUrls)); // Vérifier jusqu'à 3 segments max (optimisation)
            
            foreach (array_slice($segmentUrls, 0, $maxChecks) as $segmentUrl) {
                try {
                    $checkedUrls[] = $segmentUrl;
                    $segmentCheck = Http::timeout(2)
                        ->withOptions([
                            'curl' => [
                                CURLOPT_FRESH_CONNECT => true,
                                CURLOPT_FORBID_REUSE => true,
                            ],
                            'headers' => [
                                'Connection' => 'close',
                            ],
                        ])
                        ->head($segmentUrl . (str_contains($segmentUrl, '?') ? '&' : '?') . 'nocache=' . time());
                    if ($segmentCheck->successful()) {
                        $availableSegments[] = $segmentUrl;
                        // ✅ Accepter dès qu'on a 1 segment valide (plus tolérant pour les flux live)
                        if (count($availableSegments) >= 1) {
                            Log::info('✅ Segment HLS vérifié - Live prêt', [
                                'stream_id' => $streamId,
                                'segment_url' => $segmentUrl,
                                'total_checked' => count($checkedUrls),
                                'segments_available' => count($availableSegments),
                            ]);
                            break;
                        }
                    } else {
                        Log::debug('⚠️ Segment HLS inaccessible', [
                            'stream_id' => $streamId,
                            'segment_url' => $segmentUrl,
                            'http_status' => $segmentCheck->status(),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::debug('⚠️ Erreur vérification segment HLS', [
                        'stream_id' => $streamId,
                        'segment_url' => $segmentUrl,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            // ✅ Accepter si au moins 1 segment est accessible (plus tolérant)
            if (count($availableSegments) < 1) {
                Log::info('⏳ Aucun segment HLS accessible pour confirmer le live', [
                    'stream_id' => $streamId,
                    'candidates_checked' => count($checkedUrls),
                    'manifest_url' => $manifestUrl,
                ]);
                Cache::forget($confirmationCacheKey);
                return false;
            }

            // ✅ Vérifier si le manifest est mis à jour (indique que le stream reçoit de nouvelles données)
            // Si le manifest n'a pas changé après 5-10 secondes, le stream est probablement arrêté
            $previousManifest = Cache::get($manifestContentCacheKey);
            $currentManifestHash = md5($body);
            
            if ($previousManifest) {
                $previousHash = $previousManifest['hash'] ?? null;
                $previousTime = $previousManifest['timestamp'] ?? 0;
                $timeSinceLastCheck = time() - $previousTime;
                
                // ✅ OPTIMISATION : Si le manifest n'a pas changé depuis plus de 5 secondes, le stream est probablement arrêté
                // (réduit de 10s à 5s pour détecter l'arrêt plus rapidement)
                if ($previousHash === $currentManifestHash && $timeSinceLastCheck > 5) {
                    Log::info('⏹️ Manifest HLS inchangé depuis ' . $timeSinceLastCheck . 's - Stream probablement arrêté', [
                        'stream_id' => $streamId,
                        'time_since_last_check' => $timeSinceLastCheck,
                    ]);
                    Cache::forget($confirmationCacheKey);
                    Cache::forget($manifestContentCacheKey);
                    return false;
                }
                
                // Si le manifest a changé, le stream est actif
                if ($previousHash !== $currentManifestHash) {
                    Log::debug('🔄 Manifest HLS mis à jour - Stream actif', [
                        'stream_id' => $streamId,
                        'time_since_last_check' => $timeSinceLastCheck,
                    ]);
                }
            }
            
            // Mettre à jour le cache avec le nouveau contenu du manifest
            Cache::put($manifestContentCacheKey, [
                'hash' => $currentManifestHash,
                'timestamp' => time(),
            ], 30);
            
            // ✅ Confirmation simplifiée : si le manifest a des segments valides et est mis à jour, on le considère comme prêt
            return true;
        } catch (\Exception $e) {
            Log::warning('⚠️ Impossible de valider le manifest HLS', [
                'stream_id' => $streamId,
                'error' => $e->getMessage(),
            ]);
            Cache::forget($confirmationCacheKey);
            return false;
        }
    }

    private function confirmManifestStability(string $streamId, array $segments): bool
    {
        $cacheKey = "live_manifest_ready_confirm_{$streamId}";
        $now = time();
        $state = Cache::get($cacheKey);

        if (!$state) {
            Cache::put($cacheKey, [
                'stage' => 1,
                'timestamp' => $now,
                'segments' => $segments,
            ], 15);
            return false;
        }

        $timeSince = $now - ($state['timestamp'] ?? 0);
        if ($timeSince < 2) {
            Cache::put($cacheKey, [
                'stage' => 1,
                'timestamp' => $now,
                'segments' => $segments,
            ], 15);
            return false;
        }

        Cache::forget($cacheKey);
        return true;
    }

    /**
     * Mettre le système en pause
     */
    public function pauseSystem(): array
    {
        try {
            Log::info("⏸️ Mise en pause du système automatique");

            $activePlaylist = WebTVPlaylist::where('is_active', true)->first();

            if ($activePlaylist) {
                Cache::put('webtv_paused_playlist_id', $activePlaylist->id, 3600);
                Log::info("📝 Playlist active sauvegardée avant pause", [
                    'playlist_id' => $activePlaylist->id,
                    'playlist_name' => $activePlaylist->name,
                ]);
            }

            Cache::put('webtv_system_paused', true, 3600);

            WebTVPlaylist::where('is_active', true)->update(['is_active' => false]);

            return [
                'success' => true,
                'message' => 'Système mis en pause avec succès',
            ];
        } catch (\Exception $e) {
            Log::error("❌ Erreur mise en pause: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erreur lors de la mise en pause: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Reprendre le système automatique
     */
    public function resumeSystem(): array
    {
        try {
            Log::info("▶️ Reprise du système automatique");

            $pausedPlaylistId = Cache::get('webtv_paused_playlist_id');

            Cache::forget('webtv_system_paused');
            Cache::forget('webtv_paused_playlist_id');

            if ($pausedPlaylistId) {
                $playlist = WebTVPlaylist::find($pausedPlaylistId);

                if ($playlist) {
                    $playlist->update(['is_active' => true]);
                    Log::info("✅ Playlist réactivée après reprise", [
                        'playlist_id' => $playlist->id,
                        'playlist_name' => $playlist->name,
                    ]);

                    return [
                        'success' => true,
                        'message' => 'Système repris avec succès - Playlist réactivée',
                        'playlist_reactivated' => true,
                        'playlist_id' => $playlist->id,
                    ];
                }

                Log::warning("⚠️ Playlist sauvegardée introuvable", [
                    'playlist_id' => $pausedPlaylistId,
                ]);
            } else {
                Log::warning("⚠️ Aucune playlist sauvegardée trouvée lors de la reprise");
            }

            return [
                'success' => true,
                'message' => 'Système repris avec succès',
                'playlist_reactivated' => false,
            ];
        } catch (\Exception $e) {
            Log::error("❌ Erreur reprise système: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erreur lors de la reprise: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Obtenir le prochain élément VoD de la playlist active
     */
    public function getNextVodItem(): array
    {
        try {
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
            $cacheKey = "webtv_position_{$sessionId}";
            
            // Récupérer la position actuelle depuis le cache
            $currentPosition = Cache::get($cacheKey, 0);
            
            // Calculer la prochaine position
            $nextPosition = ($currentPosition + 1) % $vodItems->count();
            
            // Sauvegarder la nouvelle position
            Cache::put($cacheKey, $nextPosition, now()->addHours(24));
            
            $nextItem = $vodItems[$nextPosition];
            
            // Vérifier que le VoD est disponible
            $vodInfo = $this->vodService->checkVoD($nextItem);
            
            if ($vodInfo['success']) {
                $streamUrl = $vodInfo['stream_url'] ?? ($vodInfo['direct_url'] ?? null);
                if (!$streamUrl) {
                    return [
                        'success' => false,
                        'message' => 'VoD disponible mais aucune URL de lecture fournie',
                    ];
                }

                Log::info("⏭️ Prochaine vidéo: {$nextItem->title} (position {$nextPosition})");
                
                return [
                    'success' => true,
                    'message' => 'Next VoD item found',
                    'url' => $streamUrl,
                    'stream_url' => $streamUrl,
                    'direct_url' => $streamUrl,
                    'item_title' => $nextItem->title,
                    'position' => $nextPosition + 1,
                    'total' => $vodItems->count(),
                    'playlist_name' => $activePlaylist->name
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'VoD suivant non disponible'
                ];
            }
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur récupération VoD suivant: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération du VoD suivant: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Surveiller et gérer le basculement automatique
     */
    public function monitorAutoSwitch(): array
    {
        // Pour l'instant, retourner simplement l'URL actuelle
        return $this->getCurrentPlaybackUrl();
    }
    
    /**
     * Obtenir le statut de la playlist automatique - Structure plate pour Flutter
     */
    public function getAutoPlaylistStatus(): array
    {
        try {
            if ($this->isSystemPaused()) {
                return [
                    'success' => true,
                    'message' => 'Statut récupéré avec succès',
                    'data' => [
                        'is_live' => false,
                        'mode' => 'paused',
                        'live_stream' => null,
                        'is_active' => false,
                        'current_url' => null,
                        'playlist_id' => null,
                        'playlist_name' => null,
                        'item_title' => null,
                        'timestamp' => time(),
                    ],
                ];
            }

            // 🎯 Récupérer les données sans la structure imbriquée
            $liveStatus = $this->checkLiveStatus();
            $currentPlayback = $this->getCurrentPlaybackUrl();
            
            // Extraire les données de la structure imbriquée
            $playbackData = $currentPlayback['data'] ?? $currentPlayback;
            
            return [
                'success' => true,
                'message' => 'Statut récupéré avec succès',
                'data' => [
                    'is_live' => $liveStatus['is_live'] ?? false,
                    'mode' => $playbackData['mode'] ?? 'unknown',
                    'live_stream' => $liveStatus['stream_id'] ?? null,
                    'is_active' => true,
                    'current_url' => $playbackData['url'] ?? null,
                    'playlist_id' => $playbackData['playlist_id'] ?? null,
                    'playlist_name' => $playbackData['playlist_name'] ?? null,
                    'item_title' => $playbackData['item_title'] ?? null,
                    'timestamp' => time()
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération du statut: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Nettoyer les connexions fantômes d'Ant Media
     */
    public function cleanupGhostConnections(): array
    {
        try {
            $streamId = 'R9rzvVzMPvCClU6s1562847982308692';
            
            // 🧹 Forcer un reset des connexions côté Ant Media
            $antMediaUrl = "http://localhost:5080/LiveApp/rest/v2/broadcasts/{$streamId}/reset-viewer-count";
            
            $response = Http::timeout(5)
                ->withBasicAuth('admin', 'Emb2024!')
                ->withOptions([
                    'curl' => [
                        CURLOPT_TCP_NODELAY => true,
                        CURLOPT_FRESH_CONNECT => true,
                        CURLOPT_FORBID_REUSE => true,
                    ],
                    'headers' => [
                        'Connection' => 'close',
                    ],
                ])
                ->post($antMediaUrl);
            
            if ($response->successful()) {
                // Vider aussi notre cache
                Cache::forget('live_status_check');
                
                return [
                    'success' => true,
                    'message' => 'Connexions fantômes nettoyées',
                    'timestamp' => time()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Impossible de nettoyer les connexions'
            ];
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur nettoyage connexions: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors du nettoyage: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtenir les statistiques détaillées des connexions
     */
    public function getDetailedConnectionStats(): array
    {
        try {
            $streamId = 'R9rzvVzMPvCClU6s1562847982308692';
            $antMediaUrl = "http://localhost:5080/LiveApp/rest/v2/broadcasts/{$streamId}";
            
            $response = Http::timeout(5)
                ->withBasicAuth('admin', 'Emb2024!')
                ->withOptions([
                    'curl' => [
                        CURLOPT_TCP_NODELAY => true,
                        CURLOPT_FRESH_CONNECT => true,
                        CURLOPT_FORBID_REUSE => true,
                    ],
                    'headers' => [
                        'Connection' => 'close',
                    ],
                ])
                ->get($antMediaUrl);
            
            if ($response->successful()) {
                $stream = $response->json();
                
                return [
                    'success' => true,
                    'data' => [
                        'total_hls_viewers' => $stream['hlsViewerCount'] ?? 0,
                        'webrtc_viewers' => $stream['webRTCViewerCount'] ?? 0,
                        'rtmp_viewers' => $stream['rtmpViewerCount'] ?? 0,
                        'status' => $stream['status'] ?? 'unknown',
                        'start_time' => $stream['startTime'] ?? null,
                        'duration' => $stream['duration'] ?? 0,
                        'timestamp' => time()
                    ]
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Impossible de récupérer les statistiques'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Construire la séquence VoD exploitable par le builder unifié.
     */
    private function buildVodSequence(WebTVPlaylist $playlist): array
    {
        $items = $playlist->items()
            ->where('sync_status', 'synced')
            ->orderBy('order')
            ->get();

        $sequence = [];

        foreach ($items as $item) {
            if (!$item->ant_media_item_id) {
                Log::warning('⚠️ Item sans ant_media_item_id ignoré pour la séquence', [
                    'item_id' => $item->id,
                    'title' => $item->title,
                ]);
                continue;
            }

            $duration = $this->calculateVodDuration($item);
            if ($duration <= 0) {
                Log::warning('⚠️ Durée invalide pour l’item VoD', [
                    'item_id' => $item->id,
                    'title' => $item->title,
                ]);
                continue;
            }

            $streamUrl = $this->resolveStreamUrl($item);
            if (!$streamUrl) {
                Log::warning('⚠️ Stream URL introuvable pour l’item', [
                    'item_id' => $item->id,
                    'title' => $item->title,
                ]);
                continue;
            }

            $sequence[] = [
                'item_id' => $item->id,
                'title' => $item->title,
                'ant_media_item_id' => $item->ant_media_item_id,
                'stream_url' => $streamUrl,
                'duration' => $duration,
            ];
        }

        return $sequence;
    }

    private function resolveStreamUrl(WebTVPlaylistItem $item): ?string
    {
        $paths = $this->getVodPaths($item);

        if ($paths['type'] === 'shaka') {
            return sprintf(
                'https://tv.embmission.com/hls/streams/%s/master.m3u8',
                $item->ant_media_item_id
            );
        }

        if (!empty($item->stream_url)) {
            return $item->stream_url;
        }

        $vodInfo = $this->vodService->checkVoD($item);
        if (($vodInfo['success'] ?? false) && !empty($vodInfo['stream_url'])) {
            return $vodInfo['stream_url'];
        }

        if ($paths['type'] === 'legacy' && !empty($paths['playlist'])) {
            return sprintf(
                'https://tv.embmission.com/hls/streams/%s/%s',
                $item->ant_media_item_id,
                basename($paths['playlist'])
            );
        }

        if ($item->ant_media_item_id) {
            return sprintf(
                'https://tv.embmission.com/hls/streams/%s/playlist.m3u8',
                $item->ant_media_item_id
            );
        }

        return null;
    }

    private function calculateVodDuration(WebTVPlaylistItem $item): float
    {
        $paths = $this->getVodPaths($item);

        if ($paths['type'] === 'shaka' && isset($paths['video'])) {
            return $this->sumPlaylistDurations($paths['video']);
        }

        if ($paths['type'] === 'legacy' && isset($paths['playlist'])) {
            return $this->sumPlaylistDurations($paths['playlist']);
        }

        Log::warning('⚠️ Playlist HLS introuvable pour calcul de durée', [
            'item_id' => $item->id,
            'playlist_path' => $paths['type'] === 'unknown' ? null : ($paths['playlist'] ?? null),
        ]);

        return 0.0;
    }

    private function getVodPlaylistPath(WebTVPlaylistItem $item): ?string
    {
        $paths = $this->getVodPaths($item);

        if ($paths['type'] === 'shaka') {
            return $paths['master'] ?? null;
        }

        if ($paths['type'] === 'legacy') {
            return $paths['playlist'] ?? null;
        }

        return null;
    }

    /**
     * @return array{type: string, master?: string, video?: string, audio?: string, playlist?: string}
     */
    private function getVodPaths(WebTVPlaylistItem $item): array
    {
        if (!$item->ant_media_item_id) {
            return ['type' => 'unknown'];
        }

        $basePath = $this->antMediaStreamsPath . '/' . $item->ant_media_item_id;

        $shakaPaths = [
            'master' => $basePath . '/master.m3u8',
            'video' => $basePath . '/video.m3u8',
            'audio' => $basePath . '/audio.m3u8',
        ];

        if (is_file($shakaPaths['master']) && is_file($shakaPaths['video'])) {
            return array_merge(['type' => 'shaka'], $shakaPaths);
        }

        $legacyCandidates = [
            $basePath . '/playlist.m3u8',
            $basePath . '/index.m3u8',
        ];

        foreach ($legacyCandidates as $candidate) {
            if (is_file($candidate)) {
                return [
                    'type' => 'legacy',
                    'playlist' => $candidate,
                ];
            }
        }

        return ['type' => 'unknown'];
    }

    private function sumPlaylistDurations(string $playlistPath): float
    {
        $lines = @file($playlistPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($lines as $line) {
            if (str_starts_with($line, '#EXTINF:')) {
                $parts = explode(':', $line, 2);
                if (isset($parts[1])) {
                    $durationPart = $parts[1];
                    $commaPos = strpos($durationPart, ',');
                    if ($commaPos !== false) {
                        $durationPart = substr($durationPart, 0, $commaPos);
                    }

                    $value = (float) trim($durationPart);
                    if ($value > 0) {
                        $total += $value;
                    }
                }
            }
        }

        return $total;
    }

    private function shuffleSequence(int $playlistId, array $sequence, int $cycleIndex): array
    {
        if (count($sequence) <= 1) {
            return array_values($sequence);
        }

        $shuffled = array_values($sequence);
        usort($shuffled, static function (array $a, array $b) use ($playlistId, $cycleIndex) {
            $hashA = hash('sha1', $playlistId . ':' . $cycleIndex . ':' . $a['item_id']);
            $hashB = hash('sha1', $playlistId . ':' . $cycleIndex . ':' . $b['item_id']);

            return strcmp($hashA, $hashB);
        });

        return $shuffled;
    }

    /**
     * Ajoute les timings start/end aux éléments de la séquence.
     *
     * @return array{sequence: array<int, array<string, mixed>>, total_duration: float}
     */
    private function applySequenceTiming(array $sequence): array
    {
        $offset = 0.0;

        foreach ($sequence as &$item) {
            $item['start_time'] = $offset;
            $offset += $item['duration'];
            $item['end_time'] = $offset;
        }
        unset($item);

        return [
            'sequence' => $sequence,
            'total_duration' => $offset,
        ];
    }

    private function isSystemPaused(): bool
    {
        return Cache::get('webtv_system_paused', false) === true;
    }

    /**
     * Fournit un snapshot unifié (mode live/vod/pause/erreur) pour diffusion temps réel.
     */
    public function getRealtimeStatusSnapshot(): array
    {
        $timestamp = time();

        if ($this->isSystemPaused()) {
            return $this->normalizeSnapshot([
                'mode' => 'paused',
                'is_live' => false,
                'stream_url' => null,
                'stream_id' => null,
                'playlist_id' => null,
                'message' => 'Système en pause',
                'timestamp' => $timestamp,
            ]);
        }

        $liveStatus = $this->checkLiveStatus();
        if ($liveStatus['is_live'] ?? false) {
            $livePath = '/usr/local/antmedia/webapps/LiveApp/streams/live_transcoded.m3u8';

            if (is_file($livePath)) {
                // ✅ Utiliser unified.m3u8 pour le live (compatible VLC et navigateur)
                $liveUrl = 'https://tv.embmission.com/hls/streams/unified.m3u8';

                return $this->normalizeSnapshot([
                    'mode' => 'live',
                    'is_live' => true,
                    'stream_url' => $liveUrl,
                    'url' => $liveUrl,
                    'stream_id' => $liveStatus['stream_id'] ?? null,
                    'stream_name' => $liveStatus['stream_name'] ?? null,
                    'playlist_id' => null,
                    'message' => 'Live actif',
                    'timestamp' => $timestamp,
                    'sync_timestamp' => $timestamp,
                ]);
            }
        }

        $context = $this->getCurrentPlaybackContext();

        if (($context['success'] ?? false) !== true) {
            return $this->normalizeSnapshot([
                'mode' => 'error',
                'is_live' => false,
                'stream_url' => null,
                'stream_id' => null,
                'playlist_id' => null,
                'message' => $context['message'] ?? 'Contexte indisponible',
                'timestamp' => $timestamp,
            ]);
        }

        if (($context['mode'] ?? null) === 'live') {
            $live = $context['live'] ?? [];
            $livePath = '/usr/local/antmedia/webapps/LiveApp/streams/live_transcoded.m3u8';

            if (!is_file($livePath)) {
                // Si Ant Media dit "live" mais l'HLS n'est pas prêt, on retombe sur la branche VOD.
                $context['mode'] = 'vod';
            } else {
                // ✅ Utiliser unified.m3u8 pour le live (compatible VLC et navigateur)
                $unifiedUrl = 'https://tv.embmission.com/hls/streams/unified.m3u8';
                return $this->normalizeSnapshot([
                    'mode' => 'live',
                    'is_live' => true,
                    'stream_url' => $unifiedUrl,
                    'url' => $unifiedUrl,
                    'stream_id' => $live['stream_id'] ?? null,
                    'stream_name' => $live['stream_name'] ?? null,
                    'playlist_id' => null,
                    'message' => 'Live actif (contexte)',
                    'timestamp' => $timestamp,
                    'sync_timestamp' => $timestamp,
                ]);
            }
        }

        $playlist = $context['playlist'] ?? null;
        $currentItem = $context['current_item'] ?? null;

        if (!$currentItem || empty($currentItem['stream_url'])) {
            return $this->normalizeSnapshot([
                'mode' => 'error',
                'is_live' => false,
                'stream_url' => null,
                'stream_id' => null,
                'playlist_id' => $playlist['id'] ?? null,
                'message' => 'VoD indisponible ou URL manquante',
                'timestamp' => $timestamp,
            ]);
        }

        return $this->normalizeSnapshot([
            'mode' => 'vod',
            'is_live' => false,
            'stream_url' => $currentItem['stream_url'],
            'url' => $currentItem['stream_url'],
            'stream_id' => $currentItem['ant_media_item_id'] ?? null,
            'playlist_id' => $playlist['id'] ?? null,
            'playlist_name' => $playlist['name'] ?? null,
            'item_id' => $currentItem['item_id'] ?? null,
            'item_title' => $currentItem['title'] ?? null,
            'duration' => $currentItem['duration'] ?? null,
            'current_time' => $currentItem['current_time'] ?? null,
            'is_finished' => $currentItem['is_finished'] ?? false,
            'message' => 'VoD en lecture',
            'timestamp' => $timestamp,
            'sync_timestamp' => $context['context_timestamp'] ?? $timestamp,
        ]);
    }

    /**
     * Garantit la présence des clés essentielles pour les snapshots SSE.
     */
    private function normalizeSnapshot(array $snapshot): array
    {
        $snapshot['mode'] = $snapshot['mode'] ?? 'unknown';

        $streamUrl = $snapshot['stream_url'] ?? $snapshot['current_url'] ?? $snapshot['url'] ?? null;
        $snapshot['stream_url'] = $streamUrl;
        $snapshot['url'] = $snapshot['url'] ?? $streamUrl;
        $snapshot['current_url'] = $snapshot['current_url'] ?? $streamUrl;

        if ($snapshot['stream_url'] === null) {
            $snapshot['url'] = null;
            $snapshot['current_url'] = null;
        }

        return $snapshot;
    }

    /**
     * Anti-spam pour les snapshots forcés (éviter plusieurs événements identiques en quelques secondes)
     */
    private function shouldBroadcastSnapshot(string $mode): bool
    {
        $cacheKey = "webtv_snapshot_lock_{$mode}";
        if (Cache::has($cacheKey)) {
            return false;
        }

        Cache::put($cacheKey, true, now()->addSeconds(2));
        return true;
    }

    /**
     * Ajoute des métadonnées au payload lorsqu'il s'agit d'un snapshot forcé
     */
    private function appendSnapshotMetadata(array $payload, bool $snapshotForced, ?string $source = null): array
    {
        if (!$snapshotForced) {
            return $payload;
        }

        $payload['snapshot_forced'] = true;
        if ($source) {
            $payload['snapshot_source'] = $source;
        }

        return $payload;
    }

    /**
     * ✅ NOUVELLE MÉTHODE : Snapshot avec gestion de transition côté backend
     * 
     * Cette méthode gère la période de grâce lors des transitions live/VOD
     * pour éviter les changements de mode trop brusques.
     * 
     * @return array Snapshot avec mode stable pendant la transition
     */
    public function getRealtimeStatusSnapshotWithTransition(): array
    {
        $timestamp = time();
        $gracePeriodSeconds = 3; // Période de grâce de 3 secondes
        $transitionCacheKey = 'webtv_transition_state';
        $lastModeCacheKey = 'webtv_last_mode';

        if ($this->isSystemPaused()) {
            // Réinitialiser la transition en cas de pause
            Cache::forget($transitionCacheKey);
            return $this->normalizeSnapshot([
                'mode' => 'paused',
                'is_live' => false,
                'stream_url' => null,
                'stream_id' => null,
                'playlist_id' => null,
                'message' => 'Système en pause',
                'timestamp' => $timestamp,
            ]);
        }

        // Détecter le mode actuel
        $liveStatus = $this->checkLiveStatus();
        $currentMode = null;
        
        if ($liveStatus['is_live'] ?? false) {
            // Mode live détecté
            $livePath = '/usr/local/antmedia/webapps/LiveApp/streams/live_transcoded.m3u8';
            if (is_file($livePath)) {
                $currentMode = 'live';
            }
        }
        
        // Si pas de mode live, vérifier le contexte VOD
        if ($currentMode !== 'live') {
            $context = $this->getCurrentPlaybackContext();
            if (($context['success'] ?? false) === true && ($context['mode'] ?? null) === 'vod') {
                $playlist = $context['playlist'] ?? null;
                $currentItem = $context['current_item'] ?? null;
                
                if ($currentItem && !empty($currentItem['stream_url'])) {
                    // VOD valide disponible
                    $currentMode = 'vod';
                }
            }
        }

        // Récupérer le mode précédent et l'état de transition
        $lastMode = Cache::get($lastModeCacheKey);
        $transitionState = Cache::get($transitionCacheKey);

        // Détecter un changement de mode
        if ($lastMode !== null && $lastMode !== $currentMode && $currentMode !== null) {
            // Changement de mode détecté - démarrer la période de grâce
            Cache::put($transitionCacheKey, [
                'from_mode' => $lastMode,
                'to_mode' => $currentMode,
                'started_at' => $timestamp,
            ], 10);
            
            Log::info('🔄 Transition détectée - Période de grâce activée', [
                'from' => $lastMode,
                'to' => $currentMode,
            ]);
        }

        // Si en période de transition
        if ($transitionState !== null && is_array($transitionState)) {
            $transitionStart = $transitionState['started_at'] ?? 0;
            $elapsed = $timestamp - $transitionStart;
            $fromMode = $transitionState['from_mode'] ?? null;
            $toMode = $transitionState['to_mode'] ?? null;

            if ($elapsed < $gracePeriodSeconds) {
                // Pendant la période de grâce - retourner l'ancien mode
                Log::debug('⏳ Transition en cours - Maintien de l\'ancien mode', [
                    'from' => $fromMode,
                    'to' => $toMode,
                    'elapsed' => $elapsed,
                    'grace_period' => $gracePeriodSeconds,
                ]);
                
                // Retourner le snapshot avec l'ancien mode
                return $this->getSnapshotForMode($fromMode, $timestamp);
            } else {
                // Période de grâce terminée - confirmer le nouveau mode
                Cache::forget($transitionCacheKey);
                Cache::put($lastModeCacheKey, $toMode, 3600);
                
                Log::info('✅ Transition confirmée - Nouveau mode actif', [
                    'new_mode' => $toMode,
                ]);
                
                return $this->getSnapshotForMode($toMode, $timestamp);
            }
        }

        // Pas de transition - mode stable
        if ($currentMode !== null) {
            Cache::put($lastModeCacheKey, $currentMode, 3600);
            Cache::forget($transitionCacheKey); // Réinitialiser si pas de transition
        }

        // Retourner le snapshot avec le mode actuel
        return $this->getSnapshotForMode($currentMode, $timestamp);
    }

    /**
     * ✅ Helper : Génère un snapshot pour un mode donné
     * 
     * @param string|null $mode Mode ('live', 'vod', ou null pour 'error')
     * @param int $timestamp Timestamp actuel
     * @return array Snapshot normalisé
     */
    private function getSnapshotForMode(?string $mode, int $timestamp): array
    {
        if ($mode === 'live') {
            $liveStatus = $this->checkLiveStatus();
            $livePath = '/usr/local/antmedia/webapps/LiveApp/streams/live_transcoded.m3u8';

            if (is_file($livePath) && ($liveStatus['is_live'] ?? false)) {
                $liveUrl = 'https://tv.embmission.com/hls/streams/unified.m3u8';
                return $this->normalizeSnapshot([
                    'mode' => 'live',
                    'is_live' => true,
                    'stream_url' => $liveUrl,
                    'url' => $liveUrl,
                    'stream_id' => $liveStatus['stream_id'] ?? null,
                    'stream_name' => $liveStatus['stream_name'] ?? null,
                    'playlist_id' => null,
                    'message' => 'Live actif',
                    'timestamp' => $timestamp,
                    'sync_timestamp' => $timestamp,
                ]);
            }
        }

        if ($mode === 'vod') {
            $context = $this->getCurrentPlaybackContext();
            if (($context['success'] ?? false) === true) {
                $playlist = $context['playlist'] ?? null;
                $currentItem = $context['current_item'] ?? null;

                if ($currentItem && !empty($currentItem['stream_url'])) {
                    return $this->normalizeSnapshot([
                        'mode' => 'vod',
                        'is_live' => false,
                        'stream_url' => $currentItem['stream_url'],
                        'url' => $currentItem['stream_url'],
                        'stream_id' => $currentItem['ant_media_item_id'] ?? null,
                        'playlist_id' => $playlist['id'] ?? null,
                        'playlist_name' => $playlist['name'] ?? null,
                        'item_id' => $currentItem['item_id'] ?? null,
                        'item_title' => $currentItem['title'] ?? null,
                        'duration' => $currentItem['duration'] ?? null,
                        'current_time' => $currentItem['current_time'] ?? null,
                        'is_finished' => $currentItem['is_finished'] ?? false,
                        'message' => 'VoD en lecture',
                        'timestamp' => $timestamp,
                        'sync_timestamp' => $context['context_timestamp'] ?? $timestamp,
                    ]);
                }
            }
        }

        // Mode indéterminé ou erreur
        return $this->normalizeSnapshot([
            'mode' => 'error',
            'is_live' => false,
            'stream_url' => null,
            'stream_id' => null,
            'playlist_id' => null,
            'message' => 'Mode indisponible',
            'timestamp' => $timestamp,
        ]);
    }
}