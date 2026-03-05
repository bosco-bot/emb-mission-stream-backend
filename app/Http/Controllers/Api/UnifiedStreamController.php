<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebTVAutoPlaylistService;
use App\Services\UnifiedHlsBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UnifiedStreamController extends Controller
{
    private WebTVAutoPlaylistService $autoPlaylistService;
    private string $unifiedPlaylistPath;
    private UnifiedHlsBuilder $fallbackBuilder;
    
    public function __construct()
    {
        $this->autoPlaylistService = new WebTVAutoPlaylistService();
        $this->unifiedPlaylistPath = '/usr/local/antmedia/webapps/LiveApp/streams/unified.m3u8';
        $this->fallbackBuilder = new UnifiedHlsBuilder();
    }

    /**
     * ✅ Gérer les requêtes OPTIONS (prévol CORS)
     * Les en-têtes CORS sont gérés par le middleware HandleCors de Laravel
     */
    public function options(): Response
    {
        return response('', 204);
    }

    /**
     * 🎯 ENDPOINT UNIFIÉ - Stream HLS (Live + VoD)
     */
    public function getUnifiedHLS()
    {
        try {
            Log::info("📺 Requête endpoint uniforme HLS reçue");

            $context = $this->autoPlaylistService->getCurrentPlaybackContext();

            if (($context['success'] ?? false) !== true) {
                Log::warning('⚠️ Impossible de récupérer le contexte de lecture pour le flux unifié', [
                    'message' => $context['message'] ?? null,
                ]);

                return $this->generateErrorHLS("Service indisponible");
            }

            if (($context['mode'] ?? 'vod') === 'live') {
                $streamId = $context['live']['stream_id'] ?? null;

                if (!$streamId) {
                    return $this->generateErrorHLS("Stream live indisponible");
                }

                Log::info("🔴 Mode Live détecté - Service direct avec validation", ['stream_id' => $streamId]);

                // ✅ SERVICE DIRECT : Servir live_transcoded.m3u8 avec validation en temps réel
                return $this->serveLivePlaylistDirectly($streamId);
            }

            Log::info("📺 Mode VoD détecté - utilisation de la playlist unifiée générée");
            return $this->serveUnifiedPlaylist();
        } catch (\Exception $e) {
            Log::error("❌ Erreur endpoint HLS : " . $e->getMessage());
            return $this->generateErrorHLS("Erreur interne");
        }
    }

    /**
     * ✅ SOLUTION HYBRIDE : Lire la playlist Ant Media avec ses vraies métadonnées
     * et ne garder que les segments qui existent vraiment sur le disque
     * 
     * Cette approche combine le meilleur des deux mondes :
     * - Métadonnées précises d'Ant Media (durées réelles, timestamps)
     * - Validation que les segments existent vraiment sur le disque
     */
    private function serveLivePlaylistDirectly(string $streamId): Response
    {
        try {
            $livePlaylistPath = "/usr/local/antmedia/webapps/LiveApp/streams/{$streamId}.m3u8";
            
            if (!file_exists($livePlaylistPath)) {
                Log::warning('⚠️ Playlist live introuvable', [
                    'stream_id' => $streamId,
                    'path' => $livePlaylistPath,
                ]);
                return $this->generateErrorHLS("Stream live indisponible");
            }
            
            // ✅ Lire la playlist d'Ant Media avec ses vraies métadonnées
            $content = @file_get_contents($livePlaylistPath);
            if ($content === false || empty($content)) {
                Log::error('❌ Impossible de lire live_transcoded.m3u8', [
                    'stream_id' => $streamId,
                    'path' => $livePlaylistPath,
                ]);
                return $this->generateErrorHLS("Erreur lecture stream live");
            }
            
            // ✅ SOLUTION HYBRIDE : Filtrer la playlist Ant Media en gardant ses métadonnées exactes
            // On utilise les vraies durées et timestamps d'Ant Media mais on ne garde que les segments qui existent
            $validatedContent = $this->filterPlaylistWithOriginalMetadata($content, $streamId);
            
            if (empty($validatedContent)) {
                // Fallback : servir l'original si la validation échoue complètement
                Log::warning('⚠️ Aucun segment valide trouvé, fallback sur playlist originale', [
                    'stream_id' => $streamId,
                ]);
                $validatedContent = $content;
            }
            
            // ✅ Servir la playlist validée avec les métadonnées originales d'Ant Media
            return response($validatedContent, 200, [
                'Content-Type' => 'application/vnd.apple.mpegurl; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate, private',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Content-Type-Options' => 'nosniff',
                'X-Served-By' => 'Laravel-UnifiedStream',
                // Les en-têtes CORS sont gérés par le middleware HandleCors de Laravel
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur service direct live: ' . $e->getMessage(), [
                'stream_id' => $streamId,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->generateErrorHLS("Erreur interne");
        }
    }
    
    /**
     * ✅ SOLUTION HYBRIDE : Filtrer la playlist Ant Media en gardant ses métadonnées exactes
     * 
     * Cette méthode lit la playlist d'Ant Media avec ses vraies métadonnées (durées, timestamps)
     * et ne garde que les segments qui existent vraiment sur le disque.
     * On garde TOUTES les métadonnées originales d'Ant Media pour éviter les erreurs de parsing.
     */
    private function filterPlaylistWithOriginalMetadata(string $content, string $streamId): string
    {
        $lines = explode("\n", $content);
        $streamsDir = '/usr/local/antmedia/webapps/LiveApp/streams/';
        $outputLines = [];
        $validSegments = [];
        $pendingMetadata = []; // EXTINF, PROGRAM-DATE-TIME, etc.
        
        $mediaSequence = null;
        $targetDuration = null;
        $version = 3;
        
        foreach ($lines as $line) {
            $line = rtrim($line);
            
            // Ignorer les lignes vides
            if (empty($line)) {
                continue;
            }
            
            // Collecter les en-têtes
            if (strpos($line, '#EXTM3U') === 0) {
                continue; // On l'ajoutera
            }
            
            if (preg_match('/^#EXT-X-VERSION:(\d+)/', $line, $matches)) {
                $version = (int)$matches[1];
                continue;
            }
            
            if (preg_match('/^#EXT-X-TARGETDURATION:(\d+)/', $line, $matches)) {
                $targetDuration = (int)$matches[1];
                continue;
            }
            
            if (preg_match('/^#EXT-X-MEDIA-SEQUENCE:(\d+)/', $line, $matches)) {
                $mediaSequence = (int)$matches[1];
                continue;
            }
            
            // Collecter les métadonnées du segment
            if (preg_match('/^#EXT-X-PROGRAM-DATE-TIME:/', $line)) {
                $pendingMetadata[] = $line;
                continue;
            }
            
            if (preg_match('/^#EXTINF:/', $line)) {
                $pendingMetadata[] = $line;
                continue;
            }
            
            // Segment .ts
            if (preg_match('/\.ts$/', $line)) {
                $segmentFileName = trim($line);
                $segmentPath = $streamsDir . $segmentFileName;
                
                // ✅ VALIDER : Le segment existe-t-il vraiment et est-il valide ?
                if (file_exists($segmentPath) && is_readable($segmentPath)) {
                    $fileSize = @filesize($segmentPath);
                    // Vérifier taille minimale (50 KB)
                    if ($fileSize !== false && $fileSize >= 50000) {
                        // Vérifier intégrité TS (sync byte 0x47)
                        $handle = @fopen($segmentPath, 'rb');
                        $isValid = false;
                        if ($handle !== false) {
                            $firstByte = @fread($handle, 1);
                            @fclose($handle);
                            if ($firstByte !== false && ord($firstByte) === 0x47) {
                                $isValid = true;
                            }
                        }
                        
                        if ($isValid) {
                            // Segment valide avec ses métadonnées originales
                            $validSegments[] = [
                                'metadata' => $pendingMetadata,
                                'url' => $segmentFileName,
                            ];
                        }
                    }
                }
                
                // Réinitialiser les métadonnées pour le prochain segment
                $pendingMetadata = [];
                continue;
            }
        }
        
        // ✅ Ne garder que les 2-3 derniers segments valides (les plus récents)
        if (empty($validSegments)) {
            return '';
        }
        
        // ✅ Trier les segments par numéro de séquence pour s'assurer d'avoir les plus récents
        usort($validSegments, function($a, $b) use ($streamId) {
            $seqA = 0;
            $seqB = 0;
            if (preg_match('/' . preg_quote($streamId, '/') . '000000(\d+)\.ts$/', $a['url'], $matchesA)) {
                $seqA = (int)$matchesA[1];
            }
            if (preg_match('/' . preg_quote($streamId, '/') . '000000(\d+)\.ts$/', $b['url'], $matchesB)) {
                $seqB = (int)$matchesB[1];
            }
            return $seqA <=> $seqB;
        });
        
        // Prendre les 2 derniers segments (les plus récents après tri)
        $maxSegments = 2;
        $selectedSegments = array_slice($validSegments, -$maxSegments);
        
        if (empty($selectedSegments)) {
            return '';
        }
        
        // Recalculer MEDIA-SEQUENCE depuis le premier segment sélectionné
        $firstSegment = $selectedSegments[0]['url'];
        // ✅ Format: live_transcoded000001176.ts (6 zéros puis le numéro)
        // Accepter 6 zéros ou plus pour la compatibilité
        if (preg_match('/' . preg_quote($streamId, '/') . '0+(\d+)\.ts$/', $firstSegment, $matches)) {
            $mediaSequence = (int)$matches[1];
        } else {
            // ✅ Fallback : utiliser 0 si on ne peut pas extraire le numéro
            // MEDIA-SEQUENCE est OBLIGATOIRE pour les playlists HLS LIVE
            // Certains lecteurs (notamment Flutter Web) sont stricts et exigent cette balise
            $mediaSequence = 0;
        }
        
        // ✅ Reconstruire la playlist avec les métadonnées ORIGINALES d'Ant Media
        $outputLines[] = '#EXTM3U';
        $outputLines[] = "#EXT-X-VERSION:{$version}";
        $outputLines[] = $targetDuration !== null ? "#EXT-X-TARGETDURATION:{$targetDuration}" : '#EXT-X-TARGETDURATION:2';
        // ✅ TOUJOURS inclure MEDIA-SEQUENCE (obligatoire pour HLS LIVE, notamment pour Flutter Web)
        $outputLines[] = "#EXT-X-MEDIA-SEQUENCE:{$mediaSequence}";
        $outputLines[] = '#EXT-X-PLAYLIST-TYPE:LIVE';
        
        // Ajouter chaque segment avec ses métadonnées ORIGINALES d'Ant Media
        foreach ($selectedSegments as $segment) {
            foreach ($segment['metadata'] as $metadataLine) {
                $outputLines[] = $metadataLine;
            }
            $outputLines[] = $segment['url'];
        }
        
        $playlistContent = implode("\n", array_filter($outputLines)) . "\n";
        
        Log::debug('✅ Playlist filtrée avec métadonnées originales', [
            'stream_id' => $streamId,
            'selected_segments' => count($selectedSegments),
            'media_sequence' => $mediaSequence,
        ]);
        
        return $playlistContent;
    }
    
    /**
     * ✅ SOLUTION DÉFINITIVE : Construire la playlist HLS directement depuis les segments du disque
     * 
     * Cette méthode scanne le disque pour trouver les segments .ts qui existent vraiment,
     * les valide (taille, intégrité TS), et construit une playlist HLS valide.
     * Cela élimine complètement la race condition car on ne sert que des segments qui existent.
     * 
     * @deprecated Utiliser filterPlaylistWithOriginalMetadata() à la place pour garder les métadonnées exactes
     */
    private function buildPlaylistFromDisk(string $streamId): string
    {
        $streamsDir = '/usr/local/antmedia/webapps/LiveApp/streams/';
        $pattern = $streamsDir . $streamId . '*.ts';
        
        $files = glob($pattern);
        if (empty($files)) {
            Log::debug('⚠️ Aucun segment trouvé sur disque', [
                'stream_id' => $streamId,
                'pattern' => $pattern,
            ]);
            return '';
        }
        
        // Trier par nom (qui contient le numéro de séquence) pour avoir l'ordre chronologique
        usort($files, function($a, $b) {
            return strcmp(basename($a), basename($b));
        });
        
        // ✅ VALIDER chaque segment : existence, taille, intégrité TS
        // IMPORTANT : Pas de filtre d'âge minimal pour prendre les segments les plus récents possibles
        $validSegments = [];
        $minSize = 50000; // 50 KB minimum
        
        foreach ($files as $file) {
            $fileName = basename($file);
            
            // Vérifier existence et lisibilité
            if (!file_exists($file) || !is_readable($file)) {
                continue;
            }
            
            // Vérifier taille
            $fileSize = @filesize($file);
            if ($fileSize === false || $fileSize < $minSize) {
                continue;
            }
            
            // Vérifier que le fichier est stable (pas en cours d'écriture active)
            // On vérifie que la taille n'a pas changé entre deux lectures
            $size1 = @filesize($file);
            usleep(100000); // Attendre 100ms
            $size2 = @filesize($file);
            if ($size1 === false || $size2 === false || $size1 !== $size2) {
                // Le fichier est encore en cours d'écriture, l'ignorer
                continue;
            }
            
            // Vérifier intégrité TS (sync byte 0x47)
            $handle = @fopen($file, 'rb');
            if ($handle !== false) {
                $firstByte = @fread($handle, 1);
                @fclose($handle);
                if ($firstByte === false || ord($firstByte) !== 0x47) {
                    continue; // Pas un fichier TS valide
                }
            } else {
                continue; // Impossible de lire le fichier
            }
            
            // Extraire le numéro de séquence
            if (preg_match('/' . preg_quote($streamId, '/') . '000000(\d+)\.ts$/', $fileName, $matches)) {
                $sequence = (int)$matches[1];
                $fileMtime = @filemtime($file);
                $validSegments[] = [
                    'file' => $file,
                    'name' => $fileName,
                    'sequence' => $sequence,
                    'size' => $fileSize,
                    'mtime' => $fileMtime !== false ? $fileMtime : time(),
                ];
            }
        }
        
        if (empty($validSegments)) {
            Log::debug('⚠️ Aucun segment valide trouvé après validation', [
                'stream_id' => $streamId,
                'total_files' => count($files),
            ]);
            return '';
        }
        
        // Trier par séquence (du plus ancien au plus récent)
        usort($validSegments, function($a, $b) {
            return $a['sequence'] <=> $b['sequence'];
        });
        
        // ✅ PRENDRE LES 2 DERNIERS SEGMENTS SEULEMENT (les plus récents et les plus sûrs)
        // Avec 2 segments seulement, on réduit le risque qu'un segment soit supprimé
        $maxSegments = 2;
        $candidateSegments = array_slice($validSegments, -$maxSegments);
        
        if (empty($candidateSegments)) {
            return '';
        }
        
        // ✅ VÉRIFICATION FINALE : Tester que chaque segment est accessible via HTTP
        // Cela garantit que le segment est vraiment disponible pour le navigateur
        $selectedSegments = [];
        $baseUrl = 'https://tv.embmission.com/hls/streams/';
        
        foreach ($candidateSegments as $segment) {
            $segmentUrl = $baseUrl . $segment['name'];
            
            // Test rapide HTTP HEAD pour vérifier l'accessibilité
            $ch = curl_init($segmentUrl);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $selectedSegments[] = $segment;
            } else {
                Log::debug('⚠️ Segment non accessible via HTTP, ignoré', [
                    'segment' => $segment['name'],
                    'http_code' => $httpCode,
                ]);
            }
        }
        
        // Si aucun segment n'est accessible, utiliser au moins les candidats
        if (empty($selectedSegments)) {
            Log::warning('⚠️ Aucun segment accessible via HTTP, utilisation des segments du disque', [
                'stream_id' => $streamId,
            ]);
            $selectedSegments = $candidateSegments;
        }
        
        if (empty($selectedSegments)) {
            return '';
        }
        
        // Calculer MEDIA-SEQUENCE depuis le premier segment
        $mediaSequence = $selectedSegments[0]['sequence'];
        
        // ✅ Construire la playlist HLS valide
        $outputLines = [];
        $outputLines[] = '#EXTM3U';
        $outputLines[] = '#EXT-X-VERSION:3';
        $outputLines[] = '#EXT-X-TARGETDURATION:3';
        $outputLines[] = "#EXT-X-MEDIA-SEQUENCE:{$mediaSequence}";
        $outputLines[] = '#EXT-X-PLAYLIST-TYPE:LIVE';
        
        // Ajouter chaque segment avec ses métadonnées
        foreach ($selectedSegments as $segment) {
            // Durée estimée : 2 secondes (standard pour HLS live)
            $duration = 2.0;
            
            // PROGRAM-DATE-TIME basé sur le mtime du fichier
            $programDateTime = date('Y-m-d\TH:i:s.000+0000', $segment['mtime']);
            
            $outputLines[] = "#EXT-X-PROGRAM-DATE-TIME:{$programDateTime}";
            $outputLines[] = "#EXTINF:{$duration},";
            $outputLines[] = $segment['name'];
        }
        
        $playlistContent = implode("\n", $outputLines) . "\n";
        
        Log::debug('✅ Playlist construite depuis le disque', [
            'stream_id' => $streamId,
            'total_segments_found' => count($validSegments),
            'selected_segments' => count($selectedSegments),
            'media_sequence' => $mediaSequence,
        ]);
        
        return $playlistContent;
    }

    /**
     * ✅ Valider et filtrer les segments pour ne garder que ceux qui existent
     * 
     * Cette méthode parse live_transcoded.m3u8 et ne garde que les segments
     * qui existent réellement sur le disque, limitant à 5 segments pour minimiser
     * le risque de suppression entre la validation et la lecture.
     */
    private function validateAndFilterSegments(string $content, string $streamId): string
    {
        $lines = explode("\n", trim($content));
        $outputLines = [];
        $segmentsFound = 0;
        $maxSegments = 5; // Limiter à 5 segments pour minimiser le risque
        $streamsDir = '/usr/local/antmedia/webapps/LiveApp/streams/';
        
        $currentExtinf = null;
        $currentProgramDateTime = null;
        $mediaSequence = null;
        $targetDuration = null;
        $version = 3;
        
        // Première passe : collecter les en-têtes et segments
        $validSegments = [];
        
        foreach ($lines as $line) {
            $line = rtrim($line);
            
            // Ignorer les lignes vides
            if (empty($line)) {
                continue;
            }
            
            // Collecter les en-têtes
            if (strpos($line, '#EXTM3U') === 0) {
                continue; // On le rajoutera nous-mêmes
            }
            
            if (preg_match('/^#EXT-X-VERSION:(\d+)/', $line, $matches)) {
                $version = (int)$matches[1];
                continue;
            }
            
            if (preg_match('/^#EXT-X-TARGETDURATION:(\d+)/', $line, $matches)) {
                $targetDuration = (int)$matches[1];
                continue;
            }
            
            if (preg_match('/^#EXT-X-MEDIA-SEQUENCE:(\d+)/', $line, $matches)) {
                $mediaSequence = (int)$matches[1];
                continue;
            }
            
            if (preg_match('/^#EXT-X-PROGRAM-DATE-TIME:/', $line)) {
                $currentProgramDateTime = $line;
                continue;
            }
            
            if (preg_match('/^#EXTINF:([\d.]+)/', $line, $matches)) {
                $currentExtinf = $line;
                continue;
            }
            
            // Segment .ts
            if (preg_match('/\.ts$/', $line)) {
                $segmentFileName = $line;
                $segmentPath = $streamsDir . $segmentFileName;
                
                // ✅ VALIDER : Le segment existe-t-il ?
                if (file_exists($segmentPath) && is_readable($segmentPath)) {
                    // Segment valide - l'ajouter
                    $validSegments[] = [
                        'extinf' => $currentExtinf,
                        'program_date_time' => $currentProgramDateTime,
                        'url' => $segmentFileName,
                    ];
                    
                    $currentExtinf = null;
                    $currentProgramDateTime = null;
                } else {
                    // Segment invalide - l'ignorer
                    $currentExtinf = null;
                    $currentProgramDateTime = null;
                }
                continue;
            }
            
            // Conserver les autres tags (INDEPENDENT-SEGMENTS, etc.)
            if (preg_match('/^#EXT-X-/', $line)) {
                // On les ajoutera après les en-têtes principaux
                continue;
            }
        }
        
        // ✅ Ne garder que les N derniers segments valides ET récents
        $totalValid = count($validSegments);
        if ($totalValid === 0) {
            Log::warning('⚠️ Aucun segment valide trouvé après validation', [
                'stream_id' => $streamId,
            ]);
            return '';
        }
        
        // ✅ STRATÉGIE : Prendre seulement les 2-3 DERNIERS segments valides
        // Les segments les plus récents sont ceux qui ont le plus de chances d'exister
        // quand le navigateur les charge (Ant Media supprime les anciens très rapidement)
        $maxSegments = 2; // Réduire à 2 segments seulement pour maximiser la fiabilité
        
        // Prendre les N derniers segments (les plus récents dans la playlist)
        $startIndex = max(0, $totalValid - $maxSegments);
        $selectedSegments = array_slice($validSegments, $startIndex);
        
        // ✅ Double validation : vérifier une dernière fois que les segments sélectionnés existent
        $finalSegments = [];
        foreach ($selectedSegments as $segment) {
            $segmentFileName = $segment['url'];
            $segmentPath = $streamsDir . $segmentFileName;
            
            if (file_exists($segmentPath) && is_readable($segmentPath)) {
                $finalSegments[] = $segment;
            } else {
                Log::warning('⚠️ Segment sélectionné n\'existe plus', [
                    'segment' => $segmentFileName,
                ]);
            }
        }
        
        // Si aucun segment final, prendre au moins le dernier disponible
        if (empty($finalSegments) && !empty($validSegments)) {
            $lastSegment = end($validSegments);
            $lastSegmentPath = $streamsDir . $lastSegment['url'];
            if (file_exists($lastSegmentPath)) {
                $finalSegments = [$lastSegment];
                Log::warning('⚠️ Utilisation du dernier segment disponible comme fallback', [
                    'segment' => $lastSegment['url'],
                ]);
            }
        }
        
        $selectedSegments = $finalSegments;
        
        // Recalculer MEDIA-SEQUENCE
        if ($mediaSequence !== null) {
            $mediaSequence = $mediaSequence + $startIndex;
        } else {
            $mediaSequence = $startIndex;
        }
        
        // Recalculer TARGETDURATION si nécessaire
        if ($targetDuration === null) {
            $targetDuration = 2; // Par défaut pour HLS live
        }
        
        // ✅ Reconstruire la playlist avec seulement les segments valides
        $outputLines[] = '#EXTM3U';
        $outputLines[] = "#EXT-X-VERSION:{$version}";
        $outputLines[] = "#EXT-X-TARGETDURATION:{$targetDuration}";
        $outputLines[] = "#EXT-X-MEDIA-SEQUENCE:{$mediaSequence}";
        $outputLines[] = '#EXT-X-PLAYLIST-TYPE:LIVE';
        
        foreach ($selectedSegments as $segment) {
            if ($segment['program_date_time']) {
                $outputLines[] = $segment['program_date_time'];
            }
            $outputLines[] = $segment['extinf'];
            
            // ✅ GARDER LES URLs RELATIVES comme dans l'original Ant Media
            // Le navigateur les résoudra automatiquement par rapport à l'URL de la playlist
            $segmentUrl = $segment['url'];
            // Si c'est déjà une URL absolue (venant de notre parsing), extraire juste le nom du fichier
            if (preg_match('/\/([^\/]+\.ts)$/', $segmentUrl, $matches)) {
                $segmentUrl = $matches[1]; // Garder seulement le nom du fichier
            }
            $outputLines[] = $segmentUrl;
        }
        
        $validatedContent = implode("\n", $outputLines) . "\n";
        
        Log::debug('✅ Playlist live validée', [
            'stream_id' => $streamId,
            'total_segments' => $totalValid,
            'selected_segments' => count($selectedSegments),
            'media_sequence' => $mediaSequence,
        ]);
        
        return $validatedContent;
    }

    /**
     * ✅ Valider et filtrer les segments en GARDANT EXACTEMENT le format d'Ant Media
     * 
     * Cette méthode parse ligne par ligne et supprime seulement les segments qui n'existent pas,
     * en gardant exactement le même format, ordre et structure qu'Ant Media.
     */
    private function validateAndFilterSegmentsKeepFormat(string $content, string $streamId): string
    {
        $lines = explode("\n", $content);
        $outputLines = [];
        $streamsDir = '/usr/local/antmedia/webapps/LiveApp/streams/';
        
        $pendingLines = []; // Lignes en attente (EXTINF, PROGRAM-DATE-TIME, etc.)
        $maxSegments = 3; // Ne garder que les 3 derniers segments valides
        $validSegments = []; // Stocker les segments valides avec leurs métadonnées
        
        foreach ($lines as $line) {
            $line = rtrim($line);
            
            // Toujours garder les lignes vides pour préserver le format
            if (empty($line)) {
                continue; // On les rajoutera à la fin
            }
            
            // Toujours garder les en-têtes
            if (strpos($line, '#EXTM3U') === 0 ||
                preg_match('/^#EXT-X-VERSION:/', $line) ||
                preg_match('/^#EXT-X-TARGETDURATION:/', $line) ||
                preg_match('/^#EXT-X-PLAYLIST-TYPE:/', $line) ||
                preg_match('/^#EXT-X-INDEPENDENT-SEGMENTS/', $line)) {
                continue; // On les gardera à la fin
            }
            
            // Pour MEDIA-SEQUENCE, on le recalculera
            if (preg_match('/^#EXT-X-MEDIA-SEQUENCE:/', $line)) {
                continue;
            }
            
            // Collecter les métadonnées avant le segment
            if (preg_match('/^#EXT-X-PROGRAM-DATE-TIME:/', $line)) {
                $pendingLines[] = $line;
                continue;
            }
            
            if (preg_match('/^#EXTINF:/', $line)) {
                $pendingLines[] = $line;
                continue;
            }
            
            // Segment .ts
            if (preg_match('/\.ts$/', $line)) {
                $segmentFileName = trim($line);
                $segmentPath = $streamsDir . $segmentFileName;
                
                // ✅ VALIDER 1 : Le segment existe-t-il ?
                if (!file_exists($segmentPath) || !is_readable($segmentPath)) {
                    // Segment invalide - ignorer
                    $pendingLines = [];
                    continue;
                }
                
                // ✅ VALIDER 1.5 : Taille minimale (évite les segments incomplets ou corrompus)
                $segmentSize = @filesize($segmentPath);
                $minSize = 50000; // 50 KB minimum (segments très courts souvent corrompus ou incomplets)
                if ($segmentSize === false || $segmentSize < $minSize) {
                    Log::debug('⚠️ Segment filtré - taille trop petite ou invalide', [
                        'segment' => $segmentFileName,
                        'size' => $segmentSize !== false ? $segmentSize : 'unknown',
                        'min_size' => $minSize,
                    ]);
                    $pendingLines = [];
                    continue;
                }
                
                // ✅ VALIDER 1.6 : Vérifier l'intégrité du segment TS (doit commencer par 0x47 = sync byte TS)
                $handle = @fopen($segmentPath, 'rb');
                if ($handle !== false) {
                    $firstByte = @fread($handle, 1);
                    @fclose($handle);
                    // Sync byte TS = 0x47 (71 en décimal)
                    if ($firstByte === false || ord($firstByte) !== 0x47) {
                        Log::debug('⚠️ Segment filtré - header TS invalide (pas de sync byte 0x47)', [
                            'segment' => $segmentFileName,
                            'first_byte' => $firstByte !== false ? '0x' . bin2hex($firstByte) : 'unknown',
                        ]);
                        $pendingLines = [];
                        continue;
                    }
                }
                
                // ✅ VALIDER 2 : Durée minimale >= 1 seconde (évite DEMUXER_ERROR_COULD_NOT_PARSE)
                $segmentDuration = null;
                foreach ($pendingLines as $metadataLine) {
                    if (preg_match('/^#EXTINF:([\d.]+)/', $metadataLine, $matches)) {
                        $segmentDuration = (float)$matches[1];
                        break;
                    }
                }
                
                // Filtrer les segments avec durées trop courtes (< 1.5s) qui causent des erreurs de parsing
                // Augmenté à 1.5s pour plus de stabilité (segments < 1s causent souvent des erreurs)
                if ($segmentDuration !== null && $segmentDuration < 1.5) {
                    Log::debug('⚠️ Segment filtré - durée trop courte', [
                        'segment' => $segmentFileName,
                        'duration' => $segmentDuration,
                        'min_duration' => 1.5,
                    ]);
                    $pendingLines = [];
                    continue;
                }
                
                // ✅ VALIDER 3 : Vérifier que le fichier n'est pas en cours d'écriture
                // Si le fichier a été modifié il y a moins de 2 secondes, il est peut-être encore en écriture
                $fileMtime = @filemtime($segmentPath);
                if ($fileMtime !== false && (time() - $fileMtime) < 2) {
                    Log::debug('⚠️ Segment filtré - fichier trop récent (peut être en cours d\'écriture)', [
                        'segment' => $segmentFileName,
                        'age_seconds' => time() - $fileMtime,
                    ]);
                    $pendingLines = [];
                    continue;
                }
                
                    // Segment valide - stocker avec ses métadonnées
                    $validSegments[] = [
                        'metadata' => $pendingLines,
                        'url' => $segmentFileName,
                    'duration' => $segmentDuration ?? 2.0, // Durée par défaut si non trouvée
                    ];
                    $pendingLines = [];
                continue;
            }
            
            // Autres lignes - les ignorer pour l'instant
            $pendingLines = [];
        }
        
        // ✅ Ne garder que les N derniers segments valides
        $totalValid = count($validSegments);
        if ($totalValid === 0) {
            Log::warning('⚠️ Aucun segment valide trouvé dans live_transcoded.m3u8', [
                'stream_id' => $streamId,
            ]);
            
            // ✅ FALLBACK : Chercher directement les segments qui existent sur le disque
            $existingSegments = $this->findExistingSegments($streamId, $maxSegments);
            if (empty($existingSegments)) {
                return '';
            }
            $selectedSegments = $existingSegments;
        } else {
            // Prendre les N derniers segments valides
            $startIndex = max(0, $totalValid - $maxSegments);
            $selectedSegments = array_slice($validSegments, $startIndex);
        }
        
        // ✅ DOUBLE VALIDATION : Vérifier une dernière fois que les segments sélectionnés existent
        // Ant Media peut supprimer les segments entre la validation et le moment où le client les charge
        $finalSegments = [];
        foreach ($selectedSegments as $segment) {
            $segmentFileName = $segment['url'];
            $segmentPath = $streamsDir . $segmentFileName;
            
            // Vérifier une dernière fois que le segment existe et est valide
            if (file_exists($segmentPath) && is_readable($segmentPath)) {
                $segmentSize = @filesize($segmentPath);
                // Vérifier que le segment n'est pas trop petit ou en cours d'écriture
                if ($segmentSize !== false && $segmentSize >= 50000) {
                    $fileMtime = @filemtime($segmentPath);
                    // Ignorer les segments modifiés il y a moins de 2 secondes (en cours d'écriture)
                    if ($fileMtime === false || (time() - $fileMtime) >= 2) {
                        $finalSegments[] = $segment;
                    } else {
                        Log::debug('⚠️ Segment exclu - trop récent (en cours d\'écriture)', [
                            'segment' => $segmentFileName,
                            'age_seconds' => time() - $fileMtime,
                        ]);
                    }
                } else {
                    Log::debug('⚠️ Segment exclu - taille insuffisante', [
                        'segment' => $segmentFileName,
                        'size' => $segmentSize !== false ? $segmentSize : 'unknown',
                    ]);
                }
            } else {
                Log::debug('⚠️ Segment exclu - n\'existe plus sur le disque', [
                    'segment' => $segmentFileName,
                ]);
            }
        }
        
        // Si aucun segment final valide, utiliser le fallback
        if (empty($finalSegments)) {
            Log::warning('⚠️ Aucun segment final valide après double validation, utilisation du fallback', [
                'stream_id' => $streamId,
            ]);
            $existingSegments = $this->findExistingSegments($streamId, $maxSegments);
            if (!empty($existingSegments)) {
                $selectedSegments = $existingSegments;
            } else {
                // Dernier recours : utiliser les segments validés même s'ils sont douteux
                $selectedSegments = $selectedSegments;
            }
        } else {
            $selectedSegments = $finalSegments;
        }
        
        // Recalculer MEDIA-SEQUENCE
        $mediaSequence = null;
        if (!empty($selectedSegments)) {
            $firstSegment = $selectedSegments[0]['url'];
            if (preg_match('/live_transcoded000000(\d+)\.ts$/', $firstSegment, $matches)) {
                $mediaSequence = (int)$matches[1];
            }
        }
        
        // ✅ Reconstruire la playlist en gardant EXACTEMENT le format d'Ant Media
        $outputLines[] = '#EXTM3U';
        $outputLines[] = '#EXT-X-VERSION:3';
        
        // TARGETDURATION - prendre depuis l'original ou calculer
        $outputLines[] = '#EXT-X-TARGETDURATION:3';
        
        if ($mediaSequence !== null) {
            $outputLines[] = "#EXT-X-MEDIA-SEQUENCE:{$mediaSequence}";
        }
        
        $outputLines[] = '#EXT-X-PLAYLIST-TYPE:LIVE';
        
        // Ajouter les segments avec leurs métadonnées
        foreach ($selectedSegments as $segment) {
            foreach ($segment['metadata'] as $metadataLine) {
                $outputLines[] = $metadataLine;
            }
            $outputLines[] = $segment['url'];
        }
        
        $validatedContent = implode("\n", $outputLines) . "\n";
        
        Log::debug('✅ Playlist validée (format préservé)', [
            'stream_id' => $streamId,
            'total_segments' => $totalValid,
            'selected_segments' => count($selectedSegments),
            'media_sequence' => $mediaSequence,
        ]);
        
        return $validatedContent;
    }

    /**
     * ✅ FALLBACK : Trouver directement les segments qui existent sur le disque
     * 
     * Utilisé quand live_transcoded.m3u8 référence des segments obsolètes.
     * On liste les fichiers .ts sur le disque et on prend les N derniers.
     */
    private function findExistingSegments(string $streamId, int $maxSegments): array
    {
        $streamsDir = '/usr/local/antmedia/webapps/LiveApp/streams/';
        $pattern = $streamsDir . $streamId . '*.ts';
        
        $files = glob($pattern);
        if (empty($files)) {
            return [];
        }
        
        // Trier par nom (qui contient le numéro de séquence)
        usort($files, function($a, $b) {
            return strcmp(basename($a), basename($b));
        });
        
        // Prendre les N derniers
        $lastFiles = array_slice($files, -$maxSegments);
        $segments = [];
        
        foreach ($lastFiles as $file) {
            $fileName = basename($file);
            $segments[] = [
                'metadata' => [
                    '#EXTINF:2.000,', // Durée par défaut
                ],
                'url' => $fileName,
            ];
        }
        
        Log::info('✅ Segments trouvés directement sur disque', [
            'stream_id' => $streamId,
            'count' => count($segments),
        ]);
        
        return $segments;
    }

    /**
     * Détecte si le fichier unified.m3u8 est le placeholder live (sans segments .ts).
     * Après une transition Live → VOD, le worker peut ne pas avoir encore réécrit le fichier.
     */
    private function isUnifiedPlaylistLivePlaceholder(string $content): bool
    {
        return strpos($content, '.ts') === false
            && (strpos($content, 'servi dynamiquement') !== false || strpos($content, '#EXT-X-PLAYLIST-TYPE:LIVE') !== false);
    }

    private function serveUnifiedPlaylist()
    {
        try {
            if (!file_exists($this->unifiedPlaylistPath)) {
                Log::warning('⚠️ Playlist unifiée absente - tentative de régénération à la volée');

                if (!$this->fallbackBuilder->build()) {
                    return $this->generateErrorHLS('Flux en cours de préparation, réessayez');
                }
            }

            $content = @file_get_contents($this->unifiedPlaylistPath);

            if ($content === false || $content === '') {
                Log::error('❌ Lecture impossible du fichier unified.m3u8', [
                    'path' => $this->unifiedPlaylistPath,
                ]);

                return $this->generateErrorHLS('Erreur lecture flux unifié');
            }

            // ✅ Transition Live → VOD : si le fichier est encore le placeholder live (sans segments), régénérer
            if ($this->isUnifiedPlaylistLivePlaceholder($content)) {
                Log::info('🔄 Fichier unified.m3u8 encore en placeholder live — régénération VOD à la volée');
                if ($this->fallbackBuilder->build()) {
                    $content = @file_get_contents($this->unifiedPlaylistPath);
                }
                if ($content === false || $content === '' || $this->isUnifiedPlaylistLivePlaceholder($content)) {
                    return $this->generateErrorHLS('Flux en cours de préparation, réessayez');
                }
            }

            return response($content, 200, [
                'Content-Type' => 'application/vnd.apple.mpegurl; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate, private',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Content-Type-Options' => 'nosniff',
                'X-Served-By' => 'Laravel-UnifiedStream',
                // Les en-têtes CORS sont gérés par le middleware HandleCors de Laravel
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur lecture playlist unifiée: ' . $e->getMessage());
            return $this->generateErrorHLS('Erreur flux unifié');
        }
    }

    /**
     * 📖 LIRE ET NORMALISER LE M3U8 POUR COMPATIBILITÉ MAXIMALE
     * Normalise en mémoire sans modifier le fichier sur disque
     */
    private function readAndNormalizeM3U8(string $filePath): string
    {
        try {
            // Lire le contenu du fichier M3U8
            $content = file_get_contents($filePath);
            
            if ($content === false || empty($content)) {
                Log::error("❌ Impossible de lire le fichier M3U8", ["path" => $filePath]);
                return "";
            }
            
            // Diviser en lignes
            $lines = explode("\n", $content);
            
            // ✅ COLLECTER TOUS LES SEGMENTS AVEC LEURS MÉTADONNÉES
            $allSegments = []; // Tableau pour stocker [extinf, url, program_date_time]
            $headerLines = []; // Lignes d'en-tête à conserver
            $maxSegments = 15; // Limiter à 15 segments pour compatibilité
            $maxDuration = 0;
            
            $currentExtinf = null;
            $currentProgramDateTime = null;
            
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                
                // Ignorer les lignes vides
                if (empty($trimmedLine)) {
                    continue;
                }
                
                // Collecter les lignes d'en-tête importantes
                if (strpos($trimmedLine, "#EXTM3U") === 0) {
                    $headerLines[] = "#EXTM3U";
                    continue;
                }
                
                if (preg_match("/^#EXT-X-VERSION:\\d+/", $trimmedLine)) {
                    // On forcera la version 3 plus tard
                    continue;
                }
                
                if (preg_match("/^#EXT-X-TARGETDURATION:(\\d+)/", $trimmedLine, $matches)) {
                    // On recalculera plus tard
                    continue;
                }
                
                if (preg_match("/^#EXT-X-MEDIA-SEQUENCE:(\\d+)/", $trimmedLine, $matches)) {
                    // On recalculera plus tard
                    continue;
                }
                
                // Collecter #EXT-X-PROGRAM-DATE-TIME si présent
                if (preg_match("/^#EXT-X-PROGRAM-DATE-TIME:/", $trimmedLine)) {
                    $currentProgramDateTime = $trimmedLine;
                    continue;
                }
                
                // Collecter #EXTINF avec durée
                if (preg_match("/^#EXTINF:([\\d.]+)/", $trimmedLine, $matches)) {
                    $duration = (float)$matches[1];
                    $maxDuration = max($maxDuration, $duration);
                    $currentExtinf = $trimmedLine;
                    continue;
                }
                
                // Collecter l'URL du segment (.ts)
                if (preg_match("/\\.ts$/", $trimmedLine)) {
                    if ($currentExtinf !== null) {
                        // Convertir URL relative en absolue si nécessaire
                        $segmentUrl = $trimmedLine;
                        if (!preg_match("/^https?:\/\//", $segmentUrl)) {
                            $segmentUrl = "https://tv.embmission.com/webtv-live/streams/" . $segmentUrl;
                        }
                        
                        $allSegments[] = [
                            'extinf' => $currentExtinf,
                            'url' => $segmentUrl,
                            'program_date_time' => $currentProgramDateTime
                        ];
                        
                        $currentExtinf = null;
                        $currentProgramDateTime = null;
                    }
                    continue;
                }
                
                // Conserver les autres lignes d'en-tête importantes
                if (strpos($trimmedLine, "#EXT-X-INDEPENDENT-SEGMENTS") === 0 ||
                    strpos($trimmedLine, "#EXT-X-PLAYLIST-TYPE") === 0) {
                    $headerLines[] = $trimmedLine;
                }
            }
            
            // ✅ PRENDRE LES 15 DERNIERS SEGMENTS (les plus récents)
            $totalSegments = count($allSegments);
            
            if ($totalSegments === 0) {
                Log::error("❌ Aucun segment trouvé dans le M3U8");
                return "";
            }
            
            $startIndex = max(0, $totalSegments - $maxSegments);
            $selectedSegments = array_slice($allSegments, $startIndex);
            
            // Calculer MEDIA-SEQUENCE à partir du premier segment sélectionné
            $mediaSequence = $startIndex;
            
            // Recalculer TARGETDURATION correct (max 30 secondes pour compatibilité)
            // Calculer maxDuration uniquement sur les segments sélectionnés
            $maxDuration = 0;
            foreach ($selectedSegments as $segment) {
                if (preg_match("/^#EXTINF:([\\d.]+)/", $segment['extinf'], $matches)) {
                    $duration = (float)$matches[1];
                    $maxDuration = max($maxDuration, $duration);
                }
            }
            
            if ($maxDuration <= 0) {
                $targetDuration = 11;
            } else {
                $targetDuration = min(30, max(11, (int)ceil($maxDuration)));
            }
            
            // ✅ RECONSTRUIRE LE M3U8 AVEC LES SEGMENTS LES PLUS RÉCENTS
            $normalizedContent = "#EXTM3U\n";
            $normalizedContent .= "#EXT-X-VERSION:3\n";
            $normalizedContent .= "#EXT-X-TARGETDURATION:" . $targetDuration . "\n";
            $normalizedContent .= "#EXT-X-MEDIA-SEQUENCE:" . $mediaSequence . "\n";
            
            // Ajouter les autres lignes d'en-tête
            foreach ($headerLines as $headerLine) {
                if (strpos($headerLine, "#EXTM3U") !== 0) {
                    $normalizedContent .= $headerLine . "\n";
                }
            }
            
            // Ajouter les segments sélectionnés (les plus récents)
            foreach ($selectedSegments as $segment) {
                if ($segment['program_date_time'] !== null) {
                    $normalizedContent .= $segment['program_date_time'] . "\n";
                }
                $normalizedContent .= $segment['extinf'] . "\n";
                $normalizedContent .= $segment['url'] . "\n";
            }
            
            Log::info("✅ M3U8 normalisé", [
                "total_segments" => $totalSegments,
                "selected_segments" => count($selectedSegments),
                "start_index" => $startIndex,
                "media_sequence" => $mediaSequence,
                "max_duration" => $maxDuration,
                "target_duration" => $targetDuration,
                "version" => "3"
            ]);
            
            return $normalizedContent;
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur normalisation M3U8: " . $e->getMessage());
            return "";
        }
    }

    /**
     * Générer un fichier HLS pour VoD synchronisé (LEGACY - gardé pour fallback)
     */
    private function generateVoDHLS(array $status): Response
    {
        try {
            $videoUrl = $status['url'] ?? null;
            $currentTime = $status['current_time'] ?? 0;
            $duration = $status['duration'] ?? 0;
            $title = $status['item_title'] ?? 'EMB Mission TV';

            if (!$videoUrl) {
                return $this->generateErrorHLS("URL vidéo manquante");
            }

            $m3u8Content = $this->createM3U8Content($videoUrl, $duration, $title);

            Log::info("✅ HLS VoD généré", [
                'video_url' => $videoUrl,
                'duration' => $duration,
                'current_time' => $currentTime
            ]);

            return response($m3u8Content, 200, [
                'Content-Type' => 'application/x-mpegURL',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Served-By' => 'Laravel-UnifiedStream',
                // Les en-têtes CORS sont gérés par le middleware HandleCors de Laravel
            ]);
        } catch (\Exception $e) {
            Log::error("❌ Erreur génération HLS VoD : " . $e->getMessage());
            return $this->generateErrorHLS("Erreur génération VoD");
        }
    }

    /**
     * Créer le contenu M3U pour VoD - FORMAT SIMPLE COMPATIBLE VLC
     */
    private function createM3U8Content(string $videoUrl, float $duration, string $title): string
    {
        try {
            // 🎯 RÉCUPÉRER LA SYNCHRONISATION GLOBALE
            $syncPosition = $this->autoPlaylistService->getCurrentSyncPosition();
            
            if (!$syncPosition['success']) {
                // Fallback : M3U simple avec une vidéo
                return $this->createSimpleM3U($videoUrl, $duration, $title);
            }
            
            // 🎯 RÉCUPÉRER LA PLAYLIST ACTIVE
            $activePlaylist = \App\Models\WebTVPlaylist::where('is_active', true)->first();
            
            if (!$activePlaylist) {
                return $this->createSimpleM3U($videoUrl, $duration, $title);
            }
            
            // 🎯 RÉCUPÉRER LA SÉQUENCE COMPLÈTE
            $playlistSequence = $this->getPlaylistSequence($activePlaylist);
            
            if (empty($playlistSequence['items'])) {
                return $this->createSimpleM3U($videoUrl, $duration, $title);
            }
            
            // 🎯 GÉNÉRER M3U SIMPLE AVEC TOUTE LA PLAYLIST
            return $this->createFullPlaylistM3U($playlistSequence, $syncPosition, $activePlaylist);
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur création M3U complet: " . $e->getMessage());
            return $this->createSimpleM3U($videoUrl, $duration, $title);
        }
    }

    /**
     * Créer un M3U simple avec toute la playlist (compatible VLC)
     */
    private function createFullPlaylistM3U(array $playlistSequence, array $syncPosition, $activePlaylist): string
    {
        $items = $playlistSequence['items'];
        $currentItemId = $syncPosition['current_item']['item_id'];
        $isLoopEnabled = $activePlaylist->is_loop;
        
        // Trouver l'index de la vidéo actuelle
        $currentIndex = 0;
        foreach ($items as $index => $item) {
            if ($item['item_id'] == $currentItemId) {
                $currentIndex = $index;
                break;
            }
        }
        
        // Créer le M3U simple
        $m3u = "#EXTM3U\n";
        
        // 🔄 AJOUTER LES VIDÉOS SELON LE FLAG LOOP
        $totalItems = count($items);
        
        if ($isLoopEnabled) {
            // 🔁 LOOP ACTIVÉ : Toute la playlist en boucle
            for ($i = 0; $i < $totalItems; $i++) {
                $itemIndex = ($currentIndex + $i) % $totalItems;
                $item = $items[$itemIndex];
                
                $videoUrl = "https://tv.embmission.com/webtv-live/streams/" . $item['ant_media_item_id'];
                $m3u .= "#EXTINF:" . ceil($item['duration']) . "," . $item['title'] . "\n";
                $m3u .= $videoUrl . "\n";
            }
        } else {
            // 🛑 LOOP DÉSACTIVÉ : Seulement jusqu'à la fin
            for ($i = 0; $i < ($totalItems - $currentIndex); $i++) {
                $itemIndex = $currentIndex + $i;
                $item = $items[$itemIndex];
                
                $videoUrl = "https://tv.embmission.com/webtv-live/streams/" . $item['ant_media_item_id'];
                $m3u .= "#EXTINF:" . ceil($item['duration']) . "," . $item['title'] . "\n";
                $m3u .= $videoUrl . "\n";
            }
        }
        
        Log::info("✅ M3U playlist complète générée", [
            'total_items' => $totalItems,
            'current_item_id' => $currentItemId,
            'current_index' => $currentIndex,
            'is_loop_enabled' => $isLoopEnabled
        ]);
        
        return $m3u;
    }

    /**
     * M3U simple (fallback)
     */
    private function createSimpleM3U(string $videoUrl, float $duration, string $title): string
    {
        $m3u = "#EXTM3U\n";
        $m3u .= "#EXTINF:" . ceil($duration) . ",{$title}\n";
        $m3u .= $videoUrl . "\n";
        
        return $m3u;
    }

    /**
     * Récupérer la séquence de playlist (avec shuffle)
     */
    private function getPlaylistSequence(\App\Models\WebTVPlaylist $playlist): array
    {
        $cacheKey = "webtv_playlist_sequence_{$playlist->id}";
        
        return Cache::remember($cacheKey, 300, function() use ($playlist) {
            $items = $playlist->items()
                ->where('sync_status', 'synced')
                ->orderBy('order')
                ->get();
            
            // 🎲 APPLIQUER LE SHUFFLE SI ACTIVÉ
            if ($playlist->shuffle_enabled) {
                $originalSeed = mt_rand();
                mt_srand($playlist->id);
                $items = $items->shuffle();
                mt_srand($originalSeed);
            }
            
            $sequence = [];
            $totalDuration = 0;
            
            foreach ($items as $item) {
                if ($item->ant_media_item_id) {
                    // Calculer la durée via ffprobe
                    $symlinkPath = "/usr/local/antmedia/webapps/LiveApp/streams/" . $item->ant_media_item_id;
                    $duration = 0;
                    
                    if (file_exists($symlinkPath)) {
                        $command = "ffprobe -v quiet -show_entries format=duration -of csv=p=0 " . escapeshellarg($symlinkPath);
                        $durationRaw = trim(shell_exec($command));
                        if (is_numeric($durationRaw) && $durationRaw > 0) {
                            $duration = (float) $durationRaw;
                        }
                    }
                    
                    if ($duration > 0) {
                        $sequence[] = [
                            'item_id' => $item->id,
                            'title' => $item->title,
                            'ant_media_item_id' => $item->ant_media_item_id,
                            'duration' => $duration,
                            'start_time' => $totalDuration,
                            'end_time' => $totalDuration + $duration
                        ];
                        
                        $totalDuration += $duration;
                    }
                }
            }
            
            return [
                'items' => $sequence,
                'total_duration' => $totalDuration,
                'count' => count($sequence)
            ];
        });
    }

    /**
     * Générer un HLS d’erreur
     */
    private function generateErrorHLS(string $message): Response
    {
        $errorM3u8 = "#EXTM3U\n";
        $errorM3u8 .= "#EXT-X-VERSION:3\n";
        $errorM3u8 .= "#EXT-X-TARGETDURATION:10\n";
        $errorM3u8 .= "#EXT-X-MEDIA-SEQUENCE:0\n";
        $errorM3u8 .= "#EXT-X-PLAYLIST-TYPE:VOD\n";
        $errorM3u8 .= "#EXTINF:10,Erreur\n";
        $errorM3u8 .= "# " . $message . "\n";
        $errorM3u8 .= "#EXT-X-ENDLIST\n";

        return response($errorM3u8, 200, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'no-cache',
            'X-Served-By' => 'Laravel-UnifiedStream',
            // Les en-têtes CORS sont gérés par le middleware HandleCors de Laravel
        ]);
    }
}
