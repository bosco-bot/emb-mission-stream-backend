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

class WebTVPlaylistItemController extends Controller
{
    /**
     * Afficher tous les items d'une playlist WebTV
     */
    public function index(Request $request, WebTVPlaylist $webTVPlaylist): JsonResponse
    {
        try {
            $items = $webTVPlaylist->items()
                ->orderBy('order')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Items de la playlist WebTV récupérés avec succès',
                'data' => $items
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistItemController index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouvel item dans une playlist WebTV
     * 🔥 SYNCHRONISATION AUTOMATIQUE AVEC ANT MEDIA SERVER
     */
    public function store(Request $request, WebTVPlaylist $webTVPlaylist): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'video_file_id' => 'nullable|exists:media_files,id',
                'stream_url' => 'nullable|url|max:500',
                'title' => 'required|string|max:255',
                'artist' => 'nullable|string|max:255',
                'order' => 'integer|min:0',
                'duration' => 'integer|min:0',
                'quality' => 'in:720p,1080p,4K',
                'bitrate' => 'integer|min:500|max:10000',
                'is_live_stream' => 'boolean',
                'start_time' => 'nullable|date',
                'end_time' => 'nullable|date|after:start_time',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Si pas d'ordre spécifié, mettre à la fin
            if (!isset($request->order)) {
                $maxOrder = $webTVPlaylist->items()->max('order') ?? -1;
                $request->merge(['order' => $maxOrder + 1]);
            }

            // 🔥 CRÉATION DE L'ITEM DANS LARAVEL
            $item = $webTVPlaylist->items()->create($request->all());

            // 🔥 SYNCHRONISATION AUTOMATIQUE AVEC ANT MEDIA SERVER
            $antMediaService = new AntMediaPlaylistService();
            $syncResult = $antMediaService->addItemToPlaylist($item);

            // Mettre à jour les totaux de la playlist
            $webTVPlaylist->updateTotals();

            return response()->json([
                'success' => true,
                'message' => 'Item ajouté à la playlist WebTV avec succès',
                'data' => $item->fresh(), // Recharger l'item pour avoir les données mises à jour
                'ant_media_sync' => $syncResult
            ], 201);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistItemController store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout de l\'item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un item spécifique
     */
    public function show(WebTVPlaylist $webTVPlaylist, WebTVPlaylistItem $webTVPlaylistItem): JsonResponse
    {
        try {
            // Vérifier que l'item appartient à la playlist
            if ($webTVPlaylistItem->webtv_playlist_id !== $webTVPlaylist->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item non trouvé dans cette playlist'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Item de la playlist WebTV récupéré avec succès',
                'data' => $webTVPlaylistItem
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistItemController show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un item
     * 🔥 SYNCHRONISATION AUTOMATIQUE AVEC ANT MEDIA SERVER
     */
    public function update(Request $request, WebTVPlaylist $webTVPlaylist, WebTVPlaylistItem $webTVPlaylistItem): JsonResponse
    {
        try {
            // Vérifier que l'item appartient à la playlist
            if ($webTVPlaylistItem->webtv_playlist_id !== $webTVPlaylist->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item non trouvé dans cette playlist'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'video_file_id' => 'nullable|exists:media_files,id',
                'stream_url' => 'nullable|url|max:500',
                'title' => 'sometimes|string|max:255',
                'artist' => 'nullable|string|max:255',
                'order' => 'integer|min:0',
                'duration' => 'integer|min:0',
                'quality' => 'in:720p,1080p,4K',
                'bitrate' => 'integer|min:500|max:10000',
                'is_live_stream' => 'boolean',
                'start_time' => 'nullable|date',
                'end_time' => 'nullable|date|after:start_time',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 🔥 MISE À JOUR DE L'ITEM DANS LARAVEL
            $webTVPlaylistItem->update($request->all());

            // 🔥 SYNCHRONISATION AUTOMATIQUE AVEC ANT MEDIA SERVER
            $antMediaService = new AntMediaPlaylistService();
            $syncResult = $antMediaService->addItemToPlaylist($webTVPlaylistItem);

            // Mettre à jour les totaux de la playlist
            $webTVPlaylist->updateTotals();

            return response()->json([
                'success' => true,
                'message' => 'Item mis à jour avec succès',
                'data' => $webTVPlaylistItem->fresh(), // Recharger l'item pour avoir les données mises à jour
                'ant_media_sync' => $syncResult
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistItemController update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un item
     * 🔥 SYNCHRONISATION AUTOMATIQUE AVEC ANT MEDIA SERVER
     */
    public function destroy(WebTVPlaylist $webTVPlaylist, WebTVPlaylistItem $webTVPlaylistItem): JsonResponse
    {
        try {
            // Vérifier que l'item appartient à la playlist
            if ($webTVPlaylistItem->webtv_playlist_id !== $webTVPlaylist->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item non trouvé dans cette playlist'
                ], 404);
            }

            // 🔥 SUPPRESSION DANS ANT MEDIA SERVER (si l'item était synchronisé)
            if ($webTVPlaylistItem->ant_media_item_id) {
                $antMediaService = new AntMediaPlaylistService();
                $deleteResult = $antMediaService->deleteStream($webTVPlaylistItem->ant_media_item_id);
                
                Log::info("Suppression du stream Ant Media pour l'item", [
                    'item_id' => $webTVPlaylistItem->id,
                    'ant_media_item_id' => $webTVPlaylistItem->ant_media_item_id,
                    'delete_result' => $deleteResult
                ]);
            }

            // 🔥 SUPPRESSION DE L'ITEM DANS LARAVEL
            $webTVPlaylistItem->delete();

            // Mettre à jour les totaux de la playlist
            $webTVPlaylist->updateTotals();

            return response()->json([
                'success' => true,
                'message' => 'Item supprimé de la playlist WebTV avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistItemController destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour l'ordre des items
     */
    public function updateOrder(Request $request, WebTVPlaylist $webTVPlaylist): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'items' => 'required|array',
                'items.*.id' => 'required|integer|exists:webtv_playlist_items,id',
                'items.*.order' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Mettre à jour l'ordre de chaque item
            foreach ($request->items as $itemData) {
                WebTVPlaylistItem::where('id', $itemData['id'])
                    ->where('webtv_playlist_id', $webTVPlaylist->id)
                    ->update(['order' => $itemData['order']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ordre des items mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistItemController updateOrder: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'ordre',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer un item comme synchronisé
     */
    public function markAsSynced(WebTVPlaylist $webTVPlaylist, WebTVPlaylistItem $webTVPlaylistItem, Request $request): JsonResponse
    {
        try {
            // Vérifier que l'item appartient à la playlist
            if ($webTVPlaylistItem->webtv_playlist_id !== $webTVPlaylist->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item non trouvé dans cette playlist'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'ant_media_item_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            $webTVPlaylistItem->markAsSynced($request->ant_media_item_id);

            return response()->json([
                'success' => true,
                'message' => 'Item marqué comme synchronisé avec succès',
                'data' => $webTVPlaylistItem
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistItemController markAsSynced: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage de l\'item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer un item comme erreur de synchronisation
     */
    public function markAsSyncError(WebTVPlaylist $webTVPlaylist, WebTVPlaylistItem $webTVPlaylistItem): JsonResponse
    {
        try {
            // Vérifier que l'item appartient à la playlist
            if ($webTVPlaylistItem->webtv_playlist_id !== $webTVPlaylist->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item non trouvé dans cette playlist'
                ], 404);
            }

            $webTVPlaylistItem->markAsSyncError();

            return response()->json([
                'success' => true,
                'message' => 'Item marqué comme erreur de synchronisation',
                'data' => $webTVPlaylistItem
            ]);

        } catch (\Exception $e) {
            Log::error('WebTVPlaylistItemController markAsSyncError: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage de l\'item',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}








