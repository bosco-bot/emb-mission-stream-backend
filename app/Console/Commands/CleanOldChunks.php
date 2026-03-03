<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CleanOldChunks extends Command
{
    protected $signature = 'chunks:clean 
        {--hours=24 : Nombre d\'heures avant suppression (défaut: 24h)}
        {--dry-run : Afficher les chunks à supprimer sans les supprimer}
        {--force : Supprimer sans confirmation}';

    protected $description = 'Supprime les chunks temporaires plus anciens que X heures';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        $this->info("🧹 Nettoyage des chunks temporaires > {$hours}h...");
        $this->newLine();
        
        $chunksDir = storage_path('app/chunks');
        
        if (!is_dir($chunksDir)) {
            $this->warn('⚠️  Le répertoire chunks n\'existe pas');
            return 0;
        }
        
        $cutoffTime = now()->subHours($hours)->timestamp;
        $oldChunks = [];
        $totalSize = 0;
        
        // Parcourir tous les répertoires de chunks
        $directories = glob($chunksDir . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $chunkDir) {
            $dirName = basename($chunkDir);
            $dirTime = filemtime($chunkDir);
            
            if ($dirTime < $cutoffTime) {
                $dirSize = $this->getDirectorySize($chunkDir);
                $oldChunks[] = [
                    'path' => $chunkDir,
                    'name' => $dirName,
                    'size' => $dirSize,
                    'age_hours' => round((time() - $dirTime) / 3600, 1),
                    'modified' => date('Y-m-d H:i:s', $dirTime),
                ];
                $totalSize += $dirSize;
            }
        }
        
        if (empty($oldChunks)) {
            $this->info("✅ Aucun chunk ancien trouvé (> {$hours}h)");
            return 0;
        }
        
        $chunkCount = count($oldChunks);
        $totalSizeFormatted = $this->formatBytes($totalSize);
        
        $this->warn("⚠️  {$chunkCount} chunks anciens trouvés ({$totalSizeFormatted})");
        $this->newLine();

        // Afficher les chunks à supprimer
        $this->table(
            ['Nom', 'Taille', 'Âge', 'Modifié le'],
            collect($oldChunks)->map(function ($chunk) {
                return [
                    $chunk['name'],
                    $this->formatBytes($chunk['size']),
                    $chunk['age_hours'] . 'h',
                    $chunk['modified'],
                ];
            })->toArray()
        );
        
        if ($isDryRun) {
            $this->info('🔍 Mode dry-run activé - Aucune suppression effectuée');
            $this->comment('Exécutez sans --dry-run pour supprimer ces chunks');
            return 0;
        }
        
        // Demander confirmation sauf si --force
        if (!$force && !$this->confirm("Voulez-vous supprimer ces {$chunkCount} chunks ({$totalSizeFormatted}) ?")) {
            $this->info('❌ Opération annulée');
            return 0;
        }
        
        // Supprimer les chunks
        $deletedCount = 0;
        $deletedSize = 0;
        
        foreach ($oldChunks as $chunk) {
            if ($this->deleteDirectory($chunk['path'])) {
                $deletedCount++;
                $deletedSize += $chunk['size'];
                $this->line("  ✓ Supprimé: {$chunk['name']} ({$this->formatBytes($chunk['size'])})");
                
                Log::info("🧹 Chunk ancien supprimé", [
                    'name' => $chunk['name'],
                    'size' => $chunk['size'],
                    'age_hours' => $chunk['age_hours'],
                ]);
            } else {
                $this->error("  ✗ Erreur suppression: {$chunk['name']}");
            }
        }
        
        $this->newLine();
        $this->info("✅ Nettoyage terminé :");
        $this->info("   • {$deletedCount} chunks supprimés");
        $this->info("   • {$this->formatBytes($deletedSize)} libérés");
        
        return 0;
    }
    
    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    private function deleteDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }
        
        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        
        return @rmdir($directory);
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
