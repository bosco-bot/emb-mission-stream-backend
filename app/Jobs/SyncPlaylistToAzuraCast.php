<?php

namespace App\Jobs;

use App\Models\Playlist;
use App\Services\AzuraCastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SyncPlaylistToAzuraCast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $playlistId;

    public function __construct($playlistId)
    {
        $this->playlistId = $playlistId;
    }

    public function handle()
    {
        $playlist = Playlist::with('items.mediaFile')->find($this->playlistId);

        try {
            DB::beginTransaction();
            $playlist->load('items.mediaFile');

            if ($playlist->items->count() === 0) {
                $azuracastService = new AzuraCastService();

                if ($playlist->azuracast_id && $playlist->azuracast_id !== 999) {
                    $azuracastService->clearPlaylist($playlist->azuracast_id);
                    $azuracastService->restartBackend();
                }

                DB::commit();
                return;
            }

            $azuracastService = new AzuraCastService();
            $options = ['order' => $playlist->is_shuffle ? 'shuffle' : 'sequential'];

            if ($playlist->azuracast_id && $playlist->azuracast_id !== 999) {
                $result = $azuracastService->updatePlaylist($playlist->azuracast_id, $playlist->name, $options);
            } else {
                $result = $azuracastService->createPlaylist($playlist->name, $options);
            }

            $azuracastId = $result['id'] ?? null;
            if (!$azuracastId) throw new \Exception('ID playlist AzuraCast manquant');

            $playlist->update(['azuracast_id' => $azuracastId, 'sync_status' => 'synced', 'last_sync_at' => now()]);

            $syncedCount = 0; $errorCount = 0; $uploadedFiles = [];

            foreach ($playlist->items as $item) {
                try {
                    $mediaFile = $item->mediaFile;
                    if (!$mediaFile) continue;

                    $existingMedia = $azuracastService->findMediaByFilename($mediaFile->filename);

                    if (!$existingMedia) {
                        $localPath = storage_path('app/media/' . $mediaFile->file_path);
                        if (file_exists($localPath)) {
                            $azuracastService->copyMediaToDisk($localPath, $mediaFile->filename);
                            $uploadedFiles[] = $mediaFile->filename;
                        }
                    }

                    $item->update(['sync_status' => 'pending']);
                    $syncedCount++;
                } catch (\Exception $e) {
                    $item->update(['sync_status' => 'error']);
                    $errorCount++;
                }
            }

            if (count($uploadedFiles) > 0) {
                $azuracastService->triggerMediaScan();
                
                // Dispatcher le job de finalisation pour ajouter les médias aux playlists
                FinalizeAzuraCastSync::dispatch($playlist->id)->delay(now()->addMinutes(2));
            }

            DB::commit();
            $playlist->load('items.mediaFile');

            return response()->json([
                'success' => true,
                'message' => 'Playlist synchronisée avec AzuraCast avec succès',
                'data' => [
                    'playlist' => $playlist,
                    'azuracast_playlist_id' => $azuracastId,
                    'sync_stats' => [
                        'total_items' => $playlist->items->count(),
                        'synced_items' => $syncedCount,
                        'error_items' => $errorCount,
                        'uploaded_files' => count($uploadedFiles)
                    ],
                    'note' => count($uploadedFiles) > 0 ? 'Fichiers uploadés, scan en cours (1-2 min)' : 'Fichiers déjà présents'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $playlist->update(['sync_status' => 'error', 'last_sync_at' => now()]);
            return response()->json(['success' => false, 'message' => 'Erreur lors de la synchronisation', 'error' => $e->getMessage()], 500);
        }

    }
}
