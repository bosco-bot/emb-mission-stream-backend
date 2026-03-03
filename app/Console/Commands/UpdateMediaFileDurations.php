<?php

namespace App\Console\Commands;

use App\Models\MediaFile;
use App\Services\MediaMetadataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateMediaFileDurations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:update-durations {--limit= : Nombre maximum de fichiers à traiter}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Met à jour les durées des fichiers média (audio/video) qui n\'ont pas de durée';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $metadataService = new MediaMetadataService();
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        // Récupérer les fichiers audio/video sans durée
        $query = MediaFile::whereIn('file_type', ['audio', 'video'])
            ->whereNull('duration');

        if ($limit) {
            $query->limit($limit);
        }

        $files = $query->get();
        $total = $files->count();

        if ($total === 0) {
            $this->info('Aucun fichier à traiter.');
            return 0;
        }

        $this->info("Traitement de {$total} fichier(s)...");
        $this->newLine();

        $updated = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($files as $file) {
            try {
                $durationSeconds = $metadataService->getDurationInSeconds($file->file_path);

                if ($durationSeconds !== null) {
                    // Convertir en entier (secondes) pour le stockage
                    $duration = (int) round($durationSeconds);
                    $file->update(['duration' => $duration]);
                    $updated++;
                } else {
                    $failed++;
                    Log::warning("Impossible d'extraire la durée", [
                        'media_file_id' => $file->id,
                        'file_path' => $file->file_path
                    ]);
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error("Erreur lors de l'extraction de la durée", [
                    'media_file_id' => $file->id,
                    'file_path' => $file->file_path,
                    'error' => $e->getMessage()
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ {$updated} fichier(s) mis à jour");
        if ($failed > 0) {
            $this->warn("⚠️  {$failed} fichier(s) en échec");
        }

        return 0;
    }
}
