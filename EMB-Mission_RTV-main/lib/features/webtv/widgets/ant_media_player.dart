import 'dart:html' as html;
import 'dart:ui_web' as ui_web;
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:flutter/material.dart';

/// Lecteur Ant Media Server utilisant play.html
/// 
/// Ce widget charge directement la page play.html fournie par
/// Ant Media Server dans un iframe pour une lecture optimale.
class AntMediaPlayer extends StatefulWidget {
  const AntMediaPlayer({
    super.key,
    required this.streamId,
    this.isLive = false,
    this.width,
    this.height,
  });

  final String streamId;
  final bool isLive;
  final double? width;
  final double? height;

  @override
  State<AntMediaPlayer> createState() => _AntMediaPlayerState();
}

class _AntMediaPlayerState extends State<AntMediaPlayer> {
  late String _viewId;
  String? _lastStreamId;

  @override
  void initState() {
    super.initState();
    _viewId = 'ant-media-player-${DateTime.now().microsecondsSinceEpoch}';
    _lastStreamId = widget.streamId;
    _registerIframe();
  }

  @override
  void didUpdateWidget(AntMediaPlayer oldWidget) {
    super.didUpdateWidget(oldWidget);
    // Si le streamId change, recréer l'iframe avec un nouveau viewId
    if (oldWidget.streamId != widget.streamId || _lastStreamId != widget.streamId) {
      print('🔄 StreamId changé: ${oldWidget.streamId} -> ${widget.streamId}, rechargement du lecteur...');
      _lastStreamId = widget.streamId;
      // Générer un nouveau viewId pour forcer la recréation de l'iframe
      _viewId = 'ant-media-player-${DateTime.now().microsecondsSinceEpoch}';
      _registerIframe();
      // Forcer la reconstruction du widget
      setState(() {});
    }
  }

  void _registerIframe() {
    // Construire l'URL du lecteur Ant Media avec un timestamp pour éviter le cache
    final timestamp = DateTime.now().millisecondsSinceEpoch;
    final playerUrl = 'https://tv.embmission.com/webtv-live/play.html?name=${widget.streamId}&t=$timestamp';
    
    print('🎬 Chargement du lecteur Ant Media: $playerUrl');

    // Enregistrer la vue pour l'iframe
    // ignore: undefined_prefixed_name
    ui_web.platformViewRegistry.registerViewFactory(
      _viewId,
      (int viewId) {
        final iframe = html.IFrameElement()
          ..src = playerUrl
          ..style.border = 'none'
          ..style.width = '100%'
          ..style.height = '100%'
          ..allow = 'autoplay; fullscreen; encrypted-media'
          ..allowFullscreen = true;

        return iframe;
      },
    );
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
            // Iframe du lecteur
            HtmlElementView(viewType: _viewId),
            
            // Badge LIVE (si en direct)
            if (widget.isLive)
              Positioned(
                top: 12,
                left: 12,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: AppTheme.redColor,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Text(
                    'LIVE',
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











