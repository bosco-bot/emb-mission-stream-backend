<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
                'is_auto_start' => 'boolean',
                'quality' => 'in:720p,1080p,4K',
                'bitrate' => 'integer|min:500|max:10000',
                'buffer_duration' => 'integer|min:1|max:60',
                // 🔥 NOUVEAU : Validation des médias
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
                // 🔥 NOUVEAU : Identifiant unique pour éviter les doublons
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
                // 🔥 PLAYLIST EXISTANTE : MISE À JOUR
                Log::info("Playlist existante trouvée, mise à jour", [
                    'playlist_id' => $existingPlaylist->id,
                    'name' => $request->name
                ]);
                
                $playlist = $existingPlaylist;
                $playlist->update($request->except(['items']));
                
                $action = 'updated';
                $message = 'Playlist WebTV mise à jour avec succès';
            } else {
                // 🔥 NOUVELLE PLAYLIST : CRÉATION
                Log::info("Nouvelle playlist à créer", ['name' => $request->name]);
                
                $playlistData = $request->except(['items']);
                $playlist = WebTVPlaylist::create($playlistData);
                
                $action = 'created';
                $message = 'Playlist WebTV créée avec succès';
            }

            // 🔥 SYNCHRONISATION AVEC ANT MEDIA SERVER
            $antMediaService = new AntMediaPlaylistService();
            $syncResult = $antMediaService->syncPlaylist($playlist);

            if (!$syncResult['success']) {
                $playlist->update([
                    'sync_status' => 'error',
                    'last_sync_at' => now(),
                ]);
            }

            // 🔥 GESTION INTELLIGENTE DES MÉDIAS
            $mediaResults = [
                'created' => [],
                'updated' => [],
                'deleted' => [],
                'errors' => []
            ];

            if ($request->has('items') && is_array($request->items)) {
                // 🔥 RÉCUPÉRATION DES MÉDIAS EXISTANTS
                $existingItems = $playlist->items()->get()->keyBy(function($item) {
                    // Utiliser unique_id si fourni, sinon title + artist
                    return $item->unique_id ?? ($item->title . '_' . $item->artist);
                });

                // 🔥 TRAITEMENT DES NOUVEAUX MÉDIAS
                foreach ($request->items as $index => $itemData) {
                    try {
                        // Générer un identifiant unique pour le média
                        $itemUniqueId = $itemData['unique_id'] ?? ($itemData['title'] . '_' . ($itemData['artist'] ?? ''));
                        
                        if (isset($existingItems[$itemUniqueId])) {
                            // 🔥 MÉDIA EXISTANT : MISE À JOUR
                            $existingItem = $existingItems[$itemUniqueId];
                            $existingItem->update(array_merge($itemData, ['order' => $index + 1]));
                            
                            // Synchronisation avec Ant Media Server
                            $itemSyncResult = $antMediaService->addItemToPlaylist($existingItem);
                            
                            $mediaResults['updated'][] = [
                                'item' => $existingItem->fresh(),
                                'sync_result' => $itemSyncResult,
                                'action' => 'updated'
                            ];
                            
                            Log::info("Média existant mis à jour", [
                                'item_id' => $existingItem->id,
                                'title' => $existingItem->title,
                                'unique_id' => $itemUniqueId
                            ]);
                        } else {
                            // 🔥 NOUVEAU MÉDIA : CRÉATION
                            $itemData['order'] = $index + 1;
                            $itemData['unique_id'] = $itemUniqueId;
                            
                            $item = $playlist->items()->create($itemData);
                            
                            // Synchronisation avec Ant Media Server
                            $itemSyncResult = $antMediaService->addItemToPlaylist($item);
                            
                            $mediaResults['created'][] = [
                                'item' => $item->fresh(),
                                'sync_result' => $itemSyncResult,
                                'action' => 'created'
                            ];
                            
                            Log::info("Nouveau média créé", [
                                'item_id' => $item->id,
                                'title' => $item->title,
                                'unique_id' => $itemUniqueId
                            ]);
                        }
                        
                        // Marquer comme traité
                        unset($existingItems[$itemUniqueId]);
                        
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

                // 🔥 SUPPRESSION DES MÉDIAS QUI NE SONT PLUS DANS LA LISTE
                foreach ($existingItems as $uniqueId => $itemToDelete) {
                    try {
                        // Supprimer de Ant Media Server si synchronisé
                        if ($itemToDelete->ant_media_item_id) {
                            $antMediaService->deleteStream($itemToDelete->ant_media_item_id);
                        }
                        
                        $itemToDelete->delete();
                        
                        $mediaResults['deleted'][] = [
                            'item_id' => $itemToDelete->id,
                            'title' => $itemToDelete->title,
                            'unique_id' => $uniqueId
                        ];
                        
                        Log::info("Média supprimé", [
                            'item_id' => $itemToDelete->id,
                            'title' => $itemToDelete->title,
                            'unique_id' => $uniqueId
                        ]);
                        
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

            return response()->json([
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
                'media_results' => $mediaResults
            ], $action === 'created' ? 201 : 200);

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






