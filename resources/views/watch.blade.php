<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMB Mission TV</title>
    <meta name="description" content="Regardez EMB Mission TV en direct et en replay">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Si Edge affiche "Tracking Prevention blocked access to storage" : Paramètres > Confidentialité > Prévention du suivi > Exceptions et ajouter ce site. --}}
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/icons/Icon-192.png">
    @vite(['resources/css/watch.css'])
</head>
<body>
    <div id="app" class="watch-app">
        <div id="splash" class="splash" aria-live="polite">
            <img src="{{ asset('assets/assets/logo_emb_mission.png') }}" alt="EMB Mission" onerror="this.src='{{ asset('assets/assets/logo.png') }}';">
            <p id="splash-text">Chargement...</p>
        </div>
        <div id="player-wrap" class="player-wrap" hidden>
            <video id="video" class="video" controls playsinline muted preload="auto" autoplay
                   webkit-playsinline></video>
            <div id="loading" class="loading" hidden>Chargement...</div>
        </div>
        <div id="error" class="error" hidden role="alert">
            <p id="error-message">Aucun contenu disponible.</p>
            <p>Réessayez plus tard.</p>
        </div>
        <div id="watch-debug" class="watch-debug" hidden aria-live="polite"></div>
    </div>

    <script>
        (function() {
            var params = new URLSearchParams(window.location.search);
            var forceDebug = params.get('debug') === '1' || params.get('debug') === 'true';
            window.WATCH_CONFIG = {
                api: "{{ url('/api/webtv-auto-playlist/current-url') }}",
                baseUrl: "{{ rtrim(config('app.url'), '/') }}",
                streamPath: "/hls/streams/unified.m3u8",
                sseUrl: "{{ url('/api/webtv/live/status/stream') }}",
                debug: forceDebug || {{ config('app.debug') ? 'true' : 'false' }}
            };
        })();
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/hls.js@1.6.14/dist/hls.min.js" crossorigin="anonymous"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Pusher !== 'undefined' && typeof Echo !== 'undefined') {
                window.Pusher = Pusher;
                var c = window.WATCH_CONFIG || {};
                var base = (c.baseUrl || '').replace(/\/$/, '');
                var isLocal = /localhost|127\.0\.0\.1|^192\.|^10\./.test(location.hostname) || (location.port && location.port !== '443' && location.port !== '80');
                var host = isLocal && base ? new URL(base).hostname : location.hostname;
                var tls = host !== 'localhost' && host !== '127.0.0.1';
                window.Echo = new Echo({
                    broadcaster: 'reverb',
                    key: '{{ config("broadcasting.connections.reverb.key") }}',
                    wsHost: host, wsPort: 8444, wssPort: 8444, forceTLS: tls,
                    enabledTransports: tls ? ['wss'] : ['ws', 'wss'],
                    authEndpoint: isLocal ? base + '/broadcasting/auth' : '/broadcasting/auth',
                    auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' } }
                });
            }
        });
    </script>
    @vite(['resources/js/watch-page.js'])
</body>
</html>
