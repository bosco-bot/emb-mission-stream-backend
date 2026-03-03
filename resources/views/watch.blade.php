<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMB Mission TV - En Direct</title>
    
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-R78T23LM6W"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-R78T23LM6W');
    </script>
    
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/icons/Icon-192.png">
    <meta name="description" content="Regardez EMB Mission TV en direct et en replay">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #000; font-family: Arial, sans-serif; overflow: hidden; }
        #player-container { 
            width: 100vw; 
            height: 100vh; 
            position: relative; 
            background: #000; /* ✅ Fond noir pour éviter l'écran blanc */
        }
        
        #player, #html5-player { 
            width: 100%; 
            height: 100%; 
            border: none; 
        }
        #html5-player { 
            object-fit: contain; 
            display: block; /* ✅ Visible par défaut pour éviter l'écran noir */
            background: #E0F2F7; /* ✅ Utiliser la même couleur que le splash pendant la transition */
            opacity: 1; /* ✅ Assurer que le player est opaque */
            transition: background-color 0.5s ease-out; /* ✅ Transition douce vers noir */
        }
        
        /* ✅ Quand la vidéo est prête, transition vers fond noir */
        #html5-player.has-content {
            background: #000;
        }
        
        #loading, #error {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            color: white; z-index: 1000; /* ✅ z-index élevé pour être au-dessus */
            text-align: center;
            font-size: 18px;
            font-weight: bold;
        }
        #loading { display: block; } /* ✅ Visible par défaut */
        #error { color: #ff6b6b; display: none; }
        
        #sync-info {
            position: absolute; top: 10px; right: 10px;
            background: rgba(0,0,0,0.8); color: white;
            padding: 8px 12px; border-radius: 8px; font-size: 13px;
            z-index: 15; display: none !important; border-left: 3px solid #4CAF50;
        }
        
        #perf-info {
            position: absolute; top: 10px; left: 10px;
            background: rgba(0,0,0,0.7); color: #4CAF50;
            padding: 6px 10px; border-radius: 6px; font-size: 11px;
            z-index: 15; display: none !important; font-family: monospace;
        }
        
        /* 🎨 Splash Screen avec logo de la plateforme */
        #splash-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: #E0F2F7; /* ✅ Couleur de fond de la plateforme EMB Mission */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out;
        }
        
        #splash-screen.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        #splash-logo {
            width: 200px;
            height: 200px;
            max-width: 80vw;
            max-height: 80vh;
            object-fit: contain;
            animation: pulse 2s ease-in-out infinite;
            margin-bottom: 30px;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.8;
                transform: scale(1.05);
            }
        }
        
        #splash-text {
            color: #0F172A; /* ✅ Couleur de texte foncé pour contraste avec fond bleu clair */
            font-size: 18px;
            font-weight: 500;
            margin-top: 20px;
            animation: fadeInOut 2s ease-in-out infinite;
        }
        
        @keyframes fadeInOut {
            0%, 100% {
                opacity: 0.6;
            }
            50% {
                opacity: 1;
            }
        }
        
        #splash-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(15, 23, 42, 0.1); /* ✅ Utiliser la couleur de texte avec opacité */
            border-top-color: #0F172A; /* ✅ Couleur de texte pour le spinner */
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-top: 20px;
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body>
    <!-- 🎨 Splash Screen avec logo de la plateforme -->
    <div id="splash-screen">
        <img id="splash-logo" src="{{ asset('assets/assets/logo_emb_mission.png') }}" alt="EMB Mission Logo" onerror="this.src='{{ asset('assets/assets/logo.png') }}';">
        <div id="splash-text">⚡ Chargement...</div>
        <div id="splash-spinner"></div>
    </div>
    
    <div id="player-container">
        <div id="loading">⚡ Chargement ultra-rapide...</div>
        <div id="error">
            <p>Aucun contenu disponible pour le moment.</p>
            <p>Veuillez réessayer plus tard.</p>
        </div>
        <div id="sync-info"></div>
        <div id="perf-info"></div>
        <iframe id="player" src="" allowfullscreen style="display: none;"></iframe>
        <video id="html5-player" controls autoplay playsinline muted preload="metadata"></video>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.6.14/dist/hls.min.js"
            integrity="sha256-wnv8kVqHnAKznyXXBcI2U5Oly2U+ovyNRzwv811mHGc="
            crossorigin="anonymous"></script>
    
    <!-- Laravel Echo & Pusher JS pour WebSocket -->
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
    
    <script>
        // ✅ Configuration Laravel Echo pour Reverb
        // Détection automatique de l'environnement (local vs production)
        window.Pusher = Pusher;
        
        // Détecter si on est en développement local (Flutter Web) ou en production
        const isLocalDev = window.location.hostname === 'localhost' || 
                          window.location.hostname === '127.0.0.1' ||
                          window.location.hostname.startsWith('192.168.') ||
                          window.location.hostname.startsWith('10.') ||
                          (window.location.port !== '' && window.location.port !== '443' && window.location.port !== '80'); // Port personnalisé = probablement local
        
        // Configuration WebSocket selon l'environnement
        // En local, on se connecte TOUJOURS au serveur de production (tv.embmission.com)
        const wsHost = isLocalDev ? 'tv.embmission.com' : window.location.hostname;
        const wsPort = 8444;
        const wssPort = 8444;
        // Toujours utiliser TLS/WSS si on se connecte à tv.embmission.com (production)
        const useTLS = wsHost === 'tv.embmission.com';
        
        // Configuration Echo avec hostname forcé pour le développement local
        const echoConfig = {
            broadcaster: 'reverb',
            key: '{{ config("broadcasting.connections.reverb.key") }}',
            wsHost: wsHost,
            wsPort: wsPort,
            wssPort: wssPort,
            forceTLS: useTLS,
            enabledTransports: useTLS ? ['wss'] : ['ws', 'wss'], // Forcer WSS en local si on se connecte à production
            disableStats: true,
            cluster: '',
            // ✅ Configuration spécifique pour Reverb
            authEndpoint: isLocalDev ? 'https://tv.embmission.com/broadcasting/auth' : '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                }
            }
        };
        
        window.Echo = new Echo(echoConfig);
        
        console.log("📡 Laravel Echo initialisé pour Reverb", {
            environment: isLocalDev ? 'local' : 'production',
            detectedLocalDev: isLocalDev,
            windowHostname: window.location.hostname,
            windowPort: window.location.port,
            wsHost: wsHost,
            wsPort: wsPort,
            wssPort: wssPort,
            useTLS: useTLS,
            protocol: window.location.protocol
        });
    </script>
    
    <script>
        // 🚀 VARIABLES OPTIMISÉES
        const API = "https://tv.embmission.com/api/webtv-auto-playlist/current-url";
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
        let modeStableSince = null; // ✅ Timestamp de la dernière stabilité du mode
        let consecutiveStablePolls = 0; // ✅ Nombre de polls consécutifs sans changement
        const STABLE_MODE_THRESHOLD = 3; // ✅ Après 3 polls stables, réduire la fréquence
        const STALL_WINDOW_MS = 15000;
        const STALL_LIMIT = 3;
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
                    console.log("🔄 Transition live → VoD détectée - Période de grâce activée");
                    loading.style.display = "block";
                    loading.textContent = "⚡ Transition vers la playlist...";
                    errorBox.style.display = "none";
                }
                
                const transitionElapsed = Date.now() - liveToVodTransitionStart;
                if (transitionElapsed < TRANSITION_GRACE_PERIOD_MS) {
                    // Période de grâce : attendre sans afficher d'erreur
                    console.log(`⏳ Transition en cours (${Math.round(transitionElapsed/1000)}s/${TRANSITION_GRACE_PERIOD_MS/1000}s) - Attente VoD...`);
                    return; // Ne pas afficher d'erreur, juste attendre
                } else {
                    // Période de grâce expirée : considérer comme erreur
                    console.warn("⚠️ Transition live → VoD trop longue - Affichage erreur");
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
            
            if (isLiveData) {
                // ✅ Utiliser unified.m3u8 pour le live (compatible VLC et navigateur)
                const liveUrl = 'https://tv.embmission.com/hls/streams/unified.m3u8';
                
                if (mode === 'live' && lastStreamId === d.stream_id && currentUrl === liveUrl) {
                    lastData = d;
                    loading.style.display = "none";
                    errorBox.style.display = "none";
                    if (!isRealtimeActive()) {
                        managePolling(false);
                    }
                    return;
                }
                
                showLive(liveUrl, d);
            } else if (isVodData) {
                const vodUrl = d.url || d.stream_url;
                
                if (!vodUrl) {
                    console.warn("⚠️ Données VoD reçues sans URL valide");
                    // Si on est en transition, ne pas afficher d'erreur immédiatement
                    if (liveToVodTransitionStart) {
                        const transitionElapsed = Date.now() - liveToVodTransitionStart;
                        if (transitionElapsed < TRANSITION_GRACE_PERIOD_MS) {
                            console.log("⏳ VoD sans URL - Attente dans le cadre de la transition...");
                            return;
                        }
                    }
                    showError("VoD indisponible");
                    return;
                }
                
                if (mode === 'vod' && currentUrl === vodUrl && lastData && lastData.item_id === d.item_id) {
                    lastData = d;
                    loading.style.display = "none";
                    errorBox.style.display = "none";
                    if (!isRealtimeActive()) {
                        managePolling(false);
                    }
                    return;
                }
                
                showVODOptimized(vodUrl, d);
            } else {
                // ✅ Ne pas afficher d'erreur si on est en période de grâce
                if (liveToVodTransitionStart) {
                    const transitionElapsed = Date.now() - liveToVodTransitionStart;
                    if (transitionElapsed < TRANSITION_GRACE_PERIOD_MS) {
                        console.log("⏳ Mode indéterminé pendant transition - Attente...");
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
                
                console.error("❌ Erreur API:", {
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
                // ✅ Utiliser unified.m3u8 pour le live (compatible VLC et navigateur)
                const liveUrl = "https://tv.embmission.com/hls/streams/unified.m3u8";
                showLive(liveUrl, snapshot);
                return;
            }

            if (modeSnapshot === "vod") {
                const vodUrl = snapshot.url || snapshot.stream_url;
                if (vodUrl) {
                    showVODOptimized(vodUrl, snapshot);
                    return;
                }
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
                console.log(`⏸️ Rechargement différé (${remaining}s restantes) pour éviter la boucle infinie`);
                setTimeout(() => requestNextVideo(reason), MIN_ERROR_RETRY_DELAY - (now - lastErrorRetry));
                return;
            }
            
            if (reason === 'video error') {
                lastErrorRetry = now;
            }
            
            console.log(`⏭️ Transition forcée${reason ? ` (${reason})` : ""}`);
            stopFreezeWatchdog();
            currentUrl = null;
            // ✅ Réinitialiser lastStreamId pour forcer le rechargement en cas d'erreur
            if (reason === 'video error') {
                lastStreamId = null;
            }
            getData();
        }

        function startFreezeWatchdog() {
            stopFreezeWatchdog();
            lastProgressTime = video.currentTime;
            isBuffering = false;

            freezeInterval = setInterval(() => {
                if (mode !== 'vod' || video.paused || !video.src) return;

                const currentTime = video.currentTime;
                const bufferedEnd = video.buffered.length > 0 ? video.buffered.end(video.buffered.length - 1) : 0;
                const bufferedAhead = bufferedEnd - currentTime;
                
                // ✅ Détecter les freezes : temps bloqué OU buffer vide (buffering state)
                const isTimeFrozen = currentTime === lastProgressTime && currentTime > 0 && video.readyState >= 2;
                const isBufferEmpty = bufferedAhead < 1.0 && !video.paused && video.readyState >= 2; // Moins de 1s de buffer
                // ✅ Buffering state : readyState < 3 MAIS buffer vide (< 1s) pour éviter faux positifs
                const isBufferingState = video.readyState < 3 && !video.paused && currentTime > 0 && bufferedAhead < 1.0;
                
                if (isTimeFrozen || isBufferEmpty || isBufferingState) {
                    console.warn("⚠️ Détection de freeze/buffering - tentative de récupération", {
                        isTimeFrozen: isTimeFrozen,
                        isBufferEmpty: isBufferEmpty,
                        bufferedAhead: bufferedAhead.toFixed(2) + 's',
                        isBufferingState: isBufferingState,
                        readyState: video.readyState
                    });

                    if (lastData && typeof lastData.duration === "number" && currentTime >= lastData.duration - 1) {
                        requestNextVideo("freeze at end");
                        return;
                    }

                    // ✅ Seek de 0.5s au lieu de 0.1s pour mieux récupérer
                    const seekAmount = isBufferEmpty ? 1.0 : 0.5; // Seek plus important si buffer vide
                    video.currentTime = Math.min(currentTime + seekAmount, bufferedEnd > 0 ? bufferedEnd - 0.5 : currentTime + seekAmount);
                    startPlaybackOptimized();
                }

                lastProgressTime = currentTime;
            }, 3000);
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
                console.warn(`⛔ Trop de blocages (${eventName}) - passage forcé`);
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
                    console.warn("⚠️ hls.js destroy:", e);
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
                        console.log("🔄 Réinitialisation forcée HLS.js (transition Live → VoD)");
                    }
                    hlsInstance.detachMedia();
                    hlsInstance.destroy();
                } catch (e) {
                    console.warn("⚠️ hls.js destroy:", e);
                }
                hlsInstance = null;
            }

            if (!url) {
                console.warn("⚠️ URL vidéo manquante");
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
                            console.warn("⚠️ Impossible de se caler sur le live (native):", err);
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
                        // ✅ Configuration optimisée pour LIVE avec support multi-viewers
                    enableWorker: true,
                        lowLatencyMode: false,
                        backBufferLength: 120, // ✅ Augmenté à 120s pour absorber les pics de latence avec plusieurs viewers
                    liveSyncDurationCount: 8, // ✅ Augmenté à 8 pour plus de marge avec plusieurs viewers
                    liveMaxLatencyDurationCount: 40, // ✅ Augmenté à 40 pour tolérer beaucoup plus de latence
                        maxBufferLength: 120, // ✅ Augmenté à 120 secondes pour absorber les micro-coupures
                        maxMaxBufferLength: 240, // ✅ Augmenté à 240 secondes max pour plusieurs viewers
                        startLevel: -1,
                    capLevelToPlayerSize: false,
                    debug: false,
                        maxBufferHole: 1.5, // ✅ Réduit à 1.5s pour détecter les gaps plus tôt (évite accumulation)
                        highBufferWatchdogPeriod: 3, // ✅ Réduit à 3s pour réagir plus vite
                        maxFragLoadingTimeMs: 60000, // ✅ Augmenté à 60s pour supporter la latence réseau élevée
                        fragLoadingTimeOut: 60000, // ✅ Augmenté à 60s
                        manifestLoadingTimeOut: 25000, // ✅ Augmenté à 25s pour les micro-coupures avec plusieurs viewers
                        maxMaxBufferLength: 300, // ✅ Buffer max très large (300s = 5 min) pour plusieurs viewers
                        maxLoadingDelay: 8, // ✅ Augmenté à 8 pour charger plus de segments en parallèle (réduit les stalls)
                        fragLoadingTimeOut: 60000, // ✅ Timeout très long pour la latence réseau
                        maxBufferSize: 150 * 1000 * 1000, // ✅ Augmenté à 150 MB pour plusieurs viewers
                    } : {
                        // ✅ Configuration optimisée pour VoD
                        enableWorker: true,
                        lowLatencyMode: false,
                    backBufferLength: 90,
                        liveSyncDurationCount: 6,
                        liveMaxLatencyDurationCount: 20,
                        maxBufferLength: 120, // ✅ Optimisé VoD : 120 secondes pour plus de marge
                        maxMaxBufferLength: 240, // ✅ Optimisé VoD : 240 secondes max
                        startLevel: -1,
                    capLevelToPlayerSize: false,
                    debug: false,
                        maxBufferHole: 0.5, // ✅ Optimisé VoD : 0.5 seconde pour moins de tolérance aux trous
                        highBufferWatchdogPeriod: 2,
                        maxFragLoadingTimeMs: 30000, // ✅ Optimisé VoD : 30 secondes max pour segments lents
                        fragLoadingTimeOut: 30000, // ✅ Optimisé VoD : 30 secondes
                        manifestLoadingTimeOut: 10000,
                    maxBufferSize: 60 * 1000 * 1000, // 60 MB de buffer max
                    };
                    
                hlsInstance = new Hls(hlsConfig);
                
                // ✅ Gestion des erreurs HLS.js
                hlsInstance.on(Hls.Events.ERROR, (event, data) => {
                    // ✅ Ne pas logger les bufferStalledError non-fatals en live (évite logs répétitifs)
                    const shouldLog = !(isLive && !data.fatal && (data.details === 'bufferStalledError' || data.details === 'bufferAppendingError'));
                    
                    if (shouldLog) {
                    console.error("❌ Erreur HLS.js:", {
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
                            console.log(`⏳ Transition Live → VoD en cours (${Math.round(transitionElapsed/1000)}s/${TRANSITION_GRACE_PERIOD_MS/1000}s) - Ignorant levelParsingError normal pendant la transition`);
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
                                    console.log(`🔄 Tentative de récupération ${isManifestTimeout ? 'manifest' : 'segment'} ${errorRecoveryAttempts}/${MAX_RECOVERY_ATTEMPTS} (délai ${progressiveDelay}ms)...`);
                                    
                                    setTimeout(() => {
                                        try {
                                            if (hlsInstance && hlsInstance.media === video) {
                                                // ✅ Pour les timeouts de segments, utiliser recoverMediaError qui est plus doux
                                                if (isFragLoadTimeout) {
                                                    hlsInstance.recoverMediaError();
                                                } else {
                                hlsInstance.startLoad();
                                                }
                                                console.log('✅ Récupération HLS déclenchée');
                                            }
                                        } catch (e) {
                                            console.error('❌ Erreur lors de la récupération:', e);
                                        }
                                    }, progressiveDelay);
                                } else {
                                    console.error("❌ Erreur réseau HLS.js - Tentative de récupération immédiate...");
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
                                console.error("❌ Erreur média HLS.js - Tentative de récupération...");
                                hlsInstance.recoverMediaError();
                                break;
                            default:
                                console.error("❌ Erreur fatale HLS.js - Rechargement du flux...");
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
                                console.warn("⚠️ Impossible de se caler sur le live (hls.js):", err);
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
                                console.warn("⚠️ Erreur lors de la destruction ancienne instance:", e);
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
                    console.warn("⚠️ hls.js error", errorDetails);
                    
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
                        
                        console.error("🌐 Erreur réseau HLS:", {
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
                                console.log(`🔄 Manifest live 404 - Tentative ${liveManifestRetryCount}/${MAX_LIVE_MANIFEST_RETRIES} (le manifest peut ne pas être encore prêt)`);
                                // Attendre un peu avant de réessayer (le manifest peut être en cours de génération)
                                setTimeout(() => {
                                    if (hlsInstance && mode === 'live') {
                                        hlsInstance.startLoad();
                                    }
                                }, 2000 * liveManifestRetryCount); // Délai progressif : 2s, 4s, 6s, 8s, 10s
                                return; // Ne pas traiter comme une erreur fatale pour l'instant
                            } else {
                                console.warn(`⚠️ Manifest live 404 après ${MAX_LIVE_MANIFEST_RETRIES} tentatives - Le live n'est probablement pas encore prêt`);
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
                                console.error("❌ Erreur fatale HLS - déconnexion", errorDetails);
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
                console.warn("⚠️ hls.js non supporté - fallback direct");
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
                // ✅ Si transition Live → VoD, réinitialiser complètement le lecteur
                if (isLiveToVodTransition) {
                    console.log("🔄 Transition Live → VoD - Réinitialisation complète du lecteur");
                    
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
                            console.log("🧹 Détruction instance HLS.js Live...");
                            hlsInstance.detachMedia();
                            hlsInstance.destroy();
                        } catch (e) {
                            console.warn("⚠️ Erreur lors de la destruction HLS.js:", e);
                        }
                        hlsInstance = null;
                    }
                    
                    // ✅ Réinitialiser complètement le lecteur vidéo
                    resetVideoElement();
                    
                    // ✅ Réinitialiser les flags de statut
                    isLiveStream = false;
                    stopFreezeWatchdog();
                    
                    // Charger la source VoD avec réinitialisation forcée
                    console.log("✅ Chargement VoD après réinitialisation");
                    loadVideoSource(url, { isLive: false, forceReinit: true });
                    
                    // ✅ Attendre que la vidéo charge et afficher le contenu
                    const onVoDReady = () => {
                        if (video.videoWidth > 0 && video.videoHeight > 0) {
                            // ✅ POSITIONNER À current_time AVANT de démarrer (correction position VOD)
                            if (typeof d.current_time === 'number' && d.current_time > 0) {
                                const targetTime = Math.max(0, d.current_time);
                                video.currentTime = targetTime;
                                console.log(`⏱️ Position VOD (transition Live→VoD) appliquée: ${targetTime.toFixed(1)}s`);
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
                
                console.log("🎬 Nouvelle vidéo:", d.item_title);
                
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

                        // ✅ Pour HLS, attendre que le manifest soit parsé avant de positionner (correction position VOD)
                        const isM3u8 = url.includes('.m3u8');
                        if (isM3u8 && hlsInstance && targetTime > 0) {
                            const seekToPosition = (event, data) => {
                                if (video.readyState >= 2) {
                                    video.currentTime = targetTime;
                                    console.log(`⏱️ Position HLS appliquée: ${targetTime.toFixed(1)}s`);
                                    setTimeout(() => {
                                        onSeeked();
                                    }, 100);
                                }
                                hlsInstance.off(Hls.Events.MANIFEST_PARSED, seekToPosition);
                                hlsInstance.off(Hls.Events.LEVEL_LOADED, seekToPosition);
                            };
                            hlsInstance.on(Hls.Events.MANIFEST_PARSED, seekToPosition);
                            hlsInstance.on(Hls.Events.LEVEL_LOADED, seekToPosition);
                            
                            // Fallback : si pas de manifest après 3s, essayer quand même
                            setTimeout(() => {
                                if (video.readyState >= 2 && Math.abs(video.currentTime - targetTime) > 0.5) {
                                    video.currentTime = targetTime;
                                    onSeeked();
                                }
                            }, 3000);
                        } else {
                            // ✅ Pour MP4 ou si pas HLS, utiliser le code existant
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
                if (typeof d.duration === "number" && video.currentTime >= d.duration - 1) {
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
                console.log("🔄 Preloading:", url.split('/').pop());
                
                const preloadVideo = document.createElement('video');
                preloadVideo.src = url;
                preloadVideo.preload = 'metadata';
                preloadVideo.muted = true;
                preloadVideo.style.display = 'none';
                
                preloadVideo.addEventListener('loadeddata', () => {
                    console.log("✅ Preloadé:", url.split('/').pop());
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
                console.log("⏸️ startPlaybackOptimized ignoré (traitement en cours)", {
                    readyState: video.readyState,
                    paused: video.paused
                });
                return;
            }
            
            const now = Date.now();
            
            // ✅ Vérifier si un appel récent existe (protection contre appels multiples)
            if (startPlaybackLock && (now - startPlaybackLock) < STARTPLAYBACK_DEBOUNCE_MS) {
                console.log("⏸️ startPlaybackOptimized ignoré (appel récent)", {
                    timeSinceLastCall: (now - startPlaybackLock) + 'ms',
                    readyState: video.readyState,
                    paused: video.paused
                });
                return;
            }
            
            // ✅ Définir les verrous IMMÉDIATEMENT pour bloquer les appels suivants
            isProcessingPlayback = true;
            startPlaybackLock = now;
            
            console.log("🎬 startPlaybackOptimized appelé", {
                readyState: video.readyState,
                paused: video.paused,
                mode: mode,
                currentUrl: currentUrl
            });
            
            // Vérifier l'état de la vidéo avant de jouer
            if (video.readyState >= 2) { // HAVE_CURRENT_DATA
                console.log("▶️ Tentative de lecture...");
                playPromise = video.play();
                
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        console.log("▶️ Lecture démarrée");
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
                        console.log("⚠️ Autoplay:", error.name);
                        
                        if (error.name === 'AbortError') {
                            // Retry plus rapide
                            setTimeout(() => {
                                playPromise = video.play();
                                if (playPromise) {
                                    playPromise.then(() => {
                                        console.log("▶️ Lecture redémarrée");
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
                console.log("📺 Live déjà actif - AUCUNE reconnexion (économie bande passante)");
                return;
            }
            
            // 🔒 Vérifier si c'est vraiment un nouveau stream
            // ✅ Permettre le rechargement si forceReload est activé (erreur vidéo)
            if (lastStreamId === d.stream_id && mode === 'live' && !forceReload) {
                console.log("📺 Même stream Live - Pas de rechargement");
                return;
            }
            
            // ✅ Réinitialiser forceReload si on recharge
            if (forceReload) {
                console.log("🔄 Rechargement forcé suite à une erreur");
                forceReload = false;
            }
            
            const realtimeSource = sseConnected ? 'SSE' : (websocketConnected ? 'WebSocket' : 'polling');
            console.log(`📺 NOUVELLE connexion Live (${realtimeSource}):`, d.stream_id);
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

            console.log(`📺 Live connecté (${realtimeSource})`);
            if (!isRealtimeActive()) {
            console.log("📺 Live connecté - Polling adaptatif (8-10s)");
            }
        }
        
        // 🔄 SYNCHRONISATION CONTINUE OPTIMISÉE
        function startContinuousSync() {
            if (sync) clearInterval(sync);
            
            sync = setInterval(() => {
                if (mode !== 'vod' || !lastData) return;
                
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
                    console.log(`🔄 Resync auto: ${video.currentTime.toFixed(1)}s → ${safeTarget.toFixed(1)}s`);
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
                console.log(`🔄 Mode changé - Polling rapide (${quickInterval/1000}s) pour confirmation`);
                poll = setInterval(() => {
                    getData();
                }, quickInterval);
                return;
            }

            // Si WebSocket ou SSE est actif, garder seulement un polling de sécurité
            if (isRealtimeActive()) {
                console.log("📡 Connexion temps réel active - Polling de sécurité (30s)");
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
                    console.log("📺 Mode Live stable - Polling réduit (10s)");
                } else {
                    interval = 8000; // 8s si instable (au lieu de 15s - détection plus rapide)
                    console.log(`📺 Mode Live - Polling actif (8s) - Stabilité: ${consecutiveStablePolls}/${STABLE_MODE_THRESHOLD}`);
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
                console.log("📡 Mode VoD stable - Polling réduit (10s)");
            } else {
                interval = 8000; // 8s si instable (au lieu de 12s - détection plus rapide)
                console.log(`📡 Mode VoD - Polling actif (8s) - Stabilité: ${consecutiveStablePolls}/${STABLE_MODE_THRESHOLD}`);
            }
            poll = setInterval(() => {
                getData();
            }, interval);
        }
        
        // 🎬 GESTION DE FIN DE VIDÉO
        video.addEventListener('ended', () => {
            console.log("🏁 Vidéo terminée (événement ended) - Transition immédiate");
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
            
            console.error("❌ Erreur vidéo:", errorDetails);
            
            // ✅ Gestion spéciale pour les erreurs DEMUXER_ERROR_COULD_NOT_PARSE
            // Ne pas recharger immédiatement si HLS.js peut gérer la récupération
            const errorMessage = String(errorDetails.message || '').toUpperCase();
            const isDemuxerError = errorMessage.includes('DEMUXER_ERROR_COULD_NOT_PARSE') ||
                                   errorMessage.includes('PIPELINESTATUS::DEMUXER_ERROR') ||
                                   errorMessage.includes('DEMUXER_ERROR') ||
                                   (errorDetails.code === 4 && errorMessage.includes('PIPELINE'));
            
            console.log("🔍 Diagnostic erreur DEMUXER:", {
                isDemuxerError: isDemuxerError,
                errorMessage: errorMessage,
                errorCode: errorDetails.code,
                hlsInstanceExists: !!hlsInstance,
                willTryRecovery: !!(hlsInstance && isDemuxerError)
            });
            
            // ✅ Si on utilise HLS.js, laisser HLS.js gérer la récupération pour les erreurs DEMUXER
            // On vérifie simplement si hlsInstance existe (peut être attaché ou non)
            if (hlsInstance && isDemuxerError) {
                console.warn("⚠️ Erreur DEMUXER détectée - HLS.js va tenter la récupération automatique", {
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
                        console.error("❌ HLS.js n'a pas pu récupérer - rechargement forcé", {
                            error: video.error,
                            readyState: video.readyState
                        });
                        forceReload = true;
                        requestNextVideo('video error - hls recovery failed');
                    } else {
                        console.log("✅ HLS.js a réussi à récupérer de l'erreur DEMUXER");
                    }
                }, 3000);
                return; // Ne pas recharger immédiatement
            }
            
            // ✅ Pour les autres erreurs ou si HLS.js n'est pas disponible, recharger immédiatement
            forceReload = true;
            requestNextVideo('video error');
        });

        video.addEventListener('stalled', () => {
            console.warn("⚠️ Flux stalled - tentative de reprise");
            isBuffering = true;
            if (registerStall('stalled')) return;
            resetFreezeDetection();
            startPlaybackOptimized();
        });

        video.addEventListener('waiting', () => {
            console.log("⏳ Buffer en attente - surveillance renforcée");
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
                        console.log(`🎯 Recentrage live conservateur: gap=${liveGap.toFixed(1)}s → ${target.toFixed(1)}s`);
                    video.currentTime = target;
                    } else {
                        console.warn(`⚠️ Gap trop important (${liveGap.toFixed(1)}s) - recentrage ignoré pour éviter saut trop grand`);
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
                console.log("🔁 Retry après erreur");
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
                console.log("🔁 Vérification reprise après pause");
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
                    console.warn("⚠️ Laravel Echo non disponible, utilisation du polling uniquement");
                    websocketFallbackActive = true;
                    return;
                }
                
                // S'abonner au canal public
                websocketChannel = window.Echo.channel('webtv-stream-status');
                
                // Écouter les changements de statut
                websocketChannel.listen('.stream.status.changed', (data) => {
                    console.log("📡 Event WebSocket reçu:", data);
                    websocketConnected = true;
                    
                    // Traiter les données comme si elles venaient de l'API
                    // Inclure toutes les propriétés nécessaires pour updateSyncInfo
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
                    
                    // Utiliser la même logique que getData() mais sans appel API
                    handleStreamData(streamData.data);
                });
                
                // Gestion des erreurs de connexion
                window.Echo.connector.pusher.connection.bind('error', (err) => {
                    console.error("❌ Erreur WebSocket:", err);
                    websocketConnected = false;
                    if (!websocketFallbackActive) {
                        console.log("🔄 Activation du fallback polling");
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
                    console.warn("⚠️ WebSocket déconnecté, activation du fallback polling");
                    websocketConnected = false;
                    websocketFallbackActive = true;
                    websocketSnapshotRequested = false;
                    if (!poll) {
                        managePolling(false);
                    }
                });
                
                // Gestion de la reconnexion
                window.Echo.connector.pusher.connection.bind('connected', () => {
                    console.log("✅ WebSocket connecté");
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
                        console.log("📡 WebSocket actif, polling réduit");
                    }
                });
                
                console.log("📡 WebSocket initialisé et en écoute");
            } catch (error) {
                console.error("❌ Erreur initialisation WebSocket:", error);
                websocketFallbackActive = true;
                // S'assurer que le polling est actif en cas d'erreur
                if (!poll) {
                    managePolling(false);
                }
            }
        }

        function initSSE() {
            if (!window.EventSource) {
                console.warn("⚠️ SSE non supporté par ce navigateur");
                return;
            }

            const sseUrl = "https://tv.embmission.com/api/webtv/live/status/stream";
            try {
                sseSource = new EventSource(sseUrl);

                sseSource.addEventListener('open', () => {
                    console.log("✅ SSE connecté");
                    sseConnected = true;
                    websocketFallbackActive = false;
                    // ✅ Arrêter le polling si SSE fonctionne
                    if (poll) {
                        clearInterval(poll);
                        poll = null;
                        console.log("📡 SSE actif, polling arrêté");
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
                        console.log("📡 SSE statut reçu:", data);
                        handleStreamData(payload);
                    } catch (parseError) {
                        console.warn("⚠️ Erreur parsing SSE:", parseError);
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
                        console.warn("⚠️ SSE erreur - Basculage immédiat vers polling:", err);
                    sseConnected = false;
                    try {
                        sseSource.close();
                    } catch (_) {}
                        
                        // ✅ Basculer immédiatement vers polling si SSE échoue
                        websocketFallbackActive = true;
                        if (!poll) {
                            console.log("🔄 Activation immédiate du polling (SSE indisponible)");
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
                        console.warn("⚠️ SSE erreur temporaire:", err);
                    }
                };
            } catch (error) {
                console.error("❌ Erreur initialisation SSE:", error);
            }
        }
        
        function requestWebSocketSnapshot(source = 'watch') {
            if (!USE_WEBSOCKET) {
                return;
            }
            const snapshotUrl = `${API}?emit_event=1&snapshot_source=${encodeURIComponent(source)}&ts=${Date.now()}`;
            fetch(snapshotUrl, { cache: 'no-store' })
                .then(() => console.log("📸 Snapshot WebSocket demandé", { snapshotUrl }))
                .catch((err) => console.warn("⚠️ Échec requête snapshot WebSocket", err));
        }
        
        // 🚀 DÉMARRAGE ULTRA-RAPIDE
        console.log("🚀 EMB Mission TV Ultra-Optimisé initialisé");
        
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
                console.log("🎨 Splash screen affiché:", message);
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
            console.log("⚙️ WebSocket désactivé - utilisation SSE uniquement");
        }
        initSSE();
        
        // Charger les données initiales (polling de démarrage)
        getData();
    </script>
</body>
</html>