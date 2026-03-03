<?php

namespace App\Services;

use App\Models\WebTVPlaylist;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

class FFmpegStreamService
{
    private string $playlistFile;
    private string $outputPath;
    private string $pidFile;
    private string $signatureFile;
    
    public function __construct()
    {
        $this->playlistFile = '/tmp/webtv_playlist.txt';
        $this->outputPath = '/usr/local/antmedia/webapps/LiveApp/streams/live_playlist.m3u8';
        $this->pidFile = '/tmp/ffmpeg_webtv.pid';
        $this->signatureFile = '/tmp/ffmpeg_webtv_signature.txt';
    }
    
    /**
     * 🔍 CALCULER LA SIGNATURE DE LA PLAYLIST ACTUELLE (basée sur les chemins réels)
     */
    private function calculatePlaylistSignature(): ?string
    {
        try {
            $activePlaylist = WebTVPlaylist::where('is_active', true)->first();
            
            if (!$activePlaylist) {
                return null;
            }
            
            $sequence = $this->getPlaylistSequence($activePlaylist);
            
            if (empty($sequence['items'])) {
                return null;
            }
            
            // Récupérer tous les chemins réels (résolus depuis les symlinks)
            $realPaths = [];
            foreach ($sequence['items'] as $item) {
                $symlinkPath = "/usr/local/antmedia/webapps/LiveApp/streams/" . $item['ant_media_item_id'];
                
                if (file_exists($symlinkPath)) {
                    $realPath = realpath($symlinkPath);
                    if ($realPath !== false) {
                        $realPaths[] = $realPath;
                    }
                }
            }
            
            // Créer une signature (hash) basée sur les chemins réels et leur ordre
            $signature = md5(implode('|', $realPaths));
            
            return $signature;
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur calcul signature playlist: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 🔄 VÉRIFIER SI LA PLAYLIST A CHANGÉ (comparer les signatures)
     */
    public function hasPlaylistChanged(): bool
    {
        try {
            $currentSignature = $this->calculatePlaylistSignature();
            
            if ($currentSignature === null) {
                return false; // Pas de playlist active, considérer comme non changé
            }
            
            // Lire la dernière signature stockée
            $lastSignature = null;
            if (file_exists($this->signatureFile)) {
                $lastSignature = trim(file_get_contents($this->signatureFile));
            }
            
            // Si pas de signature précédente, la playlist a changé (nouveau démarrage)
            if ($lastSignature === null || $lastSignature === '') {
                Log::info("📝 Première génération de signature playlist");
                file_put_contents($this->signatureFile, $currentSignature);
                return true;
            }
            
            // Comparer les signatures
            if ($currentSignature !== $lastSignature) {
                Log::info("🔄 Playlist modifiée détectée", [
                    'old_signature' => substr($lastSignature, 0, 8),
                    'new_signature' => substr($currentSignature, 0, 8)
                ]);
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur vérification changement playlist: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 💾 SAUVEGARDER LA SIGNATURE ACTUELLE
     */
    private function savePlaylistSignature(): void
    {
        try {
            $signature = $this->calculatePlaylistSignature();
            if ($signature !== null) {
                file_put_contents($this->signatureFile, $signature);
            }
        } catch (\Exception $e) {
            Log::warning("⚠️ Impossible de sauvegarder la signature: " . $e->getMessage());
        }
    }
    
    /**
     * 🎯 GÉNÉRER LE FICHIER PLAYLIST POUR FFMPEG
     */
    public function generatePlaylistFile(): bool
    {
        try {
            Log::info("🎬 Génération playlist FFmpeg");
            
            // Récupérer la playlist active
            $activePlaylist = WebTVPlaylist::where('is_active', true)->first();
            
            if (!$activePlaylist) {
                Log::error("❌ Aucune playlist active trouvée");
                return false;
            }
            
            // Récupérer la séquence avec shuffle/ordre
            $sequence = $this->getPlaylistSequence($activePlaylist);
            
            if (empty($sequence['items'])) {
                Log::error("❌ Playlist vide");
                return false;
            }
            
            // Générer le fichier playlist.txt pour FFmpeg
            $playlistContent = "";
            foreach ($sequence['items'] as $item) {
                $symlinkPath = "/usr/local/antmedia/webapps/LiveApp/streams/" . $item['ant_media_item_id'];
                
                if (file_exists($symlinkPath)) {
                    // ✅ RÉSOUDRE LE SYMLINK POUR OBTENIR LE FICHIER RÉEL
                    $realPath = realpath($symlinkPath);
                    if ($realPath === false) {
                        Log::warning("⚠️ Impossible de résoudre le symlink: {$symlinkPath}");
                        continue;
                    }
                    
                    // Utiliser le chemin réel au lieu du symlink
                    $playlistContent .= "file '{$realPath}'\n";
                } else {
                    Log::warning("⚠️ Symlink manquant: {$symlinkPath}");
                }
            }
            
            // Écrire le fichier
            if (file_put_contents($this->playlistFile, $playlistContent) === false) {
                Log::error("❌ Impossible d'écrire playlist.txt");
                return false;
            }
            
            Log::info("✅ Playlist FFmpeg générée", [
                'file' => $this->playlistFile,
                'items' => count($sequence['items']),
                'shuffle' => $activePlaylist->shuffle_enabled,
                'loop' => $activePlaylist->is_loop
            ]);
            
            // 💾 Sauvegarder la signature de la playlist
            $this->savePlaylistSignature();
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur génération playlist FFmpeg: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 🚀 DÉMARRER LE STREAM FFMPEG
     */
    public function startStream(): bool
    {
        try {
            // ✅ VÉRIFIER SI LA PLAYLIST A CHANGÉ
            if ($this->isStreamRunning()) {
                // Vérifier si la playlist a changé
                if ($this->hasPlaylistChanged()) {
                    Log::info("🔄 Playlist modifiée - Redémarrage du stream FFmpeg");
                    $this->stopStream();
                    sleep(2); // Attendre que le processus se termine
                    // Continuer pour redémarrer avec la nouvelle playlist
                } else {
                    Log::info("⚠️ Stream FFmpeg déjà en cours (pas de changement)");
                    return true;
                }
            }
            
            // Générer la playlist
            if (!$this->generatePlaylistFile()) {
                return false;
            }
            
            // Récupérer le flag loop
            $activePlaylist = WebTVPlaylist::where('is_active', true)->first();
            $loopFlag = $activePlaylist && $activePlaylist->is_loop ? '-stream_loop -1' : '';
            
            // Commande FFmpeg optimisée pour compatibilité maximale
            // Note: FFmpeg 4.4 ne supporte pas -hls_version, on utilisera un post-traitement
            // hls_list_size ne fonctionne pas bien avec stream_loop -1
            $command = sprintf(
                'ffmpeg -re -f concat -safe 0 %s -i %s ' .
                '-c:v copy -c:a copy -f hls ' .
                '-hls_time 10 -hls_list_size 20 -hls_delete_threshold 10 ' .
                '-hls_flags delete_segments+independent_segments+program_date_time ' .
                '-hls_playlist_type event ' .
                '%s > /tmp/ffmpeg_webtv.log 2>&1 & echo $! > %s',
                $loopFlag,
                $this->playlistFile,
                $this->outputPath,
                $this->pidFile
            );
            
            Log::info("🚀 Démarrage FFmpeg", ['command' => $command]);
            
            // Exécuter la commande
            exec($command);
            
            // Attendre que le stream démarre
            sleep(3);
            
            if ($this->isStreamRunning()) {
                // ✅ Post-traitement : forcer HLS version 3 pour compatibilité
                $this->normalizeM3U8();
                
                Log::info("✅ Stream FFmpeg démarré avec succès");
                return true;
            } else {
                Log::error("❌ Échec démarrage stream FFmpeg");
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur démarrage FFmpeg: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 🛑 ARRÊTER LE STREAM FFMPEG
     */
    public function stopStream(): bool
    {
        try {
            if (!$this->isStreamRunning()) {
                Log::info("⚠️ Aucun stream FFmpeg en cours");
                return true;
            }
            
            // Lire le PID
            $pid = trim(file_get_contents($this->pidFile));
            
            if (!empty($pid) && is_numeric($pid)) {
                // Tuer le processus
                exec("kill {$pid}");
                
                // Nettoyer les fichiers
                @unlink($this->pidFile);
                @unlink($this->playlistFile);
                
                Log::info("✅ Stream FFmpeg arrêté", ['pid' => $pid]);
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur arrêt FFmpeg: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 🔍 VÉRIFIER SI LE STREAM EST EN COURS
     */
    public function isStreamRunning(): bool
    {
        try {
            if (!file_exists($this->pidFile)) {
                return false;
            }
            
            $pid = trim(file_get_contents($this->pidFile));
            
            if (empty($pid) || !is_numeric($pid)) {
                return false;
            }
            
            // Vérifier si le processus existe
            $result = exec("ps -p {$pid} -o pid=");
            return !empty(trim($result));
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 🔄 REDÉMARRER LE STREAM (quand playlist change)
     */
    public function restartStream(): bool
    {
        Log::info("🔄 Redémarrage stream FFmpeg");
        
        $this->stopStream();
        sleep(2);
        return $this->startStream();
    }
    
    /**
     * 🔧 NORMALISER LE M3U8 POUR COMPATIBILITÉ MAXIMALE
     * Force la version HLS 3 et corrige les problèmes de compatibilité
     */
    public function normalizeM3U8(): void
    {
        try {
            if (!file_exists($this->outputPath)) {
                return;
            }
            
            // Lire le contenu du M3U8
            $content = file_get_contents($this->outputPath);
            
            if ($content === false) {
                return;
            }
            
            // Forcer la version HLS 3 pour compatibilité maximale
            $content = preg_replace('/#EXT-X-VERSION:\d+/', '#EXT-X-VERSION:3', $content);
            
            // Calculer TARGETDURATION correct (max duration des segments)
            $maxDuration = 0;
            if (preg_match_all('/#EXTINF:([\d.]+)/', $content, $matches)) {
                foreach ($matches[1] as $duration) {
                    $maxDuration = max($maxDuration, (int)ceil((float)$duration));
                }
            }
            
            // Si TARGETDURATION est trop élevé (> 30), le corriger
            if ($maxDuration > 0 && $maxDuration < 30) {
                $content = preg_replace('/#EXT-X-TARGETDURATION:\d+/', "#EXT-X-TARGETDURATION:{$maxDuration}", $content);
            }
            
            // Écrire le M3U8 normalisé
            file_put_contents($this->outputPath, $content);
            
            Log::debug("✅ M3U8 normalisé", ['version' => '3', 'target_duration' => $maxDuration]);
            
        } catch (\Exception $e) {
            Log::warning("⚠️ Erreur normalisation M3U8: " . $e->getMessage());
        }
    }
    
    /**
     * 📊 STATUT DU STREAM
     */
    public function getStreamStatus(): array
    {
        $isRunning = $this->isStreamRunning();
        $outputExists = file_exists($this->outputPath);
        
        return [
            'running' => $isRunning,
            'output_exists' => $outputExists,
            'output_path' => $this->outputPath,
            'playlist_file' => $this->playlistFile,
            'pid_file' => $this->pidFile
        ];
    }
    
    /**
     * 🎲 RÉCUPÉRER LA SÉQUENCE DE PLAYLIST (avec shuffle)
     */
    private function getPlaylistSequence(WebTVPlaylist $playlist): array
    {
        $cacheKey = "webtv_playlist_sequence_{$playlist->id}";
        
        return Cache::remember($cacheKey, 300, function() use ($playlist) {
            $items = $playlist->items()
                ->where('sync_status', 'synced')
                ->orderBy('order')
                ->get();
            
            // Appliquer le shuffle si activé
            if ($playlist->shuffle_enabled) {
                $originalSeed = mt_rand();
                mt_srand($playlist->id);
                $items = $items->shuffle();
                mt_srand($originalSeed);
            }
            
            $sequence = [];
            foreach ($items as $item) {
                if ($item->ant_media_item_id) {
                    $sequence[] = [
                        'item_id' => $item->id,
                        'title' => $item->title,
                        'ant_media_item_id' => $item->ant_media_item_id
                    ];
                }
            }
            
            return [
                'items' => $sequence,
                'count' => count($sequence)
            ];
        });
    }
}

