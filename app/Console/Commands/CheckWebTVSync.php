<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebTVPlaylistItem;
use App\Models\WebTVPlaylist;
use Illuminate\Support\Facades\Log;

class CheckWebTVSync extends Command
{
    protected $signature = 'webtv:check-sync 
                           {--fix : Tenter de corriger automatiquement les items non synchronisés}
                           {--alert : Envoyer une alerte si des problèmes sont détectés}';

    protected $description = 'Vérifier la synchronisation des items WebTV et détecter les problèmes';

    public function handle()
    {
        $this->info('🔍 Vérification de la synchronisation WebTV...');

        // 1. Vérifier les items pending avec VoD existants
        $pendingWithVod = WebTVPlaylistItem::where('sync_status', 'pending')
            ->whereNotNull('ant_media_item_id')
            ->get();

        if ($pendingWithVod->count() > 0) {
            $this->warn("⚠️ {$pendingWithVod->count()} items en 'pending' mais avec VoD existants détectés !");
            
            foreach ($pendingWithVod as $item) {
                $vodPath = "/usr/local/antmedia/webapps/LiveApp/streams/{$item->ant_media_item_id}";
                $playlistExists = file_exists("{$vodPath}/playlist.m3u8");
                
                $this->line("  - Item {$item->id}: {$item->title}");
                $this->line("    VoD: {$item->ant_media_item_id} " . ($playlistExists ? '✅' : '❌'));
                
                if ($this->option('fix') && $playlistExists) {
                    $item->update(['sync_status' => 'synced']);
                    $this->info("    ✅ Corrigé automatiquement");
                }
            }
        }

        // 2. Vérifier les items synced sans VoD
        $syncedWithoutVod = WebTVPlaylistItem::where('sync_status', 'synced')
            ->whereNotNull('ant_media_item_id')
            ->get()
            ->filter(function ($item) {
                $vodPath = "/usr/local/antmedia/webapps/LiveApp/streams/{$item->ant_media_item_id}";
                return !file_exists("{$vodPath}/playlist.m3u8");
            });

        if ($syncedWithoutVod->count() > 0) {
            $this->error("❌ {$syncedWithoutVod->count()} items marqués 'synced' mais sans VoD !");
            
            foreach ($syncedWithoutVod as $item) {
                $this->line("  - Item {$item->id}: {$item->title}");
                
                if ($this->option('fix')) {
                    $item->update(['sync_status' => 'pending']);
                    $this->info("    ✅ Remis en 'pending' pour re-synchronisation");
                }
            }
        }

        // 3. Vérifier les durées manquantes
        $itemsWithoutDuration = WebTVPlaylistItem::where('duration', 0)
            ->orWhereNull('duration')
            ->get();

        if ($itemsWithoutDuration->count() > 0) {
            $this->warn("⚠️ {$itemsWithoutDuration->count()} items sans durée détectés !");
            
            foreach ($itemsWithoutDuration as $item) {
                if ($item->video_file_id && $item->mediaFile) {
                    $fileDuration = $item->mediaFile->duration;
                    if ($fileDuration > 0) {
                        $this->line("  - Item {$item->id}: durée manquante (fichier: {$fileDuration}s)");
                        
                        if ($this->option('fix')) {
                            $item->update(['duration' => $fileDuration]);
                            $this->info("    ✅ Durée corrigée");
                        }
                    }
                }
            }
        }

        // 4. Résumé et alertes
        $totalIssues = $pendingWithVod->count() + $syncedWithoutVod->count() + $itemsWithoutDuration->count();
        
        if ($totalIssues === 0) {
            $this->info('✅ Aucun problème de synchronisation détecté !');
        } else {
            $message = "⚠️ {$totalIssues} problèmes de synchronisation détectés";
            $this->warn($message);
            
            if ($this->option('alert')) {
                Log::warning('WebTV Sync Issues Detected', [
                    'pending_with_vod' => $pendingWithVod->count(),
                    'synced_without_vod' => $syncedWithoutVod->count(),
                    'missing_duration' => $itemsWithoutDuration->count(),
                    'total_issues' => $totalIssues
                ]);
            }
        }

        return $totalIssues === 0 ? 0 : 1;
    }
}

