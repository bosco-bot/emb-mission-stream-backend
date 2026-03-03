<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\MediaFile;
use App\Models\Playlist;
use App\Models\PlaylistItem;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🎵 SYNCHRONISATION LARAVEL → AZURACAST\n";
echo "=====================================\n\n";

// 1. Récupération des fichiers audio
$audioFiles = MediaFile::where('file_type', 'audio')
    ->where('status', 'completed')
    ->get();

echo "📁 Fichiers audio trouvés: " . $audioFiles->count() . "\n";

foreach ($audioFiles as $file) {
    echo "• " . $file->original_name . " (" . $file->file_path . ")\n";
}

// 2. Récupération des playlists
$playlists = Playlist::with('items.mediaFile')->get();

echo "\n🎶 Playlists trouvées: " . $playlists->count() . "\n";

foreach ($playlists as $playlist) {
    echo "• " . $playlist->name . " (" . $playlist->items->count() . " éléments)\n";
    
    foreach ($playlist->items as $item) {
        if ($item->mediaFile) {
            echo "  - " . $item->mediaFile->original_name . "\n";
        }
    }
}

// 3. Génération des commandes AzuraCast
echo "\n🔧 COMMANDES AZURACAST À EXÉCUTER:\n";
echo "==================================\n";

echo "# 1. Upload des fichiers:\n";
foreach ($audioFiles as $file) {
    $filePath = storage_path('app/' . $file->file_path);
    if (file_exists($filePath)) {
        echo "curl -X POST -H 'X-API-Key: 4c1ffa679f50abe5:2e5149b9a2a11f310f46e4ccfce73cd8' \\\n";
        echo "  -F 'file=@$filePath' \\\n";
        echo "  'http://15.235.86.98:8080/api/station/1/files'\n\n";
    }
}

echo "# 2. Création des playlists:\n";
foreach ($playlists as $playlist) {
    echo "curl -X POST -H 'X-API-Key: 4c1ffa679f50abe5:2e5149b9a2a11f310f46e4ccfce73cd8' \\\n";
    echo "  -H 'Content-Type: application/json' \\\n";
    echo "  -d '{\"name\": \"" . $playlist->name . "\", \"source\": \"songs\", \"loop\": " . ($playlist->is_loop ? 'true' : 'false') . ", \"shuffle\": " . ($playlist->is_shuffle ? 'true' : 'false') . "}' \\\n";
    echo "  'http://15.235.86.98:8080/api/station/1/playlists'\n\n";
}

echo "\n✅ SCRIPT DE SYNCHRONISATION CRÉÉ\n";
