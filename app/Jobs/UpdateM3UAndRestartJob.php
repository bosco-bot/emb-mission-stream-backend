<?php

namespace App\Jobs;

use App\Models\Playlist;
use App\Services\AzuraCastSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateM3UAndRestartJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $playlistId;
    public int $timeout = 600; // 10 minutes max
    public int $tries = 1; // Une seule tentative (pas de retry automatique)

    public function __construct(int $playlistId)
    {
        $this->playlistId = $playlistId;
    }

    public function handle(): void
    {
        $playlist = Playlist::find($this->playlistId);

        if (!$playlist) {
            Log::error("UpdateM3UAndRestartJob: Playlist introuvable", [
                'playlist_id' => $this->playlistId
            ]);
            return;
        }

        if (!$playlist->azuracast_id) {
            Log::error("UpdateM3UAndRestartJob: Playlist sans azuracast_id", [
                'playlist_id' => $this->playlistId,
                'playlist_name' => $playlist->name
            ]);
            return;
        }

        Log::info("UpdateM3UAndRestartJob: Début de la synchronisation", [
            'playlist_id' => $this->playlistId,
            'playlist_name' => $playlist->name
        ]);

        // ✅ Mettre à jour last_sync_at au début pour que l'API détecte que la synchronisation est en cours
        $playlist->update([
            'last_sync_at' => now(),
            'sync_status' => 'pending' // S'assurer que le statut est 'pending' pendant le traitement
        ]);
        $playlist->refresh();

        try {
            $syncService = new AzuraCastSyncService();
            $result = $syncService->updateM3UAndRestartService($playlist);

            if ($result['success']) {
                Log::info("UpdateM3UAndRestartJob: Synchronisation terminée avec succès", [
                    'playlist_id' => $this->playlistId,
                    'playlist_name' => $playlist->name,
                    'files_copied' => $result['files_copied'] ?? 0,
                    'm3u_lines' => $result['m3u_lines'] ?? 0
                ]);
            } else {
                Log::error("UpdateM3UAndRestartJob: Échec de la synchronisation", [
                    'playlist_id' => $this->playlistId,
                    'playlist_name' => $playlist->name,
                    'error' => $result['message'] ?? 'Erreur inconnue'
                ]);
            }
        } catch (\Exception $e) {
            Log::error("UpdateM3UAndRestartJob: Exception lors de la synchronisation", [
                'playlist_id' => $this->playlistId,
                'playlist_name' => $playlist->name ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}





