<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Storage;

/**
 * Restaure les fichiers source audio manquants à partir des copies AzuraCast
 * (copiés lors de la sync des playlists radio).
 */
class RestoreAudioSourceFromAzuraCast extends Command
{
    protected $signature = 'media:restore-audio-source-from-azuracast
                            {--dry-run : Afficher ce qui serait fait sans écrire}
                            {--path= : Chemin personnalisé du dossier média AzuraCast}';
    protected $description = 'Restaure les sources audio manquantes depuis le dossier média AzuraCast';

    /**
     * Chemins possibles du dossier média AzuraCast (à tester dans l'ordre).
     */
    protected function getAzuraCastMediaPaths(): array
    {
        $custom = $this->option('path');
        if ($custom !== null && $custom !== '') {
            return [rtrim($custom, '/') . '/'];
        }
        return [
            '/var/lib/docker/volumes/azuracast_station_data/_data/radio_emb_mission/media/',
            env('AZURACAST_MEDIA_PATH', '/var/azuracast/stations/radio_emb_mission/media/'),
        ];
    }

    protected function findSourcePath(MediaFile $mediaFile): ?string
    {
        $filename = $mediaFile->filename;
        foreach ($this->getAzuraCastMediaPaths() as $basePath) {
            if ($basePath === '' || $basePath === '/' || !is_dir($basePath)) {
                continue;
            }
            $path = $basePath . $filename;
            if (is_file($path)) {
                return $path;
            }
            $pathByOriginal = $basePath . $mediaFile->original_name;
            if (is_file($pathByOriginal)) {
                return $pathByOriginal;
            }
        }
        return null;
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $audios = MediaFile::where('file_type', 'audio')
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->get();

        $toRestore = [];
        foreach ($audios as $mediaFile) {
            $destPath = Storage::disk('media')->path($mediaFile->file_path);
            if (file_exists($destPath)) {
                continue;
            }
            $sourcePath = $this->findSourcePath($mediaFile);
            if ($sourcePath === null) {
                $this->line("<comment>Skip ID {$mediaFile->id}: pas de copie AzuraCast pour {$mediaFile->filename}</comment>");
                continue;
            }
            $toRestore[] = [
                'media' => $mediaFile,
                'source' => $sourcePath,
                'dest' => $destPath,
            ];
        }

        if (empty($toRestore)) {
            $this->info('Aucun audio à restaurer (sources déjà présentes ou copie AzuraCast absente).');
            return 0;
        }

        $this->info('Audios à restaurer depuis AzuraCast: ' . count($toRestore));
        if ($dryRun) {
            foreach ($toRestore as $r) {
                $this->line('  ID ' . $r['media']->id . ' → ' . $r['dest']);
            }
            $this->comment('Dry-run: exécutez sans --dry-run pour restaurer.');
            return 0;
        }

        $ok = 0;
        $fail = 0;
        foreach ($toRestore as $r) {
            $media = $r['media'];
            $dest = $r['dest'];
            $dir = dirname($dest);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    $this->error("Impossible de créer le répertoire: {$dir}");
                    $fail++;
                    continue;
                }
            }
            if (@copy($r['source'], $dest)) {
                chmod($dest, 0644);
                $this->line("OK ID {$media->id}: " . basename($dest));
                $ok++;
            } else {
                $this->error("Échec ID {$media->id}: copie de {$r['source']} vers {$dest}");
                $fail++;
            }
        }

        $this->newLine();
        $this->info("Résumé: {$ok} restauré(s), {$fail} échec(s).");
        return $fail > 0 ? 1 : 0;
    }
}
