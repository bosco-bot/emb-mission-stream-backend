<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMB Mission - WebRadio</title>
    
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-ZN07XKXPCN"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-ZN07XKXPCN');

        // ✅ NOUVELLE FONCTIONNALITÉ: Affichage dynamique des titres
        async function updateNowPlaying() {
            try {
                const response = await fetch('/api/radio/track/current');
                const data = await response.json();
                
                if (data.success && data.data) {
                    const artist = data.data.artist;
                    const title = data.data.title || 'Titre inconnu';
                    
                    // Si l'artiste existe et n'est pas vide, afficher Artiste - Titre
                    // Sinon, afficher seulement le titre
                    if (artist && artist.trim() !== '') {
                        document.getElementById('nowPlaying').textContent = artist + ' - ' + title;
                    } else {
                        document.getElementById('nowPlaying').textContent = title;
                    }
                } else {
                    document.getElementById('nowPlaying').textContent = 'Diffusion en cours...';
                }
            } catch (error) {
                console.log('Info: titre non disponible');
                document.getElementById('nowPlaying').textContent = 'Diffusion en cours...';
            }
        }
        
        // Mise à jour automatique toutes les 10 secondes
        setInterval(updateNowPlaying, 10000);
        
        // Premier appel immédiat
        updateNowPlaying();
        
        // Note: L'écouteur 'play' pour updateNowPlaying est ajouté dans le script principal
        // après la création de audioPlayer (voir plus bas, ligne ~483)
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .player-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 600px;
            width: 100%;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .player-body {
            padding: 40px;
        }
        
        .station-info {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .station-logo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: white;
            box-shadow: 0 10px 30px rgba(102,126,234,0.3);
        }
        
        .station-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .station-slogan {
            color: #666;
            font-size: 14px;
        }
        
        .controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .play-btn {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        
        .play-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(102,126,234,0.6);
        }
        
        .play-btn:active {
            transform: scale(0.95);
        }
        
        .play-btn.playing {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .volume-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .volume-slider {
            width: 100px;
            height: 5px;
            background: #e0e0e0;
            border-radius: 5px;
            outline: none;
            -webkit-appearance: none;
        }
        
        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
        }
        
        .volume-slider::-moz-range-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
            border: none;
        }
        
        .info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .now-playing {
            font-size: 16px;
            color: #667eea;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .status {
            font-size: 14px;
            color: #666;
        }
        
        .status.live {
            color: #4caf50;
            font-weight: bold;
        }
        
        .live-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #4caf50;
            animation: pulse 2s infinite;
            margin-right: 5px;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .audio-control {
            display: none;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="player-container">
        <div class="header">
            <h1>🎵 EMB Mission</h1>
            <p>Votre WebRadio en direct</p>
        </div>
        
        <div class="player-body">
            <div class="station-info">
                <div class="station-logo">🎙️</div>
                <div class="station-name">Radio EMB Mission</div>
                <div class="station-slogan">Diffusion en direct 24/7</div>
            </div>
            
            <div class="controls">
                <button class="play-btn" id="playBtn" onclick="togglePlay()">
                    <span id="playIcon">▶</span>
                </button>
                
                <div class="volume-control">
                    <span style="font-size: 24px;">🔊</span>
                    <input 
                        type="range" 
                        class="volume-slider" 
                        id="volumeSlider" 
                        min="0" 
                        max="100" 
                        value="100" 
                        oninput="setVolume(this.value)"
                        onclick="setVolume(this.value)"
                    >
                </div>
            </div>
            
            <div class="info">
                <div class="now-playing" id="nowPlaying">Prêt à écouter</div>
                <div class="status live" id="status">
                    <span class="live-indicator"></span>
                    <span id="statusText">En pause</span>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>© 2025 EMB Mission</p>
        </div>
        
        <div id="audioWrapper">
            <audio 
                id="audioPlayer" 
                class="audio-control" 
                preload="auto"
                crossorigin="anonymous">
            </audio>
        </div>
    </div>

    <script>
        let audioPlayer = document.getElementById('audioPlayer');
        const audioWrapper = document.getElementById('audioWrapper');
        const playBtn = document.getElementById('playBtn');
        const playIcon = document.getElementById('playIcon');
        const statusText = document.getElementById('statusText');
        const nowPlaying = document.getElementById('nowPlaying');
        
        const streamUrl = 'https://radio.embmission.com/listen';
        
        let userInteractionGranted = false;
        let awaitingUserInteraction = false;
        let reconnectAttempts = 0;
        const maxReconnectAttempts = 5;
        let reconnectTimeout = null;
        let playbackMonitorInterval = null;
        let lastPlaybackPosition = 0;
        let stalledChecks = 0;
        let listenDurationTracker = null;
        
        function buildStreamUrl() {
            return `${streamUrl}?t=${Date.now()}`;
        }
        
        function notifyInteractionRequired() {
            awaitingUserInteraction = true;
            playBtn.classList.remove('playing');
            playIcon.textContent = '▶';
            statusText.textContent = 'Cliquez sur ▶ pour lancer la radio';
            nowPlaying.textContent = 'En attente de votre action';
        }
        
        function markUserInteraction() {
            if (!userInteractionGranted) {
                userInteractionGranted = true;
                awaitingUserInteraction = false;
                console.log('✅ Autorisation utilisateur obtenue');
            }
        }
        
        function setVolume(value) {
            const volume = value / 100;
            if (audioPlayer) {
                audioPlayer.volume = volume;
            }
            console.log('Volume:', Math.round(volume * 100) + '%');
        }
        
        function attemptPlayback(context = 'auto') {
            if (!audioPlayer) {
                return Promise.reject(new Error('Aucun lecteur audio disponible'));
            }
            
            if (!userInteractionGranted) {
                notifyInteractionRequired();
                return Promise.reject(new Error('interaction-required'));
            }
            
            return audioPlayer.play().then(() => {
                console.log(`✅ Lecture (${context})`);
                playBtn.classList.add('playing');
                playIcon.textContent = '⏸';
                statusText.textContent = 'En direct';
                nowPlaying.textContent = 'Diffusion en cours...';
                startPlaybackMonitor();
                reconnectAttempts = 0;
            }).catch(error => {
                console.log(`⚠️ Lecture bloquée (${context}):`, error);
                if (context === 'user-click') {
                    notifyInteractionRequired();
                }
                return Promise.reject(error);
            });
        }
        
        function togglePlay() {
            markUserInteraction();
            reconnectAttempts = 0;
            
            if (!audioPlayer) {
                return;
            }
            
            if (audioPlayer.paused) {
                attemptPlayback('user-click').then(() => {
                    if (typeof gtag !== 'undefined') {
                        gtag('event', 'radio_play', {
                            'event_category': 'Audio',
                            'event_label': 'WebRadio - EMB Mission',
                            'stream_url': streamUrl
                        });
                    }
                }).catch(() => {
                    // L'utilisateur devra peut-être cliquer de nouveau si le navigateur bloque encore
                });
            } else {
                audioPlayer.pause();
                playBtn.classList.remove('playing');
                playIcon.textContent = '▶';
                statusText.textContent = 'En pause';
                nowPlaying.textContent = 'Prêt à écouter';
                
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'radio_pause', {
                        'event_category': 'Audio',
                        'event_label': 'WebRadio - EMB Mission',
                        'listen_duration': Math.round(audioPlayer.currentTime)
                    });
                }
            }
        }
        
        function stopPlaybackMonitor() {
            if (playbackMonitorInterval) {
                clearInterval(playbackMonitorInterval);
                playbackMonitorInterval = null;
            }
            stalledChecks = 0;
        }
        
        function startPlaybackMonitor() {
            stopPlaybackMonitor();
            if (!audioPlayer) return;
            
            lastPlaybackPosition = audioPlayer.currentTime || 0;
            playbackMonitorInterval = setInterval(() => {
                if (!audioPlayer || audioPlayer.paused) {
                    stalledChecks = 0;
                    return;
                }
                
                if (audioPlayer.currentTime <= lastPlaybackPosition + 0.1) {
                    stalledChecks++;
                    console.log(`⚠️ Aucune avancée audio détectée (${stalledChecks})`);
                    
                    if (stalledChecks >= 3) {
                        console.log('🔄 Audio bloqué, reconnexion forcée...');
                        reconnectStream('watchdog');
                        stalledChecks = 0;
                    }
                } else {
                    lastPlaybackPosition = audioPlayer.currentTime;
                    stalledChecks = 0;
                }
            }, 4000);
        }
        
        function hardResetAudioPlayer(autoPlay = true) {
            console.log('♻️ Reset complet du lecteur audio');
            stopPlaybackMonitor();
            if (reconnectTimeout) {
                clearTimeout(reconnectTimeout);
                reconnectTimeout = null;
            }
            
            const previousVolume = audioPlayer ? audioPlayer.volume : 1.0;
            
            if (audioPlayer) {
                try { audioPlayer.pause(); } catch (e) {}
            }
            
            const newAudio = document.createElement('audio');
            newAudio.id = 'audioPlayer';
            newAudio.className = 'audio-control';
            newAudio.preload = 'auto';
            newAudio.crossOrigin = 'anonymous';
            newAudio.volume = previousVolume;
            newAudio.src = buildStreamUrl();
            
            audioWrapper.innerHTML = '';
            audioWrapper.appendChild(newAudio);
            audioPlayer = newAudio;
            
            attachAudioEventListeners();
            
            if (autoPlay && userInteractionGranted) {
                attemptPlayback('hard-reset').catch(() => {
                    notifyInteractionRequired();
                });
            } else {
                notifyInteractionRequired();
            }
        }
        
        function reconnectStream(reason = 'auto') {
            if (!userInteractionGranted) {
                notifyInteractionRequired();
                return;
            }
            
            if (reconnectAttempts >= maxReconnectAttempts) {
                console.error('❌ Nombre maximum de tentatives atteint');
                statusText.textContent = 'Flux indisponible, nouvelle tentative...';
                hardResetAudioPlayer(true);
                reconnectAttempts = 0;
                return;
            }
            
            reconnectAttempts++;
            console.log(`🔄 Tentative de reconnexion ${reconnectAttempts}/${maxReconnectAttempts} (${reason})`);
            
            const timestampUrl = buildStreamUrl();
            if (audioPlayer) {
                audioPlayer.src = timestampUrl;
                audioPlayer.load();
            }
            
            reconnectTimeout = setTimeout(() => {
                attemptPlayback('reconnect').then(() => {
                    console.log('✅ Reconnexion réussie');
                    reconnectAttempts = 0;
                }).catch(error => {
                    console.log('⚠️ Échec de la reconnexion, nouvelle tentative...', error);
                    reconnectTimeout = setTimeout(() => reconnectStream('retry'), 2000 * reconnectAttempts);
                });
            }, 1000);
        }
        
        function handleError(e) {
            console.error('❌ Erreur audio:', e);
            if (!audioPlayer || !audioPlayer.error) {
                reconnectStream('error');
                return;
            }
            
            const errorCode = audioPlayer.error.code;
            if (errorCode === 2 || errorCode === 4) {
                console.log('🔄 Erreur de connexion détectée, reconnexion...');
                reconnectStream('error-code');
            }
        }
        
        function handleStalled() {
            console.log('⚠️ Stream interrompu, tentative de reconnexion...');
            if (audioPlayer && !audioPlayer.paused) {
                reconnectStream('stalled');
            }
        }
        
        function handleWaiting() {
            console.log('⏳ En attente de données...');
            setTimeout(() => {
                if (audioPlayer && audioPlayer.readyState < 3 && !audioPlayer.paused) {
                    console.log('🔄 Attente trop longue, reconnexion...');
                    reconnectStream('waiting');
                }
            }, 5000);
        }
        
        function handleLoadedMetadata() {
            attemptPlayback('loadedmetadata').catch(() => {
                notifyInteractionRequired();
            });
        }
        
        function handleCanPlay() {
            if (audioPlayer && audioPlayer.paused && userInteractionGranted) {
                attemptPlayback('canplay');
            }
        }
        
        function handlePlay() {
            nowPlaying.textContent = 'Diffusion en cours...';
            setTimeout(updateNowPlaying, 2000);
            startPlaybackMonitor();
            markUserInteraction();
            startListeningTracker();
        }
        
        function handlePause() {
            if (audioPlayer && audioPlayer.currentTime > 0) {
                nowPlaying.textContent = 'En pause';
            }
            stopPlaybackMonitor();
            stopListeningTracker();
        }
        
        function handleLoadStart() {
            statusText.textContent = 'Chargement...';
        }
        
        function handlePlaying() {
            statusText.textContent = 'En direct';
            statusText.classList.add('live');
        }
        
        function handleEnded() {
            console.log('⚠️ Stream terminé, tentative de reconnexion...');
            playBtn.classList.remove('playing');
            playIcon.textContent = '▶';
            statusText.textContent = 'En attente du stream';
            reconnectStream('ended');
        }
        
        function startListeningTracker() {
            stopListeningTracker();
            listenDurationTracker = setInterval(function() {
                if (audioPlayer && !audioPlayer.paused && audioPlayer.currentTime > 0) {
                    if (typeof gtag !== 'undefined') {
                        gtag('event', 'radio_listening_time', {
                            'event_category': 'Audio',
                            'event_label': 'Temps d\'écoute',
                            'value': Math.round(audioPlayer.currentTime / 60)
                        });
                    }
                }
            }, 60000);
        }
        
        function stopListeningTracker() {
            if (listenDurationTracker) {
                clearInterval(listenDurationTracker);
                listenDurationTracker = null;
            }
        }
        
        function attachAudioEventListeners() {
            if (!audioPlayer) return;
            
            audioPlayer.addEventListener('loadedmetadata', handleLoadedMetadata);
            audioPlayer.addEventListener('canplay', handleCanPlay);
            audioPlayer.addEventListener('error', handleError);
            audioPlayer.addEventListener('stalled', handleStalled);
            audioPlayer.addEventListener('waiting', handleWaiting);
            audioPlayer.addEventListener('play', handlePlay);
            audioPlayer.addEventListener('pause', handlePause);
            audioPlayer.addEventListener('loadstart', handleLoadStart);
            audioPlayer.addEventListener('playing', handlePlaying);
            audioPlayer.addEventListener('ended', handleEnded);
            audioPlayer.addEventListener('play', () => setTimeout(updateNowPlaying, 2000));
        }
        
        function initializeAudio() {
            if (!audioPlayer) return;
            audioPlayer.volume = 1.0;
            audioPlayer.src = buildStreamUrl();
            attachAudioEventListeners();
            audioPlayer.load();
        }
        
        initializeAudio();
        
        async function updateNowPlaying() {
            try {
                const response = await fetch('/api/radio/track/current');
                const data = await response.json();
                
                if (data.success && data.data) {
                    const artist = data.data.artist;
                    const title = data.data.title || 'Titre inconnu';
                    
                    if (artist && artist.trim() !== '') {
                        document.getElementById('nowPlaying').textContent = artist + ' - ' + title;
                    } else {
                        document.getElementById('nowPlaying').textContent = title;
                    }
                } else {
                    document.getElementById('nowPlaying').textContent = 'Diffusion en cours...';
                }
            } catch (error) {
                console.log('Info: titre non disponible');
                document.getElementById('nowPlaying').textContent = 'Diffusion en cours...';
            }
        }
        
        setInterval(updateNowPlaying, 10000);
        updateNowPlaying();
    </script>
</body>
</html>

