<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MediaFile;
use App\Models\PlaylistItem;
use Illuminate\Support\Facades\Storage;

class CleanOrphanMediaFiles extends Command
{
    protected $signature = 'media:clean-orphans {--dry-run : Afficher les fichiers orphelins sans les supprimer}';
    protected $description = 'Supprime les entrées media_files dont le fichier physique n\'existe pas';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('🔍 Recherche des fichiers orphelins...');
        
        $allFiles = MediaFile::all();
        $orphans = [];
        $totalSize = 0;
        
        foreach ($allFiles as $file) {
            $fullPath = storage_path('app/media/' . $file->file_path);
            
            if (!file_exists($fullPath)) {
                $orphans[] = $file;
                $totalSize += $file->file_size ?? 0;
            }
        }
        
        if (empty($orphans)) {
            $this->info('✅ Aucun fichier orphelin trouvé !');
            return 0;
        }
        
        $orphanCount = count($orphans);
        $this->warn("⚠️  $orphanCount fichiers orphelins trouvés ({$this->formatBytes($totalSize)} dans la DB)");
        $this->newLine();
        
        // Afficher la liste
        $this->table(
            ['ID', 'Nom', 'Type', 'Status', 'Taille', 'Créé le'],
            collect($orphans)->map(function ($file) {
                return [
                    $file->id,
                    $file->original_name,
                    $file->file_type,
                    $file->status,
                    $this->formatBytes($file->file_size ?? 0),
                    $file->created_at->format('Y-m-d H:i'),
                ];
            })->toArray()
        );
        
        if ($isDryRun) {
            $this->info('🔍 Mode dry-run activé - Aucune suppression effectuée');
            $this->comment('Exécutez sans --dry-run pour supprimer ces entrées');
            return 0;
        }
        
        // Demander confirmation
        $orphanCount = count($orphans);
        if (!$this->confirm("Voulez-vous supprimer ces $orphanCount entrées orphelines ?")) {
            $this->info('❌ Opération annulée');
            return 0;
        }
        
        // Supprimer les entrées
        $deletedCount = 0;
        $deletedFromPlaylists = 0;
        
        foreach ($orphans as $file) {
            // Supprimer des playlists d'abord
            $itemsDeleted = PlaylistItem::where('media_file_id', $file->id)->delete();
            $deletedFromPlaylists += $itemsDeleted;
            
            // Supprimer le media_file
            $file->delete();
            $deletedCount++;
            
            $this->line("  ✓ Supprimé: {$file->original_name}");
        }
        
        $this->newLine();
        $this->info("✅ Nettoyage terminé :");
        $this->info("   • $deletedCount fichiers orphelins supprimés");
        $this->info("   • $deletedFromPlaylists items retirés des playlists");
        
        return 0;
    }
    
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

