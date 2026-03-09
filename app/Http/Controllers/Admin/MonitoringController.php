<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitoringController extends Controller
{
    /**
     * Affiche le tableau de bord de monitoring
     */
    public function index()
    {
        return view('admin.monitoring');
    }

    /**
     * API : Récupère le statut de tous les services
     */
    public function getStatus(): JsonResponse
    {
        try {
            $status = [
                'services' => $this->checkServices(),
                'workers' => $this->checkWorkers(),
                'jobs' => $this->checkJobs(),
                'service_descriptions' => $this->getServiceDescriptions(),
                'cron_tasks' => $this->getCronTasks(),
                'audio_jobs' => $this->getAudioJobs(),
                'rtmp_alerts' => $this->getRtmpAlerts(),
                'disk' => $this->getDiskSpace(),
                'laravel_log_errors' => $this->getLaravelLogErrors(),
                'storage_links' => $this->getStorageLinksStatus(),
                'versions' => $this->getVersions(),
                'webtv_system_paused' => \Illuminate\Support\Facades\Cache::get('webtv_system_paused', false),
                'timestamp' => now()->toIso8601String(),
            ];

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur monitoring status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération du statut',
            ], 500);
        }
    }

    /**
     * Vérifie l'état des services système
     */
    private function checkServices(): array
    {
        $services = [
            'nginx' => $this->checkService('nginx'),
            'php-fpm' => $this->checkService('php8.2-fpm'),
            'mysql' => $this->checkService('mysql'),
            'antmedia' => $this->checkService('antmedia'),
            'ffmpeg-live-transcode' => $this->checkService('ffmpeg-live-transcode'),
            'supervisor' => $this->checkService('supervisor'),
            'cron' => $this->checkService('cron'),
            'docker' => $this->checkService('docker'),
        ];

        return $services;
    }

    /**
     * Vérifie l'état d'un service systemd
     */
    private function checkService(string $serviceName): array
    {
        $command = "systemctl is-active {$serviceName} 2>/dev/null";
        $isActive = trim(shell_exec($command)) === 'active';

        $command = "systemctl is-enabled {$serviceName} 2>/dev/null";
        $isEnabled = trim(shell_exec($command)) === 'enabled';

        // Récupérer le statut détaillé
        $statusCommand = "systemctl show {$serviceName} --property=ActiveState,SubState,MainPID --no-pager 2>/dev/null";
        $statusOutput = shell_exec($statusCommand);
        $status = [];
        if ($statusOutput) {
            foreach (explode("\n", trim($statusOutput)) as $line) {
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $status[strtolower($key)] = $value;
                }
            }
        }

        return [
            'name' => $serviceName,
            'active' => $isActive,
            'enabled' => $isEnabled,
            'status' => $status['activestate'] ?? 'unknown',
            'substate' => $status['substate'] ?? 'unknown',
            'pid' => $status['mainpid'] ?? null,
        ];
    }

    /**
     * Vérifie l'état des workers Laravel
     */
    private function checkWorkers(): array
    {
        $workers = [
            'queue-worker' => $this->checkWorker('laravel-queue-worker'),
            'unified-stream' => $this->checkWorker('unified-stream'),
            'reverb' => $this->checkWorker('laravel-reverb'),
        ];

        return $workers;
    }

    /**
     * Vérifie l'état d'un worker systemd
     */
    private function checkWorker(string $serviceName): array
    {
        $service = $this->checkService($serviceName);
        
        // Vérifier si le processus est en cours d'exécution
        $pid = $service['pid'] ?? null;
        $isRunning = $pid && file_exists("/proc/{$pid}");

        return array_merge($service, [
            'running' => $isRunning,
        ]);
    }

    /**
     * Vérifie l'état des jobs en queue
     */
    private function checkJobs(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            
            // Récupérer les derniers jobs en échec
            $recentFailed = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit(10)
                ->get(['id', 'uuid', 'connection', 'queue', 'exception', 'failed_at'])
                ->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'uuid' => $job->uuid,
                        'connection' => $job->connection,
                        'queue' => $job->queue,
                        'exception' => substr($job->exception ?? '', 0, 200),
                        'failed_at' => $job->failed_at,
                    ];
                });

            return [
                'pending' => $pending,
                'failed' => $failed,
                'recent_failed' => $recentFailed,
            ];
        } catch (\Exception $e) {
            Log::error('Erreur vérification jobs: ' . $e->getMessage());
            return [
                'pending' => 0,
                'failed' => 0,
                'recent_failed' => [],
                'error' => 'Impossible de récupérer les jobs',
            ];
        }
    }

    /**
     * API : Relance un service
     */
    public function restartService(Request $request): JsonResponse
    {
        $request->validate([
            'service' => 'required|string',
        ]);

        $serviceName = $request->input('service');
        $allowedServices = ['nginx', 'php8.2-fpm', 'mysql', 'antmedia', 'ffmpeg-live-transcode', 'laravel-queue-worker', 'unified-stream', 'laravel-reverb', 'supervisor', 'cron', 'docker'];

        if (!in_array($serviceName, $allowedServices)) {
            return response()->json([
                'success' => false,
                'error' => 'Service non autorisé',
            ], 403);
        }

        try {
            // Vérifier les permissions (doit être exécuté en tant que root ou avec sudo)
            $command = "sudo systemctl restart {$serviceName} 2>&1";
            exec($command, $output, $exitCode);

            if ($exitCode === 0) {
                Log::info("Service redémarré: {$serviceName}");
                return response()->json([
                    'success' => true,
                    'message' => "Service {$serviceName} redémarré avec succès",
                ]);
            } else {
                Log::warning("Échec redémarrage service: {$serviceName}", ['output' => implode("\n", $output)]);
                return response()->json([
                    'success' => false,
                    'error' => "Échec du redémarrage: " . implode("\n", $output),
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error("Erreur redémarrage service {$serviceName}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du redémarrage',
            ], 500);
        }
    }

    /**
     * API : Arrête un service
     */
    public function stopService(Request $request): JsonResponse
    {
        $request->validate([
            'service' => 'required|string',
        ]);

        $serviceName = $request->input('service');
        $allowedServices = ['nginx', 'php8.2-fpm', 'mysql', 'antmedia', 'ffmpeg-live-transcode', 'laravel-queue-worker', 'unified-stream', 'laravel-reverb', 'supervisor', 'cron', 'docker'];

        if (!in_array($serviceName, $allowedServices)) {
            return response()->json([
                'success' => false,
                'error' => 'Service non autorisé',
            ], 403);
        }

        try {
            // Vérifier les permissions (doit être exécuté en tant que root ou avec sudo)
            $command = "sudo systemctl stop {$serviceName} 2>&1";
            exec($command, $output, $exitCode);

            if ($exitCode === 0) {
                Log::info("Service arrêté: {$serviceName}");
                return response()->json([
                    'success' => true,
                    'message' => "Service {$serviceName} arrêté avec succès",
                ]);
            } else {
                Log::warning("Échec arrêt service: {$serviceName}", ['output' => implode("\n", $output)]);
                return response()->json([
                    'success' => false,
                    'error' => "Échec de l'arrêt: " . implode("\n", $output),
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error("Erreur arrêt service {$serviceName}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de l\'arrêt',
            ], 500);
        }
    }

    /**
     * API : Relance un job en échec
     */
    public function retryJob(Request $request): JsonResponse
    {
        $request->validate([
            'job_id' => 'required|integer',
        ]);

        try {
            $jobId = $request->input('job_id');
            $job = DB::table('failed_jobs')->where('id', $jobId)->first();

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'error' => 'Job non trouvé',
                ], 404);
            }

            // Relancer le job via artisan
            Artisan::call('queue:retry', ['id' => $job->uuid]);
            
            Log::info("Job relancé: {$job->uuid}");
            return response()->json([
                'success' => true,
                'message' => 'Job relancé avec succès',
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur relance job: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la relance du job',
            ], 500);
        }
    }

    /**
     * Retourne les descriptions des services
     */
    private function getServiceDescriptions(): array
    {
        return [
            'nginx' => 'Serveur web Nginx - Gère les requêtes HTTP/HTTPS et le routage vers PHP-FPM',
            'php-fpm' => 'PHP-FPM 8.2 - Interprète PHP pour exécuter les scripts Laravel',
            'mysql' => 'Base de données MySQL - Stocke toutes les données de l\'application',
            'antmedia' => 'Ant Media Server - Serveur de streaming vidéo en direct',
            'ffmpeg-live-transcode' => 'FFmpeg Live Transcode - Re-encode le flux live avec GOP de 2s pour Ant Media',
            'supervisor' => 'Supervisor - Gestionnaire de processus qui maintient les workers Laravel en vie',
            'cron' => 'Cron - Planificateur de tâches système pour exécuter les commandes à intervalles réguliers',
            'docker' => 'Docker - Moteur de conteneurs qui exécute AzuraCast (serveur de streaming radio)',
            'laravel-queue-worker' => 'Worker Laravel Queue - Traite les jobs en file d\'attente en arrière-plan (inclut les jobs audio/radio)',
            'unified-stream' => 'Worker Unified Stream - Gère le flux vidéo unifié (live/VOD)',
            'laravel-reverb' => 'Laravel Reverb - Serveur WebSocket pour la communication en temps réel',
        ];
    }

    /**
     * Retourne la liste des tâches cron planifiées
     */
    private function getCronTasks(): array
    {
        return [
            [
                'name' => 'unified-stream:remux-pending',
                'schedule' => 'Toutes les 5 minutes',
                'description' => 'Remux les fichiers vidéo en attente de traitement (limite: 5 fichiers)',
            ],
            [
                'name' => 'webtv:check-sync',
                'schedule' => 'Toutes les heures',
                'description' => 'Surveille la synchronisation WebTV, corrige les problèmes et envoie des alertes si nécessaire',
            ],
            [
                'name' => 'webtv:link-preconverted',
                'schedule' => 'Toutes les 2 minutes',
                'description' => 'Lie automatiquement les items en attente aux fichiers HLS pré-convertis (limite: 100 items)',
            ],
            [
                'name' => 'RetryFailedVideoConversions',
                'schedule' => 'Toutes les 15 minutes',
                'description' => 'Relance automatiquement les conversions vidéo HLS échouées ou incomplètes (fichiers des dernières 48h)',
            ],
            [
                'name' => 'stats:update',
                'schedule' => 'Toutes les heures',
                'description' => 'Met à jour les statistiques WebTV et WebRadio (audience, durée de diffusion) depuis Ant Media et AzuraCast',
            ],
        ];
    }

    /**
     * Retourne la liste des jobs liés à l'audio/radio
     */
    private function getAudioJobs(): array
    {
        return [
            [
                'name' => 'SyncPlaylistToAzuraCast',
                'type' => 'Queue Job',
                'description' => 'Synchronise une playlist audio avec AzuraCast (création/mise à jour, copie fichiers, scan médias, import M3U)',
            ],
            [
                'name' => 'FinalizeAzuraCastSync',
                'type' => 'Queue Job',
                'description' => 'Finalise la synchronisation en mettant à jour les IDs AzuraCast des PlaylistItem (exécuté 2 minutes après sync)',
            ],
            [
                'name' => 'UpdateM3UAndRestartJob',
                'type' => 'Queue Job',
                'description' => 'Met à jour le fichier M3U d\'une playlist et redémarre le backend AzuraCast',
            ],
            [
                'name' => 'UploadToAzuraCast',
                'type' => 'Queue Job',
                'description' => 'Upload un fichier audio vers AzuraCast via Docker',
            ],
        ];
    }

    /**
     * Lit les dernières alertes RTMP/HLS générées par le script de surveillance
     */
    private function getRtmpAlerts(int $limit = 20): array
    {
        $logPath = base_path('monitoring_sessions/alerts.log');

        if (!file_exists($logPath)) {
            return [];
        }

        $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_slice($lines, -$limit);

        return array_map(function (string $line) {
            if (strpos($line, '|') !== false) {
                [$timestamp, $message] = explode('|', $line, 2);
                return [
                    'timestamp' => trim($timestamp),
                    'message' => trim($message),
                ];
            }

            return [
                'timestamp' => 'N/A',
                'message' => trim($line),
            ];
        }, $lines);
    }

    /**
     * Espace disque (partitions principales)
     */
    private function getDiskSpace(): array
    {
        $paths = [
            ['path' => base_path('storage'), 'label' => 'Storage (logs, cache, médias)'],
            ['path' => '/var', 'label' => '/var'],
            ['path' => '/', 'label' => 'Racine'],
        ];
        $result = [];
        foreach ($paths as $item) {
            $path = $item['path'];
            if (!@is_dir($path)) {
                $result[] = ['path' => $path, 'label' => $item['label'], 'error' => 'Dossier inaccessible'];
                continue;
            }
            $total = @disk_total_space($path);
            $free = @disk_free_space($path);
            if ($total === false || $free === false) {
                $result[] = ['path' => $path, 'label' => $item['label'], 'error' => 'Impossible de lire l\'espace'];
                continue;
            }
            $used = $total - $free;
            $pct = $total > 0 ? round(100 * $used / $total, 1) : 0;
            $result[] = [
                'path' => $path,
                'label' => $item['label'],
                'total_gb' => round($total / (1024 ** 3), 2),
                'free_gb' => round($free / (1024 ** 3), 2),
                'used_gb' => round($used / (1024 ** 3), 2),
                'used_percent' => $pct,
                'ok' => $pct < 90,
            ];
        }
        return $result;
    }

    /**
     * Dernières lignes d'erreur du log Laravel (niveau ERROR uniquement, hors messages résolus)
     */
    private function getLaravelLogErrors(int $limit = 15): array
    {
        $logPath = storage_path('logs/laravel.log');
        if (!is_file($logPath) || !is_readable($logPath)) {
            return ['lines' => [], 'error' => 'Fichier log inaccessible'];
        }
        $content = @file_get_contents($logPath);
        if ($content === false) {
            return ['lines' => [], 'error' => 'Impossible de lire le log'];
        }
        $lines = array_filter(explode("\n", $content));
        $errorLines = [];
        foreach (array_reverse($lines) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $lower = strtolower($line);
            // Niveau ERROR uniquement (exclut WARNING, INFO, etc.)
            if (strpos($line, '.ERROR:') === false && !preg_match('/\.ERROR\s*:/', $line)) {
                continue;
            }
            // Exclure les erreurs connues/résolues (ex: getDiskSpace après déploiement)
            if (strpos($lower, 'getdiskspace') !== false) {
                continue;
            }
            $errorLines[] = ['text' => strlen($line) > 200 ? substr($line, 0, 200) . '…' : $line];
            if (count($errorLines) >= $limit) {
                break;
            }
        }
        return ['lines' => array_reverse($errorLines)];
    }

    /**
     * État des liens de stockage (symlinks)
     */
    private function getStorageLinksStatus(): array
    {
        $checks = [];
        $publicStorage = public_path('storage');
        $checks[] = [
            'name' => 'public/storage',
            'path' => $publicStorage,
            'exists' => file_exists($publicStorage),
            'is_link' => is_link($publicStorage),
            'target' => is_link($publicStorage) ? @readlink($publicStorage) : null,
        ];
        $mediaLink = base_path('storage/app/public/media');
        $checks[] = [
            'name' => 'storage/app/public/media → media',
            'path' => $mediaLink,
            'exists' => file_exists($mediaLink),
            'is_link' => is_link($mediaLink),
            'target' => is_link($mediaLink) ? @readlink($mediaLink) : null,
        ];
        $mediaDir = storage_path('app/media');
        $checks[] = [
            'name' => 'storage/app/media (dossier)',
            'path' => $mediaDir,
            'exists' => is_dir($mediaDir),
            'is_link' => false,
            'target' => null,
        ];
        return $checks;
    }

    /**
     * Versions PHP, Laravel, etc.
     */
    private function getVersions(): array
    {
        $versions = [
            'php' => PHP_VERSION,
            'laravel' => \Illuminate\Foundation\Application::VERSION,
        ];
        if (extension_loaded('pdo_mysql')) {
            try {
                $v = DB::selectOne('SELECT VERSION() as v');
                $versions['mysql'] = $v->v ?? 'N/A';
            } catch (\Throwable $e) {
                $versions['mysql'] = 'N/A';
            }
        } else {
            $versions['mysql'] = 'N/A';
        }
        return $versions;
    }

    /**
     * API : Relance tous les jobs en échec
     */
    public function retryAllFailedJobs(): JsonResponse
    {
        try {
            $count = DB::table('failed_jobs')->count();
            
            if ($count === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Aucun job en échec à relancer',
                ]);
            }

            Artisan::call('queue:retry', ['id' => 'all']);
            
            Log::info("Tous les jobs en échec relancés: {$count} jobs");
            return response()->json([
                'success' => true,
                'message' => "{$count} job(s) relancé(s) avec succès",
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur relance tous les jobs: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la relance des jobs',
            ], 500);
        }
    }

}

