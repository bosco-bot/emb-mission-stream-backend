<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebTVPlaylist;
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
     * Créer une nouvelle playlist WebTV
     * 🔥 NOUVELLE FONCTIONNALITÉ : Création complète avec médias en un seul appel
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
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 🔥 CRÉATION DE LA PLAYLIST
            $playlistData = $request->except(['items']);
            $playlist = WebTVPlaylist::create($playlistData);

            // 🔥 SYNCHRONISATION AVEC ANT MEDIA SERVER
            $antMediaService = new AntMediaPlaylistService();
            $syncResult = $antMediaService->syncPlaylist($playlist);

            if (!$syncResult['success']) {
                $playlist->update([
                    'sync_status' => 'error',
                    'last_sync_at' => now(),
                ]);
            }

            // 🔥 CRÉATION DES MÉDIAS EN UN COUP (si fournis)
            $createdItems = [];
            if ($request->has('items') && is_array($request->items)) {
                foreach ($request->items as $index => $itemData) {
                    // Ajouter l'ordre automatiquement
                    $itemData['order'] = $index + 1;
                    
                    // Créer l'item
                    $item = $playlist->items()->create($itemData);
                    
                    // 🔥 SYNCHRONISATION AUTOMATIQUE AVEC ANT MEDIA SERVER
                    $itemSyncResult = $antMediaService->addItemToPlaylist($item);
                    
                    $createdItems[] = [
                        'item' => $item->fresh(),
                        'sync_result' => $itemSyncResult
                    ];
                }
                
                // Mettre à jour les totaux
                $playlist->updateTotals();
            }

            return response()->json([
                'success' => true,
                'message' => 'Playlist WebTV créée avec succès',
                'data' => $playlist->load('items'),
                'ant_media_sync' => $syncResult,
                'items_sync' => $createdItems
            ], 201);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistController store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la playlist',
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






