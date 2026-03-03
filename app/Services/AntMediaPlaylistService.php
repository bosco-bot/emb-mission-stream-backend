<?php

namespace App\Services;

use App\Jobs\CreateVodStreamJob;
use App\Models\WebTVPlaylist;
use App\Models\WebTVPlaylistItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\AntMediaLocalProxy;
use App\Services\AntMediaVoDService;

class AntMediaPlaylistService
{
    private AntMediaLocalProxy $proxy;
    private AntMediaVoDService $vodService;

    public function __construct()
    {
        $this->proxy = new AntMediaLocalProxy();
        $this->vodService = new AntMediaVoDService();
    }

    /**
     * Créer une playlist dans Ant Media Server
     */
    public function createPlaylist(WebTVPlaylist $playlist): array
    {
        try {
            $streamId = 'playlist_' . $playlist->id . '_' . time();
            
            Log::info("🎥 Création d'une playlist dans Ant Media Server", [
                'playlist_name' => $playlist->name,
                'playlist_id' => $playlist->id,
                'stream_id' => $streamId
            ]);

            $streamData = [
                'streamId' => $streamId,
                'name' => $playlist->name,
                'description' => $playlist->description ?? 'Playlist WebTV',
                'type' => 'live',
                'publish' => true,
            ];

            $result = $this->proxy->createStream($streamData);
            
            if ($result['success']) {
                $playlist->update([
                    'ant_media_stream_id' => $result['data']['streamId'] ?? $streamId,
                    'sync_status' => 'synced',
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Playlist créée avec succès dans Ant Media Server',
                    'ant_media_stream_id' => $result['data']['streamId'] ?? $streamId,
                    'ant_media_response' => $result['data']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la création de la playlist: ' . $result['message']
                ];
            }
        } catch (\Exception $e) {
            Log::error("❌ Erreur création playlist Ant Media: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la création de la playlist: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Ajouter un item à la playlist dans Ant Media Server
     */
    public function addItemToPlaylist(WebTVPlaylistItem $item): array
    {
        try {
            // Réutiliser un VoD pré-converti pour ce media_file si disponible
            if ($item->video_file_id) {
                $vodService = new AntMediaVoDService();
                $vodName = $vodService->buildVodNameForMediaFile((int) $item->video_file_id);
                // Ne réutiliser que si le HLS est complet (ENDLIST et segments suffisants)
                if ($vodService->isVodComplete($vodName)) {
                    $streamUrl = $vodService->buildHlsUrlFromVodName($vodName);
                    $item->update([
                        'ant_media_item_id' => $vodName,
                        'stream_url' => $streamUrl,
                        'sync_status' => 'synced',
                    ]);
                    Log::info("✅ Réutilisation du VoD pré-converti COMPLET pour l'item", [
                        'item_id' => $item->id,
                        'vod_name' => $vodName,
                        'stream_url' => $streamUrl,
                    ]);
                    return [
                        'success' => true,
                        'message' => 'VoD pré-converti réutilisé',
                        'vod_file_name' => $vodName,
                        'stream_url' => $streamUrl,
                    ];
                }
            }

            Log::info("🎥 Création d'un stream VoD pour l'item de playlist", [
                'item_title' => $item->title,
                'item_id' => $item->id,
                'video_file_id' => $item->video_file_id,
                'current_sync_status' => $item->sync_status,
                'ant_media_item_id' => $item->ant_media_item_id
            ]);


            // Vérifier si video_file_id existe
            if (!$item->video_file_id) {
                Log::warning("⚠️ Item créé sans video_file_id, synchronisation reportée", [
                    'item_id' => $item->id
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Item créé sans video_file_id, synchronisation reportée'
                ];
            }


            $result = $this->vodService->createVoDStream($item);
            
            if ($result['success']) {
                // Marquer l'item comme synchronisé et mettre à jour l'ant_media_item_id
                $item->update([
                    'sync_status' => 'synced',
                    'ant_media_item_id' => $result['vod_file_name'] ?? 'vod_' . $item->id,
                    'stream_url' => $result['stream_url'] ?? null,
                ]);
                
                Log::info("✅ Stream VoD créé avec succès et item marqué comme synced", [
                    'item_id' => $item->id,
                    'stream_id' => $result['vod_file_name'] ?? 'unknown',
                    'sync_status' => 'synced',
                ]);
                
                return $result;
            } else {
                Log::error("❌ Erreur création stream VoD", [
                    'item_id' => $item->id,
                    'error' => $result['message']
                ]);
                
                $item->update([
                    'sync_status' => 'error',
                ]);
                
                return $result;
            }
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur création stream VoD: " . $e->getMessage());
            $item->update([
                'sync_status' => 'error',
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la création du stream VoD: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Supprimer un item de la playlist dans Ant Media Server
     */
    public function deleteItemFromPlaylist(WebTVPlaylistItem $item): array
    {
        try {
            Log::info("🗑️ Suppression d'un stream VoD", [
                'item_id' => $item->id,
                'stream_id' => $item->ant_media_item_id
            ]);

            // Utiliser le service VoD pour supprimer le stream VoD
            $result = $this->vodService->deleteVoDStream($item);
            
            if ($result['success']) {
                Log::info("✅ Stream VoD supprimé avec succès", [
                    'item_id' => $item->id
                ]);
            } else {
                Log::warning("⚠️ Erreur suppression stream VoD", [
                    'item_id' => $item->id,
                    'error' => $result['message']
                ]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur suppression stream VoD: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression du stream VoD: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Synchroniser une playlist avec Ant Media Server
     */
    public function syncPlaylist(WebTVPlaylist $playlist, bool $async = false): array
    {
        try {
            Log::info("🔄 Synchronisation de la playlist avec Ant Media Server", [
                'playlist_id' => $playlist->id,
                'playlist_name' => $playlist->name,
                'async' => $async,
            ]);

            $results = [];
            $queued = 0;
            $skipped = 0;

            $playlist->loadMissing('items');
            
            // Créer la playlist si elle n'existe pas
            if (!$playlist->ant_media_stream_id) {
                $playlistResult = $this->createPlaylist($playlist);
                $results['playlist_creation'] = $playlistResult;

                 if (($playlistResult['success'] ?? false) === false) {
                     return [
                         'success' => false,
                         'message' => $playlistResult['message'] ?? 'Erreur lors de la création de la playlist',
                         'results' => $results,
                     ];
                 }
            }

            // Synchroniser tous les items
            foreach ($playlist->items as $item) {
                if ($item->sync_status === 'synced') {
                    $skipped++;
                    continue;
                }

                if ($item->sync_status === 'processing') {
                    $results['skipped_processing'][] = $item->id;
                    $skipped++;
                    continue;
                }

                // CORRECTION: Réessayer la synchronisation pour les items en erreur
                // Réinitialiser le statut à 'pending' pour permettre une nouvelle tentative
                if ($item->sync_status === 'error') {
                    $item->update(['sync_status' => 'pending']);
                    Log::info("🔄 Réessai de synchronisation pour item en erreur", [
                        'item_id' => $item->id,
                        'title' => $item->title
                    ]);
                }

                if ($async) {
                    if ($item->sync_status !== 'pending') {
                        $item->update(['sync_status' => 'pending']);
                    }

                    CreateVodStreamJob::dispatch($item->id);
                    $results['queued'][] = $item->id;
                    $queued++;
                    continue;
                }

                $itemResult = $this->addItemToPlaylist($item);
                $results['item_' . $item->id] = $itemResult;
            }

            if ($async) {
                return [
                    'success' => true,
                    'message' => 'Synchronisation programmée en arrière-plan',
                    'queued' => $queued,
                    'skipped' => $skipped,
                    'results' => $results,
                ];
            }

            return [
                'success' => true,
                'message' => 'Playlist synchronisée avec Ant Media Server',
                'results' => $results
            ];
        } catch (\Exception $e) {
            Log::error("❌ Erreur synchronisation playlist Ant Media: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la synchronisation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vérifier la connexion à Ant Media Server
     */
    public function checkConnection(): array
    {
        return $this->proxy->checkConnection();
    }

    /**
     * Créer une URL de stream pour un fichier média
     */
    private function createStreamUrlForMediaFile($mediaFile, string $streamId): string
    {
        $filePath = storage_path("app/media/" . $mediaFile->file_path);
        
        if (file_exists($filePath)) {
            // Dans Ant Media Server Community Edition, nous devons utiliser
            // l'interface web pour uploader les vidéos ou utiliser RTMP
            return 'rtmp://15.235.86.98/LiveApp/' . $streamId;
        }
        
        return 'rtmp://15.235.86.98/LiveApp/' . $streamId;
    }
}
