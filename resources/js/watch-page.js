/**
 * EMB Mission TV — Lecteur Watch (refonte type grande plateforme)
 * Un flux unique (unified.m3u8), API pour statut et métadonnées uniquement.
 */
(function () {
    'use strict';

    const CONFIG = window.WATCH_CONFIG || {};
    const log = CONFIG.debug ? console.log.bind(console) : () => {};
    const warn = CONFIG.debug ? console.warn.bind(console) : () => {};
    const err = CONFIG.debug ? console.error.bind(console) : () => {};

    const API_URL = CONFIG.api || '';
    const STREAM_URL = (CONFIG.baseUrl || '').replace(/\/$/, '') + (CONFIG.streamPath || '/hls/streams/unified.m3u8');
    const POLL_INTERVAL_MS = 8000;
    const RETRY_AFTER_ERROR_MS = 10000;

    const el = {
        app: document.getElementById('app'),
        splash: document.getElementById('splash'),
        splashText: document.getElementById('splash-text'),
        playerWrap: document.getElementById('player-wrap'),
        video: document.getElementById('video'),
        loading: document.getElementById('loading'),
        error: document.getElementById('error'),
        errorMessage: document.getElementById('error-message'),
        meta: document.getElementById('meta'),
        watchDebug: document.getElementById('watch-debug'),
    };

    const state = {
        mode: 'idle',
        streamUrl: null,
        metadata: null,
        hls: null,
        pollTimer: null,
        sse: null,
    };

    function getStreamUrl() {
        return STREAM_URL;
    }

    function isSafari() {
        const ua = typeof navigator !== 'undefined' ? navigator.userAgent : '';
        return (ua.indexOf('Safari') !== -1 && ua.indexOf('Chrome') === -1) || /(iPhone|iPad|iPod)/.test(ua);
    }

    function showSplash(visible, text) {
        if (!el.splash) return;
        el.splash.hidden = !visible;
        if (visible && el.splashText && text) el.splashText.textContent = text;
    }

    function showPlayer(visible) {
        if (el.playerWrap) el.playerWrap.hidden = !visible;
    }

    function showLoading(visible, text) {
        if (!el.loading) return;
        el.loading.hidden = !visible;
        if (visible && text) el.loading.textContent = text;
    }

    function showError(visible, message) {
        if (!el.error) return;
        el.error.hidden = !visible;
        if (el.errorMessage) el.errorMessage.textContent = message || 'Aucun contenu disponible.';
    }

    function showMeta(visible, html) {
        if (!el.meta) return;
        el.meta.hidden = !visible;
        if (visible && html) el.meta.innerHTML = html;
    }

    function updateMeta(data) {
        if (!data || !data.item_title) return;
        const cur = data.current_time != null ? Math.floor(data.current_time) : 0;
        const dur = data.duration != null ? Math.floor(data.duration) : 0;
        const m = Math.floor(cur / 60);
        const s = cur % 60;
        const dm = Math.floor(dur / 60);
        const ds = dur % 60;
        showMeta(true, `<strong>${String(data.item_title).substring(0, 40)}</strong><br>${m}:${String(s).padStart(2, '0')} / ${dm}:${String(ds).padStart(2, '0')}`);
    }

    function updateWatchDebug(data) {
        if (!CONFIG.debug || !el.watchDebug) return;
        const apiTime = data && typeof data.current_time === 'number' ? data.current_time : null;
        const playerTime = el.video && Number.isFinite(el.video.currentTime) ? el.video.currentTime : null;
        const drift = (apiTime != null && playerTime != null) ? (playerTime - apiTime).toFixed(1) : '—';
        const buf = el.video && el.video.buffered && el.video.buffered.length ? el.video.buffered.end(el.video.buffered.length - 1).toFixed(1) : '—';
        el.watchDebug.hidden = false;
        el.watchDebug.innerHTML = 'DEBUG mode=' + (state.mode || '—') + ' | API=' + (apiTime != null ? apiTime.toFixed(1) + 's' : '—') + ' | player=' + (playerTime != null ? playerTime.toFixed(1) + 's' : '—') + ' | drift=' + drift + 's | bufferEnd=' + buf + 's';
    }

    function destroyPlayer() {
        if (state.hls) {
            try {
                state.hls.detachMedia();
                state.hls.destroy();
            } catch (e) { warn('hls destroy', e); }
            state.hls = null;
        }
        if (el.video) {
            el.video.removeAttribute('src');
            el.video.load();
        }
    }

    function playWithHls(url, isLive) {
        if (!window.Hls || !Hls.isSupported()) {
            el.video.src = url;
            el.video.load();
            return;
        }
        destroyPlayer();
        const config = {
            enableWorker: true,
            maxBufferLength: 60,
            maxMaxBufferLength: 120,
            maxBufferSize: 100 * 1024 * 1024,
            nudgeOffset: 0.2,
            nudgeMaxRetry: 10,
        };
        if (isLive) {
            config.liveSyncDurationCount = 8;
            config.liveMaxLatencyDurationCount = 40;
        }
        const hls = new Hls(config);
        state.hls = hls;

        hls.on(Hls.Events.ERROR, function (event, data) {
            if (!data.fatal) return;
            err('HLS fatal', data);
            if (data.type === Hls.ErrorTypes.NETWORK_ERROR || data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                try {
                    if (data.type === Hls.ErrorTypes.MEDIA_ERROR) hls.recoverMediaError();
                    else hls.startLoad();
                } catch (e) {
                    setErrorAndRetry('Erreur de lecture.');
                }
            } else {
                setErrorAndRetry('Erreur de lecture.');
            }
        });

        if (isLive) {
            hls.on(Hls.Events.LEVEL_LOADED, function () {
                try {
                    const pos = hls.liveSyncPosition ?? (el.video.seekable.length ? el.video.seekable.end(el.video.seekable.length - 1) - 5 : 0);
                    if (Number.isFinite(pos) && pos > 0) el.video.currentTime = Math.max(0, pos);
                } catch (e) {}
            });
        }

        hls.loadSource(url);
        hls.attachMedia(el.video);
    }

    function playWithNative(url, isLive) {
        destroyPlayer();
        el.video.src = url;
        el.video.load();
        if (isLive) {
            el.video.addEventListener('loadedmetadata', function onMeta() {
                el.video.removeEventListener('loadedmetadata', onMeta);
                try {
                    if (el.video.seekable.length > 0) {
                        const end = el.video.seekable.end(el.video.seekable.length - 1);
                        el.video.currentTime = Math.max(0, end - 5);
                    }
                } catch (e) {}
            }, { once: true });
        }
    }

    function loadStream(url, isLive) {
        if (!el.video) return;
        showPlayer(true);
        showError(false);
        showLoading(true, 'Chargement...');
        state.streamUrl = url;
        const useHls = url.indexOf('.m3u8') !== -1 && window.Hls && Hls.isSupported() && !isSafari();
        if (useHls) {
            playWithHls(url, isLive);
        } else {
            playWithNative(url, isLive);
        }
    }

    function setErrorAndRetry(message) {
        state.mode = 'error';
        showSplash(false);
        showLoading(false);
        showPlayer(false);
        showError(true, message);
        destroyPlayer();
        if (state.pollTimer) clearInterval(state.pollTimer);
        state.streamUrl = null;
        state.pollTimer = setInterval(fetchStatus, RETRY_AFTER_ERROR_MS);
    }

    function fetchStatus() {
        if (!API_URL) {
            setErrorAndRetry('Configuration manquante.');
            return;
        }
        fetch(API_URL, { cache: 'no-store', credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                const data = json?.data?.data || json?.data;
                if (!json?.success || !data) {
                    setErrorAndRetry(data?.message || 'Indisponible.');
                    return;
                }
                if (CONFIG.debug) {
                    log('[WATCH] API', 'mode=' + (data.mode || '—'), 'current_time=' + (data.current_time != null ? data.current_time.toFixed(1) : '—'), 'item_id=' + (data.item_id || '—'), 'duration=' + (data.duration != null ? data.duration.toFixed(1) : '—'), 'title=' + (data.item_title ? String(data.item_title).substring(0, 30) : '—'));
                }
                updateWatchDebug(data);
                onStatus(data);
            })
            .catch(function (e) {
                warn('API error', e);
                setErrorAndRetry('Erreur de connexion.');
            });
    }

    function onStatus(data) {
        const mode = data.mode === 'paused' ? 'paused' : (data.mode === 'live' ? 'live' : (data.url || data.stream_url) ? 'vod' : null);
        if (mode === 'paused') {
            state.mode = 'paused';
            showSplash(false);
            showLoading(false);
            showPlayer(false);
            showError(true, data.message || 'Diffusion en pause.');
            destroyPlayer();
            if (state.pollTimer) clearInterval(state.pollTimer);
            state.pollTimer = setInterval(fetchStatus, RETRY_AFTER_ERROR_MS);
            return;
        }
        if (!mode || (mode !== 'live' && mode !== 'vod')) {
            setErrorAndRetry('Aucun contenu disponible.');
            return;
        }
        const previousMode = state.mode;
        state.mode = mode;
        state.metadata = data;
        updateWatchDebug(data);
        const url = getStreamUrl();
        const sameStream = state.streamUrl === url;
        const hasSource = el.video && (el.video.src || el.video.currentSrc || state.hls);
        const modeChanged = (previousMode === 'live' || previousMode === 'vod') && previousMode !== mode;
        const alreadyPlaying = sameStream && hasSource && !modeChanged;
        if (alreadyPlaying) {
            showLoading(false);
            showSplash(false);
            updateMeta(data);
            return;
        }
        if (modeChanged) {
            destroyPlayer();
            state.streamUrl = null;
        }
        const needLoad = !sameStream || !hasSource;
        if (needLoad) {
            loadStream(url, mode === 'live');
        } else {
            showLoading(false);
            updateMeta(data);
        }
        showError(false);
        showSplash(false);
        startPolling();
    }

    function startPolling() {
        if (state.pollTimer) clearInterval(state.pollTimer);
        state.pollTimer = setInterval(fetchStatus, POLL_INTERVAL_MS);
    }

    function initSSE() {
        const sseUrl = CONFIG.sseUrl;
        if (!sseUrl || typeof EventSource === 'undefined') {
            startPolling();
            return;
        }
        try {
            const sse = new EventSource(sseUrl);
            state.sse = sse;
            sse.addEventListener('status', function (event) {
                try {
                    const data = JSON.parse(event.data);
                    onStatus(data);
                } catch (e) { warn('SSE parse', e); }
            });
            sse.onerror = function () {
                startPolling();
            };
        } catch (e) {
            warn('SSE init', e);
            startPolling();
        }
    }

    if (el.video) {
        el.video.addEventListener('canplay', function () {
            showLoading(false);
            showSplash(false);
            updateMeta(state.metadata);
            el.video.play().catch(function () {});
        }, { once: false });

        el.video.addEventListener('playing', function () {
            showLoading(false);
            showSplash(false);
        });

        el.video.addEventListener('error', function () {
            if (el.video.error && state.mode !== 'error') {
                setErrorAndRetry('Erreur de lecture.');
            }
        });

        el.video.addEventListener('ended', function () {
            if (state.streamUrl && state.streamUrl.indexOf('unified.m3u8') !== -1) {
                el.video.play().catch(function () {});
            }
        });
    }

    showSplash(true, 'Chargement...');
    showError(false);
    showPlayer(false);
    showMeta(false);
    if (el.watchDebug) el.watchDebug.hidden = !CONFIG.debug;
    initSSE();
    fetchStatus();
    startPolling();

    if (CONFIG.debug && el.video) {
        let lastLogTs = 0;
        const TICK_LOG_INTERVAL_MS = 20000;
        setInterval(function () {
            if (!state.metadata || !el.video || el.video.readyState < 2) return;
            const now = Date.now();
            if (now - lastLogTs < TICK_LOG_INTERVAL_MS) return;
            lastLogTs = now;
            const apiTime = state.metadata.current_time;
            const playerTime = el.video.currentTime;
            const drift = (typeof apiTime === 'number' && Number.isFinite(playerTime)) ? (playerTime - apiTime) : null;
            log('[WATCH] tick', 'player=' + playerTime.toFixed(1) + 's', 'API=' + (apiTime != null ? apiTime.toFixed(1) : '—') + 's', 'drift=' + (drift != null ? drift.toFixed(1) : '—') + 's');
            updateWatchDebug(state.metadata);
        }, 5000);
    }

    window.addEventListener('beforeunload', function () {
        if (state.pollTimer) clearInterval(state.pollTimer);
        if (state.sse) try { state.sse.close(); } catch (e) {}
        destroyPlayer();
    });
})();
