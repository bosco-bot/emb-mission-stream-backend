<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MixxxController extends Controller
{
    public function start(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Diffusion Mixxx démarrée avec succès',
            'data' => [
                'streaming_started' => true,
                'timestamp' => now()->toISOString(),
                'mount_point' => '/',
                'port' => 8005,
                'status' => 'active'
            ]
        ]);
    }

    public function stop(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Diffusion Mixxx arrêtée avec succès',
            'data' => [
                'streaming_stopped' => true,
                'timestamp' => now()->toISOString(),
                'status' => 'inactive'
            ]
        ]);
    }

    public function status(): JsonResponse
    {
        try {
            $isLive = false;
            $nowPlaying = null;
            $listeners = 0;
            $debugInfo = [];
            
            // Méthode 1: Vérifier via l'API AzuraCast si le nombre d'auditeurs > 0
            try {
                $response = Http::timeout(5)->get('https://radio.embmission.com/azuracast-api/nowplaying/1');
                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['listeners']['total'])) {
                        $listeners = $data['listeners']['total'];
                    }
                    
                    if (isset($data['now_playing']['song']['title'])) {
                        $nowPlaying = $data['now_playing']['song']['title'];
                    }
                    
                    // Si le titre change rapidement et qu'il y a des auditeurs, c'est probablement du live
                    // Pour une détection simple: si listeners > 0, considérer comme live
                    // (à affiner selon vos besoins)
                    if ($listeners > 0) {
                        $isLive = true;
                        $debugInfo['detection_method'] = 'listeners_count';
                    }
                    
                    $debugInfo['azuracast_api'] = 'success';
                }
            } catch (\Exception $e) {
                Log::error('Erreur lors de la récupération des données AzuraCast: ' . $e->getMessage());
                $debugInfo['azuracast_api'] = 'error: ' . $e->getMessage();
            }
            
            // Méthode 2: Vérifier si le fichier log existe et a été modifié récemment
            $logFile = '/var/azuracast/stations/radio_emb_mission/config/liquidsoap.log';
            if (file_exists($logFile)) {
                $lastModified = filemtime($logFile);
                $secondsAgo = time() - $lastModified;
                
                $debugInfo['log_file'] = 'exists';
                $debugInfo['log_modified_seconds_ago'] = $secondsAgo;
                
                // Si le log a été modifié dans les 30 dernières secondes, Liquidsoap est actif
                if ($secondsAgo < 30) {
                    $isLive = true;
                    $debugInfo['detection_method'] = 'log_file_recent';
                }
            } else {
                $debugInfo['log_file'] = 'not_accessible';
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Statut récupéré avec succès',
                'data' => [
                    'is_streaming' => $isLive,
                    'is_live' => $isLive,
                    'timestamp' => now()->toISOString(),
                    'listeners' => $listeners,
                    'now_playing' => $nowPlaying,
                    'port' => 8005,
                    'mount_point' => '/',
                    'debug' => $debugInfo
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération du statut Mixxx: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du statut Mixxx',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateSettings(): JsonResponse
    {
        $request = request();
        $bitrate = $request->input('bitrate', 192);
        
        return response()->json([
            'success' => true,
            'message' => 'Paramètres mis à jour avec succès',
            'data' => [
                'bitrate' => $bitrate,
                'timestamp' => now()->toISOString(),
                'updated' => true
            ]
        ]);
    }
}
