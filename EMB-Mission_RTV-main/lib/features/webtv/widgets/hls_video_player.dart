import 'dart:html' as html;
import 'dart:js' as js;
import 'package:flutter/material.dart';

/// Lecteur vidéo HTML5 avec support HLS.js pour les streams M3U8
/// 
/// Ce widget utilise un élément HTML5 video avec HLS.js pour lire
/// automatiquement les streams en direct au format M3U8.
class HlsVideoPlayer extends StatefulWidget {
  const HlsVideoPlayer({
    super.key,
    required this.streamUrl,
    this.thumbnailUrl,
    this.title,
    this.description,
    this.isLive = false,
    this.autoplay = true,
    this.muted = true, // Muted par défaut pour l'autoplay
    this.controls = true,
    this.width,
    this.height,
  });

  final String streamUrl;
  final String? thumbnailUrl;
  final String? title;
  final String? description;
  final bool isLive;
  final bool autoplay;
  final bool muted;
  final bool controls;
  final double? width;
  final double? height;

  @override
  State<HlsVideoPlayer> createState() => _HlsVideoPlayerState();
}

class _HlsVideoPlayerState extends State<HlsVideoPlayer> {
  html.VideoElement? _videoElement;
  bool _isInitialized = false;
  bool _hasError = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _initializePlayer();
  }

  @override
  void didUpdateWidget(HlsVideoPlayer oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.streamUrl != widget.streamUrl) {
      _loadNewStream();
    }
  }

  void _initializePlayer() {
    // Attendre que le widget soit monté
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _createVideoElement();
    });
  }

  void _createVideoElement() {
    try {
      // Créer l'élément vidéo HTML5
      _videoElement = html.VideoElement()
        ..id = 'hls-video-player-${DateTime.now().millisecondsSinceEpoch}'
        ..autoplay = widget.autoplay
        ..muted = widget.muted
        ..controls = widget.controls
        ..style.width = widget.width?.toString() ?? '100%'
        ..style.height = widget.height?.toString() ?? '100%'
        ..style.borderRadius = '12px'
        ..style.objectFit = 'cover';

      // Ajouter l'élément au DOM
      final container = html.document.getElementById('video-container');
      if (container != null) {
        container.children.clear();
        container.children.add(_videoElement!);
      }

      // Charger HLS.js et initialiser le lecteur
      _loadHlsLibrary();
    } catch (e) {
      setState(() {
        _hasError = true;
        _errorMessage = 'Erreur lors de la création du lecteur: $e';
      });
    }
  }

  void _loadHlsLibrary() {
    // Vérifier si HLS.js est déjà chargé
    if (js.context.hasProperty('Hls')) {
      _initializeHls();
    } else {
      // Charger HLS.js depuis CDN
      final script = html.ScriptElement()
        ..src = 'https://cdn.jsdelivr.net/npm/hls.js@latest'
        ..onLoad.listen((_) => _initializeHls())
        ..onError.listen((_) {
          setState(() {
            _hasError = true;
            _errorMessage = 'Erreur lors du chargement de HLS.js';
          });
        });
      
      html.document.head!.children.add(script);
    }
  }

  void _initializeHls() {
    if (_videoElement == null) return;

    try {
      // Vérifier si le navigateur supporte HLS nativement (Safari)
      if (_videoElement!.canPlayType('application/vnd.apple.mpegurl') != '') {
        // Support natif (Safari)
        _videoElement!.src = widget.streamUrl;
        _videoElement!.load();
        return;
      }

      // Vérifier si HLS.js est disponible
      if (js.context.hasProperty('Hls')) {
        final hls = js.JsObject(js.context['Hls']);
        
        if (hls['isSupported'] == true) {
          // Utiliser HLS.js pour les autres navigateurs
          hls['loadSource'](widget.streamUrl);
          hls['attachMedia'](_videoElement);
          
          // Gérer les événements HLS
          hls['on'](js.JsObject.jsify({
            'hlsManifestParsed': js.allowInterop((event, data) {
              print('HLS manifest parsed');
            }),
            'hlsError': js.allowInterop((event, data) {
              print('HLS error: $data');
              _fallbackToIframe();
            }),
          }));
          return;
        }
      }

      // Fallback : utiliser l'URL de lecture d'Ant Media Server
      _fallbackToIframe();

      // Gérer les événements vidéo
      _videoElement!.onLoad.listen((_) {
        print('Début du chargement vidéo');
      });

      _videoElement!.onCanPlayThrough.listen((_) {
        print('Vidéo prête à être lue');
        setState(() {
          _isInitialized = true;
        });
      });

      _videoElement!.onError.listen((event) {
        print('Erreur vidéo: $event');
        setState(() {
          _hasError = true;
          _errorMessage = 'Erreur de lecture vidéo';
        });
      });

    } catch (e) {
      setState(() {
        _hasError = true;
        _errorMessage = 'Erreur lors de l\'initialisation HLS: $e';
      });
    }
  }

  void _loadNewStream() {
    if (_videoElement != null && !_hasError) {
      _videoElement!.src = widget.streamUrl;
      _videoElement!.load();
    }
  }

  void _fallbackToIframe() {
    // Extraire le stream ID de l'URL M3U8
    final streamId = widget.streamUrl.split('/').last.split('.').first;
    final playUrl = 'https://tv.embmission.com/webtv-live/play.html?name=$streamId';
    
    // Créer un iframe pour le lecteur Ant Media Server
    final iframe = html.IFrameElement()
      ..src = playUrl
      ..style.width = '100%'
      ..style.height = '100%'
      ..style.border = 'none'
      ..style.borderRadius = '12px'
      ..allowFullscreen = true;

    // Remplacer l'élément vidéo par l'iframe
    final container = html.document.getElementById('video-container');
    if (container != null) {
      container.children.clear();
      container.children.add(iframe);
    }

    setState(() {
      _isInitialized = true;
    });
  }

  @override
  void dispose() {
    _videoElement?.remove();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Extraire le stream ID de l'URL M3U8
    final streamId = widget.streamUrl.split('/').last.split('.').first;
    final playUrl = 'https://tv.embmission.com/webtv-live/play.html?name=$streamId';
    
    return Container(
      width: widget.width,
      height: widget.height,
      decoration: BoxDecoration(
        color: Colors.black,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.1),
            blurRadius: 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Stack(
          children: [
            // Iframe direct pour Ant Media Server
            SizedBox(
              width: widget.width ?? double.infinity,
              height: widget.height ?? 400,
              child: HtmlElementView(
                viewType: 'ant-media-player',
                onPlatformViewCreated: (id) {
                  // Créer l'iframe directement
                  final iframe = html.IFrameElement()
                    ..src = playUrl
                    ..style.width = '100%'
                    ..style.height = '100%'
                    ..style.border = 'none'
                    ..style.borderRadius = '12px'
                    ..allowFullscreen = true
                    ..allow = 'autoplay; fullscreen; microphone; camera';
                  
                  // Ajouter l'iframe au DOM
                  html.document.body!.children.add(iframe);
                },
              ),
            ),
            
            // Overlay de chargement
            if (!_isInitialized && !_hasError)
              Container(
                color: Colors.black.withOpacity(0.7),
                child: const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      CircularProgressIndicator(color: Colors.white),
                      SizedBox(height: 16),
                      Text(
                        'Chargement du stream...',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            
            // Overlay d'erreur
            if (_hasError)
              Container(
                color: Colors.black.withOpacity(0.8),
                child: Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(
                        Icons.error_outline,
                        size: 64,
                        color: Colors.red,
                      ),
                      const SizedBox(height: 16),
                      const Text(
                        'Erreur de lecture',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        _errorMessage ?? 'Erreur inconnue',
                        style: const TextStyle(
                          color: Colors.white70,
                          fontSize: 14,
                        ),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: () {
                          setState(() {
                            _hasError = false;
                            _errorMessage = null;
                          });
                          _initializePlayer();
                        },
                        child: const Text('Réessayer'),
                      ),
                    ],
                  ),
                ),
              ),
            
            // Badge LIVE
            if (widget.isLive && _isInitialized)
              Positioned(
                top: 12,
                left: 12,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.red,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: const Text(
                    '🔴 LIVE',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
