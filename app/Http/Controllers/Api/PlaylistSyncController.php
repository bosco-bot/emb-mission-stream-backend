<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Models\MediaFile;
use App\Services\AzuraCastSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PlaylistSyncController extends Controller
{
    /**
     * Synchronise une playlist avec AzuraCast
     */
    public function syncPlaylist(Playlist $playlist): JsonResponse
    {
        try {
            $syncService = new AzuraCastSyncService();
            $result = $syncService->syncPlaylist($playlist);

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la synchronisation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Copie un fichier vers AzuraCast
     */
    public function copyFile(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'media_file_id' => 'required|integer|exists:media_files,id'
            ]);

            $mediaFile = MediaFile::findOrFail($request->media_file_id);
            $syncService = new AzuraCastSyncService();
            $result = $syncService->copyFileToAzuraCast($mediaFile);

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la copie',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déclenche le scan des médias
     */
    public function scanMedia(): JsonResponse
    {
        try {
            $syncService = new AzuraCastSyncService();
            $result = $syncService->scanMedia();

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du scan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les fichiers disponibles dans AzuraCast
     */
    public function getFiles(): JsonResponse
    {
        try {
            $syncService = new AzuraCastSyncService();
            $result = $syncService->getAvailableFiles();

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
