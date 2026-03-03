<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\WebTVStats;
use App\Models\WebRadioStats;

class WebTVStatsController extends Controller
{
    /**
     * Obtenir l'audience live (WebTV + WebRadio)
     */
    public function getLiveAudience(): JsonResponse
    {
        try {
            $webtvAudience = 0;
            $webtvIsLive = false;
            $webradioAudience = 0;
            $webradioIsLive = false;
            
            // ===== WEBTV =====
            $response = Http::withBasicAuth('emb_webtv', 'EmbMission!Secure#789')
                ->timeout(10)
                ->get('http://localhost:5080/LiveApp/rest/v2/broadcasts/list/0/10');
            
            if ($response->successful()) {
                $streams = $response->json();
                
                if (is_array($streams)) {
                    foreach ($streams as $stream) {
                        if (isset($stream['status']) && $stream['status'] === 'broadcasting') {
                            $webtvIsLive = true;
                            $webtvAudience = $stream['hlsViewerCount'] ?? 0;
                            break;
                        }
                    }
                }
            }
            
            // ===== WEBRADIO =====
            // Appeler AzuraCast API pour obtenir l'audience radio
            try {
                $azuraUrl = env('AZURACAST_BASE_URL');
                $azuraApiKey = env('AZURACAST_API_KEY');
                $stationId = env('AZURACAST_STATION_ID', '1');
                
                $azuraResponse = Http::withHeaders([
                    'X-API-Key' => $azuraApiKey
                ])
                ->timeout(5)
                ->get("$azuraUrl/api/station/$stationId/nowplaying");
                
                if ($azuraResponse->successful()) {
                    $nowPlaying = $azuraResponse->json();
                    
                    // Vérifier si la station est en live
                    if (isset($nowPlaying['live']) && $nowPlaying['live']['is_live']) {
                        $webradioIsLive = true;
                        // Récupérer le nombre d'auditeurs du stream live
                        $webradioAudience = $nowPlaying['live']['listeners']['total'] ?? 0;
                    } else {
                        // Si pas en live, compter les auditeurs des mounts
                        $webradioAudience = 0;
                        if (isset($nowPlaying['station']['mounts'])) {
                            foreach ($nowPlaying['station']['mounts'] as $mount) {
                                $webradioAudience += $mount['listeners']['current'] ?? 0;
                            }
                        }
                    }
                }
            } catch (\Exception $azuraException) {
                Log::warning('Erreur récupération AzuraCast: ' . $azuraException->getMessage());
                // En cas d'erreur, garder les valeurs par défaut (0)
            }
            
            $totalAudience = $webtvAudience + $webradioAudience;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'webtv' => [
                        'audience' => $webtvAudience,
                        'is_live' => $webtvIsLive
                    ],
                    'webradio' => [
                        'audience' => $webradioAudience,
                        'is_live' => $webradioIsLive
                    ],
                    'total' => [
                        'audience' => $totalAudience,
                        'is_live' => $webtvIsLive || $webradioIsLive
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('WebTVStatsController getLiveAudience: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'audience live',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtenir les vues totales sur une période (WebTV + WebRadio)
     */
    public function getTotalViews(Request $request): JsonResponse
    {
        try {
            $days = $request->query('days', 30);
            
            // Calculer les vraies vues depuis la base de données
            $webtvViews = WebTVStats::where('date', '>=', now()->subDays($days))
                ->sum('total_views');
                
            $webradioViews = WebRadioStats::where('date', '>=', now()->subDays($days))
                ->sum('total_listens');
            
            $totalViews = $webtvViews + $webradioViews;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'webtv' => $webtvViews,
                    'webradio' => $webradioViews,
                    'total' => $totalViews,
                    'period' => $days . 'j'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('WebTVStatsController getTotalViews: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des vues totales',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtenir la durée de diffusion totale sur une période (WebTV + WebRadio)
     */
    public function getBroadcastDuration(Request $request): JsonResponse
    {
        try {
            $days = $request->query('days', 30);
            
            // Calculer la vraie durée depuis la base de données
            $webtvSeconds = WebTVStats::where('date', '>=', now()->subDays($days))
                ->sum('broadcast_duration_seconds');
                
            $webradioSeconds = WebRadioStats::where('date', '>=', now()->subDays($days))
                ->sum('broadcast_duration_seconds');
                
            $totalSeconds = $webtvSeconds + $webradioSeconds;
            
            $hours = floor($totalSeconds / 3600);
            $minutes = floor(($totalSeconds % 3600) / 60);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'webtv' => [
                        'hours' => floor($webtvSeconds / 3600),
                        'minutes' => floor(($webtvSeconds % 3600) / 60),
                        'total_seconds' => $webtvSeconds
                    ],
                    'webradio' => [
                        'hours' => floor($webradioSeconds / 3600),
                        'minutes' => floor(($webradioSeconds % 3600) / 60),
                        'total_seconds' => $webradioSeconds
                    ],
                    'total' => [
                        'hours' => $hours,
                        'minutes' => $minutes,
                        'total_seconds' => $totalSeconds
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('WebTVStatsController getBroadcastDuration: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la durée de diffusion',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtenir l'engagement (WebTV + WebRadio)
     */
    public function getEngagement(): JsonResponse
    {
        try {
            // Calculer le vrai engagement depuis la base de données
            $webtvEngagement = WebTVStats::where('date', '>=', now()->subDays(30))
                ->sum('engagement');
                
            $webradioEngagement = WebRadioStats::where('date', '>=', now()->subDays(30))
                ->sum('engagement');
                
            $totalEngagement = $webtvEngagement + $webradioEngagement;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'webtv' => $webtvEngagement,
                    'webradio' => $webradioEngagement,
                    'total' => $totalEngagement
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('WebTVStatsController getEngagement: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'engagement',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Obtenir la durée de diffusion en cours (temps réel)
     * 
     * Retourne la durée du stream en cours depuis son démarrage
     */
    public function getCurrentStreamDuration(): JsonResponse
    {
        try {
            $webtvDuration = null;
            $webtvIsLive = false;
            $webradioDuration = null;
            $webradioIsLive = false;
            
            // ===== WEBTV =====
            $response = Http::withBasicAuth('emb_webtv', 'EmbMission!Secure#789')
                ->timeout(10)
                ->get('http://localhost:5080/LiveApp/rest/v2/broadcasts/list/0/10');
            
            if ($response->successful()) {
                $streams = $response->json();
                
                if (is_array($streams)) {
                    foreach ($streams as $stream) {
                        if (isset($stream['status']) && $stream['status'] === 'broadcasting') {
                            $webtvIsLive = true;
                            // duration en millisecondes, convertir en secondes
                            $durationMs = $stream['duration'] ?? 0;
                            $webtvDuration = floor($durationMs / 1000);
                            break;
                        }
                    }
                }
            }
            
            // ===== WEBRADIO =====
            // AzuraCast ne fournit pas directement la durée du stream en cours
            // On peut utiliser la durée de la piste actuelle comme approximation
            try {
                $azuraUrl = env('AZURACAST_BASE_URL');
                $azuraApiKey = env('AZURACAST_API_KEY');
                $stationId = env('AZURACAST_STATION_ID', '1');
                
                $azuraResponse = Http::withHeaders([
                    'X-API-Key' => $azuraApiKey
                ])
                ->timeout(5)
                ->get("$azuraUrl/api/station/$stationId/nowplaying");
                
                if ($azuraResponse->successful()) {
                    $nowPlaying = $azuraResponse->json();
                    
                    // Vérifier si la station est en live
                    if (isset($nowPlaying['live']) && $nowPlaying['live']['is_live']) {
                        $webradioIsLive = true;
                        // Durée écoulée de la piste actuelle (en secondes)
                        $webradioDuration = $nowPlaying['now_playing']['elapsed'] ?? 0;
                    }
                }
            } catch (\Exception $azuraException) {
                Log::warning('Erreur récupération AzuraCast: ' . $azuraException->getMessage());
            }
            
            // Formater les durées
            $formatDuration = function($seconds) {
                if ($seconds === null) return null;
                $hours = floor($seconds / 3600);
                $minutes = floor(($seconds % 3600) / 60);
                $secs = $seconds % 60;
                
                if ($hours > 0) {
                    return sprintf('%dh %02dm %02ds', $hours, $minutes, $secs);
                } elseif ($minutes > 0) {
                    return sprintf('%dm %02ds', $minutes, $secs);
                } else {
                    return sprintf('%ds', $secs);
                }
            };
            
            return response()->json([
                'success' => true,
                'data' => [
                    'webtv' => [
                        'is_live' => $webtvIsLive,
                        'duration_seconds' => $webtvDuration,
                        'duration_formatted' => $formatDuration($webtvDuration),
                        'human_readable' => $webtvDuration ? "diffuser depuis {$formatDuration($webtvDuration)}" : null
                    ],
                    'webradio' => [
                        'is_live' => $webradioIsLive,
                        'duration_seconds' => $webradioDuration,
                        'duration_formatted' => $formatDuration($webradioDuration),
                        'human_readable' => $webradioDuration ? "diffuser depuis {$formatDuration($webradioDuration)}" : null
                    ],
                    'total' => [
                        'is_live' => $webtvIsLive || $webradioIsLive,
                        'duration_seconds' => $webtvDuration ?? $webradioDuration,
                        'duration_formatted' => $formatDuration($webtvDuration ?? $webradioDuration),
                        'human_readable' => ($webtvDuration || $webradioDuration) ? "diffuser depuis {$formatDuration($webtvDuration ?? $webradioDuration)}" : null
                    ]
                ],
                'generated_at' => now()->toIso8601String()
            ]);
            
        } catch (\Exception $e) {
            Log::error('getCurrentStreamDuration error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la durée du stream',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    

}

