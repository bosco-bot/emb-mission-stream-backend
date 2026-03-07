# Audit technique : validation de la fluidité HLS (VLC, Mobile, Android TV)

**Date :** 2025-02-04  
**Contexte :** Transition Live ↔ VoD sur l’URL unique `unified.m3u8`.  
**Objectif :** Compatibilité 100% avec VLC, ExoPlayer (Android), AVPlayer (iOS).

---

## 1. Tag #EXT-X-DISCONTINUITY

### Condition
Le tag `#EXT-X-DISCONTINUITY` doit être inséré **immédiatement avant le premier segment** de la nouvelle source (ex. premier segment VoD après Live, premier segment Live après VoD). Sans ce tag, VLC et les boîtiers TV peuvent figer l’image.

### Vérification

| Transition | Fichier / méthode | Comportement | Statut |
|------------|-------------------|--------------|--------|
| **Live → VoD** | `UnifiedHlsBuilder.php` | Lorsque `last_mode === 'live'`, `$segments[0]['discontinuity'] = true` est posé (l.99-101). Dans `createPlaylist()`, pour chaque segment, si `discontinuity` est vrai, la ligne `#EXT-X-DISCONTINUITY` est émise **juste avant** `#EXTINF` et l’URI du segment (l.392-397). | ✅ Conforme |
| **VoD → Live** | `UnifiedStreamController::filterPlaylistWithOriginalMetadata()` | Quand `inject_discontinuity` est vrai (dernier mode = VoD), `#EXT-X-DISCONTINUITY` est ajouté après `#EXT-X-MEDIA-SEQUENCE` et avant `#EXT-X-PLAYLIST-TYPE:LIVE`, puis viennent les métadonnées et l’URL du **premier** segment (l.289-300). Le tag est donc immédiatement avant le premier segment. | ✅ Conforme |

**Conclusion :** Les deux sens de transition placent bien `#EXT-X-DISCONTINUITY` immédiatement avant le premier segment de la nouvelle source.

---

## 2. Continuité #EXT-X-MEDIA-SEQUENCE

### Condition
Lors du passage Live → VoD (ou VoD → Live), le numéro de séquence **ne doit pas repartir à zéro**. Il doit continuer à incrémenter par rapport au dernier numéro servi pour éviter que les apps mobiles considèrent le flux comme expiré ou invalide.

### Vérification

| Point de génération | Comportement | Statut |
|--------------------|--------------|--------|
| **Placeholder Live** (`writeLiveRedirectPlaylist`) | Utilise `getCurrentMediaSequence()` et écrit `#EXT-X-MEDIA-SEQUENCE:{$mediaSequence}` (l.465-477). Pas de 0 en dur. | ✅ Conforme |
| **Playlist Live servie** (`filterPlaylistWithOriginalMetadata`) | `min_media_sequence` = `getCurrentMediaSequence()` ; `mediaSequence = max(extracted, min_media_sequence)` (l.276-279). Puis mise à jour via `updateSequenceStateForLive($result['media_sequence'])`. | ✅ Conforme |
| **Playlist VoD** (`createPlaylist` / `applyLegacyStableSequences`) | `media_sequence` vient de `$segments[0]['sequence']`, lui-même issu du cache (`loadSequenceState`, `uri_map` / `last_sequence`). Après écriture VoD, `state['media_sequence']` et `last_mode` sont sauvegardés (l.1393, 123-125). | ✅ Conforme |
| **Playlist en pause** (`writePausedPlaylist`) | Utilise `getCurrentMediaSequence()` (l.505). | ✅ Conforme |
| **Playlist minimale** (`getMinimalPlaylistContent`) | Utilise `getCurrentMediaSequence()` (l.584). | ✅ Conforme |

**Conclusion :** Aucun reset à 0 sur le flux unifié ; la séquence est persistée et réutilisée partout.

---

## 3. Robustesse des erreurs (fallback HLS)

### Condition
Si la playlist VoD n’est pas encore prête, le serveur **ne doit pas** renvoyer un message texte (ex. « En cours de préparation »). Il doit renvoyer une **playlist HLS valide** (en-têtes + au moins un segment si possible) avec `Content-Type: application/vnd.apple.mpegurl` pour maintenir la connexion (VLC, apps).

### Vérification

| Cas | Méthode | Comportement | Statut |
|-----|---------|--------------|--------|
| Fichier unified absent | `serveUnifiedPlaylist()` | Si `build()` échoue → `respondMinimalPlaylist()` (l.1015). | ✅ Conforme |
| Fichier encore placeholder après build | `serveUnifiedPlaylist()` | Si après régénération le contenu est toujours un placeholder → `respondMinimalPlaylist()` (l.1036). | ✅ Conforme |
| `respondMinimalPlaylist()` | Contrôleur | Retourne HTTP 200 avec `getMinimalPlaylistContent()` (builder) : `#EXTM3U`, `#EXT-X-VERSION:3`, `#EXT-X-TARGETDURATION`, `#EXT-X-MEDIA-SEQUENCE`, optionnellement un segment connu, `#EXT-X-ENDLIST`. Content-Type = `application/vnd.apple.mpegurl; charset=utf-8`. | ✅ Conforme |

**Conclusion :** Aucun retour de message texte pour « en cours de préparation » ; une playlist M3U8 valide est toujours renvoyée.

---

## 4. En-têtes HTTP

### Condition
- `Content-Type` doit être systématiquement `application/vnd.apple.mpegurl` (recommandé pour HLS par Apple / VLC / ExoPlayer).
- Présence de `Cache-Control: no-cache, no-store` (et idéalement `must-revalidate, private`) pour éviter que les boîtiers TV servent une version périmée.

### Vérification et corrections

| Réponse | Avant audit | Après correction | Statut |
|---------|-------------|------------------|--------|
| `serveLivePlaylistDirectly` | `Content-Type: application/vnd.apple.mpegurl; charset=utf-8`, `Cache-Control: no-cache, no-store, must-revalidate, private` | Inchangé | ✅ OK |
| `serveUnifiedPlaylist` | Idem | Inchangé | ✅ OK |
| `respondMinimalPlaylist` | Idem | Inchangé | ✅ OK |
| `generateErrorHLS` | `Content-Type: application/vnd.apple.mpegurl`, `Cache-Control: no-cache` | **Corrigé :** `Content-Type: application/vnd.apple.mpegurl; charset=utf-8`, `Cache-Control: no-cache, no-store, must-revalidate, private`, + `Pragma`, `Expires`. | ✅ Corrigé |
| `generateVoDHLS` | `Content-Type: application/x-mpegURL` | **Corrigé :** `Content-Type: application/vnd.apple.mpegurl; charset=utf-8`, `Cache-Control` complété avec `private`, + `X-Content-Type-Options: nosniff`. | ✅ Corrigé |

**Conclusion :** Toutes les réponses HLS utilisent maintenant le bon Content-Type et un Cache-Control strict.

---

## Synthèse

| Critère | Statut |
|---------|--------|
| 1. DISCONTINUITY immédiatement avant le premier segment (Live→VoD et VoD→Live) | ✅ Conforme |
| 2. MEDIA-SEQUENCE jamais réinitialisé à 0 sur le flux unifié | ✅ Conforme |
| 3. Fallback = playlist HLS valide (pas de message texte) | ✅ Conforme |
| 4. Content-Type + Cache-Control sur toutes les réponses HLS | ✅ Conforme (2 corrections appliquées) |

**Modifications appliquées dans le code :**
- `UnifiedStreamController::generateErrorHLS()` : Content-Type avec charset, Cache-Control complet, Pragma, Expires.
- `UnifiedStreamController::generateVoDHLS()` : Content-Type `application/vnd.apple.mpegurl; charset=utf-8`, Cache-Control avec `private`, X-Content-Type-Options.

L’objectif est atteint : un flux qui ne s’arrête pas au changement de mode, avec une compatibilité maximale VLC / ExoPlayer / AVPlayer.
