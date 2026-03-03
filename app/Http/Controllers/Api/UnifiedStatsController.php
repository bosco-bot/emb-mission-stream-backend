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
use App\Services\GA4DataService;

class UnifiedStatsController extends Controller
{
    /**
     * Obtenir toutes les statistiques combinées
     * 
     * Combine les données de:
     * - Ant Media Server (audience live WebTV)
     * - AzuraCast (audience live WebRadio)
     * - Base de données Laravel (vues, durée, engagement)
     */
    public function getAllStats(Request $request): JsonResponse
    {
        try {
            $days = $request->query('days', 30);
            
            // ===== AUDIENCE LIVE =====
            $liveStats = $this->getLiveAudience();
            
            // ===== DONNÉES GA4 (30j) - Appel unique =====
            $ga4Stats = $this->getGA4Stats($days);
            
            // ===== VUES TOTALES (30j) - Extraites de GA4 =====
            $viewsStats = [
                'webtv' => $ga4Stats['webtv']['total_views'] ?? 0,
                'webradio' => $ga4Stats['webradio']['total_views'] ?? 0,
                'total' => $ga4Stats['total'] ?? 0,
                'source' => 'ga4_data_api'
            ];
            
            // ===== DURÉE DE DIFFUSION (30j) =====
            $durationStats = $this->getBroadcastDuration($days);
            
            // ===== ENGAGEMENT (30j) =====
            $engagementStats = $this->getEngagement($days);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'live_audience' => $liveStats,
                    'total_views' => $viewsStats,
                    'broadcast_duration' => $durationStats,
                    'engagement' => $engagementStats,
                    'ga4_stats' => $ga4Stats,
                    'period' => $days . 'j',
                    'generated_at' => now()->toIso8601String()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('UnifiedStatsController getAllStats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * API de debug pour diagnostiquer les problèmes de comptage
     */
    public function debugStats(): JsonResponse
    {
        try {
            $streamId = 'R9rzvVzMPvCClU6s1562847982308692';
            
            // Test direct Ant Media
            $response = Http::withBasicAuth('admin', 'Emb2024!')
                ->timeout(5)
                ->get("http://localhost:5080/LiveApp/rest/v2/broadcasts/{$streamId}");
            
            $antMediaData = null;
            if ($response->successful()) {
                $antMediaData = $response->json();
            }
            
            // Cache actuel
            $cachedStats = Cache::get('webtv_live_stats');
            
            return response()->json([
                'success' => true,
                'debug_info' => [
                    'ant_media_raw' => $antMediaData,
                    'ant_media_status' => $response->status(),
                    'cached_stats' => $cachedStats,
                    'cache_exists' => Cache::has('webtv_live_stats'),
                    'timestamp' => time()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Vider le cache des statistiques WebTV
     */
    public function clearCache(): JsonResponse
    {
        try {
            // Vider le cache des stats WebTV
            Cache::forget('webtv_live_stats');
            
            // Obtenir les nouvelles données
            $streamId = 'R9rzvVzMPvCClU6s1562847982308692';
            $response = Http::withBasicAuth('admin', 'Emb2024!')
                ->timeout(5)
                ->get("http://localhost:5080/LiveApp/rest/v2/broadcasts/{$streamId}");
            
            $freshData = null;
            if ($response->successful()) {
                $freshData = $response->json();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Cache vidé avec succès',
                'data' => [
                    'cache_cleared' => true,
                    'fresh_ant_media_data' => $freshData,
                    'timestamp' => time()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtenir l'audience live (WebTV + WebRadio)
     */
    private function getLiveAudience(): array
    {
        $webtvAudience = 0;
        $webtvIsLive = false;
        $webradioAudience = 0;
        $webradioIsLive = false;
        
        // ===== WEBTV - Rechercher dynamiquement le stream en broadcasting =====
        try {
            // 🎯 Rechercher dynamiquement le stream en broadcasting au lieu d'un ID fixe
            $webtvStats = Cache::remember('webtv_live_stats', 30, function () {
                try {
                    // Lister tous les streams et trouver celui qui est en broadcasting
                    $response = Http::withBasicAuth('admin', 'Emb2024!')
                        ->timeout(5)
                        ->get("http://localhost:5080/LiveApp/rest/v2/broadcasts/list/0/10");
                    
                    if ($response->successful()) {
                        $streams = $response->json();
                        
                        if (is_array($streams)) {
                            // Chercher le premier stream en broadcasting
                            foreach ($streams as $stream) {
                                if (isset($stream['status']) && $stream['status'] === 'broadcasting') {
                                    $streamId = $stream['streamId'] ?? null;
                                    
                                    if ($streamId) {
                                // Compter les IPs uniques au lieu du compteur brut d'Ant Media
                                $viewerCount = $this->getUniqueViewersFromLogs($streamId);
                                $viewerCount = max(0, $viewerCount); // Jamais négatif
                                
                                return [
                                    'is_live' => true,
                                    'audience' => $viewerCount,
                                            'stream_id' => $streamId,
                                    'cached_at' => time()
                                ];
                                    }
                                }
                            }
                        }
                    }
                    
                    // Aucun stream en broadcasting trouvé
                    return [
                        'is_live' => false,
                        'audience' => 0,
                        'error' => 'No broadcasting stream found',
                        'cached_at' => time()
                    ];
                    
                } catch (\Exception $e) {
                    Log::error('WebTV stats cache error: ' . $e->getMessage());
                    return [
                        'is_live' => false,
                        'audience' => 0,
                        'error' => $e->getMessage(),
                        'cached_at' => time()
                    ];
                }
            });
            
            $webtvIsLive = $webtvStats['is_live'] ?? false;
            $webtvAudience = $webtvStats['audience'] ?? 0;
            
        } catch (\Exception $e) {
            Log::warning('Erreur récupération WebTV stats: ' . $e->getMessage());
        }
        
        // ===== WEBRADIO =====
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
                
                if (isset($nowPlaying['live']) && $nowPlaying['live']['is_live']) {
                    $webradioIsLive = true;
                    // Utiliser SEULEMENT les listeners globaux "unique" - pas besoin d'additionner les mounts
                    $webradioAudience = $nowPlaying['listeners']['unique'] ?? 0;  
                    // Les mounts sont déjà inclus dans le total global, pas besoin de les additionner
                }
            }
        } catch (\Exception $e) {
            Log::warning('Erreur récupération AzuraCast: ' . $e->getMessage());
        }
        
        $totalAudience = $webtvAudience + $webradioAudience;
        
        return [
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
        ];
    }
    
    /**
     * Obtenir la durée de diffusion totale sur une période
     */
    private function getBroadcastDuration(int $days): array
    {
        $webtvSeconds = WebTVStats::where('date', '>=', now()->subDays($days))
            ->sum('broadcast_duration_seconds');
            
        $webradioSeconds = WebRadioStats::where('date', '>=', now()->subDays($days))
            ->sum('broadcast_duration_seconds');
        
        $totalSeconds = $webtvSeconds + $webradioSeconds;
        
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        
        return [
            'webtv' => [
                'hours' => floor($webtvSeconds / 3600),
                'minutes' => floor(($webtvSeconds % 3600) / 60),
                'total_seconds' => (int) $webtvSeconds
            ],
            'webradio' => [
                'hours' => floor($webradioSeconds / 3600),
                'minutes' => floor(($webradioSeconds % 3600) / 60),
                'total_seconds' => (int) $webradioSeconds
            ],
            'total' => [
                'hours' => $hours,
                'minutes' => $minutes,
                'total_seconds' => (int) $totalSeconds,
                'formatted' => sprintf('%dh %02dm', $hours, $minutes)
            ]
        ];
    }
    
    /**
     * Obtenir l'engagement sur une période
     * 
     * L'engagement est calculé comme la moyenne des vues par jour
     * multipliée par un facteur de qualité (si applicable)
     */
    private function getEngagement(int $days): array
    {
        $webtvEngagement = WebTVStats::where('date', '>=', now()->subDays($days))
            ->avg('engagement');
            
        $webradioEngagement = WebRadioStats::where('date', '>=', now()->subDays($days))
            ->avg('engagement');
        
        // Calculer l'engagement total comme moyenne pondérée
        $totalEngagement = ($webtvEngagement + $webradioEngagement) / 2;
        
        return [
            'webtv' => round($webtvEngagement ?? 0, 2),
            'webradio' => round($webradioEngagement ?? 0, 2),
            'total' => round($totalEngagement, 2),
            'interpretation' => $this->interpretEngagement($totalEngagement)
        ];
    }
    
    /**
     * Interpréter le niveau d'engagement
     */
    private function interpretEngagement(float $engagement): string
    {
        if ($engagement >= 80) {
            return 'Excellent';
        } elseif ($engagement >= 60) {
            return 'Très bon';
        } elseif ($engagement >= 40) {
            return 'Bon';
        } elseif ($engagement >= 20) {
            return 'Moyen';
        } else {
            return 'À améliorer';
        }
    }
    
    /**
     * Récupérer les statistiques GA4 pour WebTV et WebRadio
     */
    private function getGA4Stats(int $days): array
    {
        try {
            $propertyIdRadio = env('GA4_PROPERTY_ID_RADIO', '');
            $propertyIdTV = env('GA4_PROPERTY_ID_TV', '');
            
            // ===== WEBRADIO =====
            $radioViews = 0;
            $radioByCountry = [];
            if (!empty($propertyIdRadio)) {
                try {
                    $ga4Radio = new GA4DataService($propertyIdRadio);
                    
                    // Compter tous les événements de type radio_play, stream_access, m3u_playlist_download
                    $radioViews += $ga4Radio->getEventCount('radio_play', $days);
                    $radioViews += $ga4Radio->getEventCount('stream_access', $days);
                    $radioViews += $ga4Radio->getEventCount('m3u_playlist_download', $days);
                    
                    // Récupérer les stats par pays (radio_play uniquement)
                    $radioByCountry = $ga4Radio->getStatsByCountry('radio_play', $days);
                } catch (\Exception $e) {
                    Log::warning('GA4 Radio error: ' . $e->getMessage());
                }
            }
            
            // ===== WEBTV =====
            $tvViews = 0;
            $tvByCountry = [];
            if (!empty($propertyIdTV)) {
                try {
                    $ga4TV = new GA4DataService($propertyIdTV);
                    
                    // Compter tous les événements de type webtv_live_start, webtv_vod_start
                    $tvViews += $ga4TV->getEventCount('webtv_live_start', $days);
                    $tvViews += $ga4TV->getEventCount('webtv_vod_start', $days);
                    
                    // Récupérer les stats par pays (combinaison des deux)
                    $tvLiveCountry = $ga4TV->getStatsByCountry('webtv_live_start', $days);
                    $tvVodCountry = $ga4TV->getStatsByCountry('webtv_vod_start', $days);
                    
                    // Fusionner les stats par pays
                    foreach ($tvLiveCountry as $country => $count) {
                        $tvByCountry[$country] = ($tvByCountry[$country] ?? 0) + $count;
                    }
                    foreach ($tvVodCountry as $country => $count) {
                        $tvByCountry[$country] = ($tvByCountry[$country] ?? 0) + $count;
                    }
                } catch (\Exception $e) {
                    Log::warning('GA4 TV error: ' . $e->getMessage());
                }
            }
            
            return [
                'webtv' => [
                    'total_views' => $tvViews,
                    'by_country' => $tvByCountry
                ],
                'webradio' => [
                    'total_views' => $radioViews,
                    'by_country' => $radioByCountry
                ],
                'total' => $tvViews + $radioViews,
                'source' => 'ga4_data_api'
            ];
            
        } catch (\Exception $e) {
            Log::error('GA4 getAllStats error: ' . $e->getMessage());
            
            return [
                'webtv' => ['total_views' => 0, 'by_country' => []],
                'webradio' => ['total_views' => 0, 'by_country' => []],
                'total' => 0,
                'source' => 'error'
            ];
        }
    }
    
    /**
     * Compter les spectateurs uniques basé sur les IPs dans les logs Ant Media
     */
    private function getUniqueViewersFromLogs(string $streamId): int
    {
        try {
            // Lire les logs des 2 dernières minutes pour les requêtes .m3u8
            $logFile = '/usr/local/antmedia/log/0.0.0.0_access.log';
            
            // Obtenir la date et heure actuelles pour filtrer les logs récents
            $currentDate = date('d/M/Y');
            $currentHour = date('H');
            $previousHour = str_pad(($currentHour - 1 + 24) % 24, 2, '0', STR_PAD_LEFT);
            
            $command = "tail -300 {$logFile} | grep '{$streamId}.m3u8' | grep -E '\\[{$currentDate}:({$previousHour}|{$currentHour}):' | awk '{print \$1}' | sort | uniq | wc -l";
            
            $result = shell_exec($command);
            $uniqueIPs = (int)trim($result);
            
            // Pas de limitation - croissance libre selon votre audience réelle
            return max($uniqueIPs, 0);
            
        } catch (\Exception $e) {
            \Log::error('Erreur lors du comptage des IPs uniques: ' . $e->getMessage());
            return 0;
        }
    }
}

