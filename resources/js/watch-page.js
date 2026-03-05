// 🚀 VARIABLES OPTIMISÉES
// Lecture : une seule URL (unified.m3u8) pour live et VOD. L'API sert à la disponibilité (pause/erreur) et aux métadonnées (titre, sync).
function getUnifiedStreamUrl() {
    return (window.WATCH_CONFIG.baseUrl || '').replace(/\/$/, '') + (window.WATCH_CONFIG.livePlaylistPath || '/hls/streams/unified.m3u8');
}
const API = window.WATCH_CONFIG.apiUrl;
const iframe = document.getElementById("player");
const video = document.getElementById("html5-player");
const loading = document.getElementById("loading");
const errorBox = document.getElementById("error");
const syncInfo = document.getElementById("sync-info");
const perfInfo = document.getElementById("perf-info");
const splashScreen = document.getElementById("splash-screen");
const defaultErrorHTML = errorBox.innerHTML;

let currentUrl = null, mode = null, poll = null, sync = null;
let lastData = null, playPromise = null, lastStreamId = null;
let preloadedVideos = new Map(); // 🎯 Cache de preloading
let forceReload = false; // ✅ Forcer le rechargement en cas d'erreur
let lastErrorRetry = 0; // ✅ Timestamp du dernier rechargement suite à une erreur
const MIN_ERROR_RETRY_DELAY = 3000; // ✅ Délai minimum de 3 secondes entre les tentatives
let performanceMetrics = { apiCalls: 0, loadTimes: [] };
let freezeInterval = null, lastProgressTime = 0; // 🛡️ Surveillance du freeze
let freezeConfirmCount = 0; // ✅ Bonne pratique : n'agir qu'après 2 ticks consécutifs (évite faux positifs)
let modeStableSince = null; // ✅ Timestamp de la dernière stabilité du mode
let consecutiveStablePolls = 0; // ✅ Nombre de polls consécutifs sans changement
const STABLE_MODE_THRESHOLD = 3; // ✅ Après 3 polls stables, réduire la fréquence
// Diagnostic: quand la vidéo gèle, le code peut "sauter" à une autre vidéo via requestNextVideo().
// Deux causes: (1) freeze watchdog considère qu'on est en fin de vidéo (currentTime >= duration - 1)
// et appelle requestNextVideo("freeze at end"); (2) 3 événements stalled/waiting en 15s déclenchent
// requestNextVideo("stalled overflow"). On assouplit pour éviter les sauts intempestifs.
const STALL_WINDOW_MS = 30000;  // 30s (au lieu de 15s) avant de considérer overflow
const STALL_LIMIT = 5;          // 5 stalls (au lieu de 3) avant passage à une autre vidéo
const FREEZE_AT_END_THRESHOLD = 0.25; // Ne considérer "fin de vidéo" que si remaining <= 0.25s
const MIN_DURATION_FOR_END = 5;      // Ignorer "freeze at end" si duration < 5s (évite faux positifs)
/** Flux unifié (un seul URL pour toute la playlist) : current_time du backend est par item, pas position dans le stream. */
function isUnifiedStreamUrl(url) {
    return typeof url === 'string' && url.indexOf('unified.m3u8') !== -1;
}
let stallCount = 0;
let stallWindowStart = 0;
let isBuffering = false;
const supportsNativeHls = video.canPlayType('application/vnd.apple.mpegurl');
let hlsInstance = null;
let isLiveStream = false;
let errorRetry = null;
let liveManifestRetryCount = 0; // ✅ Compteur de tentatives pour le manifest live
const MAX_LIVE_MANIFEST_RETRIES = 5; // ✅ Maximum 5 tentatives avant de considérer le live comme indisponible
let liveToVodTransitionStart = null; // ✅ Timestamp du début de transition live → VoD
const TRANSITION_GRACE_PERIOD_MS = 3000; // ✅ Période de grâce de 3s pour la transition (optimisé : backend détecte en ~2s)
let errorRecoveryAttempts = 0; // ✅ Compteur global pour les tentatives de récupération HLS
const MAX_RECOVERY_ATTEMPTS = 3; // ✅ Maximum 3 tentatives avant rechargement
const RECOVERY_DELAY = 2000; // ✅ Délai de 2 secondes entre les tentatives

const USE_WEBSOCKET = false;

// ✅ WebSocket : Variables pour la connexion
let websocketConnected = false;
let websocketChannel = null;
let websocketFallbackActive = false; // Si WebSocket échoue, activer le polling comme fallback
let websocketSnapshotRequested = false; // Empêche les snapshots multiples
let sseSource = null;
let sseConnected = false;

// ✅ Protection contre appels multiples de startPlaybackOptimized()
let startPlaybackLock = null;
const STARTPLAYBACK_DEBOUNCE_MS = 500; // 500ms entre appels

// ✅ Cache pour filtrer les messages SSE dupliqués
let lastSSEPayload = null;

// ✅ Éviter le crash "Cannot read properties of undefined (reading 'payload')" connu avec Reverb/Echo
// quand le serveur envoie un événement sans propriété data (ex. pusher_internal:subscription_succeeded)
window.addEventListener('unhandledrejection', (event) => {
    const msg = (event.reason && (event.reason.message || String(event.reason))) || '';
    if (msg.includes('payload') || (event.reason && event.reason.message && event.reason.message.includes('payload'))) {
        _warn('⚠️ Événement Reverb/Echo mal formé (payload manquant) - ignoré. Mettre à jour laravel/reverb si le problème persiste.');
        event.preventDefault();
        event.stopPropagation();
    }
});

function isRealtimeActive() {
    return (USE_WEBSOCKET && websocketConnected && !websocketFallbackActive) || sseConnected;
}
// ✅ Fonction pour traiter les données de stream (utilisée par WebSocket et polling)
function handleStreamData(d) {
    if (d.mode === 'paused') {
        liveToVodTransitionStart = null; // Réinitialiser la transition
        showPaused(d.message ?? "Diffusion en pause");
        return;
    }
    
    const backendMode = d.mode || (d.stream_id ? 'live' : (d.url ? 'vod' : null));
    const previousMode = mode;
    const modeChanged = previousMode !== backendMode;
    const streamId = typeof d.stream_id === 'string' ? d.stream_id : null;
    let isLiveData = backendMode === 'live';
    let isVodData = backendMode === 'vod';

    if (!backendMode && streamId) {
        const normalizedId = streamId.toLowerCase();
        if (normalizedId.startsWith('live')) {
            isLiveData = true;
        } else if (normalizedId.startsWith('vod')) {
            isVodData = true;
        }
    }

    if (!backendMode && !streamId && (d.url || d.stream_url)) {
        isVodData = true;
    }
    
    // ✅ Détection de transition live → VoD
    if (previousMode === 'live' && !isLiveData && !isVodData) {
        // On vient de quitter le live mais pas encore de VoD disponible
        if (!liveToVodTransitionStart) {
            liveToVodTransitionStart = Date.now();
            _log("🔄 Transition live → VoD détectée - Période de grâce activée");
            loading.style.display = "block";
            loading.textContent = "⚡ Transition vers la playlist...";
            errorBox.style.display = "none";
        }
        
        const transitionElapsed = Date.now() - liveToVodTransitionStart;
        if (transitionElapsed < TRANSITION_GRACE_PERIOD_MS) {
            // Période de grâce : attendre sans afficher d'erreur
            _log(`⏳ Transition en cours (${Math.round(transitionElapsed/1000)}s/${TRANSITION_GRACE_PERIOD_MS/1000}s) - Attente VoD...`);
            return; // Ne pas afficher d'erreur, juste attendre
        } else {
            // Période de grâce expirée : considérer comme erreur
            _warn("⚠️ Transition live → VoD trop longue - Affichage erreur");
            liveToVodTransitionStart = null;
            showError();
            return;
        }
    }
    
    // ✅ Réinitialiser la transition si on a trouvé un mode valide
    if (isLiveData || isVodData) {
        liveToVodTransitionStart = null;
    }
    
    // Détecter la stabilité du mode
    if (modeChanged) {
        consecutiveStablePolls = 0;
        modeStableSince = Date.now();
    } else {
        consecutiveStablePolls++;
    }
    
    // ✅ Une seule URL pour la lecture : unified.m3u8 (live et VOD). L'API ne sert qu'à la dispo et aux métadonnées.
    const unifiedUrl = getUnifiedStreamUrl();

    if (isLiveData) {
        if (mode === 'live' && lastStreamId === d.stream_id && currentUrl === unifiedUrl) {
            lastData = d;
            loading.style.display = "none";
            errorBox.style.display = "none";
            if (!isRealtimeActive()) managePolling(false);
            return;
        }
        showLive(unifiedUrl, d);
    } else if (isVodData) {
        // VOD : on utilise la même URL unifiée (pas d.url), le serveur sert déjà le bon contenu.
        if (mode === 'vod' && currentUrl === unifiedUrl) {
            lastData = d;
            loading.style.display = "none";
            errorBox.style.display = "none";
            if (!isRealtimeActive()) managePolling(false);
            return;
        }
        showVODOptimized(unifiedUrl, d);
    } else {
        // ✅ Ne pas afficher d'erreur si on est en période de grâce
        if (liveToVodTransitionStart) {
            const transitionElapsed = Date.now() - liveToVodTransitionStart;
            if (transitionElapsed < TRANSITION_GRACE_PERIOD_MS) {
                _log("⏳ Mode indéterminé pendant transition - Attente...");
                return;
            }
        }
        showError();
    }
}

// 🚀 FONCTION PRINCIPALE OPTIMISÉE
async function getData() {
    const startTime = performance.now();
    
    try {
        const res = await fetch(API);
        const json = await res.json();
        
        // Métriques de performance
        const apiTime = performance.now() - startTime;
        performanceMetrics.apiCalls++;
        performanceMetrics.loadTimes.push(apiTime);
        updatePerfInfo(apiTime);
        
        if (!json.success || !json.data) return showError();
        
        const d = json.data.data || json.data; // Compatibilité structure

        // ✅ Utiliser la fonction centralisée pour traiter les données
        // (utilisée aussi par WebSocket)
        const previousMode = mode;
        handleStreamData(d);
        
        // Gérer le polling uniquement si WebSocket n'est pas actif
        const modeChanged = previousMode !== mode;
        if (!isRealtimeActive()) {
            loading.style.display = "none";
            errorBox.style.display = "none";
            managePolling(modeChanged);
        } else {
            // WebSocket actif - polling réduit ou désactivé
        loading.style.display = "none";
        errorBox.style.display = "none";
            // Garder un polling de sécurité toutes les 30 secondes
            if (!poll) {
                poll = setInterval(() => {
                    if (!isRealtimeActive()) {
                        getData();
                    }
                }, 30000); // Polling de sécurité toutes les 30s
            }
        }
        
    } catch (error) {
        // ✅ Diagnostic amélioré pour distinguer erreurs connexion internet vs serveur
        const errorType = error?.name || 'Unknown';
        const errorMessage = error?.message || String(error);
        const isNetworkError = errorType === 'TypeError' && errorMessage.includes('Failed to fetch');
        const isTimeout = errorMessage.includes('timeout') || errorMessage.includes('Timeout');
        
        _error("❌ Erreur API:", {
            type: errorType,
            message: errorMessage,
            isNetworkError: isNetworkError,
            isTimeout: isTimeout,
            diagnostic: isNetworkError 
                ? "🔍 Probable problème de connexion internet - Vérifiez votre connexion réseau"
                : isTimeout
                ? "🔍 Timeout - Peut être connexion lente ou serveur surchargé"
                : "🔍 Erreur serveur ou autre - Vérifiez les logs serveur"
        });
        showError();
    }
}

function applySnapshot(snapshot) {
    if (!snapshot || typeof snapshot !== "object") {
        showError();
        return;
    }

    const modeSnapshot = snapshot.mode;

    if (modeSnapshot === "paused") {
        showPaused(snapshot.message ?? "Diffusion en pause");
        currentUrl = null;
        return;
    }

    if (modeSnapshot === "error") {
        showError(snapshot.message ?? "Statut indisponible");
        currentUrl = null;
        return;
    }

    if (modeSnapshot === "live") {
        showLive(getUnifiedStreamUrl(), snapshot);
        return;
    }

    if (modeSnapshot === "vod") {
        showVODOptimized(getUnifiedStreamUrl(), snapshot);
        return;
    }

    showError(snapshot.message ?? "Statut inconnu");
}

function stopFreezeWatchdog() {
    if (freezeInterval) {
        clearInterval(freezeInterval);
        freezeInterval = null;
    }
}

function requestNextVideo(reason = "") {
    // ✅ Éviter la boucle infinie : respecter un délai minimum entre les tentatives
    const now = Date.now();
    if (reason === 'video error' && (now - lastErrorRetry) < MIN_ERROR_RETRY_DELAY) {
        const remaining = Math.ceil((MIN_ERROR_RETRY_DELAY - (now - lastErrorRetry)) / 1000);
        _log(`⏸️ Rechargement différé (${remaining}s restantes) pour éviter la boucle infinie`);
        setTimeout(() => requestNextVideo(reason), MIN_ERROR_RETRY_DELAY - (now - lastErrorRetry));
        return;
    }
    
    if (reason === 'video error') {
        lastErrorRetry = now;
    }
    
    _log(`⏭️ Transition forcée${reason ? ` (${reason})` : ""}`);
    stopFreezeWatchdog();
    currentUrl = null;
    // ✅ Réinitialiser lastStreamId pour forcer le rechargement en cas d'erreur
    if (reason === 'video error') {
        lastStreamId = null;
    }
    getData();
}

// ✅ Bonnes pratiques anti-gel : seuil buffer (s), intervalle watchdog (ms), nombre de ticks pour confirmer
const FREEZE_BUFFER_THRESHOLD_S = 0.5;   // Considérer buffer vide en dessous de 0.5s (réduit faux positifs)
const FREEZE_WATCHDOG_INTERVAL_MS = 4000; // Vérifier toutes les 4s (moins agressif)
const FREEZE_CONFIRM_TICKS = 2;           // Agir seulement après 2 ticks consécutifs (gel confirmé ~8s)

function startFreezeWatchdog() {
    stopFreezeWatchdog();
    lastProgressTime = video.currentTime;
    isBuffering = false;
    freezeConfirmCount = 0;

    freezeInterval = setInterval(() => {
        if (mode !== 'vod' || video.paused || !video.src) return;

        const currentTime = video.currentTime;
        const bufferedEnd = video.buffered.length > 0 ? video.buffered.end(video.buffered.length - 1) : 0;
        const bufferedAhead = bufferedEnd - currentTime;
        
        // ✅ Détecter les freezes : temps bloqué OU buffer vraiment vide (seuil 0.5s)
        const isTimeFrozen = currentTime === lastProgressTime && currentTime > 0 && video.readyState >= 2;
        const isBufferEmpty = bufferedAhead < FREEZE_BUFFER_THRESHOLD_S && !video.paused && video.readyState >= 2;
        const isBufferingState = video.readyState < 3 && !video.paused && currentTime > 0 && bufferedAhead < FREEZE_BUFFER_THRESHOLD_S;
        
        if (isTimeFrozen || isBufferEmpty || isBufferingState) {
            freezeConfirmCount += 1;
            // ✅ Bonne pratique : n'agir qu'après gel confirmé (2 ticks) pour éviter micro-seeks sur petits ralentissements
            if (freezeConfirmCount < FREEZE_CONFIRM_TICKS) {
                lastProgressTime = currentTime;
                return;
            }
            _warn("⚠️ Gel confirmé - récupération (buffer " + bufferedAhead.toFixed(2) + "s)", {
                isTimeFrozen, isBufferEmpty, isBufferingState, readyState: video.readyState
            });

            // Ne pas appeler requestNextVideo sur flux unifié
            if (lastData && !isUnifiedStreamUrl(lastData.url) && typeof lastData.duration === "number" &&
                lastData.duration >= MIN_DURATION_FOR_END &&
                currentTime >= lastData.duration - FREEZE_AT_END_THRESHOLD) {
                requestNextVideo("freeze at end");
                freezeConfirmCount = 0;
                lastProgressTime = currentTime;
                return;
            }

            const seekAmount = isBufferEmpty ? 1.0 : 0.5;
            video.currentTime = Math.min(currentTime + seekAmount, bufferedEnd > 0 ? bufferedEnd - 0.5 : currentTime + seekAmount);
            startPlaybackOptimized();
            freezeConfirmCount = 0;
        } else {
            freezeConfirmCount = 0;
        }

        lastProgressTime = currentTime;
    }, FREEZE_WATCHDOG_INTERVAL_MS);
}

function resetFreezeDetection() {
    lastProgressTime = video.currentTime;
}

function resetStallMonitor() {
    stallCount = 0;
    stallWindowStart = 0;
    isBuffering = false;
}

function registerStall(eventName) {
    const now = Date.now();

    if (!stallWindowStart || now - stallWindowStart > STALL_WINDOW_MS) {
        stallWindowStart = now;
        stallCount = 0;
    }

    stallCount += 1;

    if (stallCount >= STALL_LIMIT) {
        _warn(`⛔ Trop de blocages (${eventName}) - passage forcé`);
        requestNextVideo(`${eventName} overflow`);
        resetStallMonitor();
        return true;
    }

    return false;
}

function detachHls() {
    if (hlsInstance) {
        try {
            hlsInstance.detachMedia();
            hlsInstance.destroy();
        } catch (e) {
            _warn("⚠️ hls.js destroy:", e);
        }
        hlsInstance = null;
    }
}

function resetVideoElement() {
    detachHls();
    isLiveStream = false;
    video.autoplay = false;
    try {
        video.pause();
    } catch (_) {}
    video.removeAttribute('src');
    video.load();
}

function loadVideoSource(url, options = {}) {
    const { isLive = false, forceReinit = false } = options;
    
    // ✅ Réinitialiser le compteur d'erreurs pour une nouvelle vidéo
    errorRecoveryAttempts = 0;
    
    // ✅ Ne pas vider le player immédiatement - garder la dernière frame visible
    isLiveStream = isLive;
    
    // ✅ Détecter si on doit forcer une réinitialisation complète (transition Live → VoD)
    const previousWasLive = mode === 'live' && !isLive;
    const mustReinit = forceReinit || previousWasLive;
    
    // ✅ Vérifier si on peut réutiliser l'instance HLS.js existante
    // Cela évite de vider le player pendant la transition
    // ❌ MAIS : Ne JAMAIS réutiliser lors d'une transition Live → VoD
    const canReuseInstance = hlsInstance && hlsInstance.media === video && !mustReinit;
    
    // ✅ Détacher HLS.js si on ne peut pas réutiliser l'instance OU si on doit réinitialiser
    if (hlsInstance && (!canReuseInstance || mustReinit)) {
        try {
            if (mustReinit) {
                _log("🔄 Réinitialisation forcée HLS.js (transition Live → VoD)");
            }
            hlsInstance.detachMedia();
            hlsInstance.destroy();
        } catch (e) {
            _warn("⚠️ hls.js destroy:", e);
        }
        hlsInstance = null;
    }

    if (!url) {
        _warn("⚠️ URL vidéo manquante");
        return;
    }

    const isM3u8 = url.includes('.m3u8');

    // ✅ FORCER L'UTILISATION DE HLS.js POUR LE LIVE STREAM
    // Le lecteur natif a des problèmes avec les erreurs DEMUXER et ne peut pas récupérer
    // HLS.js gère beaucoup mieux les erreurs de segments et peut sauter automatiquement
    const forceHlsJsForLive = isLive && isM3u8;

    // Utiliser le lecteur natif seulement si ce n'est PAS un live stream
    if ((supportsNativeHls && isM3u8 && !forceHlsJsForLive) || !isM3u8) {
        video.src = url;
        video.load();

        if (isLive) {
            const seekToLiveEdge = () => {
                try {
                    if (video.seekable && video.seekable.length > 0) {
                        const liveEdge = video.seekable.end(video.seekable.length - 1);
                        if (Number.isFinite(liveEdge)) {
                            const startEdge = video.seekable.start(video.seekable.length - 1);
                            const span = liveEdge - startEdge;
                            const safetyOffset = span > 10 ? 3 : 0;
                            video.currentTime = Math.max(liveEdge - safetyOffset, startEdge);
                        }
                    }
                } catch (err) {
                    _warn("⚠️ Impossible de se caler sur le live (native):", err);
                }
                video.removeEventListener('loadedmetadata', seekToLiveEdge);
            };
            video.addEventListener('loadedmetadata', seekToLiveEdge);
        }
        return;
    }

    if (window.Hls && Hls.isSupported()) {
        // ✅ Réutiliser l'instance HLS.js existante si elle existe et que le player est attaché
        // Cela évite de vider le player pendant la transition (important pour VoD → Live)
        // ❌ MAIS : Ne JAMAIS réutiliser lors d'une transition Live → VoD
        const reuseInstance = hlsInstance && hlsInstance.media === video && !mustReinit;
        
        // ✅ Capturer l'ancienne instance AVANT le bloc if/else pour qu'elle soit accessible partout
        let oldHlsInstance = null;
        if (!reuseInstance) {
            oldHlsInstance = hlsInstance; // Capturer l'ancienne instance
        }
        
        if (!reuseInstance || mustReinit) {
            // Créer une nouvelle instance seulement si nécessaire
            // ✅ Pour une transition fluide VoD → Live, ne pas détacher immédiatement
            // On va créer la nouvelle instance et charger la source, puis détacher l'ancienne
            
            // ✅ Configuration différente pour Live vs VoD
            const hlsConfig = isLive ? {
                // ✅ Configuration optimisée pour LIVE (multi-viewers, anti-stall)
                enableWorker: true,
                lowLatencyMode: false,
                backBufferLength: 120,
                liveSyncDurationCount: 8,
                liveMaxLatencyDurationCount: 40,
                maxBufferLength: 120,
                maxMaxBufferLength: 300, // 5 min max pour absorber pics de latence
                startLevel: -1,
                capLevelToPlayerSize: false,
                debug: false,
                maxBufferHole: 1.5,
                highBufferWatchdogPeriod: 3,
                maxFragLoadingTimeMs: 60000,
                fragLoadingTimeOut: 60000,
                manifestLoadingTimeOut: 25000,
                maxLoadingDelay: 8, // Plus de segments en parallèle (réduit les stalls)
                maxBufferSize: 150 * 1000 * 1000, // 150 MB
            } : {
                // ✅ Configuration optimisée VoD + bonnes pratiques anti-gel
                enableWorker: true,
                lowLatencyMode: false,
                backBufferLength: 90,
                liveSyncDurationCount: 6,
                liveMaxLatencyDurationCount: 20,
                maxBufferLength: 120, // Marge de 120s pour absorber les micro-coupures
                maxMaxBufferLength: 240,
                startLevel: -1,
                capLevelToPlayerSize: false,
                debug: false,
                maxBufferHole: 0.5, // Détecter les trous tôt (réduit accumulation)
                highBufferWatchdogPeriod: 3, // Aligné avec watchdog custom (réduit réactions en double)
                maxFragLoadingTimeMs: 30000,
                fragLoadingTimeOut: 30000,
                manifestLoadingTimeOut: 10000,
                maxBufferSize: 60 * 1000 * 1000, // 60 MB
                // ✅ Récupération après buffer stall (nudge) : plus de marge pour réseaux instables
                nudgeOffset: 0.2,   // Avancer de 0.2s à chaque nudge pour franchir les micro-trous
                nudgeMaxRetry: 8,   // Plus de tentatives avant erreur fatale (évite bufferStalledError sur gels courts)
            };
            
        hlsInstance = new Hls(hlsConfig);
        
        // ✅ Gestion des erreurs HLS.js
        hlsInstance.on(Hls.Events.ERROR, (event, data) => {
            // ✅ Ne pas logger les bufferStalledError non-fatals en live (évite logs répétitifs)
            const shouldLog = !(isLive && !data.fatal && (data.details === 'bufferStalledError' || data.details === 'bufferAppendingError'));
            
            if (shouldLog) {
            _error("❌ Erreur HLS.js:", {
                type: data.type,
                details: data.details,
                fatal: data.fatal,
                url: url
            });
            }
            
            if (data.fatal) {
                // ✅ Détecter si on est en période de transition Live → VoD
                const isInTransition = liveToVodTransitionStart !== null;
                const transitionElapsed = isInTransition ? Date.now() - liveToVodTransitionStart : 0;
                const isWithinGracePeriod = isInTransition && transitionElapsed < TRANSITION_GRACE_PERIOD_MS;
                const isLevelParsingError = data.type === Hls.ErrorTypes.NETWORK_ERROR && data.details === 'levelParsingError';
                
                // ✅ Ignorer les levelParsingError pendant la transition Live → VoD (manifest peut être vide/invalide)
                if (isLevelParsingError && isWithinGracePeriod) {
                    _log(`⏳ Transition Live → VoD en cours (${Math.round(transitionElapsed/1000)}s/${TRANSITION_GRACE_PERIOD_MS/1000}s) - Ignorant levelParsingError normal pendant la transition`);
                    return; // Ne pas tenter de récupération pendant la transition
                }
                
                switch (data.type) {
                    case Hls.ErrorTypes.NETWORK_ERROR:
                        // ✅ Gestion améliorée des erreurs réseau avec retry et délai progressif
                        const isManifestTimeout = data.details === 'manifestLoadTimeOut';
                        const isFragLoadTimeout = data.details === 'fragLoadTimeOut';
                        
                        // ✅ Pour les timeouts, utiliser retry progressif avec délai croissant
                        if ((isManifestTimeout || isFragLoadTimeout) && errorRecoveryAttempts < MAX_RECOVERY_ATTEMPTS) {
                            errorRecoveryAttempts++;
                            // ✅ Délai progressif : 2s, 4s, 6s pour éviter de saturer le serveur
                            const progressiveDelay = RECOVERY_DELAY * errorRecoveryAttempts;
                            _log(`🔄 Tentative de récupération ${isManifestTimeout ? 'manifest' : 'segment'} ${errorRecoveryAttempts}/${MAX_RECOVERY_ATTEMPTS} (délai ${progressiveDelay}ms)...`);
                            
                            setTimeout(() => {
                                try {
                                    if (hlsInstance && hlsInstance.media === video) {
                                        // ✅ Pour les timeouts de segments, utiliser recoverMediaError qui est plus doux
                                        if (isFragLoadTimeout) {
                                            hlsInstance.recoverMediaError();
                                        } else {
                        hlsInstance.startLoad();
                                        }
                                        _log('✅ Récupération HLS déclenchée');
                                    }
                                } catch (e) {
                                    _error('❌ Erreur lors de la récupération:', e);
                                }
                            }, progressiveDelay);
                        } else {
                            _error("❌ Erreur réseau HLS.js - Tentative de récupération immédiate...");
                            errorRecoveryAttempts = 0; // Réinitialiser pour la prochaine fois
                            // ✅ Utiliser recoverMediaError pour les erreurs réseau non-timeout (plus doux)
                            if (hlsInstance && hlsInstance.media === video) {
                                try {
                                    hlsInstance.recoverMediaError();
                                } catch (e) {
                                    hlsInstance.startLoad();
                                }
                            }
                        }
                        break;
                    case Hls.ErrorTypes.MEDIA_ERROR:
                        _error("❌ Erreur média HLS.js - Tentative de récupération...");
                        hlsInstance.recoverMediaError();
                        break;
                    default:
                        _error("❌ Erreur fatale HLS.js - Rechargement du flux...");
                        forceReload = true;
                        requestNextVideo('hls fatal error');
                        break;
                }
            }
        });

            // ✅ Configurer les listeners pour le live seulement pour une nouvelle instance
        if (isLive) {
            let liveEdgeApplied = false;
            const seekLiveWithHls = (event, data) => {
                if (!liveEdgeApplied && data?.details) {
                    try {
                        let liveEdge = 0;
                        if (Number.isFinite(data.details.edge)) {
                            liveEdge = data.details.edge;
                        } else if (Number.isFinite(hlsInstance.liveSyncPosition)) {
                            liveEdge = hlsInstance.liveSyncPosition;
                        } else if (Number.isFinite(data.details.totalduration)) {
                            liveEdge = data.details.totalduration;
                        }

                        if (Number.isFinite(liveEdge) && liveEdge > 0) {
                            const safetyOffset = 5;
                            video.currentTime = Math.max(liveEdge - safetyOffset, 0);
                        }
                    } catch (err) {
                        _warn("⚠️ Impossible de se caler sur le live (hls.js):", err);
                    }
                    liveEdgeApplied = true;
                }
            };
            hlsInstance.on(Hls.Events.LEVEL_LOADED, seekLiveWithHls);
            hlsInstance.on(Hls.Events.DESTROYING, () => {
                hlsInstance.off(Hls.Events.LEVEL_LOADED, seekLiveWithHls);
            });
            }
        }

        if (reuseInstance) {
            // ✅ Réutiliser l'instance - juste changer la source (pas besoin de réattacher)
        hlsInstance.loadSource(url);
        } else {
            // ✅ Nouvelle instance - charger la source d'abord, puis attacher le média
            // Cela garde la dernière frame visible pendant le chargement (transition fluide)
            // Capturer oldHlsInstance dans une variable accessible aux callbacks
            const capturedOldInstance = oldHlsInstance;
            hlsInstance.loadSource(url);
            
            // ✅ Attacher le média seulement après que la source commence à charger
            // Cela évite de vider le player trop tôt (transition fluide VoD → Live)
            const onManifestLoading = () => {
                // Le manifest commence à charger - maintenant on peut attacher le média
                // Détacher l'ancienne instance seulement maintenant
                if (capturedOldInstance && capturedOldInstance.media === video) {
                    try {
                        capturedOldInstance.detachMedia();
                        capturedOldInstance.destroy();
                    } catch (e) {
                        _warn("⚠️ Erreur lors de la destruction ancienne instance:", e);
                    }
                }
                // Attacher le nouveau média
        hlsInstance.attachMedia(video);
                // Retirer le listener après utilisation
                hlsInstance.off(Hls.Events.MANIFEST_LOADING, onManifestLoading);
            };
            hlsInstance.on(Hls.Events.MANIFEST_LOADING, onManifestLoading);
            
            // ✅ Fallback : si MANIFEST_LOADING ne se déclenche pas rapidement, attacher quand même
            setTimeout(() => {
                if (hlsInstance && hlsInstance.media !== video) {
                    if (capturedOldInstance && capturedOldInstance.media === video) {
                        try {
                            capturedOldInstance.detachMedia();
                            capturedOldInstance.destroy();
                        } catch (e) {}
                    }
                    hlsInstance.attachMedia(video);
                }
            }, 500);
        }
        
        // ✅ Réinitialiser le compteur de tentatives quand le manifest est chargé avec succès
        hlsInstance.on(Hls.Events.MANIFEST_PARSED, (event, data) => {
            if (isLiveStream) {
                liveManifestRetryCount = 0;
            }
            errorRecoveryAttempts = 0; // ✅ Réinitialiser le compteur d'erreurs en cas de succès
        });
        
        hlsInstance.on(Hls.Events.ERROR, (event, data) => {
            // Log détaillé de l'erreur
            const errorDetails = {
                type: data?.type,
                fatal: data?.fatal,
                details: data?.details,
                url: data?.url,
                response: data?.response,
                error: data?.error,
                message: data?.message
            };
            _warn("⚠️ hls.js error", errorDetails);
            
            // Log spécifique pour les erreurs réseau (404, etc.)
            if (data?.type === Hls.ErrorTypes.NETWORK_ERROR) {
                const is404 = data?.response?.code === 404;
                const isLiveManifest = isLiveStream && data?.url && data.url.includes('.m3u8');
                
                // ✅ Diagnostic amélioré pour distinguer erreurs connexion internet vs serveur
                const httpStatus = data?.response?.code;
                const errorMessage = data?.details || data?.message || '';
                const isTimeout = errorMessage.includes('timeout') || errorMessage.includes('Timeout') || !httpStatus;
                const isConnectionError = errorMessage.includes('Network request failed') || errorMessage.includes('Failed to fetch');
                const isServerError = httpStatus >= 500 && httpStatus < 600;
                const isClientError = httpStatus >= 400 && httpStatus < 500;
                
                let diagnostic = '';
                if (isTimeout || isConnectionError) {
                    diagnostic = "🔍 Probable problème de connexion internet - Vérifiez votre connexion réseau";
                } else if (isServerError) {
                    diagnostic = "🔍 Erreur serveur (" + httpStatus + ") - Problème côté serveur";
                } else if (isClientError && httpStatus === 404) {
                    diagnostic = "🔍 404 - Fichier non trouvé (normal pendant transition Live → VoD)";
                } else if (isClientError) {
                    diagnostic = "🔍 Erreur client (" + httpStatus + ") - Vérifiez les paramètres de la requête";
                } else {
                    diagnostic = "🔍 Erreur réseau inconnue - Vérifiez les logs serveur";
                }
                
                _error("🌐 Erreur réseau HLS:", {
                    url: data?.url,
                    httpStatus: httpStatus,
                    message: errorMessage,
                    isLiveManifest: isLiveManifest,
                    retryCount: liveManifestRetryCount,
                    diagnostic: diagnostic
                });
                
                // ✅ Gestion spéciale pour les erreurs 404 sur les segments live
                // Les segments peuvent être supprimés rapidement, on doit récupérer
                const isLiveSegment = isLiveStream && data?.url && data.url.includes('.ts');
                if (is404 && isLiveSegment && !data?.fatal) {
                    // Erreur 404 sur un segment non fatale - HLS.js va essayer le suivant
                    return; // Laisser HLS.js gérer automatiquement
                }
                
                // ✅ Gestion spéciale pour les erreurs 404 sur le manifest live
                // Le manifest peut ne pas être immédiatement disponible au démarrage du live
                if (is404 && isLiveManifest && data?.fatal) {
                    liveManifestRetryCount++;
                    
                    if (liveManifestRetryCount < MAX_LIVE_MANIFEST_RETRIES) {
                        _log(`🔄 Manifest live 404 - Tentative ${liveManifestRetryCount}/${MAX_LIVE_MANIFEST_RETRIES} (le manifest peut ne pas être encore prêt)`);
                        // Attendre un peu avant de réessayer (le manifest peut être en cours de génération)
                        setTimeout(() => {
                            if (hlsInstance && mode === 'live') {
                                hlsInstance.startLoad();
                            }
                        }, 2000 * liveManifestRetryCount); // Délai progressif : 2s, 4s, 6s, 8s, 10s
                        return; // Ne pas traiter comme une erreur fatale pour l'instant
                    } else {
                        _warn(`⚠️ Manifest live 404 après ${MAX_LIVE_MANIFEST_RETRIES} tentatives - Le live n'est probablement pas encore prêt`);
                        // Après 5 tentatives, considérer que le live n'est pas disponible
                        // Ne pas basculer vers VoD immédiatement, attendre le prochain polling
                        liveManifestRetryCount = 0;
                        // Laisser hls.js gérer l'erreur normalement
                    }
                }
            }
            
            // ✅ Gestion des erreurs non fatales pour éviter les écrans noirs et coupures
            if (!data?.fatal) {
                // Erreurs non fatales : continuer la lecture si possible
                if (data?.type === Hls.ErrorTypes.NETWORK_ERROR && isLiveStream) {
                    // S'assurer que le player reste visible
                    if (video.style.display === "none") {
                        showLivePlayer();
                    }
                }
                
                // ✅ Gestion spécifique des erreurs de buffer qui causent des coupures
                if (data?.details === 'bufferStalledError' || data?.details === 'bufferAppendingError') {
                    // ✅ Pour le live, bufferStalledError non-fatal est souvent normal pendant le chargement initial
                    // Ignorer silencieusement pour éviter les logs répétitifs (HLS.js gère automatiquement)
                    if (hlsInstance && isLiveStream && !data.fatal) {
                        // Ne pas logger ni agir - HLS.js gère automatiquement ces erreurs non-fatales
                        return;
                    }
                    
                    // Pour VoD ou erreurs fatales, forcer HLS.js à réessayer
                    if (hlsInstance && (!isLiveStream || data.fatal)) {
                        setTimeout(() => {
                            if (hlsInstance && video.paused && video.readyState >= 2) {
                                startPlaybackOptimized();
                            }
                        }, 1000);
                    }
                }
                
                return; // Ne pas traiter les erreurs non fatales comme des erreurs fatales
            }
            
            // Erreurs fatales
            if (data?.fatal) {
                switch (data.type) {
                    case Hls.ErrorTypes.NETWORK_ERROR:
                        // Ne pas réessayer si c'est déjà une erreur 404 sur le manifest live gérée ci-dessus
                        if (!(isLiveStream && data?.url && data.url.includes('.m3u8') && data?.response?.code === 404 && liveManifestRetryCount < MAX_LIVE_MANIFEST_RETRIES)) {
                            // S'assurer que le player reste visible pendant la récupération
                            if (video.style.display === "none") {
                                showLivePlayer();
                            }
                            // ✅ Récupération silencieuse - pas de message "Reconnexion"
                            // Masquer le loading si visible et laisser HLS.js récupérer en arrière-plan
                            loading.style.display = "none";
                        hlsInstance.startLoad();
                        }
                        break;
                    case Hls.ErrorTypes.MEDIA_ERROR:
                        // S'assurer que le player reste visible pendant la récupération
                        if (video.style.display === "none") {
                            showLivePlayer();
                        }
                        loading.style.display = "block";
                        loading.textContent = "⚡ Récupération...";
                        hlsInstance.recoverMediaError();
                        break;
                    default:
                        _error("❌ Erreur fatale HLS - déconnexion", errorDetails);
                        // ✅ Récupération silencieuse - pas de message "Reconnexion"
                        // Masquer le loading et laisser HLS.js récupérer en arrière-plan
                        loading.style.display = "none";
                        // Ne pas cacher le player immédiatement, attendre un peu
                        setTimeout(() => {
                            if (video.readyState < 2) {
                        detachHls();
                        showError();
                            }
                        }, 3000);
                        break;
                }
            }
        });
    } else {
        _warn("⚠️ hls.js non supporté - fallback direct");
        video.src = url;
        video.load();
    }
}

// 🔥 NOUVELLE FONCTION VoD ULTRA-OPTIMISÉE
function showVODOptimized(url, d) {
    const loadStart = performance.now();
    
    // ✅ Réinitialiser la transition si on charge une VoD
    liveToVodTransitionStart = null;

    const isFinished = d.is_finished === true || (
        typeof d.current_time === "number" &&
        typeof d.duration === "number" &&
        d.current_time >= d.duration - 0.5
    );

    if (isFinished) {
        requestNextVideo(currentUrl === url ? "same media finished" : "api finished flag");
        return;
    }

    // ✅ Détecter spécifiquement la transition Live → VoD
    const isLiveToVodTransition = mode === 'live';
    
    if (currentUrl !== url || isLiveToVodTransition) {
        // Flux unifié : après requestNextVideo(), l'API renvoie souvent la même URL → ne pas recharger (évite redémarrage)
        if (isUnifiedStreamUrl(url) && !currentUrl && lastData && lastData.url === url && video.src) {
            currentUrl = url;
            mode = 'vod';
            lastData = d;
            updateSyncInfo(d);
            resetFreezeDetection();
            resetStallMonitor();
            startContinuousSync();
            startFreezeWatchdog();
            loading.style.display = 'none';
            errorBox.style.display = 'none';
            return;
        }
        // ✅ Si transition Live → VoD, réinitialiser complètement le lecteur
        if (isLiveToVodTransition) {
            _log("🔄 Transition Live → VoD - Réinitialisation complète du lecteur");
            
            // ✅ Mettre à jour les variables AVANT la réinitialisation
            mode = 'vod';
            currentUrl = url;
            lastData = d;
            
            // Afficher le splash screen pour une transition fluide
            showSplashScreen("⚡ Transition vers la playlist...");
            
            // ✅ Masquer le loading (le splash screen suffit)
            loading.style.display = "none";
            errorBox.style.display = "none";
            
            // ✅ S'assurer que le player est visible
            showVideoPlayer();
            
            // ✅ Détruire complètement l'instance HLS.js Live
            if (hlsInstance) {
                try {
                    _log("🧹 Détruction instance HLS.js Live...");
                    hlsInstance.detachMedia();
                    hlsInstance.destroy();
                } catch (e) {
                    _warn("⚠️ Erreur lors de la destruction HLS.js:", e);
                }
                hlsInstance = null;
            }
            
            // ✅ Réinitialiser complètement le lecteur vidéo
            resetVideoElement();
            
            // ✅ Réinitialiser les flags de statut
            isLiveStream = false;
            stopFreezeWatchdog();
            
            // Charger la source VoD avec réinitialisation forcée
            _log("✅ Chargement VoD après réinitialisation");
            loadVideoSource(url, { isLive: false, forceReinit: true });
            
            // ✅ Attendre que la vidéo charge et afficher le contenu
            const onVoDReady = () => {
                if (video.videoWidth > 0 && video.videoHeight > 0) {
                    // ✅ POSITIONNER À current_time AVANT de démarrer (correction position VOD)
                    if (typeof d.current_time === 'number' && d.current_time > 0) {
                        const targetTime = Math.max(0, d.current_time);
                        video.currentTime = targetTime;
                        _log(`⏱️ Position VOD (transition Live→VoD) appliquée: ${targetTime.toFixed(1)}s`);
                    }
                    
                    video.classList.add('has-content');
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            hideSplashScreen();
                        });
                    });
                    video.removeEventListener('loadeddata', onVoDReady);
                    video.removeEventListener('canplay', onVoDReady);
                    startPlaybackOptimized();
                    resetFreezeDetection();
                }
            };
            
            video.addEventListener('loadeddata', onVoDReady, { once: true });
            video.addEventListener('canplay', onVoDReady, { once: true });
            
            // Fallback : masquer le splash après un délai max
            setTimeout(() => {
                if (video.videoWidth > 0 && video.videoHeight > 0) {
                    video.classList.add('has-content');
                    hideSplashScreen();
                }
            }, 5000);
            
            return; // Sortir, la suite du code s'exécutera via les event listeners
        }
        
        _log("🎬 Nouvelle vidéo:", d.item_title);
        
        // ✅ Afficher le loading pendant TOUTES les transitions pour éviter l'écran noir
        loading.style.display = "block";
        loading.textContent = mode === 'live' ? "⚡ Transition vers la playlist..." : "⚡ Chargement...";
        errorBox.style.display = "none";
        
        // ✅ S'assurer que le player est visible immédiatement
        showVideoPlayer();

        const isM3u8 = url.includes('.m3u8');

        if (!isM3u8 && preloadedVideos.has(url)) {
            resetVideoElement();
            video.src = url;
            video.load();

            const targetTime = Number.isFinite(d.current_time) ? Math.max(0, d.current_time) : 0;
            const onSeeked = () => {
                video.removeEventListener('seeked', onSeeked);
                loading.style.display = "none"; // ✅ Masquer le loading après chargement
                    // ✅ Ajouter classe pour transition fond bleu → noir
                    if (video.videoWidth > 0 && video.videoHeight > 0) {
                        video.classList.add('has-content');
                        // Attendre un frame pour s'assurer que la frame est rendue
                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                // Double RAF pour garantir que la frame est affichée
                                hideSplashScreen();
                            });
                        });
                    } else {
                        // Si pas encore de frame, attendre un peu
                        setTimeout(() => {
                            video.classList.add('has-content');
                            hideSplashScreen();
                        }, 200);
                    }
                showVideoPlayer();
                startPlaybackOptimized();
                resetFreezeDetection();
            };

            video.addEventListener('seeked', onSeeked, { once: true });
            video.currentTime = targetTime;

            if (Math.abs(video.currentTime - targetTime) < 0.05) {
                onSeeked();
            }

            preloadedVideos.delete(url);
        } else {
            loadVideoSource(url);
            video.pause();

            video.addEventListener('loadeddata', () => {

                if (typeof d.duration === "number" && d.current_time >= d.duration - 0.5) {
                    requestNextVideo("loaded media already finished");
                    return;
                }

                // Chaîne linéaire (flux unifié) : rejoindre "maintenant" (live edge), pas reprendre une position
                const isUnified = isUnifiedStreamUrl(url);
                const targetTime = isUnified ? 0 : (Number.isFinite(d.current_time) ? Math.max(0, d.current_time) : 0);

                const onSeeked = () => {
                    video.removeEventListener('seeked', onSeeked);
                    loading.style.display = "none"; // ✅ Masquer le loading après chargement
                    // ✅ Ajouter classe pour transition fond bleu → noir
                    if (video.videoWidth > 0 && video.videoHeight > 0) {
                        video.classList.add('has-content');
                        // Attendre un frame pour s'assurer que la frame est rendue
                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                // Double RAF pour garantir que la frame est affichée
                                hideSplashScreen();
                            });
                        });
                    } else {
                        // Si pas encore de frame, attendre un peu
                        setTimeout(() => {
                            video.classList.add('has-content');
                            hideSplashScreen();
                        }, 200);
                    }
                    showVideoPlayer();
                    startPlaybackOptimized();
                    resetFreezeDetection();
                };

                // ✅ Pour HLS, attendre que le manifest soit parsé avant de positionner
                const isM3u8 = url.includes('.m3u8');
                if (isUnified && isM3u8 && hlsInstance) {
                    // Chaîne linéaire : rejoindre le direct (live edge) pour "continuer" à l'actualisation
                    let liveEdgeApplied = false;
                    const seekToLiveEdge = (event, data) => {
                        if (liveEdgeApplied) return;
                        try {
                            let edge = 0;
                            if (hlsInstance.liveSyncPosition != null && Number.isFinite(hlsInstance.liveSyncPosition)) {
                                edge = hlsInstance.liveSyncPosition;
                            } else if (data?.details && Number.isFinite(data.details.totalduration)) {
                                edge = data.details.totalduration;
                            } else if (video.seekable.length > 0) {
                                edge = video.seekable.end(video.seekable.length - 1);
                            }
                            if (Number.isFinite(edge) && edge > 0) {
                                const safety = 5;
                                video.currentTime = Math.max(edge - safety, 0);
                                _log(`📺 Chaîne linéaire: position au direct (${video.currentTime.toFixed(0)}s)`);
                            }
                        } catch (e) {
                            _warn("⚠️ Live edge (unified):", e);
                        }
                        liveEdgeApplied = true;
                        hlsInstance.off(Hls.Events.LEVEL_LOADED, seekToLiveEdge);
                        setTimeout(() => onSeeked(), 100);
                    };
                    hlsInstance.on(Hls.Events.LEVEL_LOADED, seekToLiveEdge);
                    setTimeout(() => {
                        if (!liveEdgeApplied) {
                            liveEdgeApplied = true;
                            hlsInstance.off(Hls.Events.LEVEL_LOADED, seekToLiveEdge);
                            onSeeked();
                        }
                    }, 4000);
                } else if (isM3u8 && hlsInstance && targetTime > 0) {
                    const seekToPosition = (event, data) => {
                        if (video.readyState >= 2) {
                            video.currentTime = targetTime;
                            _log(`⏱️ Position HLS appliquée: ${targetTime.toFixed(1)}s`);
                            setTimeout(() => onSeeked(), 100);
                        }
                        hlsInstance.off(Hls.Events.MANIFEST_PARSED, seekToPosition);
                        hlsInstance.off(Hls.Events.LEVEL_LOADED, seekToPosition);
                    };
                    hlsInstance.on(Hls.Events.MANIFEST_PARSED, seekToPosition);
                    hlsInstance.on(Hls.Events.LEVEL_LOADED, seekToPosition);
                    setTimeout(() => {
                        if (video.readyState >= 2 && Math.abs(video.currentTime - targetTime) > 0.5) {
                            video.currentTime = targetTime;
                            onSeeked();
                        }
                    }, 3000);
                } else {
                    video.addEventListener('seeked', onSeeked, { once: true });
                    video.currentTime = targetTime;
                    if (Math.abs(video.currentTime - targetTime) < 0.05) {
                        onSeeked();
                    }
                }
            }, { once: true });
        }

        currentUrl = url;
        mode = "vod";

        preloadNextVideos(d);
    } else {
        // Sur flux unifié, current_time du backend = temps dans l'item courant, pas position dans le stream → ne pas seek ni "player reached duration"
        if (!isUnifiedStreamUrl(url)) {
            if (typeof d.duration === "number" && d.duration >= MIN_DURATION_FOR_END &&
                video.currentTime >= d.duration - FREEZE_AT_END_THRESHOLD) {
                requestNextVideo("player reached duration");
                return;
            }
            const diff = Number.isFinite(video.currentTime) && Number.isFinite(d.current_time)
                ? Math.abs(video.currentTime - d.current_time)
                : 0;
            if (Number.isFinite(diff) && diff > 5) {
                const targetTime = Number.isFinite(d.current_time) ? Math.max(0, d.current_time) : 0;
                video.currentTime = targetTime;
                resetFreezeDetection();
            }
        }
    }

    updateSyncInfo(d);
    lastData = d;
    resetFreezeDetection();
    resetStallMonitor();
    startContinuousSync();
    startFreezeWatchdog();
}

// 🎯 PRELOADING INTELLIGENT DES PROCHAINES VIDÉOS
function preloadNextVideos(currentData) {
    // Si l'API fournit next_items, les utiliser
    if (currentData.next_items && currentData.next_items.length > 0) {
        currentData.next_items.forEach((nextItem, index) => {
            setTimeout(() => preloadVideo(nextItem.url), index * 1000);
        });
    } else {
        // Sinon, preloader de manière prédictive
        setTimeout(() => {
            fetch(API)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data) {
                        const nextData = data.data.data || data.data;
                        if (nextData.url && nextData.url !== currentUrl) {
                            preloadVideo(nextData.url);
                        }
                    }
                })
                .catch(() => {});
        }, 2000);
    }
}

// 🎯 FONCTION DE PRELOADING
function preloadVideo(url) {
    if (!url || url.includes('.m3u8')) {
        return; // hls.js gère le buffering pour les playlists
    }
    if (!preloadedVideos.has(url) && url !== currentUrl) {
        _log("🔄 Preloading:", url.split('/').pop());
        
        const preloadVideo = document.createElement('video');
        preloadVideo.src = url;
        preloadVideo.preload = 'metadata';
        preloadVideo.muted = true;
        preloadVideo.style.display = 'none';
        
        preloadVideo.addEventListener('loadeddata', () => {
            _log("✅ Preloadé:", url.split('/').pop());
            preloadedVideos.set(url, preloadVideo);
            
            // Nettoyer après 5 minutes
            setTimeout(() => {
                preloadedVideos.delete(url);
                preloadVideo.remove();
            }, 300000);
        });
        
        document.body.appendChild(preloadVideo);
    }
}

// ⚡ DÉMARRAGE DE LECTURE OPTIMISÉ
let canplayListenerAdded = false; // ✅ Empêcher plusieurs listeners canplay
let isProcessingPlayback = false; // ✅ Flag pour empêcher appels simultanés

function startPlaybackOptimized() {
    // ✅ Protection renforcée : bloquer si déjà en cours de traitement
    if (isProcessingPlayback) {
        _log("⏸️ startPlaybackOptimized ignoré (traitement en cours)", {
            readyState: video.readyState,
            paused: video.paused
        });
        return;
    }
    
    const now = Date.now();
    
    // ✅ Vérifier si un appel récent existe (protection contre appels multiples)
    if (startPlaybackLock && (now - startPlaybackLock) < STARTPLAYBACK_DEBOUNCE_MS) {
        _log("⏸️ startPlaybackOptimized ignoré (appel récent)", {
            timeSinceLastCall: (now - startPlaybackLock) + 'ms',
            readyState: video.readyState,
            paused: video.paused
        });
        return;
    }
    
    // ✅ Définir les verrous IMMÉDIATEMENT pour bloquer les appels suivants
    isProcessingPlayback = true;
    startPlaybackLock = now;
    
    _log("🎬 startPlaybackOptimized appelé", {
        readyState: video.readyState,
        paused: video.paused,
        mode: mode,
        currentUrl: currentUrl
    });
    
    // Vérifier l'état de la vidéo avant de jouer
    if (video.readyState >= 2) { // HAVE_CURRENT_DATA
        _log("▶️ Tentative de lecture...");
        playPromise = video.play();
        
        if (playPromise !== undefined) {
            playPromise.then(() => {
                _log("▶️ Lecture démarrée");
                isBuffering = false;
                resetStallMonitor();
                resetFreezeDetection();
                playPromise = null;
                // ✅ Libérer les verrous après succès (avec délai pour éviter appels immédiats)
                setTimeout(() => {
                    isProcessingPlayback = false;
                    startPlaybackLock = null;
                    canplayListenerAdded = false; // ✅ Réinitialiser aussi le flag
                }, STARTPLAYBACK_DEBOUNCE_MS);
            }).catch(error => {
                _log("⚠️ Autoplay:", error.name);
                
                if (error.name === 'AbortError') {
                    // Retry plus rapide
                    setTimeout(() => {
                        playPromise = video.play();
                        if (playPromise) {
                            playPromise.then(() => {
                                _log("▶️ Lecture redémarrée");
                                isBuffering = false;
                                resetStallMonitor();
                                resetFreezeDetection();
                                playPromise = null;
                                // ✅ Libérer les verrous après succès
                                setTimeout(() => {
                                    isProcessingPlayback = false;
                                    startPlaybackLock = null;
                                    canplayListenerAdded = false;
                                }, STARTPLAYBACK_DEBOUNCE_MS);
                            }).catch(() => {
                                playPromise = null;
                                // ✅ Libérer les verrous en cas d'erreur
                                setTimeout(() => {
                                    isProcessingPlayback = false;
                                    startPlaybackLock = null;
                                    canplayListenerAdded = false;
                                }, STARTPLAYBACK_DEBOUNCE_MS);
                            });
                        } else {
                            // ✅ Libérer les verrous si pas de playPromise
                            setTimeout(() => {
                                isProcessingPlayback = false;
                                startPlaybackLock = null;
                                canplayListenerAdded = false;
                            }, STARTPLAYBACK_DEBOUNCE_MS);
                        }
                    }, 100); // Réduit à 100ms
                } else {
                    playPromise = null;
                    // ✅ Libérer les verrous en cas d'erreur
                    setTimeout(() => {
                        isProcessingPlayback = false;
                        startPlaybackLock = null;
                        canplayListenerAdded = false;
                    }, STARTPLAYBACK_DEBOUNCE_MS);
                }
            });
        } else {
            // ✅ Libérer les verrous si pas de playPromise
            setTimeout(() => {
                isProcessingPlayback = false;
                startPlaybackLock = null;
                canplayListenerAdded = false;
            }, STARTPLAYBACK_DEBOUNCE_MS);
        }
    } else {
        // Attendre que la vidéo soit prête
        // ✅ Empêcher plusieurs listeners canplay (protection contre appels multiples)
        if (!canplayListenerAdded) {
            canplayListenerAdded = true;
            video.addEventListener('canplay', () => {
                canplayListenerAdded = false; // ✅ Réinitialiser pour permettre un futur listener
                // ✅ Réinitialiser les verrous avant l'appel récursif
                isProcessingPlayback = false;
                startPlaybackLock = null;
                startPlaybackOptimized();
            }, { once: true });
        } else {
            // ✅ Si listener déjà ajouté, libérer le flag de traitement
            isProcessingPlayback = false;
        }
    }
}

// 🎬 AFFICHAGE DU LECTEUR VIDÉO
function showVideoPlayer() {
    iframe.style.display = "none";
    video.style.display = "block";
    syncInfo.style.display = "block";
    perfInfo.style.display = "block";
}

function showLivePlayer() {
    iframe.style.display = "none";
    video.style.display = "block";
    syncInfo.style.display = "none";
    perfInfo.style.display = "none";
}

// 📺 FONCTION LIVE ULTRA-PROTÉGÉE
function showLive(url, d) {
    // 🛡️ PROTECTION TRIPLE contre les reconnexions
    if (currentUrl === url && iframe.style.display === "block" && mode === 'live') {
        _log("📺 Live déjà actif - AUCUNE reconnexion (économie bande passante)");
        return;
    }
    
    // 🔒 Vérifier si c'est vraiment un nouveau stream
    // ✅ Permettre le rechargement si forceReload est activé (erreur vidéo)
    if (lastStreamId === d.stream_id && mode === 'live' && !forceReload) {
        _log("📺 Même stream Live - Pas de rechargement");
        return;
    }
    
    // ✅ Réinitialiser forceReload si on recharge
    if (forceReload) {
        _log("🔄 Rechargement forcé suite à une erreur");
        forceReload = false;
    }
    
    const realtimeSource = sseConnected ? 'SSE' : (websocketConnected ? 'WebSocket' : 'polling');
    _log(`📺 NOUVELLE connexion Live (${realtimeSource}):`, d.stream_id);
    lastStreamId = d.stream_id;
    
    // ✅ Réinitialiser le compteur de tentatives pour le nouveau stream live
    liveManifestRetryCount = 0;
    liveToVodTransitionStart = null; // Réinitialiser la transition
    
    // Nettoyer VoD complètement
    if (sync) {
        clearInterval(sync);
        sync = null;
    }

    stopFreezeWatchdog();
    
    // ✅ Afficher le splash screen lors de la transition vers le live
    const isTransition = mode === 'vod';
    if (isTransition) {
        showSplashScreen("⚡ Transition vers le live...");
    }
    
    // ✅ S'assurer que le player est visible AVANT de faire quoi que ce soit
    showLivePlayer();
    
    // ✅ Afficher le loading en overlay (sans cacher le player) pour éviter l'écran noir
    loading.style.display = "block";
    loading.textContent = "⚡ Chargement du live...";
    loading.style.zIndex = "1000"; // S'assurer que le loading est au-dessus
    errorBox.style.display = "none";
    
    // ✅ Charger directement (HLS.js gère automatiquement les retry)
    // Pas besoin de préchargement redondant - on charge directement
    loadVideoSource(url, { isLive: true });
    
    syncInfo.style.display = "none";
    perfInfo.style.display = "none";
    
    // ✅ Gestion unifiée du loading - masquer quand le flux est vraiment prêt avec première frame visible
    const hideLoadingWhenReady = () => {
        // ✅ Vérifier que la vidéo a vraiment une frame visible (évite l'écran noir)
        const hasVisibleFrame = video.videoWidth > 0 && video.videoHeight > 0;
        const isReady = video.readyState >= 3; // HAVE_FUTURE_DATA ou HAVE_ENOUGH_DATA
        
        if (isReady && hasVisibleFrame) {
            loading.style.display = "none";
            // ✅ Ajouter classe pour transition fond bleu → noir
            video.classList.add('has-content');
            // ✅ Masquer le splash screen seulement quand la première frame est visible
            // Utiliser requestAnimationFrame pour s'assurer que la frame est bien rendue
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    // Double RAF pour garantir que la frame est affichée
                    hideSplashScreen();
                });
            });
        startPlaybackOptimized();
            return true;
        }
        return false;
    };
    
    // ✅ Écouter plusieurs événements pour détecter quand la première frame est visible
    const checkFrameVisible = () => {
        if (video.videoWidth > 0 && video.videoHeight > 0) {
            hideLoadingWhenReady();
        }
    };
    
    // Écouter loadeddata, canplay, playing pour masquer le loading
    video.addEventListener('loadeddata', () => {
        checkFrameVisible();
        if (!hideLoadingWhenReady()) {
            // Si pas encore prêt, attendre loadedmetadata
            video.addEventListener('loadedmetadata', checkFrameVisible, { once: true });
        }
    }, { once: true });
    
    video.addEventListener('canplay', () => {
        checkFrameVisible();
        hideLoadingWhenReady();
    }, { once: true });
    
    // ✅ Écouter playing pour être sûr que la vidéo affiche vraiment une frame
    video.addEventListener('playing', () => {
        if (!splashScreen.classList.contains('hidden')) {
            // Si le splash est encore visible quand playing commence, le masquer
            hideLoadingWhenReady();
        }
    }, { once: true });
    
    // ✅ Fallback si les événements ne se déclenchent pas rapidement
    setTimeout(() => {
        if (!hideLoadingWhenReady()) {
            loading.textContent = "⚡ Connexion en cours...";
            // Réessayer après 2 secondes
            setTimeout(() => {
                // Forcer le masquage si on a au moins des métadonnées
                if (video.readyState >= 2) {
                    hideLoadingWhenReady();
                }
            }, 2000);
        }
    }, 3000);

    currentUrl = url;
    mode = 'live';
    lastData = d; // ✅ Mettre à jour lastData pour garder les infos à jour

    _log(`📺 Live connecté (${realtimeSource})`);
    if (!isRealtimeActive()) {
    _log("📺 Live connecté - Polling adaptatif (8-10s)");
    }
}

// 🔄 SYNCHRONISATION CONTINUE OPTIMISÉE
function startContinuousSync() {
    if (sync) clearInterval(sync);
    
    sync = setInterval(() => {
        if (mode !== 'vod' || !lastData) return;
        // Sur flux unifié, lastData.current_time = temps dans l'item, pas position stream → ne pas resync (évite retour en arrière)
        if (isUnifiedStreamUrl(lastData.url)) return;
        
        const now = Math.floor(Date.now() / 1000);
        const expected = lastData.current_time + (now - lastData.sync_timestamp);
        const timeDiff = Math.abs(video.currentTime - expected);
        const RESYNC_THRESHOLD = 12;

        if (!video.paused && !isBuffering && video.readyState >= 2 && timeDiff > RESYNC_THRESHOLD) {
            let safeTarget = expected;
            if (typeof lastData.duration === 'number') {
                safeTarget = Math.min(expected, Math.max(0, lastData.duration - 0.25));
            }

            safeTarget = Math.max(0, safeTarget);
            _log(`🔄 Resync auto: ${video.currentTime.toFixed(1)}s → ${safeTarget.toFixed(1)}s`);
            video.currentTime = safeTarget;
        }
    }, 15000);
}

// 📊 AFFICHAGE DES INFOS DE SYNC
function updateSyncInfo(d) {
    // Protection contre les valeurs manquantes
    const itemTitle = d.item_title || 'Titre inconnu';
    const currentTime = d.current_time || 0;
    const duration = d.duration || 0;
    
    const minutes = Math.floor(currentTime / 60);
    const seconds = Math.floor(currentTime % 60);
    const totalMinutes = Math.floor(duration / 60);
    const totalSeconds = Math.floor(duration % 60);
    
    syncInfo.innerHTML = `
        <strong>📺 ${itemTitle.substring(0, 30)}${itemTitle.length > 30 ? '...' : ''}</strong><br>
        ⏱️ ${minutes}:${seconds.toString().padStart(2, '0')} / ${totalMinutes}:${totalSeconds.toString().padStart(2, '0')}<br>
        🔄 <span style="color: #4CAF50;">Synchronisé globalement</span>
    `;
}

// 📈 AFFICHAGE DES MÉTRIQUES DE PERFORMANCE
function updatePerfInfo(apiTime) {
    const avgTime = performanceMetrics.loadTimes.reduce((a, b) => a + b, 0) / performanceMetrics.loadTimes.length;
    const preloadCount = preloadedVideos.size;
    
    perfInfo.innerHTML = `
        API: ${apiTime.toFixed(0)}ms | Avg: ${avgTime.toFixed(0)}ms<br>
        Calls: ${performanceMetrics.apiCalls} | Cache: ${preloadCount}
    `;
    
    // Garder seulement les 10 dernières mesures
    if (performanceMetrics.loadTimes.length > 10) {
        performanceMetrics.loadTimes = performanceMetrics.loadTimes.slice(-10);
    }
}

// 🔄 POLLING INTELLIGENT (inchangé)
// ✅ POLLING ADAPTATIF OPTIMISÉ
function managePolling(modeChanged = false) {
    if (poll) clearInterval(poll);

    // Si le mode vient de changer, polling rapide pour confirmer
    if (modeChanged) {
        const quickInterval = mode === 'live' ? 3000 : 2500; // 3s live, 2.5s VoD (plus réactif)
        _log(`🔄 Mode changé - Polling rapide (${quickInterval/1000}s) pour confirmation`);
        poll = setInterval(() => {
            getData();
        }, quickInterval);
        return;
    }

    // Si WebSocket ou SSE est actif, garder seulement un polling de sécurité
    if (isRealtimeActive()) {
        _log("📡 Connexion temps réel active - Polling de sécurité (30s)");
        poll = setInterval(() => {
            if (!isRealtimeActive()) {
                getData();
            }
        }, 30000);
        return;
    }

    // Polling adaptatif basé sur la stabilité
    // ✅ Délais réduits : avec les protections (pas de rechargement si statut inchangé),
    //    on peut être plus réactif sans surcharger le serveur
    if (mode === 'live') {
        // Live : polling plus fréquent si instable, moins fréquent si stable
        let interval;
        if (consecutiveStablePolls >= STABLE_MODE_THRESHOLD) {
            interval = 10000; // 10s si stable (au lieu de 20s - meilleure réactivité)
            _log("📺 Mode Live stable - Polling réduit (10s)");
        } else {
            interval = 8000; // 8s si instable (au lieu de 15s - détection plus rapide)
            _log(`📺 Mode Live - Polling actif (8s) - Stabilité: ${consecutiveStablePolls}/${STABLE_MODE_THRESHOLD}`);
        }
        poll = setInterval(() => {
            getData();
        }, interval);
        return;
    }

    // VoD : polling moins fréquent si stable
    let interval;
    if (consecutiveStablePolls >= STABLE_MODE_THRESHOLD) {
        interval = 10000; // 10s si stable (au lieu de 15s - meilleure réactivité)
        _log("📡 Mode VoD stable - Polling réduit (10s)");
    } else {
        interval = 8000; // 8s si instable (au lieu de 12s - détection plus rapide)
        _log(`📡 Mode VoD - Polling actif (8s) - Stabilité: ${consecutiveStablePolls}/${STABLE_MODE_THRESHOLD}`);
    }
    poll = setInterval(() => {
        getData();
    }, interval);
}

// 🎬 GESTION DE FIN DE VIDÉO
video.addEventListener('ended', () => {
    _log("🏁 Vidéo terminée (événement ended) - Transition immédiate");
    requestNextVideo("HTML5 ended");

    if (typeof gtag !== 'undefined') {
        gtag('event', 'webtv_video_completed', {
            'event_category': 'WebTV',
            'video_duration': Math.round(video.duration)
        });
    }
});
// 🔄 GESTION D'ERREUR VIDÉO - AMÉLIORÉE
video.addEventListener('error', (e) => {
    const error = video.error;
    const errorDetails = {
        code: error ? error.code : 'unknown',
        message: error ? error.message : 'unknown',
        MEDIA_ERR_ABORTED: 1,
        MEDIA_ERR_NETWORK: 2,
        MEDIA_ERR_DECODE: 3,
        MEDIA_ERR_SRC_NOT_SUPPORTED: 4,
        currentSrc: video.currentSrc,
        networkState: video.networkState,
        readyState: video.readyState
    };
    
    _error("❌ Erreur vidéo:", errorDetails);
    
    // ✅ Gestion spéciale pour les erreurs DEMUXER_ERROR_COULD_NOT_PARSE
    // Ne pas recharger immédiatement si HLS.js peut gérer la récupération
    const errorMessage = String(errorDetails.message || '').toUpperCase();
    const isDemuxerError = errorMessage.includes('DEMUXER_ERROR_COULD_NOT_PARSE') ||
                           errorMessage.includes('PIPELINESTATUS::DEMUXER_ERROR') ||
                           errorMessage.includes('DEMUXER_ERROR') ||
                           (errorDetails.code === 4 && errorMessage.includes('PIPELINE'));
    
    _log("🔍 Diagnostic erreur DEMUXER:", {
        isDemuxerError: isDemuxerError,
        errorMessage: errorMessage,
        errorCode: errorDetails.code,
        hlsInstanceExists: !!hlsInstance,
        willTryRecovery: !!(hlsInstance && isDemuxerError)
    });
    
    // ✅ Si on utilise HLS.js, laisser HLS.js gérer la récupération pour les erreurs DEMUXER
    // On vérifie simplement si hlsInstance existe (peut être attaché ou non)
    if (hlsInstance && isDemuxerError) {
        _warn("⚠️ Erreur DEMUXER détectée - HLS.js va tenter la récupération automatique", {
            code: errorDetails.code,
            message: errorDetails.message,
            hlsInstanceExists: !!hlsInstance,
            hlsMediaAttached: hlsInstance?.media === video
        });
        
        // Donner à HLS.js une chance de récupérer avant de forcer un rechargement
        // HLS.js peut sauter le segment problématique et continuer
        setTimeout(() => {
            // Vérifier si HLS.js a réussi à récupérer
            if (video.error && video.readyState < 2) {
                // Si l'erreur persiste après 3 secondes, alors forcer le rechargement
                _error("❌ HLS.js n'a pas pu récupérer - rechargement forcé", {
                    error: video.error,
                    readyState: video.readyState
                });
                forceReload = true;
                requestNextVideo('video error - hls recovery failed');
            } else {
                _log("✅ HLS.js a réussi à récupérer de l'erreur DEMUXER");
            }
        }, 3000);
        return; // Ne pas recharger immédiatement
    }
    
    // ✅ Pour les autres erreurs ou si HLS.js n'est pas disponible, recharger immédiatement
    forceReload = true;
    requestNextVideo('video error');
});

video.addEventListener('stalled', () => {
    _warn("⚠️ Flux stalled - tentative de reprise");
    isBuffering = true;
    if (registerStall('stalled')) return;
    resetFreezeDetection();
    startPlaybackOptimized();
});

video.addEventListener('waiting', () => {
    _log("⏳ Buffer en attente - surveillance renforcée");
    isBuffering = true;
    if (registerStall('waiting')) return;
    resetFreezeDetection();
});

video.addEventListener('timeupdate', () => {
    isBuffering = false;
    resetStallMonitor();
    resetFreezeDetection();

    if (mode === 'live' && isLiveStream && Number.isFinite(video.duration) && Number.isFinite(video.currentTime)) {
        const liveGap = video.duration - video.currentTime;
        // ✅ Recentrage plus conservateur : seulement si gap > 15s et ne pas sauter trop loin
        if (liveGap > 15) {
            // ✅ Limiter le saut à maximum 10 secondes pour éviter les gaps trop importants
            const maxJump = 10;
            const target = Math.max(video.duration - 8, video.currentTime + maxJump);
            // ✅ Ne recentrer que si le saut est raisonnable (évite les sauts de 50s+)
            if (Math.abs(target - video.currentTime) <= maxJump * 2) {
                _log(`🎯 Recentrage live conservateur: gap=${liveGap.toFixed(1)}s → ${target.toFixed(1)}s`);
            video.currentTime = target;
            } else {
                _warn(`⚠️ Gap trop important (${liveGap.toFixed(1)}s) - recentrage ignoré pour éviter saut trop grand`);
            }
            return;
        }
    }

    if (lastData && typeof lastData.duration === 'number') {
        const remaining = lastData.duration - video.currentTime;
        if (remaining <= 1 && remaining > 0) {
            resetFreezeDetection();
        }
    }
});

// ❌ FONCTION D'ERREUR
function showError(message) {
    liveToVodTransitionStart = null; // ✅ Réinitialiser la transition
    loading.style.display = "none";
    // ✅ Masquer le splash screen en cas d'erreur
    hideSplashScreen();
    errorBox.style.display = "block";
    errorBox.innerHTML = message ? `<p>${message}</p><p>Veuillez réessayer plus tard.</p>` : defaultErrorHTML;
    iframe.style.display = "none";
    // ✅ Ne pas cacher le player immédiatement pour éviter l'écran noir
    // Garder la dernière frame visible pendant 2 secondes avant de cacher
    setTimeout(() => {
        if (video.readyState < 2) { // Seulement si la vidéo n'est pas chargée
    video.style.display = "none";
        }
    }, 2000);
    syncInfo.style.display = "none";
    perfInfo.style.display = "none";
    
    if (poll) clearInterval(poll);
    if (sync) clearInterval(sync);
    if (errorRetry) clearInterval(errorRetry);
    stopFreezeWatchdog();
    preloadedVideos.clear();

    errorRetry = setInterval(() => {
        _log("🔁 Retry après erreur");
        getData();
    }, 10000);
}

function showPaused(message = "Diffusion en pause - Reprise automatique...") {
    loading.style.display = "none";
    // ✅ Masquer le splash screen en cas de pause
    hideSplashScreen();
    errorBox.style.display = "block";
    errorBox.innerHTML = `
        <p>${message}</p>
        <p>La diffusion reprendra automatiquement dès qu'elle sera relancée.</p>
    `;
    iframe.style.display = "none";
    video.style.display = "none";
    syncInfo.style.display = "none";
    perfInfo.style.display = "none";

    lastData = null;
    currentUrl = null;
    mode = 'paused';

    if (poll) clearInterval(poll);
    if (sync) clearInterval(sync);
    if (errorRetry) clearInterval(errorRetry);
    stopFreezeWatchdog();
    preloadedVideos.clear();

    poll = setInterval(() => {
        _log("🔁 Vérification reprise après pause");
        getData();
    }, 10000);
}

// 📊 TRACKING OPTIMISÉ
let watchTimeTracker = null;

video.addEventListener('play', () => {
    if (watchTimeTracker) clearInterval(watchTimeTracker);
    resetFreezeDetection();
    resetStallMonitor();
    isBuffering = false;
    startFreezeWatchdog();
    
    watchTimeTracker = setInterval(() => {
        if (!video.paused && video.currentTime > 0) {
            if (typeof gtag !== 'undefined') {
                gtag('event', 'webtv_watch_time_optimized', {
                    'event_category': 'WebTV',
                    'value': Math.round(video.currentTime / 60),
                    'preload_cache_size': preloadedVideos.size
                });
            }
        }
    }, 60000);
});

video.addEventListener('playing', () => {
    isBuffering = false;
    resetStallMonitor();
});

video.addEventListener('pause', () => {
    if (watchTimeTracker) {
        clearInterval(watchTimeTracker);
        watchTimeTracker = null;
    }
    stopFreezeWatchdog();
    isBuffering = false;
});

// 🧹 NETTOYAGE
window.addEventListener('beforeunload', () => {
    if (poll) clearInterval(poll);
    if (sync) clearInterval(sync);
    stopFreezeWatchdog();
    if (watchTimeTracker) clearInterval(watchTimeTracker);
    preloadedVideos.clear();
    if (sseSource) {
        try {
            sseSource.close();
        } catch (_) {}
    }
    if (window.Echo && websocketChannel) {
        try {
            websocketChannel.stopListening('.stream.status.changed');
            window.Echo.leave('webtv-stream-status');
        } catch (_) {}
    }
});

// ✅ WEBSOCKET : Initialisation et écoute des événements
function initWebSocket() {
    try {
        if (!window.Echo) {
            _warn("⚠️ Laravel Echo non disponible, utilisation du polling uniquement");
            websocketFallbackActive = true;
            return;
        }
        
        // S'abonner au canal public
        websocketChannel = window.Echo.channel('webtv-stream-status');
        
        // Écouter les changements de statut (guard: Reverb peut envoyer des événements sans data)
        websocketChannel.listen('.stream.status.changed', (data) => {
            try {
                if (!data || typeof data !== 'object') {
                    _warn("📡 Event WebSocket sans données valides, ignoré:", data);
                    return;
                }
                _log("📡 Event WebSocket reçu:", data);
                websocketConnected = true;

                // Traiter les données comme si elles venaient de l'API
                const streamData = {
                    success: true,
                    data: {
                        mode: data.mode,
                        stream_id: data.stream_id,
                        stream_name: data.stream_name,
                        url: data.url || data.stream_url,
                        stream_url: data.stream_url || data.url,
                        sync_timestamp: data.sync_timestamp || Date.now() / 1000,
                        item_title: data.item_title || null,
                        item_id: data.item_id || null,
                        current_time: data.current_time || 0.0,
                        duration: data.duration || null,
                        is_finished: data.is_finished || false,
                    }
                };

                handleStreamData(streamData.data);
            } catch (e) {
                _error("📡 Erreur traitement event WebSocket:", e);
            }
        });
        
        // Gestion des erreurs de connexion
        window.Echo.connector.pusher.connection.bind('error', (err) => {
            _error("❌ Erreur WebSocket:", err);
            websocketConnected = false;
            if (!websocketFallbackActive) {
                _log("🔄 Activation du fallback polling");
                websocketFallbackActive = true;
                websocketSnapshotRequested = false;
                // S'assurer que le polling est actif
                if (!poll) {
                    managePolling(false);
                }
            }
        });
        
        // Gestion de la déconnexion
        window.Echo.connector.pusher.connection.bind('disconnected', () => {
            _warn("⚠️ WebSocket déconnecté, activation du fallback polling");
            websocketConnected = false;
            websocketFallbackActive = true;
            websocketSnapshotRequested = false;
            if (!poll) {
                managePolling(false);
            }
        });
        
        // Gestion de la reconnexion
        window.Echo.connector.pusher.connection.bind('connected', () => {
            _log("✅ WebSocket connecté");
            websocketConnected = true;
            websocketFallbackActive = false;
            if (!websocketSnapshotRequested) {
                websocketSnapshotRequested = true;
                requestWebSocketSnapshot('watch');
            }
            // Réduire le polling si WebSocket fonctionne
            if (poll && consecutiveStablePolls >= STABLE_MODE_THRESHOLD) {
                clearInterval(poll);
                poll = null;
                _log("📡 WebSocket actif, polling réduit");
            }
        });
        
        _log("📡 WebSocket initialisé et en écoute");
    } catch (error) {
        _error("❌ Erreur initialisation WebSocket:", error);
        websocketFallbackActive = true;
        // S'assurer que le polling est actif en cas d'erreur
        if (!poll) {
            managePolling(false);
        }
    }
}

function initSSE() {
    if (!window.EventSource) {
        _warn("⚠️ SSE non supporté par ce navigateur");
        return;
    }

    const sseUrl = window.WATCH_CONFIG.sseUrl;
    try {
        sseSource = new EventSource(sseUrl);

        sseSource.addEventListener('open', () => {
            _log("✅ SSE connecté");
            sseConnected = true;
            websocketFallbackActive = false;
            // ✅ Arrêter le polling si SSE fonctionne
            if (poll) {
                clearInterval(poll);
                poll = null;
                _log("📡 SSE actif, polling arrêté");
            }
        });

        sseSource.addEventListener('status', (event) => {
            try {
                const data = JSON.parse(event.data);
                sseConnected = true;
                websocketFallbackActive = false;
                if (poll) {
                    clearInterval(poll);
                    poll = null;
                }

                const payload = {
                    mode: data.mode,
                    stream_id: data.stream_id || null,
                    stream_name: data.stream_name || null,
                    url: data.url || data.stream_url || data.current_url || null,
                    stream_url: data.stream_url || data.url || data.current_url || null,
                    sync_timestamp: data.sync_timestamp || data.timestamp || Date.now() / 1000,
                    item_title: data.item_title || null,
                    item_id: data.item_id || null,
                    current_time: data.current_time || 0.0,
                    duration: data.duration || null,
                    is_finished: data.is_finished || false,
                };

                // ✅ Comparer AVANT traitement pour filtrer les messages dupliqués
                if (lastSSEPayload && 
                    lastSSEPayload.mode === payload.mode &&
                    lastSSEPayload.stream_id === payload.stream_id &&
                    lastSSEPayload.url === payload.url &&
                    lastSSEPayload.item_id === payload.item_id) {
                    // Statut identique, ignorer (pas de log ni de traitement)
                    return;
                }
                
                // ✅ Nouveau statut, mettre à jour le cache et traiter
                lastSSEPayload = payload;
                _log("📡 SSE statut reçu:", data);
                handleStreamData(payload);
            } catch (parseError) {
                _warn("⚠️ Erreur parsing SSE:", parseError);
            }
        });

        sseSource.addEventListener('heartbeat', () => {
            // Heartbeat pour garder la connexion
        });

        sseSource.onerror = (err) => {
            // ✅ Détecter les erreurs HTTP/2 qui bloquent complètement SSE
            const isHttp2Error = err.target?.readyState === EventSource.CLOSED && sseSource.readyState === EventSource.CLOSED;
            const shouldUsePolling = isHttp2Error || !sseConnected;
            
            if (shouldUsePolling) {
                _warn("⚠️ SSE erreur - Basculage immédiat vers polling:", err);
            sseConnected = false;
            try {
                sseSource.close();
            } catch (_) {}
                
                // ✅ Basculer immédiatement vers polling si SSE échoue
                websocketFallbackActive = true;
                if (!poll) {
                    _log("🔄 Activation immédiate du polling (SSE indisponible)");
                    managePolling(false);
                    // ✅ Appeler immédiatement getData pour récupérer le statut
                    getData();
                }
                
                // Ne pas réessayer SSE immédiatement si erreur HTTP/2
                if (!isHttp2Error) {
            setTimeout(() => {
                        if (!sseConnected && !websocketConnected) {
                    initSSE();
                }
            }, 5000);
                }
            } else {
                _warn("⚠️ SSE erreur temporaire:", err);
            }
        };
    } catch (error) {
        _error("❌ Erreur initialisation SSE:", error);
    }
}

function requestWebSocketSnapshot(source = 'watch') {
    if (!USE_WEBSOCKET) {
        return;
    }
    const snapshotUrl = `${API}?emit_event=1&snapshot_source=${encodeURIComponent(source)}&ts=${Date.now()}`;
    fetch(snapshotUrl, { cache: 'no-store' })
        .then(() => _log("📸 Snapshot WebSocket demandé", { snapshotUrl }))
        .catch((err) => _warn("⚠️ Échec requête snapshot WebSocket", err));
}

// 🚀 DÉMARRAGE ULTRA-RAPIDE
_log("🚀 EMB Mission TV Ultra-Optimisé initialisé");

// ✅ Afficher le player avec loading immédiatement pour éviter l'écran noir
// Le player est déjà visible par défaut dans le CSS, on s'assure juste que le loading est visible
loading.style.display = "block";
loading.textContent = "⚡ Chargement...";
// S'assurer que le player vidéo est visible (déjà visible par défaut dans le CSS)
video.style.display = "block";
iframe.style.display = "none";
errorBox.style.display = "none";

// ✅ S'assurer que le player a un fond noir visible immédiatement
video.style.backgroundColor = "#000";

// ✅ Fonction pour afficher le splash screen (lors des transitions)
function showSplashScreen(message = "⚡ Chargement...") {
    if (splashScreen) {
        // Réinitialiser le splash screen (le rendre visible)
        splashScreen.classList.remove('hidden');
        splashScreen.style.display = 'flex';
        splashScreen.style.opacity = '1';
        // Mettre à jour le texte
        const splashText = document.getElementById('splash-text');
        if (splashText) {
            splashText.textContent = message;
        }
        _log("🎨 Splash screen affiché:", message);
    }
}

// ✅ Fonction pour masquer le splash screen (utilisée à plusieurs endroits)
function hideSplashScreen() {
    if (splashScreen && !splashScreen.classList.contains('hidden')) {
        splashScreen.classList.add('hidden');
        setTimeout(() => {
            if (splashScreen) {
                splashScreen.style.display = 'none';
            }
        }, 500);
    }
}

// Initialiser WebSocket + SSE
if (USE_WEBSOCKET) {
    initWebSocket();
} else {
    _log("⚙️ WebSocket désactivé - utilisation SSE uniquement");
}
initSSE();

// Charger les données initiales (polling de démarrage)
getData();
