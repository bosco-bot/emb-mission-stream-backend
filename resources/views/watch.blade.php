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
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/watch.css'])

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
        // ✅ Config : lecture = toujours unified.m3u8 (live + VOD). L’API current-url sert à la dispo (pause/erreur) et aux métadonnées (titre, sync).
        window.WATCH_CONFIG = {
            apiUrl: "{{ url('/api/webtv-auto-playlist/current-url') }}",
            baseUrl: "{{ rtrim(config('app.url'), '/') }}",
            sseUrl: "{{ url('/api/webtv/live/status/stream') }}",
            livePlaylistPath: "/hls/streams/unified.m3u8"
        };
        window.WATCH_DEBUG = {{ config('app.debug') ? 'true' : 'false' }};
        window._log = window.WATCH_DEBUG ? console.log.bind(console) : function(){};
        window._warn = window.WATCH_DEBUG ? console.warn.bind(console) : function(){};
        window._error = window.WATCH_DEBUG ? console.error.bind(console) : function(){};
        // ✅ Capturer l'erreur "payload" (Reverb/Echo) avant que Echo ne se connecte
        window.addEventListener('unhandledrejection', function(event) {
            var msg = (event.reason && (event.reason.message || String(event.reason))) || '';
            if (msg.indexOf('payload') !== -1) {
                if (window._warn) window._warn('⚠️ Événement Reverb/Echo mal formé (payload) – ignoré.');
                event.preventDefault();
                event.stopPropagation();
            }
        });
    </script>
    <script>
        // ✅ Configuration Laravel Echo pour Reverb
        // Détection automatique de l'environnement (local vs production)
        window.Pusher = Pusher;
        
        const isLocalDev = window.location.hostname === 'localhost' || 
                          window.location.hostname === '127.0.0.1' ||
                          window.location.hostname.startsWith('192.168.') ||
                          window.location.hostname.startsWith('10.') ||
                          (window.location.port !== '' && window.location.port !== '443' && window.location.port !== '80');
        
        const wsHost = isLocalDev ? (new URL(window.WATCH_CONFIG.baseUrl)).hostname : window.location.hostname;
        const wsPort = 8444;
        const wssPort = 8444;
        const useTLS = wsHost !== 'localhost' && wsHost !== '127.0.0.1';
        
        const echoConfig = {
            broadcaster: 'reverb',
            key: '{{ config("broadcasting.connections.reverb.key") }}',
            wsHost: wsHost,
            wsPort: wsPort,
            wssPort: wssPort,
            forceTLS: useTLS,
            enabledTransports: useTLS ? ['wss'] : ['ws', 'wss'],
            disableStats: true,
            cluster: '',
            authEndpoint: isLocalDev ? (window.WATCH_CONFIG.baseUrl + '/broadcasting/auth') : '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                }
            }
        };
        
        window.Echo = new Echo(echoConfig);
        
        _log("📡 Laravel Echo initialisé pour Reverb", {
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
    
    @vite(['resources/js/watch-page.js'])
</body>
</html>