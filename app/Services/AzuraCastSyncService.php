<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Log;
use App\Jobs\FinalizeAzuraCastSync;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Services\AzuraCastService;
use Exception;


class AzuraCastSyncService
{
    private string $baseUrl;
    private string $apiKey;
    private string $stationId;
    private string $containerName;
    private string $mediaPath;
    private ?string $stationShortName;

    public function __construct()
    {
        $this->baseUrl = config('services.azuracast.base_url');
        $this->apiKey = config('services.azuracast.api_key');
        $this->stationId = config('services.azuracast.station_id');
        $this->containerName = env('AZURACAST_CONTAINER_NAME', 'azuracast');
        $this->mediaPath = env('AZURACAST_MEDIA_PATH', '/var/azuracast/stations/radio_emb_mission/media/');
        $this->stationShortName = env('AZURACAST_STATION_SHORT_NAME');
    }

    private function handleShuffleAndRestart(Playlist $playlist): void
    {
        if (!$playlist->azuracast_id) {
            return;
        }

        try {
            $azuraCastService = new AzuraCastService();

            if ($playlist->is_shuffle) {
                $azuraCastService->reshufflePlaylist($playlist->azuracast_id);
                Log::info("Playlist AzuraCast reshuffled", [
                    'playlist' => $playlist->name,
                    'azuracast_id' => $playlist->azuracast_id,
                ]);
            }

            $azuraCastService->restartBackend();
            Log::info("Backend AzuraCast redémarré après synchronisation", [
                'playlist' => $playlist->name,
            ]);
            
            // Attendre un peu puis démarrer explicitement pour s'assurer que la lecture reprend
            sleep(2);
            try {
                $azuraCastService->startBackend();
                Log::info("Backend AzuraCast démarré explicitement après synchronisation", [
                    'playlist' => $playlist->name,
                ]);
            } catch (\Exception $startError) {
                Log::warning("Erreur démarrage backend (non bloquant): " . $startError->getMessage(), [
                    'playlist' => $playlist->name,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("Impossible de reshuffle/redémarrer AzuraCast: " . $e->getMessage(), [
                'playlist' => $playlist->name,
            ]);
        }
    }

    /**
     * Vérifier quels fichiers de la playlist sont manquants dans AzuraCast
     */
    private function checkMissingFilesInAzuraCast(Playlist $playlist): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey
            ])->timeout(30)->get("{$this->baseUrl}/api/station/{$this->stationId}/files");

            if (!$response->successful()) {
                Log::warning("Impossible de vérifier les fichiers AzuraCast");
                return [];
            }

            $azuracastFiles = $response->json();
            $azuracastFileNames = [];
            
            foreach ($azuracastFiles as $azuracastFile) {
                $filename = $azuracastFile['path'] ?? $azuracastFile['name'] ?? null;
                if ($filename) {
                    $azuracastFileNames[] = basename($filename);
                }
            }

            $missingFiles = [];
            foreach ($playlist->items as $item) {
                if ($item->mediaFile && $item->mediaFile->file_type === 'audio') {
                    $originalName = basename($item->mediaFile->original_name);
                    if (!in_array($originalName, $azuracastFileNames)) {
                        $missingFiles[] = $originalName;
                    }
                }
            }

            return $missingFiles;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la vérification des fichiers manquants: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Vérifier si un fichier existe déjà dans AzuraCast
     */
    private function checkFileExistsInAzuraCast(string $filename): bool
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey
            ])->timeout(30)->get("{$this->baseUrl}/api/station/{$this->stationId}/files");

            if ($response->successful()) {
                $files = $response->json();
                foreach ($files as $file) {
                    if (isset($file['path']) && basename($file['path']) === $filename) {
                        return true;
                    }
                }
            }
            return false;
        } catch (\Exception $e) {
            Log::warning("Erreur lors de la vérification de l'existence du fichier: " . $e->getMessage());
            return false;
        }
    }

    public function copyFileToAzuraCast(MediaFile $mediaFile, bool $triggerScan = false): array
    {
        try {
            //$sourcePath = storage_path('app/' . $mediaFile->file_path);

            //CODE CORRIGÉ
            $sourcePath = Storage::disk('media')->path($mediaFile->file_path);

            if (!file_exists($sourcePath)) {
                Log::error("Fichier source introuvable: {$sourcePath}");
                return [
                    'success' => false,
                    'message' => 'Fichier source introuvable: ' . $sourcePath
                ];
            }

            $fileName = basename($mediaFile->original_name);
            $destPath = $this->mediaPath . $fileName;

            $output = [];
            $returnCode = 0;
            
            $scriptPath = '/var/www/emb-mission/copy-to-azuracast.sh';
            if (!file_exists($scriptPath)) {
                throw new Exception("Script de copie introuvable: {$scriptPath}");
            }
            
            exec("{$scriptPath} \"{$sourcePath}\" \"{$destPath}\"", $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception("Erreur Docker (code $returnCode): " . implode("\n", $output));
            }
            
            $result = implode("\n", $output);

            Log::info("Fichier copié vers AzuraCast: {$fileName}", ['result' => $result]);

            // Ne scanner qu'à la demande
            if ($triggerScan) {
                $this->triggerMediaScan();
            }

            return [
                'success' => true,
                'message' => 'Fichier copié avec succès',
                'file_name' => $fileName,
                'docker_result' => $result
            ];

        } catch (Exception $e) {
            Log::error("Erreur lors de la copie vers AzuraCast: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }

    private function triggerMediaScan(): void
    {
        try {
            $stationArg = $this->stationShortName ?: (string) $this->stationId;
            $command = sprintf(
                'sudo docker exec %s php /var/azuracast/www/bin/console azuracast:media:sync %s 2>&1',
                escapeshellarg($this->containerName),
                escapeshellarg($stationArg)
            );

            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                Log::info("Scan des médias AzuraCast déclenché via CLI", [
                    'station' => $stationArg,
                    'output' => $output,
                ]);
            } else {
                Log::warning("Échec du scan AzuraCast (CLI)", [
                    'station' => $stationArg,
                    'output' => $output,
                    'return_code' => $returnCode,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Erreur lors du déclenchement du scan: " . $e->getMessage());
        }
    }

    public function syncPlaylist(Playlist $playlist): array
    {
        try {
            Log::info("Début de la synchronisation de la playlist: {$playlist->name}");

            // CORRECTION: Vérifier si la playlist existe vraiment dans AzuraCast
            if ($playlist->azuracast_id) {
                try {
                    $checkResponse = Http::timeout(30)->withHeaders([
                        'X-API-Key' => $this->apiKey
                    ])->get("{$this->baseUrl}/api/station/{$this->stationId}/playlist/{$playlist->azuracast_id}");

                    if (!$checkResponse->successful()) {
                        // La playlist n'existe plus dans AzuraCast, réinitialiser l'ID
                        Log::warning("Playlist AzuraCast introuvable (ID: {$playlist->azuracast_id}), réinitialisation...", [
                            'playlist_id' => $playlist->id,
                            'playlist_name' => $playlist->name,
                            'status_code' => $checkResponse->status()
                        ]);
                        $playlist->update(['azuracast_id' => null, 'sync_status' => 'pending']);
                        $playlist->refresh();
                    }
                } catch (\Exception $e) {
                    Log::warning("Erreur lors de la vérification de la playlist AzuraCast: " . $e->getMessage());
                    // En cas d'erreur, on continue quand même (peut-être un problème réseau temporaire)
                }
            }

            if (!$playlist->azuracast_id) {
                $createResult = $this->createPlaylistInAzuraCast($playlist);
                if (!$createResult['success']) {
                    throw new Exception("Impossible de créer la playlist dans AzuraCast: " . $createResult['message']);
                }
                $playlist->refresh();
            }

            // CORRECTION: Copier TOUS les fichiers d'abord, PUIS scanner une seule fois
            $copiedFilesCount = 0;
            $filesToScan = []; // Liste des fichiers à vérifier après scan
            
            foreach ($playlist->items as $item) {
                if ($item->mediaFile && $item->mediaFile->file_type === 'audio') {
                    // Check if file exists in AzuraCast before copying
                    $existsInAzuraCast = $this->checkFileExistsInAzuraCast($item->mediaFile->original_name);
                    
                    if (!$item->mediaFile->azuracast_id && !$existsInAzuraCast) {
                        // Ne pas scanner après chaque fichier
                        $copyResult = $this->copyFileToAzuraCast($item->mediaFile, false);
                        if ($copyResult['success']) {
                            $copiedFilesCount++;
                            Log::info("Fichier copié ({$copiedFilesCount}): {$item->mediaFile->original_name}");
                        } else {
                            Log::warning("Échec de la copie du fichier", [
                                'file_id' => $item->mediaFile->id,
                                'error' => $copyResult['message']
                            ]);
                        }
                    }
                    
                    // Garder trace de tous les fichiers audio pour vérification
                    $filesToScan[] = $item->mediaFile->original_name;
                }
            }

            // CORRECTION: TOUJOURS scanner après mise à jour de playlist
            // Même si aucun fichier n'a été copié, il faut scanner pour détecter les fichiers existants
            $totalAudioFiles = count($filesToScan);
            
            if ($copiedFilesCount > 0 || $totalAudioFiles > 0) {
                if ($copiedFilesCount > 0) {
                    Log::info("Tous les fichiers copiés ({$copiedFilesCount}), déclenchement du scan...");
                } else {
                    Log::info("Déclenchement du scan pour vérifier les fichiers existants ({$totalAudioFiles} fichiers audio)...");
                }
                
                $this->triggerMediaScan();
                
                // Attendre plus longtemps pour le scan de TOUS les fichiers
                // Base + 3s par fichier copié + 1s par fichier total
                $waitTime = 5 + ($copiedFilesCount * 3) + ($totalAudioFiles * 1);
                Log::info("Attente de {$waitTime} secondes pour le scan complet...");
                sleep($waitTime);
                
                // Mettre à jour les azuracast_id (même pour les fichiers existants)
                $this->updateFileAzuraCastIds();
            }

            $this->updatePlaylistSettings($playlist->azuracast_id, [
                'name' => $playlist->name,
                'description' => $playlist->description ?? '',
                'order' => $playlist->is_shuffle ? 'random' : 'sequential',
                'loop' => $playlist->is_loop ?? false,
                'is_enabled' => true,
            ]);

            // CORRECTION: Vérifier que tous les fichiers existent dans AzuraCast avant l'import
            $missingFiles = $this->checkMissingFilesInAzuraCast($playlist);
            if (!empty($missingFiles)) {
                Log::warning("Fichiers manquants dans AzuraCast", [
                    'missing_files' => $missingFiles,
                    'count' => count($missingFiles)
                ]);
                
                // Attendre un peu plus et réessayer
                Log::info("Attente supplémentaire de 5 secondes pour le scan...");
                sleep(5);
                $this->triggerMediaScan();
                sleep(5);
                $this->updateFileAzuraCastIds();
                
                // Vérifier à nouveau
                $missingFiles = $this->checkMissingFilesInAzuraCast($playlist);
                if (!empty($missingFiles)) {
                    Log::error("Toujours des fichiers manquants après attente", [
                        'missing_files' => $missingFiles
                    ]);
                }
            }

            $this->clearPlaylist($playlist->azuracast_id);

            $m3uContent = $this->generateM3U($playlist);
            $importResult = $this->importPlaylistToAzuraCast($playlist->azuracast_id, $m3uContent);

            if ($importResult['success']) {
                // VÉRIFICATION: Comparer le nombre de fichiers dans la playlist Laravel vs AzuraCast
                $expectedCount = $playlist->items()->whereHas('mediaFile', function($query) {
                    $query->where('file_type', 'audio');
                })->count();
                
                // Utiliser directement l'API pour récupérer les détails de la playlist
                // La réponse contient déjà num_songs ou la liste des médias
                $actualCount = null;
                try {
                    $playlistResponse = Http::timeout(30)->withHeaders([
                        'X-API-Key' => $this->apiKey
                    ])->get("{$this->baseUrl}/api/station/{$this->stationId}/playlist/{$playlist->azuracast_id}");
                    
                    if ($playlistResponse->successful()) {
                        $playlistData = $playlistResponse->json();
                        $actualCount = $playlistData['num_songs'] ?? count($playlistData['media'] ?? []);
                    }
                } catch (\Exception $e) {
                    Log::warning("Impossible de vérifier le nombre de fichiers dans AzuraCast: " . $e->getMessage());
                }
                
                if ($actualCount !== null && $actualCount !== $expectedCount) {
                    Log::warning("⚠️ Nombre de fichiers différent entre Laravel et AzuraCast", [
                        'playlist' => $playlist->name,
                        'expected_count' => $expectedCount,
                        'actual_count' => $actualCount,
                        'difference' => $expectedCount - $actualCount
                    ]);
                } else if ($actualCount !== null) {
                    Log::info("✅ Nombre de fichiers correspond: {$expectedCount} fichiers", [
                        'playlist' => $playlist->name
                    ]);
                }
                
                $playlist->update([
                    'sync_status' => 'synced',
                    'last_sync_at' => now(),
                ]);
                Log::info("Synchronisation terminée avec succès", [
                    'playlist' => $playlist->name,
                    'files_copied' => $copiedFilesCount,
                    'expected_files' => $expectedCount,
                    'actual_files' => $actualCount,
                    'import_result' => $importResult
                ]);

                $this->handleShuffleAndRestart($playlist);
                
                // Dispatcher le job de finalisation pour ajouter les medias aux playlists
                if ($copiedFilesCount > 0) {
                    FinalizeAzuraCastSync::dispatch($playlist->id)->delay(now()->addMinutes(2));
                }
            } else {
                $playlist->update([
                    'sync_status' => 'error',
                    'last_sync_at' => now(),
                ]);
                Log::error("Échec de la synchronisation de la playlist", [
                    'playlist' => $playlist->name,
                    'error' => $importResult['message']
                ]);
            }

            return [
                'success' => $importResult['success'],
                'message' => $importResult['success'] ? 'Synchronisation réussie' : 'Erreur: ' . $importResult['message'],
                'files_copied' => $copiedFilesCount,
                'import_results' => $importResult['import_results'] ?? []
            ];

        } catch (\Exception $e) {
            Log::error("Erreur lors de la synchronisation de la playlist {$playlist->name}: " . $e->getMessage());
            $playlist->update([
                'sync_status' => 'error',
                'last_sync_at' => now(),
            ]);
            return [
                'success' => false,
                'message' => 'Erreur lors de la synchronisation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Mise à jour rapide M3U et redémarrage AzuraCast (sans copie/scan de fichiers)
     * Utilisée pour les modifications simples (ajout/suppression d'items)
     * Plus rapide et plus fiable que syncPlaylistQuick (utilise Docker + API)
     */
    public function updateM3UAndRestartService(Playlist $playlist): array
    {
        try {
            Log::info("Début mise à jour M3U rapide et redémarrage AzuraCast", [
                'playlist_id' => $playlist->id,
                'playlist_name' => $playlist->name
            ]);

            // Vérifier que la playlist a un azuracast_id
            if (!$playlist->azuracast_id) {
                // Si la playlist n'existe pas dans AzuraCast, utiliser la synchronisation complète
                return $this->syncPlaylist($playlist);
            }

            // Recharger la playlist avec ses items et mediaFiles
            $playlist->load('items.mediaFile');

            // 1. Copier les fichiers manquants dans AzuraCast
            $copiedFilesCount = 0;
            $filesToScan = [];
            
            foreach ($playlist->items as $item) {
                if ($item->mediaFile && $item->mediaFile->file_type === 'audio') {
                    // Utiliser basename pour la comparaison (original_name peut contenir un chemin)
                    $fileName = basename($item->mediaFile->original_name);
                    
                    // ✅ CORRECTION: Toujours vérifier si le fichier existe RÉELLEMENT dans AzuraCast
                    // Même s'il a un azuracast_id en base, le fichier peut avoir été supprimé d'AzuraCast
                    $existsInAzuraCast = $this->checkFileExistsInAzuraCast($fileName);
                    
                    // ✅ Décider si on doit copier :
                    // - Le fichier n'existe pas dans AzuraCast (même s'il a un azuracast_id en base)
                    // - OU le fichier n'a pas d'azuracast_id
                    $shouldCopy = !$existsInAzuraCast;
                    
                    Log::info("Vérification fichier pour copie", [
                        'file_id' => $item->mediaFile->id,
                        'original_name' => $item->mediaFile->original_name,
                        'file_name' => $fileName,
                        'azuracast_id' => $item->mediaFile->azuracast_id,
                        'exists_in_azuracast' => $existsInAzuraCast,
                        'should_copy' => $shouldCopy
                    ]);
                    
                    // ✅ Copier le fichier s'il n'existe pas dans AzuraCast
                    if ($shouldCopy) {
                        $copyResult = $this->copyFileToAzuraCast($item->mediaFile, false);
                        if ($copyResult['success']) {
                            $copiedFilesCount++;
                            // ✅ Réinitialiser l'azuracast_id si le fichier n'existait pas (il sera mis à jour après le scan)
                            if ($existsInAzuraCast === false) {
                                $item->mediaFile->update(['azuracast_id' => null]);
                            }
                            Log::info("Fichier copié vers AzuraCast ({$copiedFilesCount}): {$item->mediaFile->original_name}");
                        } else {
                            Log::warning("Échec de la copie du fichier", [
                                'file_id' => $item->mediaFile->id,
                                'file_name' => $item->mediaFile->original_name,
                                'error' => $copyResult['message']
                            ]);
                        }
                    } else {
                        Log::info("Fichier déjà présent dans AzuraCast, copie ignorée: {$fileName}");
                    }
                    
                    // Garder trace de tous les fichiers audio pour vérification
                    $filesToScan[] = $item->mediaFile->original_name;
                }
            }

            // 2. Scanner les médias si des fichiers ont été copiés OU si on doit vérifier les fichiers existants
            if ($copiedFilesCount > 0) {
                Log::info("Tous les fichiers copiés ({$copiedFilesCount}), déclenchement du scan...");
                $this->triggerMediaScan();
                
                // Attendre que le scan soit terminé (base + 3s par fichier copié)
                $waitTime = 5 + ($copiedFilesCount * 3);
                Log::info("Attente de {$waitTime} secondes pour le scan complet...");
                sleep($waitTime);
                
                // Mettre à jour les azuracast_id des fichiers
                $this->updateFileAzuraCastIds();
            } else if (count($filesToScan) > 0) {
                // ✅ CORRECTION: Même si aucun fichier n'a été copié, TOUJOURS scanner
                // Les fichiers peuvent exister physiquement mais ne pas être scannés par AzuraCast
                Log::info("Aucun nouveau fichier copié, mais scan nécessaire pour vérifier les fichiers existants (" . count($filesToScan) . " fichiers audio)...");
                $this->triggerMediaScan();
                
                // ✅ Attendre plus longtemps pour le scan complet (important pour détecter tous les fichiers)
                $waitTime = 10 + (count($filesToScan) * 2); // Base de 10s + 2s par fichier
                Log::info("Attente de {$waitTime} secondes pour le scan complet...");
                sleep($waitTime);
                
                // Mettre à jour les azuracast_id des fichiers
                $this->updateFileAzuraCastIds();
            }

            // 3. Vérifier que tous les fichiers existent dans AzuraCast avant de générer le M3U
            $missingFilesBeforeImport = [];
            foreach ($playlist->items as $item) {
                if ($item->mediaFile && $item->mediaFile->file_type === 'audio') {
                    $fileName = basename($item->mediaFile->original_name);
                    if (!$this->checkFileExistsInAzuraCast($fileName)) {
                        $missingFilesBeforeImport[] = $fileName;
                    }
                }
            }
            
            if (!empty($missingFilesBeforeImport)) {
                Log::warning("⚠️ Fichiers manquants dans AzuraCast avant import M3U", [
                    'missing_files' => $missingFilesBeforeImport,
                    'count' => count($missingFilesBeforeImport)
                ]);
                
                // ✅ CORRECTION: Forcer la copie des fichiers manquants
                $forcedCopyCount = 0;
                foreach ($playlist->items as $item) {
                    if ($item->mediaFile && $item->mediaFile->file_type === 'audio') {
                        $fileName = basename($item->mediaFile->original_name);
                        if (in_array($fileName, $missingFilesBeforeImport)) {
                            Log::info("⚠️ Tentative de copie forcée du fichier manquant: {$fileName}");
                            
                            // Réinitialiser l'azuracast_id si le fichier n'existe pas
                            if ($item->mediaFile->azuracast_id) {
                                $item->mediaFile->update(['azuracast_id' => null]);
                                Log::info("Azuracast ID réinitialisé pour forcer la copie: {$fileName}");
                            }
                            
                            // Copier le fichier
                            $copyResult = $this->copyFileToAzuraCast($item->mediaFile, false);
                            if ($copyResult['success']) {
                                $forcedCopyCount++;
                                Log::info("Fichier manquant copié avec succès ({$forcedCopyCount}): {$fileName}");
                            } else {
                                Log::error("Échec de la copie forcée du fichier manquant: {$fileName}", [
                                    'error' => $copyResult['message']
                                ]);
                            }
                        }
                    }
                }
                
                // Si des fichiers ont été copiés, attendre et scanner
                if ($forcedCopyCount > 0) {
                    Log::info("Fichiers manquants copiés ({$forcedCopyCount}), nouveau scan...");
                    $this->triggerMediaScan();
                    $waitTime = 10 + ($forcedCopyCount * 3);
                    Log::info("Attente de {$waitTime} secondes pour le scan des fichiers copiés...");
                    sleep($waitTime);
                    $this->updateFileAzuraCastIds();
                } else {
                    // Attendre un peu plus et réessayer le scan seulement
                Log::info("Nouveau scan des médias pour détecter les fichiers manquants...");
                $this->triggerMediaScan();
                    $waitTime = 10 + (count($missingFilesBeforeImport) * 2);
                    Log::info("Attente de {$waitTime} secondes pour le scan...");
                    sleep($waitTime);
                $this->updateFileAzuraCastIds();
                }
                
                // Vérifier à nouveau après le scan/la copie
                $stillMissing = [];
                foreach ($playlist->items as $item) {
                    if ($item->mediaFile && $item->mediaFile->file_type === 'audio') {
                        $fileName = basename($item->mediaFile->original_name);
                        if (!$this->checkFileExistsInAzuraCast($fileName)) {
                            $stillMissing[] = $fileName;
                        }
                    }
                }
                
                if (!empty($stillMissing)) {
                    Log::error("❌ ERREUR: Des fichiers sont toujours manquants après scan/copie forcée", [
                        'still_missing' => $stillMissing,
                        'count' => count($stillMissing)
                    ]);
                }
            }

            // 4. Générer le contenu M3U
            $m3uContent = $this->generateM3U($playlist);
            
            // Log du contenu M3U pour debug (premières lignes seulement)
            $m3uPreview = implode("\n", array_slice(explode("\n", $m3uContent), 0, 10));
            Log::info("M3U généré (aperçu)", [
                'playlist_id' => $playlist->id,
                'm3u_length' => strlen($m3uContent),
                'lines_count' => substr_count($m3uContent, "\n"),
                'preview' => $m3uPreview . (substr_count($m3uContent, "\n") > 10 ? "\n..." : "")
            ]);

            // 5. Vider la playlist AzuraCast
            $this->clearPlaylist($playlist->azuracast_id);
            Log::info("Playlist AzuraCast vidée", ['playlist_id' => $playlist->azuracast_id]);

            // 6. Importer le nouveau M3U
            $importResult = $this->importPlaylistToAzuraCast($playlist->azuracast_id, $m3uContent);
            
            if (!$importResult['success']) {
                $playlist->update([
                    'sync_status' => 'error',
                    'last_sync_at' => now(),
                ]);
                return [
                    'success' => false,
                    'message' => 'Erreur lors de l\'import du M3U: ' . $importResult['message'],
                ];
            }
            
            // ✅ Vérifier si des fichiers n'ont pas été trouvés lors de l'import
            $notFoundFiles = $importResult['not_found_files'] ?? [];
            $notFoundCount = $importResult['not_found_count'] ?? 0;
            $expectedCount = $playlist->items()->whereHas('mediaFile', function($query) {
                $query->where('file_type', 'audio');
            })->count();
            
            if ($notFoundCount > 0) {
                Log::error("❌ ERREUR: Des fichiers n'ont pas été trouvés lors de l'import M3U", [
                    'playlist_id' => $playlist->azuracast_id,
                    'not_found_count' => $notFoundCount,
                    'expected_files' => $expectedCount,
                    'not_found_files' => $notFoundFiles,
                    'import_results' => $importResult['import_results'] ?? []
                ]);
                
                // ✅ Si tous les fichiers sont manquants, c'est une erreur critique
                if ($notFoundCount >= $expectedCount) {
                    $playlist->update([
                        'sync_status' => 'error',
                        'last_sync_at' => now(),
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => "Erreur: Tous les fichiers sont manquants dans AzuraCast ({$notFoundCount}/{$expectedCount} fichiers non trouvés)",
                        'files_copied' => $copiedFilesCount,
                        'not_found_files' => $notFoundFiles,
                    ];
                } else {
                    // ✅ Si seulement quelques fichiers sont manquants, on continue mais on log l'erreur
                    Log::warning("⚠️ ATTENTION: Certains fichiers sont manquants mais la synchronisation continue", [
                        'missing_files' => $notFoundFiles,
                        'missing_count' => $notFoundCount,
                        'total_expected' => $expectedCount
                    ]);
                }
            }

            Log::info("M3U importé avec succès", [
                'playlist_id' => $playlist->azuracast_id,
                'import_results' => $importResult['import_results'] ?? []
            ]);

            // Vérifier le nombre de fichiers dans la playlist AzuraCast après import
            try {
                $playlistResponse = Http::timeout(30)->withHeaders([
                    'X-API-Key' => $this->apiKey
                ])->get("{$this->baseUrl}/api/station/{$this->stationId}/playlist/{$playlist->azuracast_id}");
                
                if ($playlistResponse->successful()) {
                    $playlistData = $playlistResponse->json();
                    $actualCount = $playlistData['num_songs'] ?? count($playlistData['media'] ?? []);
                    $expectedCount = $playlist->items()->whereHas('mediaFile', function($query) {
                        $query->where('file_type', 'audio');
                    })->count();
                    
                    Log::info("Vérification playlist après import", [
                        'playlist_id' => $playlist->azuracast_id,
                        'expected_files' => $expectedCount,
                        'actual_files' => $actualCount,
                        'difference' => $expectedCount - $actualCount
                    ]);
                    
                    // ✅ Vérifier si des fichiers sont manquants après l'import
                    if ($actualCount === 0 && $expectedCount > 0) {
                        Log::error("❌ ERREUR CRITIQUE: La playlist AzuraCast est vide après import !", [
                            'playlist_id' => $playlist->azuracast_id,
                            'expected_files' => $expectedCount,
                            'actual_files' => $actualCount,
                            'import_results' => $importResult['import_results'] ?? []
                        ]);
                        
                        // ✅ Retourner une erreur si la playlist est vide alors qu'elle devrait contenir des fichiers
                        $playlist->update([
                            'sync_status' => 'error',
                            'last_sync_at' => now(),
                        ]);
                        
                        return [
                            'success' => false,
                            'message' => "Erreur: La playlist AzuraCast est vide après import (attendu: {$expectedCount} fichiers, obtenu: {$actualCount})",
                            'files_copied' => $copiedFilesCount,
                            'expected_files' => $expectedCount,
                            'actual_files' => $actualCount,
                        ];
                    } else if ($actualCount < $expectedCount && $expectedCount > 0) {
                        // ✅ Avertir si seulement quelques fichiers sont manquants
                        Log::warning("⚠️ ATTENTION: Certains fichiers sont manquants dans la playlist AzuraCast", [
                            'playlist_id' => $playlist->azuracast_id,
                            'expected_files' => $expectedCount,
                            'actual_files' => $actualCount,
                            'missing_count' => $expectedCount - $actualCount,
                            'import_results' => $importResult['import_results'] ?? []
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Impossible de vérifier la playlist après import: " . $e->getMessage());
            }

            // 7. Mettre à jour les paramètres de la playlist pour s'assurer qu'elle est activée
            $this->updatePlaylistSettings($playlist->azuracast_id, [
                'name' => $playlist->name,
                'description' => $playlist->description ?? '',
                'order' => $playlist->is_shuffle ? 'random' : 'sequential',
                'loop' => $playlist->is_loop ?? false,
                'is_enabled' => true,
                'include_in_automation' => true, // ✅ Important : inclure dans l'automation
                'weight' => 5,
            ]);
            Log::info("Paramètres de la playlist mis à jour", ['playlist_id' => $playlist->azuracast_id]);

            // 8. Redémarrer AzuraCast - Docker en priorité (plus fiable pour forcer la mise à jour)
            // restartStationsContainer() démarre automatiquement le backend après le redémarrage
            $azuracastService = new AzuraCastService();
            try {
                // Redémarrer le conteneur Docker (force l'arrêt complet et la relecture)
                // Le backend sera démarré automatiquement une fois l'API disponible
                $restartResult = $azuracastService->restartStationsContainer(true); // autoStartBackend = true
                Log::info("AzuraCast redémarré via Docker avec démarrage automatique du backend", [
                    'playlist_id' => $playlist->id,
                    'docker_output' => $restartResult['output'] ?? '',
                    'api_ready' => $restartResult['api_ready'] ?? false,
                    'wait_time' => $restartResult['wait_time'] ?? 0
                ]);
                
            } catch (\Exception $dockerError) {
                Log::error("Erreur redémarrage Docker, tentative API", [
                    'playlist_id' => $playlist->id,
                    'error' => $dockerError->getMessage()
                ]);
                
                // Fallback : redémarrer puis démarrer via l'API si Docker échoue
                try {
                    $backendRestart = $azuracastService->restartBackend();
                    Log::info("Backend AzuraCast redémarré via API (fallback)", [
                        'playlist_id' => $playlist->id,
                        'api_response' => $backendRestart ?? 'OK'
                    ]);
                    sleep(3);
                    
                    // Démarrer explicitement le backend
                    try {
                        $backendStart = $azuracastService->startBackend();
                        Log::info("Backend AzuraCast démarré via API (fallback)", [
                            'playlist_id' => $playlist->id,
                            'api_response' => $backendStart ?? 'OK'
                        ]);
                    } catch (\Exception $startError) {
                        Log::warning("Erreur démarrage backend (non bloquant)", [
                            'playlist_id' => $playlist->id,
                            'error' => $startError->getMessage()
                        ]);
                    }
                } catch (\Exception $apiError) {
                    Log::error("Erreur redémarrage API également", [
                        'playlist_id' => $playlist->id,
                        'error' => $apiError->getMessage()
                    ]);
                    // Ne pas bloquer, on continue quand même
                }
            }

            // Mettre à jour le statut de la playlist
            $playlist->update([
                'sync_status' => 'synced',
                'last_sync_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'M3U mis à jour et AzuraCast redémarré avec succès',
                'm3u_lines' => substr_count($m3uContent, "\n"),
                'files_copied' => $copiedFilesCount,
                'import_results' => $importResult['import_results'] ?? []
            ];

        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour M3U rapide et redémarrage", [
                'playlist_id' => $playlist->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $playlist->update([
                'sync_status' => 'error',
                'last_sync_at' => now(),
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour M3U et redémarrage: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Synchronisation rapide de la playlist (sans copie/scan de fichiers)
     * Utilisée pour les modifications simples (ajout/suppression d'items)
     * @deprecated Utiliser updateM3UAndRestartService() à la place (plus fiable avec Docker)
     */
    public function syncPlaylistQuick(Playlist $playlist): array
    {
        try {
            Log::info("Début de la synchronisation rapide de la playlist: {$playlist->name}");

            if (!$playlist->azuracast_id) {
                // Si la playlist n'existe pas dans AzuraCast, utiliser la synchronisation complète
                return $this->syncPlaylist($playlist);
            }

            // Mettre à jour les paramètres de la playlist
            $this->updatePlaylistSettings($playlist->azuracast_id, [
                'name' => $playlist->name,
                'description' => $playlist->description ?? '',
                'order' => $playlist->is_shuffle ? 'random' : 'sequential',
                'loop' => $playlist->is_loop ?? false,
                'is_enabled' => true,
            ]);

            // Vider la playlist
            $this->clearPlaylist($playlist->azuracast_id);

            // Régénérer le M3U avec les items restants
            $m3uContent = $this->generateM3U($playlist);
            
            // Réimporter la playlist
            $importResult = $this->importPlaylistToAzuraCast($playlist->azuracast_id, $m3uContent);

            if ($importResult['success']) {
                $playlist->update([
                    'sync_status' => 'synced',
                    'last_sync_at' => now(),
                ]);
                Log::info("Synchronisation rapide terminée avec succès", [
                    'playlist' => $playlist->name,
                    'import_result' => $importResult
                ]);

                $this->handleShuffleAndRestart($playlist);
            } else {
                $playlist->update([
                    'sync_status' => 'error',
                    'last_sync_at' => now(),
                ]);
                Log::error("Échec de la synchronisation rapide de la playlist", [
                    'playlist' => $playlist->name,
                    'error' => $importResult['message']
                ]);
            }

            return [
                'success' => $importResult['success'],
                'message' => $importResult['success'] ? 'Synchronisation rapide réussie' : 'Erreur: ' . $importResult['message'],
                'import_results' => $importResult['import_results'] ?? []
            ];

        } catch (\Exception $e) {
            Log::error("Erreur lors de la synchronisation rapide de la playlist {$playlist->name}: " . $e->getMessage());
            $playlist->update([
                'sync_status' => 'error',
                'last_sync_at' => now(),
            ]);
            return [
                'success' => false,
                'message' => 'Erreur lors de la synchronisation rapide: ' . $e->getMessage()
            ];
        }
    }

    private function createPlaylistInAzuraCast(Playlist $playlist): array
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'X-API-Key' => $this->apiKey
            ])->post("{$this->baseUrl}/api/station/{$this->stationId}/playlists", [
                'name' => $playlist->name,
                'type' => 'default',
                'source' => 'songs',
                'order' => $playlist->is_shuffle ? 'random' : 'sequential',
                'weight' => 3,
                'is_enabled' => true,
                'include_in_automation' => true
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $playlist->update(['azuracast_id' => $data['id']]);
                
                Log::info("Playlist créée dans AzuraCast", [
                    'playlist_id' => $data['id'],
                    'name' => $playlist->name
                ]);

                return [
                    'success' => true,
                    'message' => 'Playlist créée avec succès',
                    'playlist_id' => $data['id']
                ];
            } else {
                throw new Exception("Erreur API AzuraCast: " . $response->body());
            }

        } catch (\Exception $e) {
            Log::error("Erreur lors de la création de la playlist: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }

    private function updateFileAzuraCastIds(): void
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey
            ])->get("{$this->baseUrl}/api/station/{$this->stationId}/files");

            if (!$response->successful()) {
                Log::error("Impossible de récupérer les fichiers AzuraCast");
                return;
            }

            $azuracastFiles = $response->json();
            $remoteMap = [];
            foreach ($azuracastFiles as $azuracastFile) {
                $filename = $azuracastFile['path'] ?? $azuracastFile['name'] ?? null;
                if ($filename) {
                    $remoteMap[strtolower(basename($filename))] = $azuracastFile['id'];
                }
            }

            $updatedCount = 0;
            $unchangedCount = 0;
            $resetCount = 0;

            $localFiles = MediaFile::where('file_type', 'audio')->get(['id', 'original_name', 'azuracast_id']);

            foreach ($localFiles as $mediaFile) {
                $basename = strtolower(basename($mediaFile->original_name));

                if (isset($remoteMap[$basename])) {
                    $newId = $remoteMap[$basename];
                    if ($mediaFile->azuracast_id != $newId) {
                        $mediaFile->update(['azuracast_id' => $newId]);
                            $updatedCount++;
                        Log::info("AzuraCast ID mis à jour pour: {$mediaFile->original_name} -> {$newId}");
                        } else {
                            $unchangedCount++;
                        }
                } elseif ($mediaFile->azuracast_id) {
                    $mediaFile->update(['azuracast_id' => null]);
                    $resetCount++;
                    Log::warning("AzuraCast ID réinitialisé (fichier manquant côté AzuraCast)", [
                        'file' => $mediaFile->original_name,
                        'previous_id' => $mediaFile->azuracast_id,
                    ]);
                }
            }

            Log::info("Mise à jour IDs AzuraCast terminée", [
                'updated' => $updatedCount,
                'unchanged' => $unchangedCount,
                'reset' => $resetCount,
            ]);

        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour des AzuraCast ID: " . $e->getMessage());
        }
    }

    public function updatePlaylistSettings(int $azuracastPlaylistId, array $settings): void
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'X-API-Key' => $this->apiKey
            ])->put("{$this->baseUrl}/api/station/{$this->stationId}/playlist/{$azuracastPlaylistId}", $settings);

            if ($response->successful()) {
                Log::info("Paramètres de la playlist mis à jour dans AzuraCast", [
                    'playlist_id' => $azuracastPlaylistId,
                    'settings' => $settings
                ]);
            } else {
                Log::error("Erreur lors de la mise à jour des paramètres de la playlist", [
                    'playlist_id' => $azuracastPlaylistId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour des paramètres: " . $e->getMessage());
        }
    }

    public function clearPlaylist(int $azuracastPlaylistId): void
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'X-API-Key' => $this->apiKey
            ])->delete("{$this->baseUrl}/api/station/{$this->stationId}/playlist/{$azuracastPlaylistId}/empty");

            if ($response->successful()) {
                Log::info("Playlist AzuraCast vidée", ['playlist_id' => $azuracastPlaylistId]);
            } else {
                Log::error("Erreur lors du vidage de la playlist: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Erreur lors du vidage de la playlist: " . $e->getMessage());
        }
    }

    public function generateM3U(Playlist $playlist): string
    {
        $m3u = "#EXTM3U\n";
        $fileCount = 0;
        $missingFiles = [];
        
        foreach ($playlist->items as $item) {
            if ($item->mediaFile && $item->mediaFile->file_type === 'audio') {
                // ✅ CORRECTION: Utiliser basename() car AzuraCast cherche les fichiers par leur nom uniquement
                $fileName = basename($item->mediaFile->original_name);
                
                // Vérifier que le fichier existe dans AzuraCast
                $existsInAzuraCast = $this->checkFileExistsInAzuraCast($fileName);
                
                if (!$existsInAzuraCast) {
                    $missingFiles[] = $fileName;
                    Log::warning("Fichier manquant dans AzuraCast pour M3U", [
                        'file_name' => $fileName,
                        'original_name' => $item->mediaFile->original_name,
                        'file_id' => $item->mediaFile->id
                    ]);
                }
                
                // Utiliser uniquement le nom de fichier (basename) dans le M3U
                $m3u .= "#EXTINF:-1,{$fileName}\n";
                $m3u .= "{$fileName}\n";
                $fileCount++;
            }
        }
        
        Log::info("M3U généré avec détails", [
            'playlist_id' => $playlist->id,
            'playlist_name' => $playlist->name,
            'total_files' => $fileCount,
            'missing_files' => $missingFiles,
            'missing_count' => count($missingFiles)
        ]);
        
        if (!empty($missingFiles)) {
            Log::error("⚠️ ATTENTION: Des fichiers sont manquants dans AzuraCast", [
                'missing_files' => $missingFiles,
                'count' => count($missingFiles)
            ]);
        }
        
        return $m3u;
    }

    public function importPlaylistToAzuraCast(int $azuracastPlaylistId, string $m3uContent): array
    {
        try {
            // Log du contenu M3U avant import pour debug
            $m3uLines = explode("\n", $m3uContent);
            $fileEntries = array_filter($m3uLines, function($line) {
                return !empty(trim($line)) && !str_starts_with(trim($line), '#');
            });
            
            Log::info("Import M3U dans AzuraCast", [
                'playlist_id' => $azuracastPlaylistId,
                'm3u_length' => strlen($m3uContent),
                'total_lines' => count($m3uLines),
                'file_entries' => count($fileEntries),
                'first_files' => array_slice($fileEntries, 0, 5) // Premiers fichiers pour debug
            ]);
            
            $tempFile = tempnam(sys_get_temp_dir(), 'playlist_');
            file_put_contents($tempFile, $m3uContent);

            $response = Http::timeout(60)->withHeaders([
                'X-API-Key' => $this->apiKey
            ])->attach('playlist_file', file_get_contents($tempFile), 'playlist.m3u')
              ->post("{$this->baseUrl}/api/station/{$this->stationId}/playlist/{$azuracastPlaylistId}/import");

            unlink($tempFile);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Analyser les résultats d'import pour détecter les fichiers non trouvés
                $importResults = $responseData['import_results'] ?? [];
                $notFoundFiles = [];
                $successCount = 0;
                
                if (is_array($importResults)) {
                    foreach ($importResults as $result) {
                        if (isset($result['success']) && $result['success']) {
                            $successCount++;
                        } elseif (isset($result['error']) || (isset($result['success']) && !$result['success'])) {
                            $notFoundFiles[] = $result['file'] ?? $result['path'] ?? 'unknown';
                        }
                    }
                }
                
                Log::info("Playlist importée dans AzuraCast", [
                    'playlist_id' => $azuracastPlaylistId,
                    'total_entries' => count($fileEntries),
                    'success_count' => $successCount,
                    'not_found_files' => $notFoundFiles,
                    'not_found_count' => count($notFoundFiles),
                    'full_response' => $responseData
                ]);
                
                if (!empty($notFoundFiles)) {
                    Log::error("❌ Fichiers non trouvés lors de l'import M3U", [
                        'not_found_files' => $notFoundFiles,
                        'count' => count($notFoundFiles)
                    ]);
                }
                
                return [
                    'success' => true,
                    'message' => 'Playlist importée avec succès',
                    'import_results' => $importResults,
                    'success_count' => $successCount,
                    'not_found_count' => count($notFoundFiles),
                    'not_found_files' => $notFoundFiles
                ];
            } else {
                Log::error("Erreur lors de l'import de la playlist", [
                    'playlist_id' => $azuracastPlaylistId,
                    'status_code' => $response->status(),
                    'response_body' => $response->body()
                ]);
                return [
                    'success' => false,
                    'message' => 'Erreur lors de l\'import: ' . $response->body(),
                    'status_code' => $response->status()
                ];
            }
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'import de la playlist", [
                'playlist_id' => $azuracastPlaylistId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Force l'arrêt immédiat en redémarrant la station AzuraCast
     * Supprime automatiquement la playlist "default" si AzuraCast la recrée
     */
    public function forceStop(): array
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'X-API-Key' => $this->apiKey
            ])->post("$this->baseUrl/api/station/$this->stationId/reload");

            if ($response->successful()) {
                Log::info("Station AzuraCast rechargée pour arrêt forcé");
                
                // ✅ Attendre un peu pour qu'AzuraCast recrée la playlist "default" si nécessaire
                sleep(2);
                
                // ✅ Supprimer la playlist "default" si AzuraCast l'a recréée automatiquement
                $this->deleteDefaultPlaylistIfExists();
                
                return [
                    'success' => true,
                    'message' => 'Station rechargée - arrêt forcé réussi'
                ];
            } else {
                Log::error("Erreur redémarrage station: " . $response->body());
                return [
                    'success' => false,
                    'message' => 'Erreur redémarrage: ' . $response->body()
                ];
            }
        } catch (Exception $e) {
            Log::error("Erreur redémarrage station: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Supprime la playlist "default" si elle existe dans AzuraCast
     * (AzuraCast la recrée automatiquement si aucune playlist active n'existe)
     */
    public function deleteDefaultPlaylistIfExists(): void
    {
        try {
            // Récupérer toutes les playlists
            $response = Http::timeout(30)->withHeaders([
                'X-API-Key' => $this->apiKey
            ])->get("{$this->baseUrl}/api/station/{$this->stationId}/playlists");

            if ($response->successful()) {
                $playlists = $response->json();
                
                foreach ($playlists as $playlist) {
                    // Vérifier si c'est la playlist "default" et qu'elle n'est pas liée à une playlist locale
                    if (isset($playlist['name']) && strtolower($playlist['name']) === 'default') {
                        $playlistId = $playlist['id'];
                        
                        // Vérifier si cette playlist existe dans notre base de données locale
                        $existsInLocal = \App\Models\Playlist::where('azuracast_id', $playlistId)->exists();
                        
                        if (!$existsInLocal) {
                            // Supprimer la playlist "default" non liée
                            $deleteResponse = Http::timeout(30)->withHeaders([
                                'X-API-Key' => $this->apiKey
                            ])->delete("{$this->baseUrl}/api/station/{$this->stationId}/playlist/{$playlistId}");

                            if ($deleteResponse->successful()) {
                                Log::info("Playlist 'default' automatique supprimée après reload", [
                                    'playlist_id' => $playlistId
                                ]);
                            } else {
                                Log::warning("Impossible de supprimer la playlist 'default'", [
                                    'playlist_id' => $playlistId,
                                    'response' => $deleteResponse->body()
                                ]);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Erreur lors de la suppression de la playlist 'default': " . $e->getMessage());
        }
    }

    /**
     * Force l'arrêt complet d'AzuraCast en supprimant TOUTES les playlists
     * Utilisé quand toutes les playlists sont supprimées
     */
    public function forceStopAllPlaylists(): array
    {
        try {
            Log::info("🛑 Arrêt complet d'AzuraCast - Suppression de toutes les playlists");
            
            // 1. Arrêter le backend d'abord
            $stopResult = Http::timeout(30)->withHeaders([
                'X-API-Key' => $this->apiKey
            ])->post("{$this->baseUrl}/api/station/{$this->stationId}/backend/restart");
            
            if ($stopResult->successful()) {
                Log::info("Backend AzuraCast arrêté");
                sleep(3); // Attendre que l'arrêt soit effectif
            }
            
            // 2. Récupérer toutes les playlists dans AzuraCast
            $response = Http::timeout(30)->withHeaders([
                'X-API-Key' => $this->apiKey
            ])->get("{$this->baseUrl}/api/station/{$this->stationId}/playlists");
            
            if (!$response->successful()) {
                throw new Exception("Impossible de récupérer les playlists AzuraCast");
            }
            
            $playlists = $response->json();
            $deletedCount = 0;
            $errors = [];
            
            // 3. Supprimer TOUTES les playlists (y compris "default")
            foreach ($playlists as $playlist) {
                $playlistId = $playlist['id'] ?? null;
                $playlistName = $playlist['name'] ?? 'Unknown';
                
                if ($playlistId) {
                    try {
                        $deleteResponse = Http::timeout(30)->withHeaders([
                            'X-API-Key' => $this->apiKey
                        ])->delete("{$this->baseUrl}/api/station/{$this->stationId}/playlist/{$playlistId}");
                        
                        if ($deleteResponse->successful()) {
                            $deletedCount++;
                            Log::info("Playlist supprimée: {$playlistName} (ID: {$playlistId})");
                        } else {
                            $errors[] = "Erreur suppression {$playlistName}: " . $deleteResponse->body();
                            Log::warning("Erreur suppression playlist", [
                                'name' => $playlistName,
                                'id' => $playlistId,
                                'response' => $deleteResponse->body()
                            ]);
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Exception suppression {$playlistName}: " . $e->getMessage();
                        Log::error("Exception lors de la suppression de la playlist", [
                            'name' => $playlistName,
                            'id' => $playlistId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            // 4. Redémarrer le backend pour appliquer les changements
            sleep(2);
            $restartResult = Http::timeout(30)->withHeaders([
                'X-API-Key' => $this->apiKey
            ])->post("{$this->baseUrl}/api/station/{$this->stationId}/backend/restart");
            
            if ($restartResult->successful()) {
                Log::info("Backend AzuraCast redémarré après suppression de toutes les playlists");
            }
            
            return [
                'success' => true,
                'message' => "{$deletedCount} playlist(s) supprimée(s)",
                'deleted_count' => $deletedCount,
                'total_playlists' => count($playlists),
                'errors' => $errors
            ];
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'arrêt complet d'AzuraCast: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }
}
