import 'dart:html' as html;
import 'package:flutter/material.dart';

/// Lecteur vidéo HTML5 simple pour streams M3U8
/// 
/// Ce widget utilise un élément HTML5 video natif pour lire
/// les streams M3U8 directement.
class NativeVideoPlayer extends StatefulWidget {
  const NativeVideoPlayer({
    super.key,
    required this.streamUrl,
    this.isLive = false,
    this.width,
    this.height,
  });

  final String streamUrl;
  final bool isLive;
  final double? width;
  final double? height;

  @override
  State<NativeVideoPlayer> createState() => _NativeVideoPlayerState();
}

class _NativeVideoPlayerState extends State<NativeVideoPlayer> {
  html.VideoElement? _videoElement;
  bool _isLoaded = false;
  bool _hasError = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _createVideoElement();
    });
  }

  void _createVideoElement() {
    try {
      print('Création lecteur vidéo avec URL: ${widget.streamUrl}');
      
      // Créer l'élément vidéo HTML5
      _videoElement = html.VideoElement()
        ..src = widget.streamUrl
        ..autoplay = true
        ..muted = true // Muted pour permettre l'autoplay
        ..controls = true
        ..style.width = '100%'
        ..style.height = '100%'
        ..style.borderRadius = '12px'
        ..style.objectFit = 'cover'
        ..crossOrigin = 'anonymous';

      // Gérer les événements vidéo
      _videoElement!.onLoad.listen((_) {
        print('Début du chargement vidéo');
      });

      _videoElement!.onCanPlayThrough.listen((_) {
        print('Vidéo prête à être lue');
        setState(() {
          _isLoaded = true;
        });
      });

      _videoElement!.onError.listen((event) {
        print('Erreur vidéo: $event');
        setState(() {
          _hasError = true;
          _errorMessage = 'Erreur de lecture vidéo';
        });
      });

      _videoElement!.onLoadedData.listen((_) {
        print('Données vidéo chargées');
        setState(() {
          _isLoaded = true;
        });
      });

      // Ajouter l'élément au DOM
      html.document.body!.children.add(_videoElement!);
      
    } catch (e) {
      print('Erreur création vidéo: $e');
      setState(() {
        _hasError = true;
        _errorMessage = 'Erreur lors de la création du lecteur: $e';
      });
    }
  }

  @override
  void dispose() {
    _videoElement?.remove();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
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
            // Lecteur vidéo HTML5
            SizedBox(
              width: widget.width ?? double.infinity,
              height: widget.height ?? 400,
              child: HtmlElementView(
                viewType: 'native-video-player',
                onPlatformViewCreated: (id) {
                  _createVideoElement();
                },
              ),
            ),
            
            // Overlay de chargement
            if (!_isLoaded && !_hasError)
              Container(
                color: Colors.black.withOpacity(0.8),
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
                          _createVideoElement();
                        },
                        child: const Text('Réessayer'),
                      ),
                    ],
                  ),
                ),
              ),
            
            // Badge LIVE
            if (widget.isLive && _isLoaded)
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
