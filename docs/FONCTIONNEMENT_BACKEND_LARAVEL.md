# Fonctionnement du backend Laravel EMB-MISSION

Document de référence fonctionnelle — backend Laravel déployé sur `/var/www/emb-mission`.

**Version :** commit `1e6c242`  
**Framework :** Laravel 11 · PHP 8.2 · MySQL  
**Domaines :** `rtv.embmission.com` · `tv.embmission.com` · `radio.embmission.com`

---

## Table des matières

1. [Rôle du backend](#1-rôle-du-backend)
2. [Architecture globale](#2-architecture-globale)
3. [Entrées HTTP (routes)](#3-entrées-http-routes)
4. [Authentification et sécurité](#4-authentification-et-sécurité)
5. [Module Médias](#5-module-médias)
6. [Module WebRadio (AzuraCast)](#6-module-webradio-azuracast)
7. [Module WebTV (Ant Media)](#7-module-webtv-ant-media)
8. [Flux HLS unifié (Live + VoD)](#8-flux-hls-unifié-live--vod)
9. [Statistiques et analytics](#9-statistiques-et-analytics)
10. [Monitoring système](#10-monitoring-système)
11. [Jobs, files d'attente et tâches planifiées](#11-jobs-files-dattente-et-tâches-planifiées)
12. [Base de données](#12-base-de-données)
13. [Stockage fichiers](#13-stockage-fichiers)
14. [Intégrations externes](#14-intégrations-externes)
15. [Points d'attention](#15-points-dattention)

---

## 1. Rôle du backend

Le backend Laravel EMB-MISSION est le **moteur central** de la plateforme. Il assure :

- l'**API REST** consommée par le back office Flutter Web ;
- la **gestion des utilisateurs** et l'authentification ;
- la **médiathèque** (upload, conversion, métadonnées) ;
- l'**orchestration WebRadio** via AzuraCast ;
- l'**orchestration WebTV** via Ant Media Server ;
- la **construction du flux HLS unifié** (bascule live / VoD) ;
- les **statistiques** (base locale + Google Analytics 4) ;
- le **monitoring opérationnel** des services serveur ;
- les pages web Laravel (lecteur public `/watch`, embed, monitoring).

Le frontend Flutter Web (dans `public/`) est une **interface d'administration**. Laravel reste la source de vérité métier.

---

## 2. Architecture globale

```
┌─────────────────────────────────────────────────────────────────┐
│                        NAVIGATEUR / CLIENTS                      │
│   Flutter Web (rtv)  │  Lecteur public (tv/watch)  │  Apps API  │
└──────────┬───────────────────────┬──────────────────────────────┘
           │ HTTP/JSON             │ HTTP/SSE/HLS
           ▼                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                         NGINX (reverse proxy)                    │
│  /api/*  → Laravel    /watch  → Laravel    /*  → Flutter SPA  │
└──────────────────────────────┬──────────────────────────────────┘
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    LARAVEL (PHP-FPM 8.2)                         │
│  Routes (web.php + api.php)                                      │
│  Controllers  →  Services  →  Jobs  →  Events                     │
└──────┬──────────────┬──────────────┬──────────────┬─────────────┘
       ▼              ▼              ▼              ▼
   MySQL         Ant Media       AzuraCast        GA4
 (emb_mission)   (WebTV/HLS)    (WebRadio)    (Analytics)
```

### Couches applicatives Laravel

| Couche | Dossier | Rôle |
|--------|---------|------|
| Routes | `routes/web.php`, `routes/api.php` | Point d'entrée HTTP |
| Controllers | `app/Http/Controllers/` | Réception requêtes, validation, réponses JSON/HTML |
| Services | `app/Services/` | Logique métier (streaming, sync, HLS, stats) |
| Models | `app/Models/` | Accès base de données (Eloquent ORM) |
| Jobs | `app/Jobs/` | Traitements asynchrones (queue) |
| Commands | `app/Console/Commands/` | Tâches CLI et daemons |
| Events | `app/Events/` | Broadcast temps réel (Reverb/WebSocket) |

---

## 3. Entrées HTTP (routes)

### 3.1 Routes API (`/api/*`)

Préfixe global : `/api` (défini dans `bootstrap/app.php`).

#### Authentification

| Méthode | Route | Contrôleur | Description |
|---------|-------|------------|-------------|
| POST | `/api/register` | `RegisterController` | Inscription utilisateur |
| POST | `/api/login` | `RegisterController` | Connexion → token Sanctum |
| POST | `/api/logout` | `RegisterController` | Déconnexion (auth requise) |
| POST | `/api/forgot-password` | `RegisterController` | Demande reset mot de passe |
| POST | `/api/reset-password` | `RegisterController` | Réinitialisation mot de passe |
| GET | `/api/auth/me` | Closure | Profil utilisateur connecté |
| GET | `/api/user` | Closure | Utilisateur courant (Sanctum) |

#### Médias

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/media/files` | Liste des fichiers (filtres audio/vidéo/image) |
| GET | `/api/media/files/{id}` | Détail d'un fichier |
| POST | `/api/media/upload` | Upload simple |
| POST | `/api/media/upload-chunk` | Upload par morceaux (gros fichiers) |
| POST | `/api/media/finalize-chunk-upload` | Finalisation upload chunké |
| PUT | `/api/media/files/{id}` | Mise à jour métadonnées |
| DELETE | `/api/media/files/{id}` | Suppression |
| GET | `/api/media/library/stats` | Statistiques médiathèque |

#### Playlists Radio (AzuraCast)

| Méthode | Route | Description |
|---------|-------|-------------|
| GET/POST | `/api/playlists` | CRUD playlists radio |
| POST | `/api/playlists/{id}/items` | Ajouter un morceau |
| DELETE | `/api/playlists/{id}/items/{item}` | Retirer un morceau |
| PUT | `/api/playlists/{id}/items/order` | Réordonner |
| POST | `/api/playlists/{id}/sync` | Synchroniser vers AzuraCast |
| POST | `/api/sync/azuracast` | Sync globale AzuraCast |
| GET/POST | `/api/azuracast/*` | Proxy direct API AzuraCast |

#### WebRadio

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/radio/broadcast-info` | Infos diffusion (port, mount point) |
| GET | `/api/radio/stream/current` | Piste en cours de lecture |
| GET | `/api/radio/stream/history` | Historique des morceaux |
| GET | `/api/webradio/current-stream` | Stream courant |
| POST | `/api/webradio/control` | Contrôle diffusion (start/stop) |
| POST | `/api/mixxx/start` | Démarrer diffusion Mixxx |
| POST | `/api/mixxx/stop` | Arrêter diffusion Mixxx |

#### WebTV — Playlists

| Méthode | Route | Description |
|---------|-------|-------------|
| GET/POST | `/api/webtv-playlists` | CRUD playlists WebTV |
| GET/PUT/DELETE | `/api/webtv-playlists/{id}` | Gestion playlist |
| POST | `/api/webtv-playlists/{id}/sync` | Sync vers Ant Media |
| GET/POST | `/api/webtv-playlists/{id}/items` | CRUD items playlist |
| PUT | `/api/webtv-playlists/{id}/items/order` | Réordonner items |

#### WebTV — Auto-playlist (orchestration live/VoD)

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/webtv-auto-playlist/status` | Statut auto-playlist |
| GET | `/api/webtv-auto-playlist/current-url` | URL de lecture actuelle |
| POST | `/api/webtv-auto-playlist/stop` | Suspendre diffusion WebTV |
| POST | `/api/webtv-auto-playlist/resume` | Reprendre diffusion WebTV |
| GET | `/api/webtv-auto-playlist/next-vod` | Prochain item VoD |
| GET | `/api/webtv-auto-playlist/obs-params` | Paramètres connexion OBS |
| GET | `/api/webtv-auto-playlist/public-stream-url` | URL publique M3U8 |

#### WebTV — Streams et statut live

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/webtv/streams` | Liste des streams Ant Media |
| GET | `/api/webtv/recent-broadcasts` | Diffusions récentes |
| GET | `/api/webtv/check-connection` | Test connexion Ant Media |
| GET | `/api/webtv/live/status/stream` | SSE statut live/VoD (temps réel) |
| GET | `/api/webtv/live/status/stream-new` | SSE avec transitions gérées backend |

#### Statistiques

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/webtv/stats/all` | Stats agrégées (GA4 + DB + Ant Media) |
| GET | `/api/webtv/stats/live-audience` | Audience en direct |
| GET | `/api/webtv/stats/current-duration` | Durée diffusion en cours |
| GET | `/api/webtv/stats/total-views` | Vues totales |
| POST | `/api/track-listen` | Tracking écoute radio → GA4 |

### 3.2 Routes Web (`routes/web.php`)

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/` | Redirection vers login |
| GET/POST | `/auth/login` | Connexion web (session Blade) |
| GET/POST | `/auth/register` | Inscription web |
| POST | `/auth/logout` | Déconnexion web |
| GET | `/dashboard` | Dashboard Blade (auth requise) |
| GET | `/watch` | Lecteur public WebTV |
| GET | `/player` | Lecteur alternatif |
| GET | `/embed/{videoId?}` | Embed YouTube partenaires |
| GET | `/playlist.m3u` | Playlist M3U WebRadio + tracking GA4 |
| GET | `/api/stream/unified.m3u8` | **Flux HLS unifié Live+VoD** |
| GET | `/system-monitoring` | Dashboard monitoring (auth mot de passe) |
| GET | `/system-monitoring/status` | API JSON statut serveur |

---

## 4. Authentification et sécurité

### 4.1 API — Laravel Sanctum

Utilisé par le back office Flutter Web.

```
Flutter → POST /api/login (email + password)
       ← Token Bearer Sanctum + profil user
Flutter → Requêtes suivantes avec header Authorization: Bearer {token}
       ← Données JSON
```

- Token stocké côté Flutter (`UserService`)
- Routes protégées : `logout`, `/api/auth/me`, `/api/user`
- Table : `personal_access_tokens`

### 4.2 Web — Sessions Laravel

Utilisé par les pages Blade (login, dashboard, monitoring).

- Driver : `database` (table `sessions`)
- Middleware : `auth` sur `/dashboard`
- Contrôleur : `Controller_login_register`

### 4.3 Monitoring — Auth séparée

- Mot de passe configuré dans `.env` : `MONITORING_PASSWORD`
- Session dédiée : `monitoring_authenticated`
- Middleware : `MonitoringAuth`
- Indépendant de l'auth utilisateur principale

### 4.4 CORS

Origines autorisées (`config/cors.php`) :
- `rtv.embmission.com`
- `tv.embmission.com`
- `radio.embmission.com`
- localhost (développement)

---

## 5. Module Médias

### Fonctionnement

```
Upload (Flutter) → MediaController
    → Stockage disque (storage/app/media/)
    → Enregistrement BDD (media_files)
    → Job ProcessMediaFile (finalisation)
    → Job ConvertVideoToHls (si vidéo, conversion Ant Media)
```

### États d'un fichier média

| Statut | Signification |
|--------|---------------|
| `importing` | Upload en cours |
| `processing` | Conversion / traitement |
| `completed` | Prêt à l'emploi |
| `error` | Échec (retry possible) |

### Upload chunké (gros fichiers)

Pour les vidéos volumineuses, l'upload se fait par morceaux :

1. `POST /api/media/upload-session` → crée une session
2. `POST /api/media/upload-chunk` → envoie chaque morceau
3. `POST /api/media/finalize-chunk-upload` → assemble et finalise

### Modèle `MediaFile`

Champs clés : `filename`, `original_name`, `file_type` (audio/video/image), `file_size`, `duration`, `status`, `azuracast_id`, `metadata`.

---

## 6. Module WebRadio (AzuraCast)

### Fonctionnement

```
Admin (Flutter) → API Laravel (/api/playlists, /api/webradio)
    → AzuraCastSyncService
        → Copie fichiers audio vers AzuraCast
        → Génère playlist M3U
        → Import dans AzuraCast
        → Redémarre backend AzuraCast
    → AzuraCast diffuse le flux radio
```

### Services impliqués

| Service | Rôle |
|---------|------|
| `AzuraCastService` | API bas niveau (playlists, restart, stop) |
| `AzuraCastSyncService` | Orchestration sync complète |
| `AzuraCastApiService` | Client REST AzuraCast |
| `AzuraCastUploadService` | Upload fichiers vers AzuraCast |

### Flux d'écoute public

```
Auditeur → radio.embmission.com/listen
        → Nginx proxy → AzuraCast (port 8080)
        → Flux MP3 en direct
```

### Modèles

- `Playlist` : playlist radio (nom, loop, shuffle, sync_status)
- `PlaylistItem` : morceau dans une playlist (media_file_id, order)
- `RadioSource` : sources radio configurables

---

## 7. Module WebTV (Ant Media)

### Fonctionnement global

```
Admin (Flutter) → Crée/modifie playlist WebTV
    → WebTVPlaylistController
        → Enregistrement BDD (webtv_playlists + webtv_playlist_items)
        → AntMediaPlaylistService (sync vers Ant Media)
        → AntMediaVoDService (création streams VoD)
    → WebTVAutoPlaylistService (orchestration lecture)
        → Bascule live ↔ VoD automatique
        → Gestion position sync (chaîne TV)
        → Construction flux HLS unifié
```

### Service central : `WebTVAutoPlaylistService`

C'est le cœur métier WebTV. Il gère :

| Fonction | Description |
|----------|-------------|
| Détection live | Vérifie si un stream live est actif sur Ant Media |
| Bascule live/VoD | Passe automatiquement du direct à la playlist vidéo |
| Position sync | Maintient la position de lecture (comme une chaîne TV) |
| Pause/Reprise | `stop` / `resume` via cache `webtv_system_paused` |
| Prochain VoD | Calcule le prochain item à diffuser |
| Nettoyage | Supprime les connexions fantômes Ant Media |

### Playlist TV active (production)

- ID : `38`
- Nom : `Playlist TV`
- Créée : `2025-11-07`
- Items : vidéos de la médiathèque, ordonnés

### Modèles

- `WebTVPlaylist` : playlist (type, is_loop, shuffle, sync_status, ant_media_stream_id)
- `WebTVPlaylistItem` : item (video_file_id, stream_url, order, duration, sync_status, unique_id)
- `WebTVStream` : historique des streams Ant Media

### Sync Ant Media

Chaque item vidéo est converti en stream VoD Ant Media :

```
MediaFile (vidéo) → AntMediaVoDService → Stream VoD Ant Media
    → Fichiers HLS dans /usr/local/antmedia/webapps/LiveApp/streams/{vodName}/
    → Référencé dans webtv_playlist_items.ant_media_item_id
```

---

## 8. Flux HLS unifié (Live + VoD)

### Principe

Un seul flux public pour les spectateurs :

```
https://tv.embmission.com/hls/streams/unified.m3u8
```

Ce flux bascule automatiquement entre :
- **Live** : quand un diffuseur est connecté (OBS, etc.)
- **VoD** : lecture séquentielle de la playlist TV

### Fonctionnement technique

```
Spectateur → GET /api/stream/unified.m3u8
          → UnifiedStreamController
              → WebTVAutoPlaylistService (détecte mode live ou VoD)
              → UnifiedHlsBuilder (construit la playlist M3U8)
                  → Mode LIVE : segments live_transcoded.m3u8
                  → Mode VoD : segments du prochain item playlist
                  → Mode PAUSED : playlist vide / message
          ← Fichier M3U8 (25 segments, fenêtre glissante)
```

### Service `UnifiedHlsBuilder`

- Construit dynamiquement le fichier `unified.m3u8`
- Fenêtre glissante de 25 segments
- État persisté dans `storage/app/unified_hls_sequence_state.json`
- Métadonnées segments dans `storage/app/unified_segment_metadata.json`
- Position sync par playlist dans `storage/app/webtv_sync_position_{id}.json`

### SSE — Statut temps réel

```
Client → GET /api/webtv/live/status/stream (SSE)
      ← Événements : mode (live/vod/paused), item courant, transitions
```

Utilisé par la page `/watch` et le back office pour afficher l'état en direct.

---

## 9. Statistiques et analytics

### Sources de données

| Source | Données |
|--------|---------|
| `web_tv_stats` (MySQL) | Stats journalières WebTV |
| `web_radio_stats` (MySQL) | Stats journalières WebRadio |
| Ant Media API | Audience live, connexions |
| AzuraCast API | Auditeurs radio, historique |
| Google Analytics 4 | Vues, pays, appareils, engagement |

### Service `GA4DataService`

- Client Google Analytics Data API
- Propriétés : `GA4_PROPERTY_ID_RADIO`, `GA4_PROPERTY_ID_TV`
- Credentials : fichier JSON service account (`GA4_CREDENTIALS_PATH`)

### Service `UnifiedStatsController`

Agrège toutes les sources en un seul endpoint :

```
GET /api/webtv/stats/all → {
    audience, views, duration, engagement,
    countries, devices, recent_broadcasts
}
```

### Tracking écoute radio

```
POST /api/track-listen → GA4 Measurement Protocol
GET /playlist.m3u → Téléchargement M3U + événement GA4
```

---

## 10. Monitoring système

### URL

`https://tv.embmission.com/system-monitoring` (aussi sur `rtv.embmission.com`)

### Fonctionnement

```
Admin → Page login (mot de passe MONITORING_PASSWORD)
     → Dashboard MonitoringController
         → Vérifie services systemd (nginx, php-fpm, mysql, antmedia…)
         → Vérifie workers Laravel (queue, unified-stream, reverb)
         → Espace disque, versions, liens storage
         → Jobs en échec (failed_jobs)
         → Erreurs récentes (laravel.log)
         → Alertes RTMP
     ← JSON status (rafraîchissement auto toutes les 30s)
```

### Actions disponibles

| Action | Route | Effet |
|--------|-------|-------|
| Redémarrer un service | POST `/system-monitoring/restart-service` | `sudo systemctl restart {service}` |
| Arrêter un service | POST `/system-monitoring/stop-service` | `sudo systemctl stop {service}` |
| Relancer un job | POST `/system-monitoring/retry-job` | `php artisan queue:retry {id}` |
| Relancer tous les jobs | POST `/system-monitoring/retry-all-jobs` | Retry tous les failed_jobs |
| Suspendre WebTV | POST `/api/webtv-auto-playlist/stop` | Pause diffusion |
| Reprendre WebTV | POST `/api/webtv-auto-playlist/resume` | Reprise diffusion |

---

## 11. Jobs, files d'attente et tâches planifiées

### File d'attente

- Driver : `database` (table `jobs`)
- Worker : `laravel-queue-worker` (Supervisor)
- Jobs échoués : table `failed_jobs` (relançables depuis le monitoring)

### Jobs principaux

| Job | Déclencheur | Action |
|-----|-------------|--------|
| `ProcessMediaFile` | Upload terminé | Finalise import, met statut `completed` |
| `ConvertVideoToHls` | Vidéo uploadée | Conversion HLS via Ant Media |
| `CreateVodStreamJob` | Item playlist WebTV | Crée stream VoD Ant Media |
| `ShakaRemuxJob` | VoD créé | Remux/packaging Shaka |
| `SyncPlaylistToAzuraCast` | Sync playlist radio | Copie + import AzuraCast |
| `FinalizeAzuraCastSync` | Après sync | Met à jour IDs, finalise |
| `UpdateM3UAndRestartJob` | Changement playlist | Régénère M3U, restart AzuraCast |
| `UploadToAzuraCast` | Upload radio | Envoie fichier vers AzuraCast |
| `RetryFailedVideoConversions` | Planifié (15 min) | Relance conversions échouées |

### Tâches planifiées (cron Laravel)

| Commande | Fréquence | Rôle |
|----------|-----------|------|
| `webtv:advance-sync-position` | **Chaque minute** | Avance position lecture chaîne TV |
| `webtv:link-preconverted` | Toutes les 2 min | Lie items pending aux HLS pré-convertis |
| `unified-stream:remux-pending` | Toutes les 5 min | Remux VoD en attente |
| `webtv:check-sync` | Toutes les heures | Surveillance sync WebTV |
| `stats:update` | Toutes les heures | MAJ stats WebTV/WebRadio |
| `RetryFailedVideoConversions` | Toutes les 15 min | Relance conversions vidéo |

### Daemons (Supervisor)

| Processus | Rôle |
|-----------|------|
| `laravel-queue-worker` | Traite les jobs en file d'attente |
| `unified-stream` | Maintient le flux HLS unifié |
| `laravel-reverb` | WebSocket temps réel (Reverb) |
| `vod:daemon` | Daemon remux VoD continu |
| `media:preconvert-daemon` | Pré-conversion médias en arrière-plan |

---

## 12. Base de données

### Schéma principal

```
users
├── id, name, email, password, avatar, role, is_active, last_login_at

media_files
├── id, filename, original_name, file_type, file_size, duration
├── status (importing/processing/completed/error)
├── azuracast_id, metadata (JSON)

media_file_relations
├── media_file_id, related_media_file_id, relation_type

upload_sessions / upload_session_files
├── Gestion uploads multi-fichiers chunkés

playlists (radio)
├── id, name, description, is_loop, shuffle_enabled
├── azuracast_id, sync_status, last_sync_at

playlist_items (radio)
├── playlist_id, media_file_id, order, title, artist, duration

webtv_playlists
├── id, name, type, is_active, is_loop, shuffle_enabled
├── ant_media_stream_id, sync_status, total_duration, total_items

webtv_playlist_items
├── webtv_playlist_id, video_file_id, stream_url, title
├── order, duration, ant_media_item_id, sync_status, unique_id

webtv_streams
├── Historique streams Ant Media (soft deletes)

web_tv_stats / web_radio_stats
├── Stats journalières agrégées

radio_sources
├── Sources radio configurables

Tables système Laravel :
├── sessions, password_reset_tokens, cache, jobs, failed_jobs
├── personal_access_tokens (Sanctum)
```

### Connexion

- Host : `127.0.0.1` (local au serveur)
- Base : `emb_mission`
- Utilisateur : `emb_user`

---

## 13. Stockage fichiers

### Disques Laravel

| Disque | Chemin | Usage |
|--------|--------|-------|
| `local` | `storage/app/` | Stockage interne |
| `media` | `storage/app/media/` | Fichiers média uploadés |
| `public` | `storage/app/public/` | Fichiers publics (symlink `public/storage`) |

### Arborescence média

```
storage/app/media/
├── audios/2025/          # Fichiers audio
└── videos/2025/          # Fichiers vidéo
```

### Fichiers d'état (runtime)

```
storage/app/
├── unified_hls_sequence_state.json      # Position séquence HLS
├── unified_segment_metadata.json        # Métadonnées segments
└── webtv_sync_position_{playlist_id}.json  # Position lecture par playlist
```

### Ant Media (externe)

```
/usr/local/antmedia/webapps/LiveApp/streams/
├── unified.m3u8                         # Playlist HLS unifiée
├── live_transcoded.m3u8                 # Stream live
└── vod_mf_{id}/                         # Segments VoD par média
    ├── playlist.m3u8
    └── segment_00001.ts, ...
```

### URL publique médias

```
https://tv.embmission.com/storage/media/{chemin}
```

---

## 14. Intégrations externes

### Ant Media Server (WebTV)

| Paramètre | Valeur type |
|-----------|-------------|
| URL interne | `http://localhost:5080` |
| App ID | `LiveApp` |
| URL publique | `https://tv.embmission.com/webtv-live` |
| Streams locaux | `/usr/local/antmedia/webapps/LiveApp/streams/` |

**Utilisé pour :** streams live, VoD, HLS, thumbnails, WebRTC.

### AzuraCast (WebRadio)

| Paramètre | Valeur type |
|-----------|-------------|
| URL interne | `http://localhost:8080` |
| Station | `radio_emb_mission` |
| Container Docker | Configurable via `.env` |

**Utilisé pour :** diffusion radio, playlists, historique morceaux, auditeurs.

### Google Analytics 4

| Usage | Méthode |
|-------|---------|
| Tracking temps réel | Measurement Protocol (`GA4_API_SECRET`) |
| Stats agrégées | Data API (`GA4_CREDENTIALS_PATH`) |
| Événements | `track-listen`, `m3u_playlist_download` |

### Laravel Reverb (WebSocket)

- Canal : `webtv-stream-status`
- Événement : `StreamStatusChanged`
- Utilisé pour notifier les clients du changement de statut live/VoD.

### FFmpeg / Shaka

- Conversion vidéo → HLS
- Remux segments VoD
- Transcodage live (`ffmpeg-live-transcode` service)

---

## 15. Points d'attention

### Sécurité API

La majorité des endpoints API (médias, playlists, WebTV) sont **publics** (sans `auth:sanctum`). Seuls `logout`, `/api/auth/me` et `/api/user` requièrent un token.

### Routes non implémentées

Certaines routes sont déclarées mais les méthodes correspondantes sont absentes des contrôleurs :
- `PlaylistController@syncToAzuraCast`
- `WebTVController@getStreamStatus`
- `WebTVController@getEmbedCode`

### Contrôleurs existants sans route

Ces classes existent mais ne sont pas exposées via `routes/api.php` :
- `WatchController`, `WatchStreamController`
- `PlaybackMetricsController`
- `AntMediaWebhookController`
- `ClientLogController`
- `RadioSourceController`

### Flux critique

La route `GET /api/stream/unified.m3u8` est définie dans `web.php` (pas `api.php`) pour compatibilité Nginx et CORS. C'est le flux principal des spectateurs.

### Triple authentification

Le système utilise 3 mécanismes d'auth distincts :
1. **Sanctum** (API Flutter) — token Bearer
2. **Session web** (Blade login/dashboard) — cookie session
3. **Monitoring** (mot de passe `.env`) — session dédiée

---

## Annexe — Schéma de flux complet

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   ADMIN      │     │  SPECTATEUR  │     │   AUDITEUR   │
│  (Flutter)   │     │   (watch)    │     │   (radio)    │
└──────┬───────┘     └──────┬───────┘     └──────┬───────┘
       │                    │                     │
       ▼                    ▼                     ▼
  rtv.embmission.com   tv.embmission.com    radio.embmission.com
       │                    │                     │
       ├─ /api/* ────────────┤                     │
       │  (Laravel API)      │                     │
       │                    ├─ /watch             ├─ /listen
       │                    ├─ /hls/streams/      ├─ /api/*
       │                    │   unified.m3u8     │
       │                    │  (HLS unifié)      │
       ▼                    ▼                     ▼
┌─────────────────────────────────────────────────────────┐
│                    LARAVEL BACKEND                       │
│                                                          │
│  Auth ── Médias ── Playlists Radio ── Playlists WebTV   │
│  Stats ── Monitoring ── Flux HLS ── SSE Live Status      │
└──────┬──────────────┬──────────────┬────────────────────┘
       ▼              ▼              ▼
    MySQL         Ant Media       AzuraCast
  (emb_mission)   (WebTV/HLS)    (WebRadio)
```

---

*Document généré le 28 juillet 2026 — Backend Laravel EMB-MISSION v1e6c242*
