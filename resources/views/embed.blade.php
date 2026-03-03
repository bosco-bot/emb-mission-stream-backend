<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Embed — {{ $videoId }}</title>
    {{-- Préconnexion pour accélérer le chargement de l'iframe YouTube --}}
    <link rel="preconnect" href="https://www.youtube.com" crossorigin>
    <link rel="preconnect" href="https://www.google.com" crossorigin>
    <link rel="dns-prefetch" href="https://www.youtube.com">
    <link rel="dns-prefetch" href="https://i.ytimg.com">
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; background: #000; }
        #player-container { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        #player-container iframe { display: block; width: 100%; height: 100%; border: 0; }
        .open-in-tab { position: fixed; bottom: 8px; right: 8px; z-index: 10; padding: 6px 10px; font-size: 12px; font-family: sans-serif; color: #fff; background: rgba(0,0,0,0.7); border: 1px solid #666; border-radius: 4px; cursor: pointer; text-decoration: none; }
        .open-in-tab:hover { background: rgba(50,50,50,0.9); }
    </style>
</head>
<body>
    <div id="open-in-tab-wrap"></div>
    <script>
        (function() {
            if (window.self !== window.top) {
                var a = document.createElement('a');
                a.id = 'open-in-tab-link';
                a.className = 'open-in-tab';
                a.href = '#';
                a.textContent = "Ouvrir dans l'onglet actuel";
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.top.location.href = window.location.href;
                });
                document.getElementById('open-in-tab-wrap').appendChild(a);
            }
        })();
    </script>
    <div id="player-container">
        <iframe
            id="yt-embed"
            src="https://www.youtube.com/embed/{{ $videoId }}?origin={{ urlencode($origin) }}&modestbranding=1&rel=0&autoplay=1@if(empty($minimal))&enablejsapi=1@endif"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
            referrerpolicy="strict-origin-when-cross-origin"
            fetchpriority="high"
            loading="eager"
        ></iframe>
    </div>

    @if(empty($minimal))
    <script>
        (function() {
            var origin = {{ json_encode($origin) }};
            var videoId = {{ json_encode($videoId) }};

            function sendToParent(event, data) {
                try {
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage({
                            source: 'embmission-embed',
                            event: event,
                            videoId: videoId,
                            data: data || {}
                        }, '*');
                    }
                } catch (e) {}
            }

            function onYouTubeIframeAPIReady() {
                var player = new YT.Player('yt-embed', {
                    videoId: videoId,
                    events: {
                        onReady: function(e) {
                            sendToParent('ready', { target: e.target });
                        },
                        onStateChange: function(e) {
                            sendToParent('stateChange', { state: e.data, code: e.data });
                        }
                    }
                });
            }

            if (typeof YT !== 'undefined' && YT.Player) {
                onYouTubeIframeAPIReady();
            } else {
                window.onYouTubeIframeAPIReady = onYouTubeIframeAPIReady;
                var tag = document.createElement('script');
                tag.src = 'https://www.youtube.com/iframe_api';
                var first = document.getElementsByTagName('script')[0];
                first.parentNode.insertBefore(tag, first);
            }
        })();
    </script>
    @endif
</body>
</html>
