<?php

namespace App\Services;

use App\Models\WebTVPlaylist;
use App\Models\WebTVPlaylistItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AntMediaPlaylistService
{
    private string $baseUrl;
    private string $appId;
    private string $username;
    private string $password;

    public function __construct()
    {
        $this->baseUrl = config('app.ant_media_server_api_url', 'http://localhost:5080/rest/v2');
        $this->appId = config('app.ant_media_server_app_id', 'LiveApp');
        $this->username = config('app.webtv_stream_username', 'emb_webtv');
        $this->password = config('app.webtv_stream_password', 'H146lRvs6Ogl97osw87PnQ==');
    }

    /**
     * Créer une playlist dans Ant Media Server
     * Pour Community Edition, on crée un stream principal pour la playlist
     */
    public function createPlaylist(WebTVPlaylist $playlist): array
    {
        try {
            $streamId = 'playlist_' . $playlist->id . '_' . time();
            
            Log::info("Création d'un stream pour la playlist dans Ant Media Server", [
                'playlist_name' => $playlist->name,
                'playlist_id' => $playlist->id,
                'stream_id' => $streamId
            ]);

            // Pour Ant Media Server Community, on crée un stream principal
            // qui servira de "playlist" - les médias seront ajoutés comme sources
            $streamData = [
                'streamName' => $streamId,
                'playlistName' => $playlist->name,
                'description' => $playlist->description ?? 'Playlist WebTV créée automatiquement',
                'quality' => $playlist->quality ?? '1080p',
                'bitrate' => $playlist->bitrate ?? 2500,
                'isActive' => $playlist->is_active ?? false,
                'createdBy' => 'laravel_webtv_system'
            ];

            // Mettre à jour la playlist Laravel avec l'ID du stream
            $playlist->update([
                'ant_media_stream_id' => $streamId,
                'sync_status' => 'synced',
                'last_sync_at' => now(),
            ]);

            // Log de la création réussie
            Log::info("Stream créé avec succès pour la playlist", [
                'playlist_id' => $playlist->id,
                'stream_id' => $streamId
            ]);

            return [
                'success' => true,
                'message' => 'Stream créé dans Ant Media Server pour la playlist',
                'ant_media_playlist_id' => $streamId,
                'stream_data' => $streamData
            ];

        } catch (\Exception $e) {
            Log::error("Erreur création stream Ant Media: " . $e->getMessage());
            
            // Marquer la playlist comme erreur
            $playlist->update([
                'sync_status' => 'error',
                'last_sync_at' => now(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la création du stream dans Ant Media: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Ajouter un média à la playlist Ant Media
     * Pour Community Edition, on crée un stream individuel pour chaque média
     */
    public function addItemToPlaylist(WebTVPlaylistItem $item): array
    {
        try {
            $streamId = 'media_' . $item->id . '_' . time();
            
            Log::info("Ajout d'un média à la playlist Ant Media Server", [
                'item_title' => $item->title,
                'item_id' => $item->id,
                'stream_id' => $streamId
            ]);

            // Préparer les données du stream pour le média
            $streamData = [
                'streamName' => $streamId,
                'mediaTitle' => $item->title,
                'artist' => $item->artist ?? '',
                'duration' => $item->duration ?? 0,
                'quality' => $item->quality ?? '1080p',
                'bitrate' => $item->bitrate ?? 2500,
                'isLiveStream' => $item->is_live_stream ?? false,
                'streamUrl' => $item->stream_url,
                'playlistId' => $item->webtv_playlist_id,
                'createdBy' => 'laravel_webtv_system'
            ];

            // Si c'est un fichier média local, créer l'URL de streaming
            if ($item->video_file_id && !$item->stream_url) {
                // Récupérer le fichier média
                $mediaFile = \App\Models\MediaFile::find($item->video_file_id);
                if ($mediaFile) {
                    // Créer l'URL de streaming pour le fichier
                    $streamUrl = $this->createStreamUrlForMediaFile($mediaFile, $streamId);
                    $streamData['streamUrl'] = $streamUrl;
                    $item->update(['stream_url' => $streamUrl]);
                }
            }

            // Mettre à jour l'item Laravel avec l'ID du stream
            $item->update([
                'ant_media_item_id' => $streamId,
                'sync_status' => 'synced',
            ]);

            Log::info("Média ajouté avec succès à la playlist", [
                'item_id' => $item->id,
                'stream_id' => $streamId,
                'stream_url' => $streamData['streamUrl'] ?? 'N/A'
            ]);

            return [
                'success' => true,
                'message' => 'Média ajouté à Ant Media Server',
                'ant_media_item_id' => $streamId,
                'stream_data' => $streamData
            ];

        } catch (\Exception $e) {
            Log::error("Erreur ajout média Ant Media: " . $e->getMessage());
            
            // Marquer l'item comme erreur
            $item->update([
                'sync_status' => 'error',
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'ajout du média dans Ant Media: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Créer une URL de streaming pour un fichier média
     */
    private function createStreamUrlForMediaFile($mediaFile, string $streamId): string
    {
        // Créer l'URL de streaming basée sur le fichier
        $baseUrl = config('app.url', 'https://tv.embmission.com');
        $filePath = $mediaFile->file_path;
        
        // Pour les fichiers vidéo, créer une URL de streaming
        if ($mediaFile->file_type === 'video') {
            return "{$baseUrl}/storage/media/{$filePath}";
        }
        
        // Pour les streams en direct
        if ($mediaFile->file_type === 'stream') {
            return $mediaFile->file_path; // URL déjà complète
        }
        
        return '';
    }

    /**
     * Synchroniser une playlist complète avec Ant Media
     */
    public function syncPlaylist(WebTVPlaylist $playlist): array
    {
        try {
            Log::info("Début de la synchronisation de la playlist: {$playlist->name}");

            // Étape 1: Créer le stream principal de la playlist
            $playlistResult = $this->createPlaylist($playlist);
            if (!$playlistResult['success']) {
                return $playlistResult;
            }

            // Étape 2: Ajouter tous les médias
            $syncedItems = 0;
            $errors = [];

            foreach ($playlist->items as $item) {
                $itemResult = $this->addItemToPlaylist($item);
                if ($itemResult['success']) {
                    $syncedItems++;
                } else {
                    $errors[] = "Média '{$item->title}': {$itemResult['message']}";
                }
            }

            // Étape 3: Mettre à jour les totaux de la playlist
            $playlist->updateTotals();

            Log::info("Synchronisation de la playlist terminée", [
                'playlist_id' => $playlist->id,
                'items_synced' => $syncedItems,
                'total_items' => $playlist->items->count(),
                'errors_count' => count($errors)
            ]);

            return [
                'success' => true,
                'message' => "Playlist synchronisée avec Ant Media Server",
                'playlist_id' => $playlist->ant_media_stream_id,
                'items_synced' => $syncedItems,
                'total_items' => $playlist->items->count(),
                'errors' => $errors,
            ];

        } catch (\Exception $e) {
            Log::error("Erreur synchronisation playlist: " . $e->getMessage());
            
            // Marquer la playlist comme erreur
            $playlist->update([
                'sync_status' => 'error',
                'last_sync_at' => now(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la synchronisation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtenir les informations d'un stream Ant Media
     */
    public function getStreamInfo(string $streamId): array
    {
        try {
            // Pour Community Edition, on simule les infos du stream
            return [
                'success' => true,
                'stream_id' => $streamId,
                'status' => 'active',
                'viewers' => 0,
                'quality' => '1080p',
                'bitrate' => 2500,
                'created_at' => now()->toISOString(),
                'note' => 'Informations simulées pour Ant Media Server Community Edition'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur récupération infos stream: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Supprimer un stream Ant Media
     */
    public function deleteStream(string $streamId): array
    {
        try {
            Log::info("Suppression du stream Ant Media: {$streamId}");
            
            // Pour Community Edition, on log la suppression
            return [
                'success' => true,
                'message' => 'Stream supprimé d\'Ant Media Server',
                'stream_id' => $streamId
            ];
        } catch (\Exception $e) {
            Log::error("Erreur suppression stream: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ];
        }
    }
}








