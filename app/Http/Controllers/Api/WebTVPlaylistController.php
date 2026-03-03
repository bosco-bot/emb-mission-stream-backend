<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CreateVodStreamJob;
use App\Models\WebTVPlaylist;
use App\Models\WebTVPlaylistItem;
use App\Services\AntMediaPlaylistService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class WebTVPlaylistController extends Controller
{
    /**
     * Afficher toutes les playlists WebTV
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = WebTVPlaylist::with(['items' => function($query) {
                $query->orderBy('order');
            }]);

            // Filtres optionnels
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $playlists = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Playlists WebTV récupérées avec succès',
                'data' => $playlists
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistController index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des playlists',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer ou mettre à jour une playlist WebTV avec synchronisation intelligente
     * 🔥 NOUVELLE FONCTIONNALITÉ : Gestion des doublons et synchronisation intelligente
     * ✅ OPTIMISATION : Batch insert pour performance
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'required|in:live,scheduled,loop',
                'is_active' => 'boolean',
                'is_loop' => 'boolean',
                'shuffle_enabled' => 'boolean',
                'is_auto_start' => 'boolean',
                'quality' => 'in:720p,1080p,4K',
                'bitrate' => 'integer|min:500|max:10000',
                'buffer_duration' => 'integer|min:1|max:60',
                'items' => 'nullable|array',
                'items.*.title' => 'required|string|max:255',
                'items.*.artist' => 'nullable|string|max:255',
                'items.*.duration' => 'integer|min:0',
                'items.*.quality' => 'in:720p,1080p,4K',
                'items.*.bitrate' => 'integer|min:500|max:10000',
                'items.*.video_file_id' => 'nullable|exists:media_files,id',
                'items.*.stream_url' => 'nullable|url|max:500',
                'items.*.is_live_stream' => 'boolean',
                'items.*.start_time' => 'nullable|date',
                'items.*.end_time' => 'nullable|date|after:items.*.start_time',
                'items.*.unique_id' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 🔥 RECHERCHE DE PLAYLIST EXISTANTE PAR NOM
            $existingPlaylist = WebTVPlaylist::where('name', $request->name)->first();
            
            if ($existingPlaylist) {
                $playlist = $existingPlaylist;
                $playlist->update($request->except(['items']));
                $action = 'updated';
                $message = 'Playlist WebTV mise à jour avec succès';
            } else {
                $playlistData = $request->except(['items']);
                $playlistData['is_active'] = true;
                $playlist = WebTVPlaylist::create($playlistData);
                $action = 'created';
                $message = 'Playlist WebTV créée avec succès';
            }

            // 🔥 SYNCHRONISATION AVEC ANT MEDIA SERVER
            $antMediaService = new AntMediaPlaylistService();
            $syncResult = [
                'success' => true,
                'message' => 'Synchronisation non exécutée',
                'queued' => 0,
                'skipped' => 0,
                'results' => [],
            ];

            // 🔥 GESTION INTELLIGENTE DES MÉDIAS
            $mediaResults = [
                'created' => [],
                'updated' => [],
                'deleted' => [],
                'errors' => []
            ];

            if ($request->has('items') && is_array($request->items)) {
                // ✅ OPTIMISATION: Récupération des médias existants en UNE requête
                $existingItems = $playlist->items()->get()->keyBy(function($item) {
                    return $item->unique_id ?? ($item->title . '_' . $item->artist);
                });
                // ✅ Idempotence batch: index supplémentaire par video_file_id (si présent)
                $existingByVideoFileId = $playlist->items()
                    ->whereNotNull('video_file_id')
                    ->get()
                    ->keyBy('video_file_id');

                // ✅ OPTIMISATION: Préparer batch insert pour nouveaux items
                $newItemsToInsert = [];
                $lastOrder = $playlist->items()->max('order') ?? 0;
                
                // 🔥 PREMIER PASSAGE : Séparer nouveaux items vs existants
                foreach ($request->items as $index => $itemData) {
                    try {
                        $itemUniqueId = $itemData['unique_id'] ?? ($itemData['title'] . '_' . ($itemData['artist'] ?? ''));
                        $videoFileId = $itemData['video_file_id'] ?? null;
                        
                        // 🔒 Idempotence 1: si video_file_id déjà présent pour cette playlist → ne pas recréer
                        if ($videoFileId && isset($existingByVideoFileId[$videoFileId])) {
                            $already = $existingByVideoFileId[$videoFileId];
                            // Si déjà en cours ou déjà prêt, on renvoie comme "existant" et on ne recrée pas
                            $mediaResults['updated'][] = [
                                'item' => $already->fresh(),
                                'action' => 'existing',
                                'queued_for_sync' => false,
                            ];
                            // Retirer aussi de $existingItems si la clé unique correspond pour éviter suppression
                            $existingKey = $already->unique_id ?? ($already->title . '_' . ($already->artist ?? ''));
                            unset($existingItems[$existingKey]);
                            continue;
                        }
                        
                        if (isset($existingItems[$itemUniqueId])) {
                            // 🔥 MÉDIA EXISTANT : MISE À JOUR + remise en file de synchro
                            $existingItem = $existingItems[$itemUniqueId];
                            // Idempotence 2: si l'item est déjà pending/queued/processing/synced, ne pas relancer inutilement
                            if (in_array($existingItem->sync_status, ['pending', 'queued', 'processing', 'synced'])) {
                                $existingItem->update([
                                    'order' => $index + 1,
                                ]);
                                $mediaResults['updated'][] = [
                                    'item' => $existingItem->fresh(),
                                    'action' => 'existing',
                                    'queued_for_sync' => false,
                                ];
                                unset($existingItems[$itemUniqueId]);
                                continue;
                            }
                            
                            $existingItem->update(array_merge($itemData, [
                                'order' => $index + 1,
                                'sync_status' => 'pending',
                            ]));

                            $mediaResults['updated'][] = [
                                'item' => $existingItem->fresh(),
                                'action' => 'updated',
                                'queued_for_sync' => true,
                            ];
                            
                            unset($existingItems[$itemUniqueId]);
                        } else {
                            // ✅ NOUVEAU MÉDIA : Préparer pour BATCH INSERT
                            $lastOrder++;
                            $newItemsToInsert[] = [
                                'webtv_playlist_id' => $playlist->id,
                                'video_file_id' => $itemData['video_file_id'] ?? null,
                                'stream_url' => $itemData['stream_url'] ?? null,
                                'title' => $itemData['title'],
                                'artist' => $itemData['artist'] ?? null,
                                'order' => $lastOrder,
                                'duration' => $itemData['duration'] ?? null,
                                'quality' => $itemData['quality'] ?? null,
                                //'bitrate' => $itemData['bitrate'] ?? null,
                                'bitrate' => $itemData['bitrate'] ?? 2500,
                                'is_live_stream' => $itemData['is_live_stream'] ?? false,
                                'start_time' => $itemData['start_time'] ?? null,
                                'end_time' => $itemData['end_time'] ?? null,
                                'unique_id' => $itemUniqueId,
                                'sync_status' => 'pending',
                                'created_at' => now(),
                                'updated_at' => now()
                            ];
                        }
                        
                    } catch (\Exception $e) {
                        Log::error("Erreur traitement média", [
                            'item_data' => $itemData,
                            'error' => $e->getMessage()
                        ]);
                        
                        $mediaResults['errors'][] = [
                            'item_data' => $itemData,
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
                // ✅ BATCH INSERT pour tous les nouveaux items en UNE SEULE requête
                if (!empty($newItemsToInsert)) {
                    WebTVPlaylistItem::insert($newItemsToInsert);
                    
                    Log::info("✅ Batch insert réussi", [
                        'count' => count($newItemsToInsert),
                        'webtv_playlist_id' => $playlist->id
                    ]);
                    
                    // Récupérer les items créés pour synchronisation VoD
                    $insertedItems = $playlist->items()
                        ->whereIn('unique_id', array_column($newItemsToInsert, 'unique_id'))
                        ->get();
                    
                    // Lier immédiatement un VoD pré-converti si disponible (vod_mf_{video_file_id}), sinon pending
                    foreach ($insertedItems as $item) {
                        $linked = false;
                        try {
                            if ($item->video_file_id) {
                                $vodService = new \App\Services\AntMediaVoDService();
                                $vodName = $vodService->buildVodNameForMediaFile((int) $item->video_file_id);
                                $playlistPath = '/usr/local/antmedia/webapps/LiveApp/streams/' . $vodName . '/playlist.m3u8';
                                if (file_exists($playlistPath)) {
                                    $streamUrl = $vodService->buildHlsUrlFromVodName($vodName);
                                    $item->update([
                                        'ant_media_item_id' => $vodName,
                                        'stream_url' => $streamUrl,
                                        'sync_status' => 'synced',
                                    ]);
                                    \Log::info("✅ Item batch lié à VoD pré-converti", [
                                        'item_id' => $item->id,
                                        'vod_name' => $vodName,
                                    ]);
                                    $linked = true;
                                }
                            }
                        } catch (\Throwable $e) {
                            \Log::warning("⚠️ Lien VoD pré-converti (batch) impossible", [
                                'item_id' => $item->id,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        if ($linked) {
                            $mediaResults['created'][] = [
                                'item' => $item->fresh(),
                                'action' => 'created',
                                'sync_status' => 'synced',
                                'queued' => false,
                            ];
                        } else {
                            $item->update(['sync_status' => 'pending']);
                        $mediaResults['created'][] = [
                            'item' => $item->fresh(),
                            'action' => 'created',
                                'sync_status' => 'pending',
                                'queued' => true,
                        ];
                        }
                    }
                }

                // 🔥 SUPPRESSION DES MÉDIAS
                foreach ($existingItems as $uniqueId => $itemToDelete) {
                    try {
                        if ($itemToDelete->ant_media_item_id) {
                            $antMediaService->deleteItemFromPlaylist($itemToDelete);
                        }
                        $itemToDelete->delete();
                        
                        $mediaResults['deleted'][] = [
                            'item_id' => $itemToDelete->id,
                            'title' => $itemToDelete->title,
                            'unique_id' => $uniqueId
                        ];
                    } catch (\Exception $e) {
                        Log::error("Erreur suppression média", [
                            'item_id' => $itemToDelete->id,
                            'error' => $e->getMessage()
                        ]);
                        
                        $mediaResults['errors'][] = [
                            'item_id' => $itemToDelete->id,
                            'action' => 'delete',
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
                // Mettre à jour les totaux
                $playlist->updateTotals();
            }

            // CORRECTION: Utiliser la queue ASYNCHRONE pour synchroniser les nouveaux items
            // Les nouveaux items sont dispatchés via CreateVodStreamJob
            // On synchronise seulement les items existants qui ont été mis à jour et sont en 'pending'
            // Les lecteurs continuent avec les items DÉJÀ synchronisés pendant ce temps
            $hasNewItems = !empty($mediaResults['created']);
            
            if ($hasNewItems) {
                // Les nouveaux items sont déjà dispatchés via CreateVodStreamJob (queue asynchrone)
                // On synchronise aussi les items existants qui ont été mis à jour et sont en 'pending'
                // (ceux qui n'ont pas été créés dans cette requête mais qui sont en 'pending')
                $newItemIds = collect($mediaResults['created'])->pluck('item.id')->filter()->toArray();
                $pendingItems = $playlist->items()
                    ->where('sync_status', 'pending')
                    ->when(!empty($newItemIds), function($query) use ($newItemIds) {
                        return $query->whereNotIn('id', $newItemIds);
                    })
                    ->get();
                
                if ($pendingItems->count() > 0) {
                    Log::info("🔄 Dispatch des items mis à jour pour synchronisation", [
                        'count' => $pendingItems->count(),
                        'item_ids' => $pendingItems->pluck('id')->toArray(),
                    ]);
                    
                    foreach ($pendingItems as $item) {
                        try {
                            CreateVodStreamJob::dispatch($item->id);
                        } catch (\Exception $e) {
                            Log::error("❌ Erreur dispatch item mis à jour", [
                                'item_id' => $item->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                
                $syncResult = [
                    'success' => true,
                    'message' => 'Items dispatchés pour synchronisation asynchrone',
                    'queued_items' => count($mediaResults['created']) + $pendingItems->count(),
                ];
            } else {
                // Pas de nouveaux items, utiliser la synchronisation normale pour les items 'pending'
                $shouldSyncAsync = ($action === 'updated'); // Async seulement pour les mises à jour
                $syncResult = $antMediaService->syncPlaylist($playlist, $shouldSyncAsync);
            }
            
            // Si c'est une création et que la synchronisation synchrone a réussi, forcer la génération du flux
            if ($action === 'created' && ($syncResult['success'] ?? false)) {
                Log::info("✅ Playlist créée et synchronisée - Les lecteurs peuvent maintenant reprendre", [
                    'playlist_id' => $playlist->id,
                    'playlist_name' => $playlist->name,
                    'items_synced' => $playlist->items()->where('sync_status', 'synced')->count()
                ]);
            }

            if (!($syncResult['success'] ?? false)) {
                $playlist->update([
                    'sync_status' => 'error',
                    'last_sync_at' => now(),
                ]);
            }

            $response = response()->json([
                'success' => true,
                'message' => $message,
                'action' => $action,
                'data' => $playlist->load('items'),
                'ant_media_sync' => $syncResult,
                'media_sync_summary' => [
                    'created' => count($mediaResults['created']),
                    'updated' => count($mediaResults['updated']),
                    'deleted' => count($mediaResults['deleted']),
                    'errors' => count($mediaResults['errors'])
                ],
                'media_results' => $mediaResults,
                'sync_in_progress' => !empty($mediaResults['created']) && 
                    array_reduce($mediaResults['created'], function($carry, $item) {
                        return $carry || ($item['sync_status'] ?? null) === 'processing';
                    }, false),
            ], $action === 'created' ? 201 : 200);
            
            // Retourner la réponse normalement
            // La synchronisation se fait via CreateVodStreamJob (queue asynchrone)
            // Les lecteurs continuent avec les items déjà synchronisés
            // Les nouveaux items sont ajoutés progressivement au flux
            return $response;

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistController store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création/mise à jour de la playlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une playlist spécifique
     */
    public function show(WebTVPlaylist $webTVPlaylist): JsonResponse
    {
        try {
            $webTVPlaylist->load(['items' => function($query) {
                $query->orderBy('order');
            }]);

            return response()->json([
                'success' => true,
                'message' => 'Playlist WebTV récupérée avec succès',
                'data' => $webTVPlaylist
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistController show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la playlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour une playlist
     */
    public function update(Request $request, WebTVPlaylist $webTVPlaylist): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'type' => 'sometimes|in:live,scheduled,loop',
                'is_active' => 'boolean',
                'is_loop' => 'boolean',
                'shuffle_enabled' => 'boolean',
                'is_auto_start' => 'boolean',
                'quality' => 'in:720p,1080p,4K',
                'bitrate' => 'integer|min:500|max:10000',
                'buffer_duration' => 'integer|min:1|max:60',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            $webTVPlaylist->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Playlist WebTV mise à jour avec succès',
                'data' => $webTVPlaylist->load('items')
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistController update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la playlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une playlist
     */
    public function destroy(WebTVPlaylist $webTVPlaylist): JsonResponse
    {
        try {
            foreach ($webTVPlaylist->items as $item) {
                if ($item->ant_media_item_id) {
                    $antMediaService = new AntMediaPlaylistService();
                    $antMediaService->deleteItemFromPlaylist($item);
                }
                $item->delete();
            }
            
            $webTVPlaylist->delete();

            return response()->json([
                'success' => true,
                'message' => 'Playlist WebTV supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistController destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la playlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Basculer le statut actif/inactif
     */
    public function toggleStatus(WebTVPlaylist $webTVPlaylist): JsonResponse
    {
        try {
            $webTVPlaylist->update(['is_active' => !$webTVPlaylist->is_active]);

            return response()->json([
                'success' => true,
                'message' => 'Statut de la playlist WebTV basculé avec succès',
                'data' => $webTVPlaylist
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistController toggleStatus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques d'une playlist
     */
    public function getStats(WebTVPlaylist $webTVPlaylist): JsonResponse
    {
        try {
            $stats = [
                'total_items' => $webTVPlaylist->items()->count(),
                'total_duration' => $webTVPlaylist->items()->sum('duration'),
                'active_items' => $webTVPlaylist->items()->where('sync_status', 'synced')->count(),
                'pending_items' => $webTVPlaylist->items()->where('sync_status', 'pending')->count(),
                'error_items' => $webTVPlaylist->items()->where('sync_status', 'error')->count(),
                'average_quality' => $webTVPlaylist->items()->avg('bitrate'),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Statistiques de la playlist WebTV récupérées avec succès',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistController getStats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Synchroniser manuellement avec Ant Media Server
     */
    public function syncWithAntMedia(WebTVPlaylist $webTVPlaylist): JsonResponse
    {
        try {
            $antMediaService = new AntMediaPlaylistService();
            $syncResult = $antMediaService->syncPlaylist($webTVPlaylist);

            return response()->json([
                'success' => $syncResult['success'],
                'message' => $syncResult['message'],
                'data' => $syncResult,
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistController syncWithAntMedia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la synchronisation avec Ant Media Server',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
