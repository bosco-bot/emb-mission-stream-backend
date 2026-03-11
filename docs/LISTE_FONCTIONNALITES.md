# Liste détaillée des fonctionnalités – Projet EMB Mission

Ce document recense les fonctionnalités implémentées dans le projet. Pour chaque entrée sont indiqués : le nom ou une description concise, le module/fichier principal, les interactions ou dépendances, le type de fonctionnalité, et les conditions ou limitations connues.

---

## 1. Flux HLS unifié (Live + VoD sur une seule URL)

| Critère | Détail |
|--------|--------|
| **Nom / description** | Un seul flux HLS (`unified.m3u8`) qui affiche soit le direct (Ant Media), soit la playlist VoD automatique, avec transitions propres (DISCONTINUITY, MEDIA-SEQUENCE) et mode « pause » (playlist minimale). |
| **Module / fichier principal** | `app/Http/Controllers/Api/UnifiedStreamController.php`, `app/Services/UnifiedHlsBuilder.php`, `app/Services/WebTVAutoPlaylistService.php`. Route : `GET /api/stream/unified.m3u8` (et réécriture Nginx pour `/hls/streams/unified.m3u8`). |
| **Interactions / dépendances** | Ant Media (lecture des `.m3u8`, validation des segments sur disque), cache Laravel (dernier mode, MEDIA-SEQUENCE), fichiers sur disque dans `/usr/local/antmedia/webapps/LiveApp/streams/`, `WebTVAutoPlaylistService::getCurrentPlaybackContext()`. |
| **Type** | Backend (Laravel), API HLS (réponse M3U8), intégration Ant Media, traitement à la volée. |
| **Conditions / limitations** | Chemins Ant Media en dur côté serveur ; en mode pause une playlist minimale (sans segment) est écrite puis servie. |

---

## 2. Playlist automatique WebTV (timeline VoD)

| Critère | Détail |
|--------|--------|
| **Nom / description** | Enchaînement automatique des vidéos de la playlist active (ordre, boucle, shuffle optionnel), avec une timeline globale et un « item courant » pour la lecture et la génération HLS. |
| **Module / fichier principal** | `app/Services/WebTVAutoPlaylistService.php`, `app/Http/Controllers/Api/WebTVAutoPlaylistController.php`. Modèles : `WebTVPlaylist`, `WebTVPlaylistItem`. Routes : préfixe `api/webtv-auto-playlist`. |
| **Interactions / dépendances** | Base de données (playlists, items, is_active, shuffle_enabled, is_loop, durées), cache (`playback_context_v2_*`, `webtv_system_paused`, `live_status_*`), `UnifiedHlsBuilder`, fichiers de position de sync. |
| **Type** | Backend, API JSON, logique métier (timeline, boucle, shuffle), base de données. |
| **Conditions / limitations** | Pause globale via cache `webtv_system_paused` ; dépend de la cohérence des durées et timestamps des items. |

---

## 3. Détection du direct (passage automatique Live / VoD)

| Critère | Détail |
|--------|--------|
| **Nom / description** | Détection en temps (quasi) réel qu’un stream Ant Media est en direct ; le contexte passe alors en « live » pour que le flux unifié serve le direct au lieu du VoD. |
| **Module / fichier principal** | `app/Services/WebTVAutoPlaylistService.php` : `checkLiveStatus()`, `probeLiveStatus()`, `isHlsManifestReady()`. |
| **Interactions / dépendances** | API REST Ant Media (`GET /LiveApp/rest/v2/broadcasts/{streamId}`), fichiers HLS sur disque, cache Laravel (`live_status_check`, `live_status_last_confirmed`, verrou, contenu manifest). |
| **Type** | Backend, intégration externe (Ant Media), traitement automatique (polling + cache). |
| **Conditions / limitations** | Liste de streams candidats en dur ; hystérésis (maintien du live quelques secondes si API en erreur tant que le manifest est à jour) ; timeouts courts sur les appels HTTP. |

---

## 4. Webhook Ant Media (démarrage / fin de live)

| Critère | Détail |
|--------|--------|
| **Nom / description** | Endpoint appelé par Ant Media lors du démarrage ou de l’arrêt d’un stream ; invalide les caches de statut live pour que la détection repasse immédiatement à jour. |
| **Module / fichier principal** | `app/Http/Controllers/Api/AntMediaWebhookController.php`. Route : `POST /api/webhooks/antmedia`. |
| **Interactions / dépendances** | Ant Media (config listenerHookURL), cache Laravel (`live_status_check`, `live_status_last_confirmed`, clés `playback_context_v2_*`). |
| **Type** | Backend, API webhook, intégration externe. |
| **Conditions / limitations** | Accès restreint à localhost ; Nginx doit bloquer l’accès externe. Ant Media doit être configuré pour envoyer les actions (liveStreamStarted, liveStreamEnded, etc.). |

---

## 5. Page Watch (lecteur vidéo web)

| Critère | Détail |
|--------|--------|
| **Nom / description** | Page web dédiée au visionnage : un lecteur vidéo HLS (un flux unique `unified.m3u8`), avec statut (live / vod / paused), messages d’erreur et de chargement, fond sombre type YouTube pour les bandes. |
| **Module / fichier principal** | `resources/views/watch.blade.php`, `resources/js/watch-page.js`, `resources/css/watch.css`. Assets compilés : `public/build/assets/watch-page-*.js`, `watch-*.css`. |
| **Interactions / dépendances** | API `current-url`, flux `unified.m3u8`, SSE `live/status/stream`, HLS.js, Laravel Echo / Reverb. |
| **Type** | Frontend (Blade, CSS, JavaScript), consommation d’API et de flux HLS. |
| **Conditions / limitations** | `object-fit: contain` : toute l’image visible ; sur écrans très larges, bandes sur les côtés (couleur #0f0f0f). Fallback polling si SSE/WebSocket indisponible. |

---

## 6. Tableau de bord Monitoring système

| Critère | Détail |
|--------|--------|
| **Nom / description** | Page d’administration : état des services (systemd), workers Laravel, jobs (en attente / en échec), disque, versions, liens de stockage, dernières erreurs Laravel, tâches cron, jobs audio, alertes RTMP ; boutons Redémarrer/Arrêter services et Suspendre/Reprendre WebTV. |
| **Module / fichier principal** | `app/Http/Controllers/Admin/MonitoringController.php`, `resources/views/admin/monitoring.blade.php`. Routes : préfixe `system-monitoring`. |
| **Interactions / dépendances** | Systemd (restart/stop/start), BDD (`jobs`, `failed_jobs`), fichiers (logs, alerts), sudoers, API webtv-auto-playlist (stop, resume). |
| **Type** | Backend, API JSON, frontend (Blade + JS), intégration système. |
| **Conditions / limitations** | Liste des services en dur ; page accessible sans auth (à protéger en prod si besoin). Filtrage des erreurs Laravel (niveau ERROR, exclusion getDiskSpace). |

---

## 7. Service GA4 (Google Analytics 4)

| Critère | Détail |
|--------|--------|
| **Nom / description** | Service PHP qui interroge l’API Google Analytics Data (GA4) pour récupérer comptages d’événements, stats par pays, par appareil, utilisateurs actifs en temps réel. |
| **Module / fichier principal** | `app/Services/GA4DataService.php`. Utilisé par `UnifiedStatsController` et éventuellement d’autres contrôleurs. |
| **Interactions / dépendances** | Google Analytics Data API, fichier credentials JSON (`GA4_CREDENTIALS_PATH`), `.env` (GA4_PROPERTY_ID_RADIO, GA4_PROPERTY_ID_TV), Log Laravel. |
| **Type** | Backend, intégration externe (API Google), service réutilisable. |
| **Conditions / limitations** | Credentials et droits GA4 requis ; en cas d’erreur retour de valeurs par défaut (0 ou []) et log détaillé ; quotas API Google. |

---

## 8. API de statistiques unifiées (WebTV + WebRadio)

| Critère | Détail |
|--------|--------|
| **Nom / description** | Endpoints qui exposent des statistiques agrégées (vues, par pays, etc.) pour WebTV et WebRadio sur une période (ex. 30 jours), basées sur GA4. |
| **Module / fichier principal** | `app/Http/Controllers/Api/UnifiedStatsController.php`. Routes : `api/webtv/stats/all`, `stats/debug`, `stats/clear-cache`. |
| **Interactions / dépendances** | `GA4DataService`, cache Laravel. Consommée par le front (Flutter, etc.). |
| **Type** | Backend, API JSON, agrégation de données (GA4). |
| **Conditions / limitations** | En cas d’erreur GA4, stats vides ou partielles ; période et types d’événements définis dans le code. |

---

## 9. Tracking des écoutes radio (/listen)

| Critère | Détail |
|--------|--------|
| **Nom / description** | Endpoint appelé lorsqu’un client écoute la radio ; envoi d’un événement à GA4 (ex. stream_access) et enregistrement en log (IP, device, pays). |
| **Module / fichier principal** | `app/Http/Controllers/ListenTrackingController.php`. Route(s) dans `routes/web.php` (radio / listen / m3u). |
| **Interactions / dépendances** | GA4 (Measurement Protocol ou équivalent si `GA4_API_SECRET` défini), Log Laravel. |
| **Type** | Backend, API (tracking), intégration GA4. |
| **Conditions / limitations** | Sans `GA4_API_SECRET` le tracking GA4 est désactivé. Gestion des erreurs sans bloquer la réponse. |

---

## 10. Synchronisation AzuraCast (playlists audio)

| Critère | Détail |
|--------|--------|
| **Nom / description** | Synchronisation des playlists audio avec AzuraCast : envoi des fichiers, mise à jour M3U, scan, redémarrage du backend (Docker) pour appliquer les changements. |
| **Module / fichier principal** | `app/Services/AzuraCastSyncService.php`. Jobs : `SyncPlaylistToAzuraCast`, `FinalizeAzuraCastSync`, `UpdateM3UAndRestartJob`, `UploadToAzuraCast`. |
| **Interactions / dépendances** | Queue Laravel, AzuraCast (API / conteneurs), Docker / docker-compose, BDD playlists / items audio. |
| **Type** | Backend, jobs asynchrones (queue), intégration externe (AzuraCast, Docker). |
| **Conditions / limitations** | Dépend de la disponibilité d’AzuraCast et des conteneurs ; échecs possibles dans failed_jobs, relançables depuis le Monitoring. |

---

## 11. Relance des jobs en échec (depuis le Monitoring)

| Critère | Détail |
|--------|--------|
| **Nom / description** | Relance d’un job en échec (par ID) ou de tous les jobs en échec depuis l’interface Monitoring, via des appels API qui exécutent `queue:retry`. |
| **Module / fichier principal** | `MonitoringController::retryJob()`, `retryAllFailedJobs()`, `checkJobs()`. Vue : tableau des jobs en échec, boutons Relancer / Relancer tout. Routes : `POST system-monitoring/retry-job`, `retry-all-jobs`. |
| **Interactions / dépendances** | Table `failed_jobs`, `Artisan::call('queue:retry')`. |
| **Type** | Backend, API JSON, frontend (boutons), base de données (lecture failed_jobs). |
| **Conditions / limitations** | Relance = nouveau passage en queue ; si la cause persiste, le job peut ré-échouer. Pas de suppression des enregistrements failed_jobs ici. |

---

## 12. Application Flutter (WebTV, contrôle, stats)

| Critère | Détail |
|--------|--------|
| **Nom / description** | Application Flutter (web et/ou mobile) qui affiche la WebTV, permet de contrôler la diffusion (stop/resume) et d’afficher des stats, en appelant les APIs Laravel. |
| **Module / fichier principal** | `EMB-Mission_RTV-main/lib/features/webtv/view/webtv_player_page.dart`, `webtv_control_page.dart`, autres écrans dans `EMB-Mission_RTV-main/lib/features/`. |
| **Interactions / dépendances** | APIs Laravel (webtv-auto-playlist, webtv/stats, flux unified.m3u8), WebSocket / Reverb si utilisé. |
| **Type** | Frontend (Flutter, web/mobile), consommateur d’API. |
| **Conditions / limitations** | Hébergement et build Flutter séparés de Laravel ; URLs d’API possibles en dur ou configurées dans l’app. |

---

## 13. Script de test de connexion GA4

| Critère | Détail |
|--------|--------|
| **Nom / description** | Script PHP exécutable en CLI pour vérifier la configuration GA4 (credentials, property IDs) et tester les appels (getEventCount, getStatsByCountry, getActiveUsersRealTime). |
| **Module / fichier principal** | `test_ga4_connection.php` (bootstrap Laravel, lecture .env, instanciation GA4DataService, appels de test). |
| **Interactions / dépendances** | `.env` (GA4_*), fichier credentials JSON, `GA4DataService`, librairie Google. |
| **Type** | Script CLI, outil de diagnostic, intégration GA4. |
| **Conditions / limitations** | À lancer depuis la racine du projet ; utile pour vérifier droits (utilisateur vs www-data) et connectivité API. |

---

## 14. Vérification du routage du flux unifié

| Critère | Détail |
|--------|--------|
| **Nom / description** | Script et documentation pour vérifier que l’URL publique du flux unifié (`/hls/streams/unified.m3u8`) est bien servie par Laravel (en-tête X-Served-By: Laravel-UnifiedStream) et non par un autre service (Rust, fichier statique). |
| **Module / fichier principal** | `scripts/verify-unified-stream-routing.sh`, `docs/VERIFICATION_FLUX_UNIFIE.md`, `docs/PLAN_EXECUTION_ROUTAGE_UNIFIED_LARAVEL.md`, `nginx-snippet-unified-stream-laravel.conf`. |
| **Interactions / dépendances** | Nginx (location unified.m3u8, fastcgi_pass), Laravel (UnifiedStreamController), curl. |
| **Type** | Script shell, documentation, configuration serveur. |
| **Conditions / limitations** | À exécuter sur l’environnement cible (ou avec BASE_URL adapté) ; ne modifie pas la config, diagnostic uniquement. |

---

*Document généré pour le projet EMB Mission. Dernière mise à jour : à adapter selon les évolutions du dépôt.*
