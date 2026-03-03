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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #000;
            font-family: Arial, sans-serif;
            overflow: hidden;
        }
        
        #player-container {
            width: 100vw;
            height: 100vh;
            position: relative;
        }
        
        #player {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        #html5-player {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: none;
        }
        
        #loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 18px;
            z-index: 10;
        }
        
        #error {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #ff6b6b;
            font-size: 16px;
            text-align: center;
            z-index: 10;
            display: none;
        }
    </style>
</head>
<body>
    <div id="player-container">
        <div id="loading">Chargement du lecteur...</div>
        <div id="error">
            <p>Aucun contenu disponible pour le moment.</p>
            <p>Veuillez réessayer plus tard.</p>
        </div>
        <iframe id="player" src="" width="100%" height="100%" frameborder="0" allowfullscreen="true" style="display: none;"></iframe>
        <video id="html5-player" controls autoplay playsinline muted style="display: none;"></video>
    </div>
    
    <script>
        let currentUrl = null;
        
        function updatePlayer() {
            fetch("https://tv.embmission.com/api/webtv-auto-playlist/current-url")
                .then(response => response.json())
                .then(data => {
                    console.log("API Response:", data);
                    
                    if (data.success && data.data) {
                        let playerUrl;
                        let useIframe = false;
                        
                        if (data.data.stream_id) {
                            // MODE LIVE: Utiliser play.html avec stream_id
                            playerUrl = "https://tv.embmission.com/webtv-live/play.html?name=" + data.data.stream_id;
                            useIframe = true;
                        } else if (data.data.url) {
                            // MODE VOD: Utiliser l'URL directe
                            playerUrl = data.data.url;
                            useIframe = false;
                        } else {
                            // MODE NONE: Aucune source disponible
                            showError();
                            return;
                        }
                        
                        // Ne mettre à jour que si l'URL a changé
                        if (currentUrl === playerUrl) {
                            console.log("URL unchanged, skipping update");
                            return;
                        }
                        
                        currentUrl = playerUrl;
                        
                        if (useIframe) {
                            document.getElementById("player").src = playerUrl;
                            document.getElementById("player").style.display = "block";
                            document.getElementById("html5-player").style.display = "none";
                            document.getElementById("html5-player").pause();
                            
                            // Tracking GA4 - Mode Live
                            if (typeof gtag !== 'undefined') {
                                gtag('event', 'webtv_live_start', {
                                    'event_category': 'WebTV',
                                    'event_label': 'Live Streaming',
                                    'stream_id': data.data.stream_id
                                });
                            }
                        } else {
                            document.getElementById("html5-player").src = playerUrl;
                            document.getElementById("html5-player").style.display = "block";
                            document.getElementById("player").style.display = "none";
                            
                            // ✅ Forcer la lecture automatique
                            const videoPlayer = document.getElementById("html5-player");
                            videoPlayer.load();
                            videoPlayer.play().then(() => {
                                console.log("✅ Lecture automatique démarrée");
                                
                                // Tracking GA4 - Mode VoD
                                if (typeof gtag !== 'undefined') {
                                    gtag('event', 'webtv_vod_start', {
                                        'event_category': 'WebTV',
                                        'event_label': 'VoD Streaming',
                                        'vod_url': playerUrl
                                    });
                                }
                            }).catch(error => {
                                console.log("⚠️ Lecture automatique bloquée par le navigateur:", error);
                                // L'utilisateur devra cliquer sur play
                                // Tracking GA4 - Mode VoD
                                if (typeof gtag !== 'undefined') {
                                    gtag('event', 'webtv_vod_start', {
                                        'event_category': 'WebTV',
                                        'event_label': 'VoD Streaming (manual)',
                                        'vod_url': playerUrl
                                    });
                                }
                            });
                            
                            // Quand la vidéo se termine, charger le prochain
                            videoPlayer.onended = function() {
                                console.log("Video ended, loading next...");
                                
                                // Tracking GA4 - Vidéo terminée
                                if (typeof gtag !== 'undefined') {
                                    gtag('event', 'webtv_vod_completed', {
                                        'event_category': 'WebTV',
                                        'event_label': 'VoD Completed',
                                        'duration': Math.round(document.getElementById("html5-player").currentTime)
                                    });
                                }
                                
                                loadNextVod();
                            };
                        }
                        
                        document.getElementById("loading").style.display = "none";
                        document.getElementById("error").style.display = "none";
                        console.log("Player updated with URL:", playerUrl);
                    } else {
                        showError();
                    }
                })
                .catch(error => {
                    console.error("Erreur API:", error);
                    showError();
                });
        }
        
        function loadNextVod() {
            console.log("Loading next VOD...");
            document.getElementById("loading").style.display = "block";
            
            fetch("https://tv.embmission.com/api/webtv-auto-playlist/next-vod")
                .then(response => response.json())
                .then(data => {
                    console.log("Next VOD Response:", data);
                    
                    if (data.success && data.data && data.data.url) {
                        currentUrl = data.data.url;
                        const videoPlayer = document.getElementById("html5-player");
                        videoPlayer.src = data.data.url;
                        videoPlayer.load();
                        
                        // ✅ Forcer la lecture automatique pour la prochaine vidéo
                        videoPlayer.play().then(() => {
                            console.log("✅ Vidéo suivante démarrée automatiquement");
                        }).catch(error => {
                            console.log("⚠️ Lecture automatique vidéo suivante bloquée:", error);
                        });
                        
                        document.getElementById("loading").style.display = "none";
                        console.log("Next VOD loaded:", data.data.url);
                    } else {
                        // Plus de vidéos, recommencer
                        console.log("No more videos, restarting playlist");
                        currentUrl = null;
                        updatePlayer();
                    }
                })
                .catch(error => {
                    console.error("Erreur next VOD:", error);
                    showError();
                });
        }
        
        function showError() {
            document.getElementById("loading").style.display = "none";
            document.getElementById("error").style.display = "block";
            document.getElementById("player").style.display = "none";
            document.getElementById("html5-player").style.display = "none";
        }
        
        // Mettre à jour au chargement
        updatePlayer();
        
        // Tracking du temps de visionnage toutes les 60 secondes (pour les vidéos VoD)
        let watchTimeTracker = null;
        const html5Player = document.getElementById("html5-player");
        
        if (html5Player) {
            html5Player.addEventListener('play', function() {
                // Envoyer un événement toutes les 60 secondes
                watchTimeTracker = setInterval(function() {
                    if (!html5Player.paused && html5Player.currentTime > 0) {
                        if (typeof gtag !== 'undefined') {
                            gtag('event', 'webtv_watch_time', {
                                'event_category': 'WebTV',
                                'event_label': 'Temps de visionnage',
                                'value': Math.round(html5Player.currentTime / 60) // temps en minutes
                            });
                        }
                    }
                }, 60000); // toutes les 60 secondes
            });
            
            html5Player.addEventListener('pause', function() {
                // Arrêter le tracker quand l'utilisateur met en pause
                if (watchTimeTracker) {
                    clearInterval(watchTimeTracker);
                    watchTimeTracker = null;
                }
            });
        }
    </script>
</body>
</html>
