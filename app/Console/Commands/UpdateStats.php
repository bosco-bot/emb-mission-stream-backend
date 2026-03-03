<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebTVStats;
use App\Models\WebRadioStats;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateStats extends Command
{
    protected $signature = 'stats:update';
    protected $description = 'Mise à jour des statistiques WebTV et WebRadio';


    public function handle()
    {
        try {
            $today = now()->format('Y-m-d');
            
            // Vérifier si les stats existent déjà pour aujourd'hui
            $webtvStat = WebTVStats::where('date', $today)->first();
            $radioStat = WebRadioStats::where('date', $today)->first();
            
            // ===== RÉCUPÉRATION DES DONNÉES WebTV =====
            $webtvAudience = 0;
            $webtvDurationSeconds = 0;
            
            try {
                $response = Http::withBasicAuth('emb_webtv', 'EmbMission!Secure#789')
                    ->timeout(5)
                    ->get('http://localhost:5080/LiveApp/rest/v2/broadcasts/list/0/10');

                
                if ($response->successful()) {
                    $streams = $response->json();
                    if (is_array($streams)) {
                        $todayTimestamp = strtotime($today . ' 00:00:00') * 1000; // Timestamp du début du jour en millisecondes
                        
                        foreach ($streams as $stream) {
                            // Récupérer l'audience pour les streams en cours de diffusion
                            if (isset($stream['status']) && $stream['status'] === 'broadcasting') {
                                $webtvAudience = $stream['hlsViewerCount'] ?? 0;
                                
                                // Récupérer la durée de diffusion en cours (en millisecondes, on convertit en secondes)
                                if (isset($stream['duration'])) {
                                    $webtvDurationSeconds = max($webtvDurationSeconds, intval($stream['duration'] / 1000));
                                }
                            }
                            
                            // Pour la durée totale: prendre les streams terminés aujourd'hui
                            if (isset($stream['date']) && $stream['date'] >= $todayTimestamp) {
                                if (isset($stream['status']) && $stream['status'] === 'finished' && isset($stream['duration'])) {
                                    $webtvDurationSeconds = max($webtvDurationSeconds, intval($stream['duration'] / 1000));
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Erreur récupération WebTV: ' . $e->getMessage());
            }
            
            // ===== RÉCUPÉRATION DES DONNÉES WebRadio =====
            $radioAudience = 0;
            $radioDurationSeconds = 0;
            
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
                        // Récupérer l'audience
                        $radioAudience = $nowPlaying['live']['listeners']['total'] ?? 0;
                        
                        // Récupérer la durée écoulée de la diffusion
                        if (isset($nowPlaying['live']['elapsed'])) {
                            $radioDurationSeconds = intval($nowPlaying['live']['elapsed']);
                        }
                    } else {
                        // Si pas en live, compter les auditeurs des mounts
                        if (isset($nowPlaying['station']['mounts'])) {
                            foreach ($nowPlaying['station']['mounts'] as $mount) {
                                $radioAudience += $mount['listeners']['current'] ?? 0;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Erreur récupération AzuraCast: ' . $e->getMessage());
            }
            
            // ===== MISE À JOUR WebTV =====
            if ($webtvStat) {
                // Si une diffusion est en cours, on prend le maximum entre la durée déjà enregistrée et la nouvelle durée
                // Sinon on garde la valeur déjà enregistrée
                $newDuration = $webtvDurationSeconds > 0 
                    ? max($webtvStat->broadcast_duration_seconds, $webtvDurationSeconds)
                    : $webtvStat->broadcast_duration_seconds;
                
                WebTVStats::where('date', $today)->update([
                    'live_audience' => max($webtvStat->live_audience, $webtvAudience),
                    'broadcast_duration_seconds' => $newDuration,
                    'updated_at' => now()
                ]);
                $this->info('Statistiques WebTV mises à jour');
            } else {
                WebTVStats::create([
                    'date' => $today,
                    'live_audience' => $webtvAudience,
                    'total_views' => 0,
                    'broadcast_duration_seconds' => $webtvDurationSeconds,
                    'engagement' => 0
                ]);
                $this->info('Statistiques WebTV créées');
            }
            
            // ===== MISE À JOUR WebRadio =====
            if ($radioStat) {
                // Si une diffusion est en cours, on prend le maximum entre la durée déjà enregistrée et la nouvelle durée
                $newDuration = $radioDurationSeconds > 0 
                    ? max($radioStat->broadcast_duration_seconds, $radioDurationSeconds)
                    : $radioStat->broadcast_duration_seconds;
                
                WebRadioStats::where('date', $today)->update([
                    'live_audience' => max($radioStat->live_audience, $radioAudience),
                    'broadcast_duration_seconds' => $newDuration,
                    'updated_at' => now()
                ]);
                $this->info('Statistiques WebRadio mises à jour');
            } else {
                WebRadioStats::create([
                    'date' => $today,
                    'live_audience' => $radioAudience,
                    'total_listens' => 0,
                    'broadcast_duration_seconds' => $radioDurationSeconds,
                    'engagement' => 0
                ]);
                $this->info('Statistiques WebRadio créées');
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            Log::error('Erreur UpdateStats: ' . $e->getMessage());
            $this->error('Erreur: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

