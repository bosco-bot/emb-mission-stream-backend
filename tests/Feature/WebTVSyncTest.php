<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\WebTVPlaylist;
use App\Models\WebTVPlaylistItem;
use App\Models\MediaFile;
use App\Services\AntMediaPlaylistService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebTVSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que les items sont marqués comme synced après création VoD réussie
     */
    public function test_items_are_marked_as_synced_after_successful_vod_creation()
    {
        // Créer une playlist de test
        $playlist = WebTVPlaylist::factory()->create([
            'name' => 'Test Playlist',
            'is_active' => true,
        ]);

        // Créer un fichier média de test
        $mediaFile = MediaFile::factory()->create([
            'file_type' => 'video',
            'duration' => 180,
            'status' => 'completed',
        ]);

        // Créer un item de playlist
        $item = WebTVPlaylistItem::create([
            'webtv_playlist_id' => $playlist->id,
            'video_file_id' => $mediaFile->id,
            'title' => 'Test Video',
            'duration' => 180,
            'sync_status' => 'pending',
        ]);

        // Vérifier que l'item est initialement en pending
        $this->assertEquals('pending', $item->sync_status);

        // Simuler la création VoD (en mockant le service)
        $service = new AntMediaPlaylistService();
        
        // Mock du service VoD pour retourner success
        $this->mock(\App\Services\AntMediaVoDService::class, function ($mock) {
            $mock->shouldReceive('createVoDStream')
                 ->once()
                 ->andReturn([
                     'success' => true,
                     'vod_file_name' => 'vod_test',
                     'stream_url' => 'https://test.com/vod_test/playlist.m3u8'
                 ]);
        });

        // Appeler la méthode de synchronisation
        $result = $service->addItemToPlaylist($item);

        // Vérifier que le résultat est success
        $this->assertTrue($result['success']);

        // Recharger l'item depuis la DB
        $item->refresh();

        // ASSERTION CRITIQUE : L'item doit être marqué comme synced
        $this->assertEquals('synced', $item->sync_status);
        $this->assertEquals('vod_test', $item->ant_media_item_id);
        $this->assertNotNull($item->stream_url);
    }

    /**
     * Test que les items en erreur ne sont pas marqués comme synced
     */
    public function test_items_are_marked_as_error_on_vod_creation_failure()
    {
        $playlist = WebTVPlaylist::factory()->create();
        $mediaFile = MediaFile::factory()->create(['file_type' => 'video']);
        
        $item = WebTVPlaylistItem::create([
            'webtv_playlist_id' => $playlist->id,
            'video_file_id' => $mediaFile->id,
            'title' => 'Test Video',
            'sync_status' => 'pending',
        ]);

        // Mock du service VoD pour retourner échec
        $this->mock(\App\Services\AntMediaVoDService::class, function ($mock) {
            $mock->shouldReceive('createVoDStream')
                 ->once()
                 ->andReturn([
                     'success' => false,
                     'message' => 'Erreur de conversion'
                 ]);
        });

        $service = new AntMediaPlaylistService();
        $result = $service->addItemToPlaylist($item);

        $this->assertFalse($result['success']);
        
        $item->refresh();
        $this->assertEquals('error', $item->sync_status);
    }
}

