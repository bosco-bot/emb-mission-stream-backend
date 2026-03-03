<?php
/**
 * Script de diagnostic pour vérifier l'état du live WebTV
 * Usage: php check_live_status.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

echo "=== DIAGNOSTIC LIVE WEBTV ===\n\n";

// 1. Vérifier Ant Media Server
echo "1. Vérification Ant Media Server...\n";
try {
    $response = Http::withBasicAuth('admin', 'Emb2024!')
        ->timeout(5)
        ->get('http://localhost:5080/LiveApp/rest/v2/broadcasts/list/0/10');
    
    if ($response->successful()) {
        $streams = $response->json();
        echo "   ✅ Connexion Ant Media OK\n";
        
        if (is_array($streams) && count($streams) > 0) {
            echo "   📊 Streams trouvés: " . count($streams) . "\n\n";
            
            $hasBroadcasting = false;
            foreach ($streams as $stream) {
                $status = $stream['status'] ?? 'unknown';
                $streamId = $stream['streamId'] ?? 'N/A';
                $viewers = $stream['listenerCount'] ?? 0;
                $hlsViewers = $stream['hlsViewerCount'] ?? 0;
                
                echo "   Stream ID: {$streamId}\n";
                echo "   Status: {$status}\n";
                echo "   Viewers: {$viewers} (HLS: {$hlsViewers})\n";
                
                if ($status === 'broadcasting') {
                    $hasBroadcasting = true;
                    echo "   ✅ SIGNAL ACTIF - STREAM EN BROADCASTING\n";
                    
                    if (isset($stream['duration'])) {
                        $durationSec = floor(($stream['duration'] ?? 0) / 1000);
                        $minutes = floor($durationSec / 60);
                        $seconds = $durationSec % 60;
                        echo "   ⏱️  Durée: {$minutes}m {$seconds}s\n";
                    }
                }
                echo "   ---\n";
            }
            
            if (!$hasBroadcasting) {
                echo "   ⚠️  Aucun stream en broadcasting détecté\n";
            }
        } else {
            echo "   ⚠️  Aucun stream trouvé\n";
        }
    } else {
        echo "   ❌ Erreur API Ant Media: HTTP " . $response->status() . "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

// 2. Vérifier fichier HLS
echo "\n2. Vérification fichier HLS...\n";
$hlsPath = '/usr/local/antmedia/webapps/LiveApp/streams/live_transcoded.m3u8';
if (file_exists($hlsPath)) {
    $mtime = filemtime($hlsPath);
    $age = time() - $mtime;
    echo "   ✅ Fichier HLS trouvé\n";
    echo "   📅 Dernière modif: " . date('Y-m-d H:i:s', $mtime) . " (il y a {$age}s)\n";
    
    if ($age < 10) {
        echo "   ✅ Fichier récent - Stream probablement actif\n";
    } else {
        echo "   ⚠️  Fichier ancien - Stream peut être inactif\n";
    }
} else {
    echo "   ❌ Fichier HLS non trouvé: {$hlsPath}\n";
}

// 3. Vérifier API Laravel
echo "\n3. Vérification API Laravel...\n";
try {
    $apiResponse = Http::timeout(10)->get('http://localhost/api/webtv/stats/all');
    if ($apiResponse->successful()) {
        $data = $apiResponse->json();
        $webtv = $data['webtv'] ?? null;
        if ($webtv) {
            echo "   ✅ API Laravel répond\n";
            echo "   📊 is_live: " . ($webtv['is_live'] ? 'OUI ✅' : 'NON ❌') . "\n";
            echo "   👥 Audience: " . ($webtv['audience'] ?? 0) . "\n";
            if (isset($webtv['stream_id'])) {
                echo "   🆔 Stream ID: " . $webtv['stream_id'] . "\n";
            }
        } else {
            echo "   ⚠️  Données WebTV non trouvées dans la réponse\n";
        }
    } else {
        echo "   ⚠️  Erreur HTTP: " . $apiResponse->status() . "\n";
    }
} catch (\Exception $e) {
    echo "   ⚠️  Erreur API: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DIAGNOSTIC ===\n";

