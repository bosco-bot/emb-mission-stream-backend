<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait PlaylistCacheManager
{
    /**
     * 🔄 Invalider le cache de playlist pour mise à jour dynamique
     */
    protected function invalidatePlaylistCache(int $playlistId): void
    {
        try {
            $cacheKey = "webtv_playlist_sequence_{$playlistId}";
            
            if (Cache::forget($cacheKey)) {
                Log::info("🔄 Cache playlist invalidé", [
                    'playlist_id' => $playlistId,
                    'cache_key' => $cacheKey
                ]);
            }
            
            // Invalider aussi le cache de statut live si nécessaire
            Cache::forget('live_status_check');
            
            Log::info("✅ Mise à jour dynamique activée", [
                'playlist_id' => $playlistId,
                'action' => 'cache_invalidated'
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur invalidation cache playlist", [
                'playlist_id' => $playlistId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 🔄 Invalider tous les caches de playlists
     */
    protected function invalidateAllPlaylistCaches(): void
    {
        try {
            // Récupérer toutes les playlists actives
            $playlists = \App\Models\WebTVPlaylist::where('is_active', true)->get();
            
            foreach ($playlists as $playlist) {
                $this->invalidatePlaylistCache($playlist->id);
            }
            
            Log::info("🔄 Tous les caches playlists invalidés", [
                'count' => $playlists->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur invalidation tous caches", [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 🚀 Endpoint pour refresh manuel du cache
     */
    public function refreshPlaylistCache(int $playlistId = null): array
    {
        try {
            if ($playlistId) {
                $this->invalidatePlaylistCache($playlistId);
                $message = "Cache playlist {$playlistId} rafraîchi";
            } else {
                $this->invalidateAllPlaylistCaches();
                $message = "Tous les caches playlists rafraîchis";
            }
            
            return [
                'success' => true,
                'message' => $message,
                'timestamp' => time()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur refresh cache: ' . $e->getMessage()
            ];
        }
    }
}
