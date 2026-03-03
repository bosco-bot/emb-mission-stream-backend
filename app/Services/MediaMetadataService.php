<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class MediaMetadataService
{
    public function getDurationInSeconds(string $diskPath): ?float
    {
        $absolutePath = storage_path('app/media/' . ltrim($diskPath, '/'));

        if (! file_exists($absolutePath)) {
            Log::warning('ffprobe: fichier introuvable', ['path' => $absolutePath]);
            return null;
        }

        $process = new Process([
            'ffprobe',
            '-v',
            'error',
            '-show_entries',
            'format=duration',
            '-of',
            'default=noprint_wrappers=1:nokey=1',
            $absolutePath,
        ]);

        // Timeout de 30 secondes pour éviter de bloquer l'upload
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('ffprobe: échec d\'extraction de durée', [
                'path' => $absolutePath,
                'error' => $process->getErrorOutput(),
            ]);
            return null;
        }

        $duration = trim($process->getOutput());

        return is_numeric($duration) ? (float) $duration : null;
    }
}




















