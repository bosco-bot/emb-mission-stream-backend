<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\MediaFile;
use Exception;

class ProcessMediaFile implements ShouldQueue
{
    use Queueable;

    public $mediaFile;
    public $tries = 3;
    public $timeout = 300;

    public function __construct(MediaFile $mediaFile)
    {
        $this->mediaFile = $mediaFile;
    }

    public function handle(): void
    {
        try {
            Log::info("🚀 Traitement fichier: {$this->mediaFile->original_name}");
            
            // SOLUTION UNIFIÉE: Utiliser uniquement le disque par défaut
            $filePath = storage_path('app/media/' . $this->mediaFile->file_path);
            
            if (!file_exists($filePath)) {
                Log::error("❌ Fichier physique introuvable: {$filePath}");
                
                $this->mediaFile->update([
                    "status" => "error",
                    "progress" => 0,
                    "error_message" => "Fichier physique introuvable: " . $filePath,
                    "estimated_time_remaining" => null,
                ]);
                
                throw new Exception("Fichier physique introuvable: {$filePath}");
            }
            
            // Vérifier la taille du fichier
            $fileSize = filesize($filePath);
            if ($fileSize === 0) {
                Log::error("❌ Fichier vide: {$filePath}");
                
                $this->mediaFile->update([
                    "status" => "error",
                    "progress" => 0,
                    "error_message" => "Le fichier est vide (0 octets)",
                    "estimated_time_remaining" => null,
                ]);
                
                throw new Exception("Fichier vide: {$filePath}");
            }
            
            // Tout est OK, marquer comme completed
            $this->mediaFile->update([
                "status" => "completed",
                "progress" => 100,
                "error_message" => null,
                "estimated_time_remaining" => null,
            ]);
            
            Log::info("✅ Terminé: {$this->mediaFile->original_name} ({$fileSize} octets)");
            
        } catch (Exception $e) {
            Log::error("❌ Erreur: {$e->getMessage()}");
            
            $this->mediaFile->update([
                "status" => "error",
                "error_message" => $e->getMessage(),
                "progress" => 0,
            ]);
            
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error("❌ Job échoué: {$this->mediaFile->original_name}");
        
        $this->mediaFile->update([
            "status" => "error",
            "error_message" => "Échec: {$exception->getMessage()}",
        ]);
    }
}
