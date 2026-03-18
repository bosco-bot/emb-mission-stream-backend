# Liste détaillée des fonctionnalités – EMB Mission

Document généré pour analyse du projet. Pour chaque fonctionnalité : nom, module principal, interactions, type, conditions/limitations connues.

---

## 1. Authentification et utilisateurs

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 1.1 | Connexion / Inscription (web) | `Controller_login_register`, routes `auth/login`, `auth/register` | Sessions web, vues login/register | Frontend + Backend | CSRF requis sur les formulaires |
| 1.2 | API Auth (register, login, logout, forgot/reset password) | `Api/RegisterController`, routes `api/auth/*` | Sanctum, modèle `User` | API | Compatible Flutter (Sanctum) |
| 1.3 | Récupération utilisateur courant (API) | `GET /api/auth/me` (closure dans `api.php`) | Middleware `auth:sanctum`, modèle `User` | API | Retourne 401 si non authentifié |
| 1.4 | Validation email / mot de passe en temps réel (AJAX) | `Controller_login_register`: `checkEmail`, `checkPasswordStrength` | Routes `auth/check-email`, `auth/check-password` | Frontend + Backend | Utilisé sur les formulaires d’inscription |

---

## 2. WebTV – Flux unifié (Live + VOD)

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 2.1 | Endpoint HLS unifié (unified.m3u8) | `UnifiedStreamController::getUnifiedHLS`, route `GET /api/stream/unified.m3u8` | `WebTVAutoPlaylistService::getCurrentPlaybackContext`, `UnifiedHlsBuilder`, Ant Media (fichiers .m3u8) | API + Backend | Nginx doit router `/hls/streams/unified.m3u8` vers Laravel. CORS géré par middleware |
| 2.2 | Mode Live (service direct) | `UnifiedStreamController::serveLivePlaylistDirectly` | Contexte `mode=live`, fichiers Ant Media `{streamId}.m3u8`, `UnifiedHlsBuilder` (métadonnées, DISCONTINUITY) | Backend | Streams candidats : `live_transcoded`, `R9rzvVzMPvCClU6s1562847982308692` |
| 2.3 | Mode VOD (playlist unifiée générée) | `UnifiedStreamController::serveUnifiedPlaylist` | Fichier `/usr/local/antmedia/.../unified.m3u8`, généré par `UnifiedHlsBuilder` | Backend | Si fichier absent ou placeholder live, régénération à la volée |
| 2.4 | Mode Pause (système suspendu) | `UnifiedStreamController` (branche `mode === 'paused'`) | Cache `webtv_system_paused`, `UnifiedHlsBuilder::writePausedPlaylist` | Backend | Playlist minimale sans segments ; bandes noires côté lecteur |
| 2.5 | Génération playlist HLS unifiée (Live/VOD/Pause) | `UnifiedHlsBuilder` | `WebTVAutoPlaylistService`, Cache (séquence, mode), fichiers Ant Media, stockage `unified.m3u8` | Traitement automatique + Backend | Transition Live↔VOD avec DISCONTINUITY et MEDIA-SEQUENCE |
| 2.6 | Contexte de lecture (mode live/vod/paused, item courant) | `WebTVAutoPlaylistService::getCurrentPlaybackContext` | `checkLiveStatus`, playlists WebTV, séquence VOD, sync position, cache `playback_context_v2_*` | Backend | Cache ~2s ; invalidation par webhook Ant Media |
| 2.7 | Détection live (Ant Media) | `WebTVAutoPlaylistService::checkLiveStatus`, `probeLiveStatus` | API REST Ant Media (broadcasts), cache 5s, hystérésis 10s, fichiers .m3u8 sur disque | Backend | Timeout 1.5s ; fallback sur fichier HLS si API en erreur |
| 2.8 | Webhook Ant Media (démarrage/fin de stream) | `AntMediaWebhookController::handle` | Cache `live_status_*`, `playback_context_v2_*` | API (POST) | Route à enregistrer (ex. `POST /api/webhooks/antmedia`). Sécurité : localhost uniquement recommandé |
| 2.9 | Avancement position de lecture (sync chaîne TV) | Commande `webtv:advance-sync-position`, `WebTVAutoPlaylistService` (sync position) | Fichiers JSON `webtv_sync_position_{playlistId}`, playlist active, séquence VOD | Traitement automatique (cron 1 min) | Dépend de l’heure et de la durée des items |
| 2.10 | Remux VOD en attente | Commande `unified-stream:remux-pending`, job/commande | Playlist items (HLS), Ant Media, `UnifiedHlsBuilder` | Traitement automatique (cron 5 min) | Limite configurable (`--limit`) |
| 2.11 | Liaison items pending ↔ HLS pré-convertis | Commande `webtv:link-preconverted` | Playlist items, fichiers HLS pré-générés | Traitement automatique (cron 2 min) | `--limit=100` par défaut |

---

## 3. WebTV – Playlists et contrôle

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 3.1 | CRUD playlists WebTV | `WebTVPlaylistController`, routes `webtv-playlists/*` | Modèles `WebTVPlaylist`, `WebTVPlaylistItem` | API | Une playlist active à la fois (is_active) |
| 3.2 | CRUD items de playlist WebTV | `WebTVPlaylistItemController` | `WebTVPlaylist`, Ant Media (VOD), sync statut | API | Statuts sync : pending, synced, error |
| 3.3 | Sync playlist → Ant Media | `WebTVPlaylistController::syncWithAntMedia` | Ant Media REST, items, VOD | API | Peut être long ; à appeler après ajout/modification |
| 3.4 | Playlist automatique (start/stop/resume) | `WebTVAutoPlaylistController` (startAutoPlaylist, stopAutoPlaylist, resumeAutoPlaylist) | `WebTVAutoPlaylistService::pauseSystem`, `resumeSystem`, cache `webtv_system_paused` | API | Stop = mise en pause globale (live + VOD) |
| 3.5 | URL de lecture courante / statut (current-url, status) | `WebTVAutoPlaylistController::getCurrentPlaybackUrl`, `getAutoPlaylistStatus` | `WebTVAutoPlaylistService::getCurrentPlaybackContext`, snapshot temps réel | API | Utilisé par la page Watch et l’app Flutter |
| 3.6 | Paramètres OBS / URL stream public | `WebTVAutoPlaylistController::getOBSConnectionParams`, `getPublicStreamUrl` | Config app, Ant Media | API | Pour configurer OBS et liens publics |
| 3.7 | Vérification sync WebTV (commande) | Commande `webtv:check-sync` | Playlist items, Ant Media, alertes | Traitement automatique (cron 1 h) | Options `--fix`, `--alert` |
| 3.8 | Surveillance des streams / broadcasts récents | `WebTVController` (getStreams, getRecentBroadcasts, getStreamStatus, checkConnection, getEmbedCode) | Ant Media REST | API | Lecture seule |

---

## 4. Page Watch (lecteur Web)

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 4.1 | Page Watch (lecteur unique) | Vue `watch.blade.php`, `resources/js/watch-page.js`, `resources/css/watch.css` | API `current-url`, SSE `webtv/live/status/stream`, flux `/hls/streams/unified.m3u8`, Hls.js, Reverb (Echo) | Frontend | Un seul flux (unified.m3u8) ; fond noir type YouTube pour bandes |
| 4.2 | Polling / SSE statut live | `watch-page.js` (fetchStatus, initSSE) | `GET /api/webtv-auto-playlist/current-url`, `GET /api/webtv/live/status/stream` | Frontend | Bascule automatique live/VOD/pause selon la réponse |
| 4.3 | Affichage mode Pause | `watch-page.js` (onStatus, mode paused) | Message d’erreur « Diffusion en pause » | Frontend | Détruit le lecteur HLS et affiche le message |

---

## 5. Statistiques WebTV / WebRadio et analytics

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 5.1 | Stats unifiées (WebTV + WebRadio) | `UnifiedStatsController::getAllStats` | GA4 (GA4DataService), Ant Media (audience), AzuraCast, cache | API | Cache ; clé selon plage de jours |
| 5.2 | Données GA4 (événements, pays, appareils) | `GA4DataService` | Google Analytics Data API, credentials JSON, propriétés GA4 Radio/TV | Backend | Nécessite `GA4_CREDENTIALS_PATH`, `GA4_PROPERTY_ID_RADIO/TV` ; quota API |
| 5.3 | Stats WebTV (audience, durée, vues, engagement) | `WebTVStatsController` | Ant Media, logs, modèles/DB si présents | API | Endpoints de compatibilité |
| 5.4 | Mise à jour des stats (commande) | Commande `stats:update` | GA4, Ant Media, AzuraCast, DB stats | Traitement automatique (cron 1 h) | À exécuter régulièrement pour données à jour |
| 5.5 | Tracking écoute radio (listen) | `ListenTrackingController::track` | GA4 (Measurement Protocol), IP, User-Agent | API (POST) | Route `/api/track-listen` ; envoi événement `stream_access` |

---

## 6. WebRadio / AzuraCast

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 6.1 | Playlists radio (CRUD, sync AzuraCast) | `PlaylistController`, `PlaylistItemController` | Modèles `Playlist`, `PlaylistItem`, `MediaFile`, `AzuraCastSyncService` | API | Sync peut déclencher jobs (SyncPlaylistToAzuraCast, etc.) |
| 6.2 | Sync vers AzuraCast (playlist, M3U, restart) | `PlaylistController::syncToAzuraCast`, `updateM3UAndRestart`, `PlaylistSyncController` | AzuraCast (API/Docker), jobs `SyncPlaylistToAzuraCast`, `UpdateM3UAndRestartJob`, `FinalizeAzuraCastSync` | API + Jobs | Dépend d’AzuraCast opérationnel |
| 6.3 | Upload / ajout médias vers AzuraCast | `AzuraCastUploadController`, `AzuraCastUploadService` | AzuraCast API, playlists | API | Fichiers audio |
| 6.4 | Contrôle broadcast WebRadio (current-stream, track-history, control) | `WebRadioController` | AzuraCast ou proxy radio | API | Selon config backend radio |
| 6.5 | Stream radio (piste courante, historique) | `RadioStreamController` | Source radio (AzuraCast ou autre) | API | Routes `/api/radio/stream/current`, `track/current`, `stream/history` |
| 6.6 | Paramètres streaming radio (clé, test connexion) | `RadioStreamingController` | Config, génération clé | API | Génération de clé pour flux |
| 6.7 | Mixxx (start/stop/status/settings) | `MixxxController` | Logiciel Mixxx (si utilisé) | API | Dépend de l’installation Mixxx |
| 6.8 | Fichier M3U pour WebRadio | Closure dans `web.php` (`/playlist.m3u`) | URL stream radio, tracking GA4 (m3u_playlist_download) | Backend + Analytics | Téléchargement fichier + envoi GA4 si configuré |

---

## 7. Médias (bibliothèque, upload, conversion)

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 7.1 | Liste / détail / statut des fichiers média | `MediaController` (index, show, getImportingFiles, getErrorFiles, getCompletedFiles, getConversionStatus) | Modèle `MediaFile`, filesystem, jobs de conversion | API | Filtres par type (audio/video/image) |
| 7.2 | Upload (session, simple, multiple, chunks) | `MediaController` (createUploadSession, uploadFile, uploadMultipleFiles, uploadChunk, finalizeChunkUpload, cancelChunkUpload) | `UploadSession`, `MediaFile`, stockage | API | Chunks pour gros fichiers |
| 7.3 | Annulation / retry import | `MediaController::cancelImport`, `retryImport` | Jobs, `MediaFile` | API | Selon état du fichier |
| 7.4 | Traitement des fichiers média (import, conversion) | Job `ProcessMediaFile` | `MediaMetadataService`, FFmpeg, Ant Media (VOD), queue | Traitement automatique (queue) | Dépend de la queue Laravel |
| 7.5 | Conversion vidéo vers HLS | Job `ConvertVideoToHls` | FFmpeg, Ant Media, stockage HLS | Traitement automatique (queue) | Peut être long pour gros fichiers |
| 7.6 | Relance des conversions vidéo échouées | Job `RetryFailedVideoConversions` | Fichiers récents (ex. 48h), queue | Traitement automatique (cron 15 min) | Limité dans le temps pour perfs |
| 7.7 | Statistiques bibliothèque | `MediaLibraryController::getStats` | `MediaFile`, agrégations | API | Vue d’ensemble |

---

## 8. Ant Media (VOD, streams)

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 8.1 | Services Ant Media (REST, VOD, playlists) | `AntMediaService`, `AntMediaVoDService`, `AntMediaPlaylistService` | Ant Media Server (REST API), fichiers VOD | Backend | Credentials et URL Ant Media dans config |
| 8.2 | Création VOD / remux | Jobs `CreateVodStreamJob`, `ShakaRemuxJob` ; commandes Remux | Ant Media, HLS, Shaka (si utilisé) | Traitement automatique | Dépend de la disponibilité Ant Media |

---

## 9. Monitoring système

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 9.1 | Tableau de bord monitoring | `MonitoringController::index`, vue `admin/monitoring` | Routes `system-monitoring/*` | Frontend + Backend | Page publique (pas de middleware auth par défaut) |
| 9.2 | Statut global (services, workers, jobs, disque, versions, logs, etc.) | `MonitoringController::getStatus` | systemctl (services), filesystem (disque), PHP/MySQL versions, `storage/links`, `laravel.log` | API (GET) | Nécessite droits lecture sur le serveur ; PHP 8.2-fpm pour le service php-fpm |
| 9.3 | Redémarrage / arrêt de services | `MonitoringController::restartService`, `stopService` | systemctl (nginx, php8.2-fpm, mysql, antmedia, ffmpeg-live-transcode, queue-worker, unified-stream, reverb, supervisor, etc.) | API (POST) | Sudoers doit autoriser www-data pour `systemctl restart/stop/start` |
| 9.4 | Relance jobs en échec (un / tous) | `MonitoringController::retryJob`, `retryAllFailedJobs` | Table `failed_jobs`, Artisan `queue:retry` | API (POST) | IDs de jobs valides |
| 9.5 | Suspendre / Reprendre WebTV depuis le monitoring | Boutons + appels `POST /api/webtv-auto-playlist/stop` et `resume` | Cache `webtv_system_paused`, `WebTVAutoPlaylistService` | Frontend + API | Affiche bouton selon `webtv_system_paused` dans getStatus |
| 9.6 | Dernières erreurs Laravel (filtrées) | `MonitoringController::getLaravelLogErrors` | Fichier `storage/logs/laravel.log` | Backend | Filtre niveau ERROR ; exclut messages getDiskSpace résolus |

---

## 10. Diffusion en direct (Live) et embed

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 10.1 | Flux SSE statut live/VOD | `LiveStatusController::stream`, `LiveStatusControllerNew::stream` | `WebTVAutoPlaylistService`, contexte lecture | API (SSE) | Utilisé par la page Watch pour mises à jour temps réel |
| 10.2 | Embed YouTube (chaînes partenaires) | `EmbedController`, route `GET /embed/{videoId?}` | Paramètre video ID, vue embed | Frontend + Backend | Optionnel ; si pas d’ID, comportement à définir |

---

## 11. File d’attente et jobs

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 11.1 | Sync playlist → AzuraCast | Job `SyncPlaylistToAzuraCast` | Playlist, items, `AzuraCastSyncService` | Traitement automatique (queue) | Déclenché par les actions de sync |
| 11.2 | Finalisation sync AzuraCast | Job `FinalizeAzuraCastSync` | IDs AzuraCast, PlaylistItem | Traitement automatique (queue) | Après sync principal |
| 11.3 | Mise à jour M3U et redémarrage | Job `UpdateM3UAndRestartJob` | Fichier M3U, AzuraCast (Docker) | Traitement automatique (queue) | Dépend d’AzuraCast |
| 11.4 | Upload vers AzuraCast | Job `UploadToAzuraCast` | Fichier, AzuraCast API | Traitement automatique (queue) | Fichiers audio |
| 11.5 | Autres jobs (ProcessMediaFile, ConvertVideoToHls, RetryFailedVideoConversions, etc.) | `app/Jobs/*` | Voir sections Médias et Ant Media | Traitement automatique | Queue worker doit tourner (supervisor/systemd) |

---

## 12. Base de données et modèles

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 12.1 | Utilisateurs | Modèle `User`, migrations users | Sanctum, auth web/API | Base de données | Champs role, is_active, avatar, etc. |
| 12.2 | Playlists et items (radio) | Modèles `Playlist`, `PlaylistItem` | MediaFile, AzuraCast IDs | Base de données | Sync avec AzuraCast |
| 12.3 | Playlists et items WebTV | Modèles `WebTVPlaylist`, `WebTVPlaylistItem` | Ant Media (VOD), sync_status, unique_id | Base de données | Une playlist active ; items avec durée, ordre |
| 12.4 | Fichiers média | Modèle `MediaFile` | UploadSession, relations, AzuraCast | Base de données | États import/conversion |
| 12.5 | Sessions d’upload | Modèles `UploadSession`, `UploadSessionFile` | Upload chunks | Base de données | Pour uploads volumineux |
| 12.6 | Sources radio / stats | Modèles `RadioSource`, `WebTVStats`, `WebRadioStats` | Config, statistiques | Base de données | Selon migrations et usage |
| 12.7 | Jobs échoués | Table `failed_jobs` (Laravel) | Queue, retry depuis monitoring | Base de données | Nettoyage manuel ou commande |

---

## 13. Broadcast et temps réel

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 13.1 | Canal WebTV (statut stream) | `routes/channels.php` : canal `webtv-stream-status` | Reverb, Echo (frontend) | Backend | Canal public ; utilisé pour notifications temps réel |
| 13.2 | Canal utilisateur (Sanctum) | `channels.php` : `App.Models.User.{id}` | Auth | Backend | Pour notifications par utilisateur |

---

## 14. Sécurité et CORS

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 14.1 | CORS API | `bootstrap/app.php` (middleware HandleCors sur api et web) | Toutes les routes /api/* | Backend | Exemption CSRF pour `api/*` |
| 14.2 | Sanctum (API auth) | Middleware `auth:sanctum`, `EnsureFrontendRequestsAreStateful` | Routes protégées, cookies | Backend | Compatible SPA et Flutter |

---

## 15. Autres commandes et utilitaires

| # | Fonctionnalité | Module / Fichier principal | Interactions / Dépendances | Type | Conditions / Limitations |
|---|----------------|----------------------------|----------------------------|------|---------------------------|
| 15.1 | Restauration source audio depuis AzuraCast | Commande `RestoreAudioSourceFromAzuraCast` | AzuraCast, sources | Traitement automatique / CLI | À lancer manuellement si besoin |
| 15.2 | Restauration source vidéo depuis VOD | Commande `RestoreVideoSourceFromVod` | Ant Media VOD | Traitement automatique / CLI | Idem |
| 15.3 | Diagnostic sources manquantes | Commande `DiagnoseSourceMissing` | Playlists, médias | CLI | Diagnostic |
| 15.4 | Mise à jour durées des médias | Commande `UpdateMediaFileDurations` | MediaFile, métadonnées | CLI | Met à jour les durées en DB |
| 15.5 | Nettoyage chunks upload | Commande `CleanOldChunks` | Stockage chunks | Traitement automatique / CLI | Évite accumulation disque |
| 15.6 | Nettoyage médias orphelins | Commande `CleanOrphanMediaFiles` | MediaFile, filesystem | CLI | À lancer avec précaution |
| 15.7 | Préconversion médias (daemon) | Commande `MediaPreconvertDaemon` | File d’attente, conversion | Traitement automatique | Optionnel, selon déploiement |
| 15.8 | Test flux unifié | Commande `RunUnifiedStreamTest` | UnifiedStreamController, contexte | CLI | Tests |
| 15.9 | Tracker d’écoute (analytics) | Commande `AnalyticsListeningTracker` | Données d’écoute | Traitement automatique / CLI | Selon configuration |

---

## Résumé des types

- **Frontend** : pages Watch, Player, Login/Register, Dashboard, Monitoring (vue).
- **Backend** : services, logique métier, contexte lecture, génération HLS, cache.
- **API** : routes REST sous `/api/*` (auth, radio, webtv, media, playlists, stats, monitoring, track-listen, etc.).
- **Base de données** : modèles User, Playlist, PlaylistItem, MediaFile, WebTVPlaylist, WebTVPlaylistItem, UploadSession, etc.
- **Traitement automatique** : cron (console.php), jobs (queue), commandes Artisan (remux, sync, stats, retry conversions, etc.).

---

*Document généré à partir de l’analyse du dépôt EMB Mission. À mettre à jour en cas d’ajout ou de suppression de fonctionnalités.*
