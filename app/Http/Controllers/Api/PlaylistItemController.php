<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Services\AzuraCastSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PlaylistItemController extends Controller
{
    /**
     * Ajoute un item à une playlist et synchronise
     */
    public function addItem(Request $request, Playlist $playlist): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'media_file_id' => 'required|integer|exists:media_files,id',
                'order' => 'nullable|integer',
                'auto_sync' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Vérification des doublons
            $exists = PlaylistItem::where('playlist_id', $playlist->id)
                ->where('media_file_id', $request->media_file_id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce fichier est déjà dans la playlist'
                ], 409);
            }

            // Création de l'item
            $playlistItem = PlaylistItem::create([
                'playlist_id' => $playlist->id,
                'media_file_id' => $request->media_file_id,
                'order' => $request->order ?? ($playlist->items->count() + 1)
            ]);

            $playlistItem->load('mediaFile');

            // Recalculer les stats de la playlist
            $playlist->recalculateStats();

            // Synchronisation automatique si demandée (version rapide sans copie/scan)
            $syncResult = null;
            if ($request->input('auto_sync', true)) {
                $syncService = new AzuraCastSyncService();
                // ✅ Utiliser la synchronisation rapide (pas de copie/scan de fichiers)
                $syncResult = $syncService->syncPlaylistQuick($playlist);
                
                // CORRECTION: Redémarrer le conteneur stations via docker-compose
                // Plus complet que backend/restart - force l'arrêt complet de la lecture en cours
                // Cohérent avec removeItem()
                if ($playlist->azuracast_id) {
                    try {
                        $azuraCastService = new \App\Services\AzuraCastService();
                        $restartResult = $azuraCastService->restartStationsContainer();
                        Log::info("Conteneur stations redémarré après ajout d'item", [
                            'playlist_id' => $playlist->id,
                            'playlist_name' => $playlist->name,
                            'item_id' => $playlistItem->id,
                            'result' => $restartResult
                        ]);
                    } catch (\Exception $e) {
                        Log::error("Erreur lors du redémarrage du conteneur stations après ajout d'item: " . $e->getMessage(), [
                            'playlist_id' => $playlist->id,
                            'playlist_name' => $playlist->name,
                            'item_id' => $playlistItem->id
                        ]);
                        // Fallback sur restartBackend() si docker-compose échoue
                        try {
                            $azuraCastService->restartBackend();
                            Log::info("Fallback: Backend redémarré via API après échec docker-compose");
                        } catch (\Exception $e2) {
                            Log::error("Échec du fallback restartBackend(): " . $e2->getMessage());
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $playlistItem,
                'sync' => $syncResult,
                'message' => 'Élément ajouté à la playlist avec succès'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout à la playlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprime un item d'une playlist et synchronise
     */
    public function removeItem(Playlist $playlist, PlaylistItem $item, Request $request): JsonResponse
    {
        try {
            // Vérifier que l'item appartient bien à la playlist
            if ($item->playlist_id !== $playlist->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet élément n\'appartient pas à cette playlist'
                ], 403);
            }

            $item->delete();

            // Recalculer les stats de la playlist
            $playlist->recalculateStats();

            // Synchronisation AzuraCast à déclencher manuellement via l'endpoint dédié
            $syncResult = [
                'success' => true,
                'message' => 'MàJ AzuraCast non déclenchée automatiquement. Utilisez /api/playlists/{playlist}/update-m3u-restart si nécessaire.'
            ];

            return response()->json([
                'success' => true,
                'sync' => $syncResult,
                'message' => 'Élément supprimé de la playlist avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Met à jour l'ordre des items et synchronise
     */
    public function updateOrder(Request $request, Playlist $playlist): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'items' => 'nullable|array',
                'items.*.id' => 'required|integer|exists:playlist_items,id',
                'items.*.order' => 'required|integer',
                'auto_sync' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            $itemsData = $request->input('items', []);
            if (empty($itemsData)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Aucun élément à réordonner',
                    'sync' => null,
                ]);
            }

            // Vérifier que tous les items appartiennent à la playlist
            $itemIds = array_column($itemsData, 'id');
            $itemsBelongToPlaylist = PlaylistItem::whereIn('id', $itemIds)
                ->where('playlist_id', $playlist->id)
                ->count() === count($itemIds);

            if (!$itemsBelongToPlaylist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Un ou plusieurs éléments n\'appartiennent pas à cette playlist'
                ], 403);
            }

            // Mise à jour de l'ordre
            foreach ($itemsData as $itemData) {
                PlaylistItem::where('id', $itemData['id'])
                    ->where('playlist_id', $playlist->id)
                    ->update(['order' => $itemData['order']]);
            }

            // Recalculer les stats de la playlist
            $playlist->recalculateStats();

            // Synchronisation automatique si demandée (version rapide sans copie/scan)
            $syncResult = null;
            if ($request->input('auto_sync', true)) {
                $syncService = new AzuraCastSyncService();
                // ✅ Utiliser la synchronisation rapide (pas de copie/scan de fichiers)
                $syncResult = $syncService->syncPlaylistQuick($playlist);
            }

            return response()->json([
                'success' => true,
                'sync' => $syncResult,
                'message' => 'Ordre mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

