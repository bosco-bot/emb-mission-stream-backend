<?php
namespace App\Jobs;
use App\Models\Playlist;
use App\Services\AzuraCastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeAzuraCastSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $playlistId;

    public function __construct($playlistId) { $this->playlistId = $playlistId; }

    public function handle()
    {
        sleep(10);

        $playlist = Playlist::with('items.mediaFile')->find($this->playlistId);

        $azuracastService = new AzuraCastService();

        foreach ($playlist->items as $item) {
            // Skip items that already have azuracast_song_id or are synced
            if ($item->azuracast_song_id !== null || $item->sync_status === 'synced') continue;

            try {
                $mediaFile = $item->mediaFile;
                //$azuracastMediaId = $azuracastService->getMediaIdByFilename($mediaFile->filename);
                $azuracastMediaId = $azuracastService->getMediaIdByFilename($mediaFile->original_name);

                if ($azuracastMediaId) {
                   // Just update the item with the media ID (no need to add to playlist via SQL as M3U import already did it)
                   $item->update(['sync_status' => 'synced', 'azuracast_song_id' => $azuracastMediaId]);
                }
            } catch (\Exception $e) {
                $item->update(['sync_status' => 'error']);
            }
        }
    }
}
