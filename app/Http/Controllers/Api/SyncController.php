<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\Playlist;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function getSyncCommands(): JsonResponse
    {
        try {
            $audioFiles = MediaFile::where('file_type', 'audio')
                ->where('status', 'completed')
                ->get();

            $playlists = Playlist::with('items.mediaFile')->get();

            $commands = [
                'files' => [],
                'playlists' => []
            ];

            // Commandes pour l'upload des fichiers
            foreach ($audioFiles as $file) {
                $filePath = storage_path('app/media/' . $file->file_path);
                if (file_exists($filePath)) {
                    $commands['files'][] = [
                        'id' => $file->id,
                        'name' => $file->original_name,
                        'path' => $file->file_path,
                        'command' => "curl -X POST -H 'X-API-Key: 4c1ffa679f50abe5:2e5149b9a2a11f310f46e4ccfce73cd8' -F 'file=@$filePath' 'http://15.235.86.98:8080/api/station/1/files'"
                    ];
                }
            }

            // Commandes pour la création des playlists
            foreach ($playlists as $playlist) {
                $commands['playlists'][] = [
                    'id' => $playlist->id,
                    'name' => $playlist->name,
                    'command' => "curl -X POST -H 'X-API-Key: 4c1ffa679f50abe5:2e5149b9a2a11f310f46e4ccfce73cd8' -H 'Content-Type: application/json' -d '{\"name\": \"" . $playlist->name . "\", \"source\": \"songs\", \"loop\": " . ($playlist->is_loop ? 'true' : 'false') . ", \"shuffle\": " . ($playlist->is_shuffle ? 'true' : 'false') . "}' 'http://15.235.86.98:8080/api/station/1/playlists'"
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $commands,
                'message' => 'Commandes de synchronisation générées'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération des commandes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
