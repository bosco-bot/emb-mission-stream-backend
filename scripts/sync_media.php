<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MediaFile;

$azuracastPath = '/var/lib/docker/volumes/azuracast_station_data/_data/radio_emb_mission/media/';

echo "🚀 Démarrage de la synchronisation\n";

$mediaFiles = MediaFile::where('status', 'processed')
    ->where('type', 'audio')
    ->get();

$totalFiles = $mediaFiles->count();
echo "📊 Fichiers trouvés: {$totalFiles}\n";

$copied = 0;
$skipped = 0;
$errors = 0;

foreach ($mediaFiles as $mediaFile) {
    $sourcePath = storage_path('app/' . $mediaFile->file_path);
    $targetPath = $azuracastPath . $mediaFile->filename;

        echo "❌ Fichier source introuvable: {$mediaFile->filename}\n";
        $errors++;
        continue;
    }

    if (file_exists($targetPath)) {
        $skipped++;
        continue;
    }

    if (copy($sourcePath, $targetPath)) {
        $copied++;
        if (($copied % 10) == 0) {
            echo "✅ Progression: {$copied}/{$totalFiles} fichiers copiés\n";
        }
    } else {
        echo "❌ Erreur de copie: {$mediaFile->filename}\n";
        $errors++;
    }
}

echo "\n📊 RÉSULTAT:\n";
echo "• Copiés: {$copied}\n";
echo "• Déjà présents: {$skipped}\n";
echo "• Erreurs: {$errors}\n";
echo "\n✅ Synchronisation terminée\n";
