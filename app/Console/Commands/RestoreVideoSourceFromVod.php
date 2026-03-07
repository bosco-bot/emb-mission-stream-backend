<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Restaure les fichiers source vidéo manquants à partir des copies HLS VOD
 * (Ant Media: /usr/local/antmedia/webapps/LiveApp/streams/vod_mf_{media_file_id}/).
 */
class RestoreVideoSourceFromVod extends Command
{
    protected $signature = 'media:restore-video-source-from-vod
                            {--dry-run : Afficher ce qui serait fait sans écrire}
                            {--id= : Restaurer uniquement ce media_file_id}';
    protected $description = 'Restaure les sources vidéo manquantes à partir du HLS VOD (vod_mf_*)';

    private string $streamsBase = '/usr/local/antmedia/webapps/LiveApp/streams';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $onlyId = $this->option('id') ? (int) $this->option('id') : null;

        $query = MediaFile::where('file_type', 'video')
            ->whereNotNull('file_path')
            ->orderBy('id');

        if ($onlyId !== null) {
            $query->where('id', $onlyId);
        }

        $videos = $query->get();
        $toRestore = [];

        foreach ($videos as $mediaFile) {
            $destPath = Storage::disk('media')->path($mediaFile->file_path);
            if (file_exists($destPath)) {
                continue;
            }
            $vodName = 'vod_mf_' . $mediaFile->id;
            $playlistPath = $this->streamsBase . '/' . $vodName . '/playlist.m3u8';
            if (!is_file($playlistPath)) {
                $this->line("<comment>Skip ID {$mediaFile->id}: pas de VOD HLS à {$playlistPath}</comment>");
                continue;
            }
            $toRestore[] = [
                'media' => $mediaFile,
                'playlist' => $playlistPath,
                'dest' => $destPath,
            ];
        }

        if (empty($toRestore)) {
            $this->info('Aucune vidéo à restaurer (sources déjà présentes ou VOD HLS absent).');
            return 0;
        }

        $this->info('Vidéos à restaurer depuis le VOD HLS: ' . count($toRestore));
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

            $process = new Process([
                'ffmpeg',
                '-y',
                '-i', $r['playlist'],
                '-c', 'copy',
                '-movflags', '+faststart',
                $dest,
            ]);
            $process->setTimeout(3600);
            $process->run();

            if ($process->isSuccessful()) {
                $this->line("OK ID {$media->id}: " . basename($dest));
                $ok++;
            } else {
                $this->error("Échec ID {$media->id}: " . $process->getErrorOutput());
                $fail++;
            }
        }

        $this->newLine();
        $this->info("Résumé: {$ok} restauré(s), {$fail} échec(s).");
        return $fail > 0 ? 1 : 0;
    }
}
