<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Storage;

class DiagnoseSourceMissing extends Command
{
    protected $signature = 'diagnose:source-missing';
    protected $description = 'Diagnostique la cause des "Source manquante" (fichier physique absent) sans rien supprimer';

    public function handle(): int
    {
        $this->info('Diagnostic: cause du badge "Source manquante"');
        $this->info('Critère: file_path tel que le fichier n\'existe pas (disque media).');
        $this->newLine();

        $videoFiles = MediaFile::where('file_type', 'video')
            ->whereNotNull('file_path')
            ->orderBy('created_at', 'desc')
            ->get();

        $sourceMissing = [];
        foreach ($videoFiles as $mediaFile) {
            $pathUsed = Storage::disk('media')->path($mediaFile->file_path);
            $exists = file_exists($pathUsed);

            if (!$exists) {
                // Vérifier si le fichier existerait avec un ancien format (sans préfixe media/)
                $pathWithoutMediaPrefix = null;
                $existsWithoutPrefix = false;
                if (str_starts_with(ltrim($mediaFile->file_path, '/'), 'media/')) {
                    $altPath = preg_replace('#^media/#', '', ltrim($mediaFile->file_path, '/'));
                    $pathWithoutMediaPrefix = Storage::disk('media')->path($altPath);
                    $existsWithoutPrefix = file_exists($pathWithoutMediaPrefix);
                }
                $sourceMissing[] = [
                    'id' => $mediaFile->id,
                    'original_name' => $mediaFile->original_name,
                    'file_path' => $mediaFile->file_path,
                    'resolved_path' => $pathUsed,
                    'exists' => $exists,
                    'exists_alt' => $existsWithoutPrefix,
                    'alt_path' => $pathWithoutMediaPrefix,
                ];
            }
        }

        if (empty($sourceMissing)) {
            $this->info('Aucun fichier vidéo avec source manquante.');
            return 0;
        }

        $this->warn(count($sourceMissing) . ' vidéo(s) avec source manquante:');
        $this->table(
            ['ID', 'original_name', 'file_path (DB)', 'existe (chemin actuel)', 'existe (sans préfixe media/)'],
            collect($sourceMissing)->map(fn ($r) => [
                $r['id'],
                \Illuminate\Support\Str::limit($r['original_name'], 35),
                \Illuminate\Support\Str::limit($r['file_path'], 45),
                $r['exists'] ? 'oui' : 'non',
                $r['exists_alt'] ? 'oui' : ($r['alt_path'] === null ? '-' : 'non'),
            ])->toArray()
        );
        $this->newLine();
        $this->comment('Causes possibles à vérifier (sans supprimer les entrées):');
        $this->comment('  1. Multi-serveur: upload sur un nœud, API sur un autre → stockage non partagé.');
        $this->comment('  2. Symlink storage/app ou volume différent après déploiement.');
        $this->comment('  3. Ancien format file_path (ex. préfixe "media/" en double) → si "existe (sans préfixe)" = oui, corriger file_path en BDD.');
        $this->comment('  4. Fichier supprimé manuellement ou par un script hors Laravel.');
        $this->newLine();
        $this->comment('Exemple de chemin résolu (premier enregistrement): ' . ($sourceMissing[0]['resolved_path'] ?? 'N/A'));
        if (!empty($sourceMissing[0]['exists_alt']) && !empty($sourceMissing[0]['alt_path'])) {
            $this->comment('  → Fichier trouvé ici (sans préfixe media/): ' . $sourceMissing[0]['alt_path']);
        }

        return 0;
    }
}
