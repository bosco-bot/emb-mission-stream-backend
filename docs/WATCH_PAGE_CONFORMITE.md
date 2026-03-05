# Analyse de conformité – Page Watch

**Date :** 2026-02  
**Fichiers :** `watch.blade.php`, `watch-page.js`, `watch.css`

---

## 1. Sécurité

| Élément | Statut | Détail |
|--------|--------|--------|
| CSRF | OK | `<meta name="csrf-token" content="{{ csrf_token() }}">` + Echo auth `X-CSRF-TOKEN` |
| URLs / config | OK | `apiUrl`, `baseUrl`, `sseUrl` via Blade (pas d’URL en dur dans le JS) |
| Scripts externes | OK | HLS.js, Pusher, Echo, GA avec SRI sur HLS.js (`integrity="sha256-..."`) |
| Échappement | OK | Pas d’injection HTML utilisateur directe dans la Blade |

---

## 2. Structure Blade

| Élément | Statut | Détail |
|--------|--------|--------|
| Doctype / lang | OK | `<!DOCTYPE html>`, `lang="fr"` |
| Viewport | OK | `width=device-width, initial-scale=1.0` |
| Favicon / PWA | OK | `favicon.png`, `apple-touch-icon` |
| WATCH_CONFIG | OK | `apiUrl`, `baseUrl`, `sseUrl`, `livePlaylistPath` injectés |
| WATCH_DEBUG | OK | Basé sur `config('app.debug')` |
| Assets | OK | `@vite(['resources/css/watch.css'])`, `@vite(['resources/js/watch-page.js'])` |

---

## 3. Logique JS – Flux unifié / chaîne linéaire

| Règle | Statut | Détail |
|-------|--------|--------|
| Détection flux unifié | OK | `isUnifiedStreamUrl(url)` → `url.indexOf('unified.m3u8') !== -1` |
| Même URL VOD = pas de rechargement | OK | `handleStreamData` : si `mode === 'vod' && currentUrl === vodUrl` → mise à jour `lastData` puis return |
| Pas de seek sur `current_time` (unifié) | OK | `showVODOptimized` else : seek / “player reached duration” uniquement si `!isUnifiedStreamUrl(url)` |
| Freeze watchdog (unifié) | OK | Pas de `requestNextVideo("freeze at end")` si `isUnifiedStreamUrl(lastData.url)` |
| Resync continue (unifié) | OK | `startContinuousSync` : return si `isUnifiedStreamUrl(lastData.url)` |
| Après `requestNextVideo` (même URL unifiée) | OK | Si même URL unifiée + `video.src` déjà défini → mise à jour state sans rechargement |
| Chaîne linéaire au chargement | OK | Pour unifié + M3U8 : seek au live edge (`LEVEL_LOADED`, `liveSyncPosition` / `seekable.end`) puis `onSeeked()` |

---

## 4. Logique JS – Live / VOD / Erreurs

| Règle | Statut | Détail |
|-------|--------|--------|
| Source live | OK | `liveUrl = WATCH_CONFIG.baseUrl + WATCH_CONFIG.livePlaylistPath` → `/hls/streams/unified.m3u8` (aligné backend) |
| Transition live → VOD | OK | Période de grâce `TRANSITION_GRACE_PERIOD_MS`, pas d’erreur immédiate |
| Gestion erreur Reverb/Echo | OK | `unhandledrejection` (Blade + JS) sur message contenant `"payload"` |
| Limitation rechargement erreur | OK | `MIN_ERROR_RETRY_DELAY`, `lastErrorRetry` pour éviter boucles |

---

## 5. CSS

| Élément | Statut | Détail |
|--------|--------|--------|
| Reset / box-sizing | OK | `* { margin: 0; padding: 0; box-sizing: border-box; }` |
| Plein écran | OK | `#player-container` 100vw/100vh, `#player` / `#html5-player` 100% |
| États loading / error | OK | Position centrée, `#error` masqué par défaut |
| Splash | OK | Fixe, z-index 9999, transition, `.hidden` pour disparition |
| Accessibilité visuelle | OK | Contraste texte/fond (blanc/bleu sur splash, blanc/rouge pour erreur) |

---

## 6. Points à surveiller (non bloquants)

| Point | Recommandation |
|-------|----------------|
| **Live = même URL que VOD** | `livePlaylistPath: "/hls/streams/unified.m3u8"` : live et VOD passent par le même flux unifié. Vérifier que le backend sert bien le bon contenu (live vs playlist) selon le mode. |
| **Google Analytics** | Requêtes GA peuvent échouer (bloqueurs). Pas d’impact fonctionnel ; désactiver GA sur `/watch` si les logs console gênent. |
| **SSE en HTTP/2** | `EventSource` peut échouer en browser (Nginx + HTTP/2). Fallback polling actif ; si besoin SSE fiable, envisager un accès HTTP/1.1 pour ce flux. |
| **Echo chargé même si USE_WEBSOCKET = false** | Scripts Pusher/Echo chargés dans la Blade alors que `USE_WEBSOCKET = false`. Acceptable (prêt si tu actives le WebSocket plus tard). |

---

## 7. Synthèse

- **Sécurité, structure Blade, config, CSS** : conformes.
- **Flux unifié / chaîne linéaire** : règles respectées (pas de seek inutile, pas de resync sur unifié, reprise au live edge au chargement).
- **Live, VOD, erreurs, Echo** : cohérents avec l’architecture actuelle.
- Aucune incohérence bloquante détectée ; les points listés en §6 sont des optimisations ou vérifications optionnelles.
