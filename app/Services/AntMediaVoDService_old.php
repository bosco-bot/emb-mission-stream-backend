<?php

namespace App\Services;

use App\Models\MediaFile;
use App\Models\WebTVPlaylistItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AntMediaVoDService
{
    private string $antMediaStreamsPath;
    private string $antMediaBaseUrl;
    
    public function __construct()
    {
        $this->antMediaStreamsPath = '/usr/local/antmedia/webapps/LiveApp/streams';
        $this->antMediaBaseUrl = 'http://15.235.86.98:5080/LiveApp/streams';
    }
    
    /**
     * Créer un VoD directement avec symlink (solution optimale)
     */
    public function createVoDStream(WebTVPlaylistItem $item): array
    {
        try {
            if (!$item->video_file_id) {
                return [
                    'success' => false,
                    'message' => 'Aucun fichier vidéo associé à cet item'
                ];
            }
            
            $mediaFile = MediaFile::find($item->video_file_id);
            if (!$mediaFile) {
                return [
                    'success' => false,
                    'message' => 'Fichier média non trouvé'
                ];
            }
            
            $vodFileName = 'vod_' . $item->id . '_' . time() . '.mp4';
            $sourcePath = storage_path('app/media/' . $mediaFile->file_path);
            $vodPath = $this->antMediaStreamsPath . '/' . $vodFileName;
            
            Log::info("🎥 Création d'un VoD avec symlink (solution optimale)", [
                'item_id' => $item->id,
                'vod_file_name' => $vodFileName,
                'source_path' => $sourcePath
            ]);
            
            // Créer le symlink directement vers le répertoire VoD
            if (symlink($sourcePath, $vodPath)) {
                // Mettre à jour l'item
                $item->update([
                    'ant_media_item_id' => $vodFileName,
                    'sync_status' => 'synced',
                    'stream_url' => $this->antMediaBaseUrl . '/' . $vodFileName
                ]);
                
                Log::info("✅ VoD créé avec succès (solution optimale)", [
                    'item_id' => $item->id,
                    'vod_file_name' => $vodFileName,
                    'vod_path' => $vodPath,
                    'stream_url' => $this->antMediaBaseUrl . '/' . $vodFileName
                ]);
                
                return [
                    'success' => true,
                    'message' => 'VoD créé avec succès - Accès direct via serveur web',
                    'vod_file_name' => $vodFileName,
                    'vod_path' => $vodPath,
                    'stream_url' => $this->antMediaBaseUrl . '/' . $vodFileName,
                    'direct_url' => $this->antMediaBaseUrl . '/' . $vodFileName
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la création du symlink VoD'
                ];
            }
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur création VoD: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la création du VoD: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Lister tous les fichiers VoD (solution optimale)
     */
    public function getVodFiles(): array
    {
        try {
            $vodPath = $this->antMediaStreamsPath;
            
            $files = collect(glob($vodPath . '/*.mp4'))
                ->map(function($file) {
                    $fileName = basename($file);
                    return [
                        'name' => $fileName,
                        'url' => $this->antMediaBaseUrl . '/' . $fileName,
                        'direct_url' => $this->antMediaBaseUrl . '/' . $fileName,
                        'file_path' => $file,
                        'size' => filesize($file),
                        'created_at' => filemtime($file)
                    ];
                })
                ->values()
                ->toArray();
            
            Log::info("📋 Liste des fichiers VoD récupérée", [
                'count' => count($files),
                'files' => array_column($files, 'name')
            ]);
            
            return $files;
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur récupération fichiers VoD: " . $e->getMessage());
            
            return [];
        }
    }
    
    /**
     * Obtenir l'URL directe d'un VoD
     */
    public function getVodUrl(string $vodFileName): string
    {
        return $this->antMediaBaseUrl . '/' . $vodFileName;
    }
    
    /**
     * Vérifier l'existence d'un VoD
     */
    public function checkVoD(WebTVPlaylistItem $item): array
    {
        try {
            $vodFileName = $item->ant_media_item_id;
            
            if (!$vodFileName) {
                return [
                    'success' => false,
                    'message' => 'Aucun VoD associé'
                ];
            }
            
            $vodPath = $this->antMediaStreamsPath . '/' . $vodFileName;
            
            if (is_link($vodPath) || file_exists($vodPath)) {
                $targetPath = is_link($vodPath) ? readlink($vodPath) : $vodPath;
                $targetExists = file_exists($targetPath);
                
                return [
                    'success' => true,
                    'message' => 'VoD existe',
                    'vod_path' => $vodPath,
                    'target_path' => $targetPath,
                    'target_exists' => $targetExists,
                    'direct_url' => $this->antMediaBaseUrl . '/' . $vodFileName
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'VoD n\'existe pas'
                ];
            }
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur vérification VoD: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification du VoD: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Supprimer un VoD
     */
    public function deleteVoDStream(WebTVPlaylistItem $item): array
    {
        try {
            $vodFileName = $item->ant_media_item_id;
            
            if (!$vodFileName) {
                return [
                    'success' => true,
                    'message' => 'Aucun VoD à supprimer'
                ];
            }
            
            $vodPath = $this->antMediaStreamsPath . '/' . $vodFileName;
            
            Log::info("🗑️ Suppression d'un VoD", [
                'item_id' => $item->id,
                'vod_file_name' => $vodFileName,
                'vod_path' => $vodPath
            ]);
            
            if (is_link($vodPath) || file_exists($vodPath)) {
                unlink($vodPath);
                
                Log::info("✅ VoD supprimé avec succès", [
                    'item_id' => $item->id,
                    'vod_file_name' => $vodFileName
                ]);
                
                return [
                    'success' => true,
                    'message' => 'VoD supprimé avec succès'
                ];
            } else {
                return [
                    'success' => true,
                    'message' => 'VoD déjà supprimé'
                ];
            }
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur suppression VoD: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression du VoD: ' . $e->getMessage()
            ];
        }
    }
}
