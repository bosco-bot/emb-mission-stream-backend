import 'dart:async';
import 'package:emb_mission_dashboard/core/models/webtv_models.dart';
import 'package:emb_mission_dashboard/core/services/webtv_service.dart';
import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/emb_logo.dart';
import 'package:emb_mission_dashboard/core/widgets/quick_share_dialog.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:emb_mission_dashboard/features/webtv/view/share_page.dart';
import 'package:emb_mission_dashboard/features/webtv/widgets/ant_media_player.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'dart:html' as html;
import 'dart:ui_web' as ui_web;
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';
import 'package:http/http.dart' as http;
import 'package:video_player/video_player.dart';
import 'dart:js_util' as js_util;

/// Page Lecteur WebTV
/// 
/// Interface pour visualiser et gérer le flux WebTV en direct,
/// conforme à la maquette fournie.
class WebtvPlayerPage extends StatefulWidget {
  const WebtvPlayerPage({super.key});

  @override
  State<WebtvPlayerPage> createState() => _WebtvPlayerPageState();
}

class _WebtvPlayerPageState extends State<WebtvPlayerPage> {
  WebTvStream? _currentStream;
  bool _isLoading = true;
  String? _errorMessage;
  Map<String, dynamic>? _autoPlaylistStatus;
  Map<String, dynamic>? _autoPlaylistCurrentUrl;
  int _viewersCount = 0; // Nombre de spectateurs WebTV depuis l'API unifiée
  String? _broadcastDuration; // Durée de diffusion WebTV depuis l'API
  static const String _unifiedUrl = 'https://tv.embmission.com/hls/streams/unified.m3u8';
  String? _lastPlayerMode; // Pour détecter le changement de mode et forcer le rechargement
  String? _lastVideoUrl; // Pour détecter le changement d'URL
  String? _lastItemTitle; // Pour détecter le changement de contenu (nouvelle vidéo VOD)
  Timer? _statusTimer;
  bool _refreshInProgress = false;
  static const Duration _statusRefreshInterval = Duration(seconds: 4);
  
  // ✅ Variables WebSocket
  bool _websocketConnected = false;
  bool _websocketFallbackActive = false;
  dynamic _websocketChannel;
  dynamic _websocketEcho;

  @override
  void initState() {
    super.initState();
    if (kIsWeb) {
      _initWebSocket();
    }
    _loadData();
    _startPeriodicRefresh();
  }

  void _startPeriodicRefresh() {
    _statusTimer?.cancel();
    
    // ✅ Désactiver le polling si WebSocket est connecté
    if (_websocketConnected && !_websocketFallbackActive) {
      print('📡 WebSocket actif - Polling désactivé');
      return;
    }
    
    // ✅ Activer le polling uniquement si WebSocket n'est pas disponible
    print('🔄 Polling activé (WebSocket non disponible ou en fallback)');
    _statusTimer = Timer.periodic(_statusRefreshInterval, (_) {
      if (!mounted) {
        return;
      }
        _loadData(showLoader: false);
    });
  }

  Future<void> _loadData({bool showLoader = true}) async {
    if (_refreshInProgress && !showLoader) {
      return;
    }

    _refreshInProgress = true;

    if (showLoader) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final rawStatus = await WebTvService.instance.getAutoPlaylistStatus();
      final status = _sanitizeAutoPlaylistStatus(rawStatus);
      Map<String, dynamic>? currentUrl;
      try {
        final rawUrl = await WebTvService.instance.getAutoPlaylistCurrentUrl();
        currentUrl = _sanitizeAutoPlaylistCurrentUrl(rawUrl);
      } catch (e) {
        print('⚠️ Impossible de récupérer l\'URL actuelle: $e');
      }

      if (mounted) {
        // Mettre à jour les données d'abord pour que _isLiveMode() fonctionne correctement
        setState(() {
          _autoPlaylistStatus = status;
          _autoPlaylistCurrentUrl = currentUrl;
          _currentStream = null;
          _isLoading = false;
          _errorMessage = null;
        });
        
        // Détecter le changement de mode APRÈS la mise à jour pour forcer le rechargement du lecteur
        final currentMode = _isLiveMode() ? 'live' : 'vod';
        final modeChanged = _lastPlayerMode != null && _lastPlayerMode != currentMode;
        if (modeChanged) {
          print('🔄 Changement de mode détecté: $_lastPlayerMode -> $currentMode, rechargement du lecteur...');
          // Forcer un rebuild pour recréer le lecteur avec la nouvelle clé
          setState(() {
            _lastPlayerMode = currentMode;
          });
        } else {
          _lastPlayerMode = currentMode;
        }
      }

      await _loadViewersCount();
      await _loadBroadcastDuration();
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = e.toString();
          _isLoading = false;
        });
      }
    } finally {
      _refreshInProgress = false;
    }
  }

  Map<String, dynamic>? _sanitizeAutoPlaylistStatus(Map<String, dynamic>? data) {
    if (data == null) return null;
    final sanitized = _sanitizeMap(data);
    return sanitized;
  }

  Map<String, dynamic>? _sanitizeAutoPlaylistCurrentUrl(Map<String, dynamic>? data) {
    if (data == null) return null;
    final sanitized = _sanitizeMap(data);
    return sanitized;
  }

  Map<String, dynamic> _sanitizeMap(Map<dynamic, dynamic> source) {
    final result = <String, dynamic>{};
    source.forEach((key, value) {
      final keyString = key is String ? key : key.toString();
      result[keyString] = _sanitizeValue(value);
    });
    return result;
  }

  dynamic _sanitizeValue(dynamic value) {
    if (value is String) {
      if (value.contains('/webtv-live/streams/')) {
        return _unifiedUrl;
      }
      return value;
    } else if (value is Map) {
      return _sanitizeMap(value as Map<dynamic, dynamic>);
    } else if (value is List) {
      return value.map(_sanitizeValue).toList();
    }
    return value;
  }
  
  /// Charge le nombre de spectateurs WebTV depuis l'API unifiée
  Future<void> _loadViewersCount() async {
    try {
      final response = await http.get(
        Uri.parse('https://rtv.embmission.com/api/webtv/stats/all'),
        headers: {'Content-Type': 'application/json'},
      );
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true && data['data'] != null) {
          final liveAudienceData = data['data']['live_audience'];
          if (liveAudienceData != null && liveAudienceData['webtv'] != null) {
            final webtvData = liveAudienceData['webtv'];
            final viewers = webtvData['audience'] ?? 0;
            
            if (mounted) {
              setState(() {
                _viewersCount = viewers is int ? viewers : (viewers is num ? viewers.toInt() : 0);
              });
            }
          }
        }
      }
    } catch (e) {
      print('Erreur chargement nombre de spectateurs: $e');
      // Ne pas bloquer l'interface si l'API échoue
    }
  }
  
  /// Charge la durée de diffusion WebTV depuis l'API
  Future<void> _loadBroadcastDuration() async {
    try {
      final response = await http.get(
        Uri.parse('https://rtv.embmission.com/api/webtv/stats/current-duration'),
        headers: {'Content-Type': 'application/json'},
      );
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true && data['data'] != null) {
          final webtvData = data['data']['webtv'];
          if (webtvData != null) {
            // Utiliser human_readable en priorité, sinon duration_formatted
            final duration = webtvData['human_readable'] ?? webtvData['duration_formatted'];
            
            if (mounted) {
              setState(() {
                _broadcastDuration = duration?.toString();
              });
            }
          }
        }
      }
    } catch (e) {
      print('Erreur chargement durée de diffusion: $e');
      // Ne pas bloquer l'interface si l'API échoue
    }
  }

  /// Détermine si on est en mode live
  bool _isLiveMode() {
    if (_autoPlaylistStatus?['is_live'] != null) {
      final isLive = _autoPlaylistStatus!['is_live'];
      return isLive is bool ? isLive : (isLive == true);
    }
    return _currentStream?.isLive ?? false;
  }

  /// Récupère le streamId pour le lecteur Ant Media
  String? _getStreamId() {
    // Priorité 1: live_stream depuis autoPlaylistStatus
    if (_autoPlaylistStatus?['live_stream'] != null) {
      final streamId = _autoPlaylistStatus!['live_stream'];
      return streamId is String ? streamId : streamId?.toString();
    }
    
    // Priorité 2: currentStream
    if (_currentStream != null) {
      return _currentStream!.streamId;
    }
    
    return null;
  }

  /// Détermine si la diffusion est en pause
  bool _isPaused() {
    return _autoPlaylistStatus?['mode'] == 'paused';
  }

  @override
  void dispose() {
    _statusTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        final isTablet = constraints.maxWidth >= 768 && constraints.maxWidth < 1024;
        
        return Column(
          children: [
            // Header avec titre et actions
            _Header(isMobile: isMobile),
            
            // Contenu principal
            Expanded(
              child: SingleChildScrollView(
                padding: EdgeInsets.symmetric(
                  horizontal: isMobile ? 16 : 24,
                  vertical: isMobile ? 16 : 20,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Titre et sous-titre
                    Text(
                      'Lecteur WebTV',
                      style: TextStyle(
                        fontSize: isMobile ? 24 : 32,
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF111827),
                      ),
                    ),
                    const Gap(4),
                    Text(
                      'Gérez et visualisez votre flux en direct.',
                      style: TextStyle(
                        fontSize: isMobile ? 14 : 16,
                        color: const Color(0xFF6B7280),
                      ),
                    ),
                    
                    const Gap(24),
                    
                    // Loader ou Lecteur vidéo
                    if (_isLoading)
                      const Center(
                        child: Padding(
                          padding: EdgeInsets.all(40.0),
                          child: CircularProgressIndicator(),
                        ),
                      )
                    else if (_errorMessage != null)
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(40),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.red.shade200),
                        ),
                        child: Column(
                          children: [
                            const Icon(Icons.error_outline, size: 64, color: Colors.red),
                            const Gap(16),
                            const Text(
                              'Erreur de chargement du stream',
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w700,
                                color: Colors.red,
                              ),
                            ),
                            const Gap(8),
                            Text(
                              _errorMessage!,
                              style: const TextStyle(color: Colors.grey),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      )
                    else ...[
                      // Lecteur vidéo selon le mode et l'état de diffusion
                      // Utiliser une clé unique basée sur le mode et le streamId pour forcer le rechargement quand nécessaire
                      if (!_isPaused() && _isLiveMode() && _getStreamId() != null)
                        // Mode Live: Utiliser AntMediaPlayer
                        AntMediaPlayer(
                          key: ValueKey('live-${_getStreamId()}-${_lastPlayerMode}'),
                          streamId: _getStreamId()!,
                          isLive: true,
                          width: double.infinity,
                          height: isMobile ? 250 : 500,
                        )
                      else if (!_isPaused())
                        // Mode non-live : pointer directement sur le flux unifié
                        _VideoPlayerWidget(
                          key: ValueKey('vod-${_unifiedUrl}-${_lastPlayerMode}'),
                          videoUrl: _unifiedUrl,
                          title: 'Playlist WebTV',
                          playlistName: 'Diffusion automatique',
                          height: isMobile ? 250 : 500,
                        )
                      else
                        Center(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(
                                Icons.pause_circle_outline,
                                size: 64,
                                color: const Color(0xFF6B7280),
                              ),
                              const Gap(16),
                              const Text(
                                'Diffusion en pause',
                                style: TextStyle(
                                  color: Color(0xFF6B7280),
                                  fontSize: 16,
                                ),
                                textAlign: TextAlign.center,
                              ),
                            ],
                          ),
                        ),
                    
                    const Gap(24),
                    
                    // Informations de l'événement en direct
                      _LiveEventInfo(
                        stream: _currentStream,
                        autoPlaylistStatus: _autoPlaylistStatus,
                        autoPlaylistCurrentUrl: _autoPlaylistCurrentUrl,
                        viewersCount: _viewersCount,
                        broadcastDuration: _broadcastDuration,
                      ),
                    ],
                    
                    const Gap(24),
                    
                    // Bouton de partage rapide dynamique
                    Center(
                      child: ElevatedButton.icon(
                        onPressed: () {
                          // Déterminer le titre, sous-titre et URL selon les données disponibles
                          String title;
                          String subtitle;
                          String shareUrl;
                          
                          if (_autoPlaylistCurrentUrl != null) {
                            // Utiliser les nouvelles APIs
                            final isLive = _autoPlaylistStatus?['is_live'] == true;
                            final streamName = _autoPlaylistCurrentUrl!['stream_name'];
                            final itemTitle = _autoPlaylistCurrentUrl!['item_title'];
                            title = (streamName is String ? streamName : null) ?? 
                                    (itemTitle is String ? itemTitle : null) ?? 
                                    'EMB WebTV Stream';
                            subtitle = isLive ? 'Diffusion en direct !' : 'Stream disponible';
                            // Pour le partage, utiliser l'URL publique de la page WebTV
                            shareUrl = 'https://tv.embmission.com/watch';
                          } else if (_currentStream != null) {
                            // Utiliser les anciennes données
                            title = _currentStream!.title;
                            subtitle = _currentStream!.isLive ? 'Diffusion en direct !' : 'Stream disponible';
                            // URL publique pour le partage social
                            shareUrl = 'https://tv.embmission.com/watch';
                          } else {
                            title = 'EMB WebTV Stream';
                            subtitle = 'Stream disponible';
                            shareUrl = 'https://tv.embmission.com/watch';
                          }
                          
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (context) => SharePage(
                                title: title,
                                subtitle: subtitle,
                                shareUrl: shareUrl,
                              ),
                            ),
                          );
                        },
                        icon: const Icon(Icons.share),
                        label: const Text('Partage rapide'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppTheme.blueColor,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

/// Header avec titre et actions
class _Header extends StatelessWidget {
  const _Header({required this.isMobile});
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    if (isMobile) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.1),
              blurRadius: 4,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          children: [
            Row(
              children: [
                const EmbLogo(size: 32),
                const Gap(8),
                const Text(
                  'EMB-MISSION',
                  style: TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                    color: Color(0xFF0F172A),
                  ),
                ),
                const Spacer(),
              ],
            ),
            const Gap(12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const UserProfileChip(),
                ElevatedButton.icon(
                  onPressed: () => context.go('/webtv/diffusionlive'),
                  icon: const Icon(Icons.live_tv, size: 18),
                  label: const Text('Démarrer Diffusion Live'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.redColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.1),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          const EmbLogo(size: 40),
          const Gap(12),
          const Text(
            'EMB-MISSION',
            style: TextStyle(
              fontWeight: FontWeight.w900,
              fontSize: 20,
              color: Color(0xFF0F172A),
            ),
          ),
          const Spacer(),
          const UserProfileChip(),
          const Gap(16),
          ElevatedButton.icon(
            onPressed: () => context.go('/webtv/diffusionlive'),
            icon: const Icon(Icons.live_tv),
            label: const Text('Démarrer Diffusion Live'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.redColor,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Lecteur vidéo principal
class _VideoPlayer extends StatelessWidget {
  const _VideoPlayer({this.stream});
  
  final WebTvStream? stream;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
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
        child: AspectRatio(
          aspectRatio: 16 / 9,
          child: stream != null && stream!.isLive
              ? Stack(
                  children: [
                    // Image de fond (thumbnail)
                    if (stream!.thumbnailUrl.isNotEmpty)
                      Positioned.fill(
                        child: Image.network(
                          stream!.thumbnailUrl,
                          fit: BoxFit.cover,
                          errorBuilder: (context, error, stackTrace) =>
                              Container(color: Colors.black),
                        ),
                      ),
                    // Overlay avec message et bouton
                    Positioned.fill(
                      child: Container(
                        color: Colors.black.withValues(alpha: 0.7),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(
                              Icons.play_circle_outline,
                              size: 80,
                              color: Colors.white,
                            ),
                            const Gap(16),
                            const Text(
                              'Stream WebTV en direct',
          style: TextStyle(
            color: Colors.white,
            fontSize: 24,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const Gap(8),
                            Text(
                              stream!.title,
                              style: const TextStyle(
                                color: Colors.white70,
                                fontSize: 16,
                              ),
                              textAlign: TextAlign.center,
                            ),
                            const Gap(16),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 16,
                                vertical: 8,
                              ),
                              decoration: BoxDecoration(
                                color: AppTheme.redColor,
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: const Text(
                                '🔴 LIVE',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 14,
                                ),
                              ),
                            ),
                            const Gap(24),
                            const Text(
                              'Le lecteur vidéo nécessite un plugin WebRTC',
                              style: TextStyle(
                                color: Colors.white60,
                                fontSize: 14,
                              ),
                              textAlign: TextAlign.center,
                            ),
                            const Gap(8),
                            Text(
                              'URL: ${stream!.playbackUrl}',
                              style: TextStyle(
                                color: Colors.white.withValues(alpha: 0.4),
                                fontSize: 12,
                              ),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                )
              : Container(
                  color: Colors.black,
                  child: const Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.tv_off,
                          size: 64,
                          color: Colors.white54,
                        ),
                        Gap(16),
                        Text(
                          'Aucun stream en direct',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 20,
            fontWeight: FontWeight.w600,
                          ),
                        ),
                        Gap(8),
                        Text(
                          'Le stream n\'est pas actif pour le moment',
                          style: TextStyle(
                            color: Colors.white60,
                            fontSize: 14,
                          ),
                        ),
                      ],
                    ),
                  ),
          ),
        ),
      ),
    );
  }
}

/// Informations de l'événement en direct (responsive)
class _LiveEventInfo extends StatelessWidget {
  const _LiveEventInfo({
    this.stream,
    this.autoPlaylistStatus,
    this.autoPlaylistCurrentUrl,
    this.viewersCount = 0,
    this.broadcastDuration,
  });
  
  final WebTvStream? stream;
  final Map<String, dynamic>? autoPlaylistStatus;
  final Map<String, dynamic>? autoPlaylistCurrentUrl;
  final int viewersCount;
  final String? broadcastDuration;
  
  /// Formate le nombre avec séparateurs de milliers
  String _formatViewersCount(int count) {
    if (count < 1000) {
      return count.toString();
    } else if (count < 1000000) {
      return '${(count / 1000).toStringAsFixed(1)}k'.replaceAll('.0', '');
    } else {
      return '${(count / 1000000).toStringAsFixed(1)}M'.replaceAll('.0', '');
    }
  }
  
  /// Retourne le texte formaté pour la durée de diffusion
  String _getBroadcastDurationText() {
    if (broadcastDuration != null && broadcastDuration!.isNotEmpty) {
      return 'Diffusé depuis $broadcastDuration';
    }
    return 'Diffusion non disponible';
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        final isTablet = constraints.maxWidth >= 768 && constraints.maxWidth < 1024;
        
        // Déterminer le titre et le statut selon les données disponibles
        String title;
        bool isLive;
        bool isPaused = autoPlaylistStatus?['mode'] == 'paused';
        
        if (stream != null) {
          title = stream!.title;
          isLive = stream!.isLive;
        } else if (autoPlaylistCurrentUrl != null) {
          isLive = autoPlaylistStatus?['is_live'] == true;
          // Afficher "Diffusion en direct" pour le mode live, sinon le titre de la vidéo VoD
          if (isLive) {
            final streamName = autoPlaylistCurrentUrl!['stream_name'];
            title = (streamName is String ? streamName : null) ?? 'Diffusion en direct';
          } else {
            final itemTitle = autoPlaylistCurrentUrl!['item_title'];
            title = (itemTitle is String ? itemTitle : null) ?? 'Playlist TV';
          }
        } else {
          title = 'EMB WebTV Stream';
          isLive = false;
        }
        
        return Container(
          width: double.infinity,
          padding: EdgeInsets.all(isMobile ? 16 : 20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.05),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Titre de l'événement avec badge EN DIRECT
              Row(
                children: [
                  Expanded(
                    child: Text(
                      title,
                      style: TextStyle(
                        fontSize: isMobile ? 18 : (isTablet ? 20 : 24),
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF111827),
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const Gap(12),
                  if (isPaused)
                    Container(
                      padding: EdgeInsets.symmetric(
                        horizontal: isMobile ? 8 : 12,
                        vertical: isMobile ? 4 : 6,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF59E0B), // Orange pour pause
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        'PAUSE',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                          fontSize: isMobile ? 10 : 12,
                        ),
                      ),
                    )
                  else if (isLive)
                    Container(
                      padding: EdgeInsets.symmetric(
                        horizontal: isMobile ? 8 : 12,
                        vertical: isMobile ? 4 : 6,
                      ),
                      decoration: BoxDecoration(
                        color: AppTheme.redColor,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        'EN DIRECT',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                          fontSize: isMobile ? 10 : 12,
                        ),
                      ),
                    )
                  else
                    Container(
                      padding: EdgeInsets.symmetric(
                        horizontal: isMobile ? 8 : 12,
                        vertical: isMobile ? 4 : 6,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.grey.shade600,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        'VOD',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                          fontSize: isMobile ? 10 : 12,
                        ),
                      ),
                    ),
                ],
              ),
              
              Gap(isMobile ? 12 : 16),
              
              // Statistiques (responsive)
              if (isMobile) ...[
                // Mode mobile : statistiques empilées
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(
                          Icons.visibility,
                          color: Color(0xFF6B7280),
                          size: 18,
                        ),
                        const Gap(8),
                        Expanded(
                          child: Text(
                            '${_formatViewersCount(viewersCount)} spectateurs',
                            style: const TextStyle(
                              color: Color(0xFF6B7280),
                              fontSize: 14,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                    const Gap(8),
                    Row(
                      children: [
                        const Icon(
                          Icons.access_time,
                          color: Color(0xFF6B7280),
                          size: 18,
                        ),
                        const Gap(8),
                        Expanded(
                          child: Text(
                            _getBroadcastDurationText(),
                            style: const TextStyle(
                              color: Color(0xFF6B7280),
                              fontSize: 14,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ] else ...[
                // Mode tablet/desktop : statistiques en ligne
                Wrap(
                  spacing: 24,
                  runSpacing: 8,
                  children: [
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.visibility,
                          color: Color(0xFF6B7280),
                          size: 20,
                        ),
                        const Gap(8),
                        Text(
                          '${_formatViewersCount(viewersCount)} spectateurs',
                          style: const TextStyle(
                            color: Color(0xFF6B7280),
                            fontSize: 16,
                          ),
                        ),
                      ],
                    ),
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.access_time,
                          color: Color(0xFF6B7280),
                          size: 20,
                        ),
                        const Gap(8),
                        Text(
                          _getBroadcastDurationText(),
                          style: const TextStyle(
                            color: Color(0xFF6B7280),
                            fontSize: 16,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
              
              Gap(isMobile ? 12 : 16),
              
              // Bouton de partage dynamique (responsive)
              SizedBox(
                width: isMobile ? double.infinity : null,
                child: ElevatedButton.icon(
                  onPressed: () {
                    // Déterminer le titre, sous-titre et URL selon les données disponibles
                    String title;
                    String subtitle;
                    String shareUrl;
                    
                    if (autoPlaylistCurrentUrl != null) {
                      // Utiliser les nouvelles APIs
                      final isLive = autoPlaylistStatus?['is_live'] == true;
                      final streamName = autoPlaylistCurrentUrl!['stream_name'];
                      final itemTitle = autoPlaylistCurrentUrl!['item_title'];
                      title = (streamName is String ? streamName : null) ?? 
                              (itemTitle is String ? itemTitle : null) ?? 
                              'EMB WebTV Stream';
                      subtitle = isLive ? 'Diffusion en direct !' : 'Stream disponible';
                      // Pour le partage, utiliser l'URL publique de la page WebTV
                      shareUrl = 'https://tv.embmission.com/watch';
                    } else if (stream != null) {
                      // Utiliser les anciennes données
                      title = stream!.title;
                      subtitle = stream!.isLive ? 'Diffusion en direct !' : 'Stream disponible';
                      // URL publique pour le partage social
                      shareUrl = 'https://tv.embmission.com/watch';
                    } else {
                      title = 'EMB WebTV Stream';
                      subtitle = 'Stream disponible';
                      shareUrl = 'https://tv.embmission.com/watch';
                    }
                    
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (context) => SharePage(
                          title: title,
                          subtitle: subtitle,
                          shareUrl: shareUrl,
                        ),
                      ),
                    );
                  },
                  icon: const Icon(Icons.share),
                  label: const Text('Partage rapide'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.blueColor,
                    foregroundColor: const Color(0xFF2F3B52),
                    padding: EdgeInsets.symmetric(
                      horizontal: isMobile ? 16 : 20,
                      vertical: isMobile ? 12 : 14,
                    ),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Widget lecteur vidéo avec VideoPlayerController
class _VideoPlayerWidget extends StatefulWidget {
  const _VideoPlayerWidget({
    required this.videoUrl,
    required this.title,
    required this.playlistName,
    required this.height,
  });
  
  final String videoUrl;
  final String title;
  final String playlistName;
  final double height;

  @override
  State<_VideoPlayerWidget> createState() => _VideoPlayerWidgetState();
}

class _VideoPlayerWidgetState extends State<_VideoPlayerWidget> {
  bool _isInitialized = false;
  late String _viewId;
  dynamic _shakaPlayer;
  html.VideoElement? _videoElement;
  bool _autoplayBlocked = false;
  bool _awaitingAudioUnlock = false;
  html.EventListener? _audioUnlockListener;
  static const String _unifiedUrl = 'https://tv.embmission.com/hls/streams/unified.m3u8';
  Timer? _playbackWatchdog;
  int _autoReloadAttempts = 0;
  static const int _maxAutoReloadAttempts = 3;
  static const Duration _playbackTimeout = Duration(seconds: 3);

  String _withNoCacheIfNeeded(String url) {
    if (url.isEmpty) return url;
    if (url.contains('/vod_')) {
      final separator = url.contains('?') ? '&' : '?';
      return '$url${separator}nocache=${DateTime.now().millisecondsSinceEpoch}';
    }
    return url;
  }

  String _normalizeUrl(String url) {
    if (url.contains('/webtv-live/streams/')) {
      return _unifiedUrl;
    }
    return url;
  }

  void _handleUserPlay() {
    final element = _videoElement;
    if (element == null) {
      return;
    }
    if (_awaitingAudioUnlock) {
      _restoreAudioVolume();
      return;
    }
    element.play().then((_) {
      if (mounted) {
        setState(() {
          _autoplayBlocked = false;
        });
      }
    }).catchError((error) {
      if (_handleAutoplayRejection(error, element)) {
        return;
      }
      print('⚠️ Lecture manuelle impossible: $error');
    });
  }
  
  @override
  void initState() {
    super.initState();
    _viewId = 'video-player-${DateTime.now().microsecondsSinceEpoch}';
    
    // Sur le web, enregistrer la vue AVANT de construire le widget
    if (kIsWeb) {
      _registerVideoElement();
    } else {
      // Sur mobile/desktop, essayer video_player
      _initializeVideoPlayer();
    }
  }

  void _registerVideoElement() {
    // Enregistrer la vue pour l'élément vidéo HTML natif
    // ignore: undefined_prefixed_name
    ui_web.platformViewRegistry.registerViewFactory(
      _viewId,
      (int viewId) {
        final normalizedUrl = _normalizeUrl(widget.videoUrl);
        final isUnifiedSource = normalizedUrl == _unifiedUrl;
        print('🎬 Lecture URL: $normalizedUrl');
        
        DateTime loadStart = DateTime.now();
        final videoElement = html.VideoElement()
          ..autoplay = false
          ..controls = true
          ..preload = 'auto'
          ..style.width = '100%'
          ..style.height = '100%'
          ..style.objectFit = 'contain';
        _videoElement = videoElement;
        _detachAudioUnlockListener();
        if (mounted) {
          setState(() {
            _autoplayBlocked = false;
            _awaitingAudioUnlock = false;
          });
        }

        videoElement.onWaiting.listen((_) => print('⌛ WAITING (player)'));
        videoElement.onStalled.listen((_) => print('⚠️ STALLED (player)'));
        videoElement.onPlaying.listen((_) {
          print('▶️ lecture (player)');
          _cancelPlaybackWatchdog();
          _autoReloadAttempts = 0;
          if (_autoplayBlocked && mounted) {
            setState(() {
              _autoplayBlocked = false;
            });
          }
        });
        videoElement.onError.listen((event) {
          print('❌ ERROR (player): ${videoElement.error}');
        });

        videoElement.onCanPlay.listen((_) {
          final readyIn = DateTime.now().difference(loadStart).inMilliseconds;
          print('▶️ prêt en ${readyIn} ms');
          _cancelPlaybackWatchdog();
          if (videoElement.paused) {
            videoElement.play().catchError((error) {
              if (_handleAutoplayRejection(error, videoElement)) {
                return;
              }
              print('⚠️ Autoplay bloqué (player): $error');
              if (mounted) {
                setState(() {
                  _autoplayBlocked = true;
                });
              }
            });
          }
        });

        final preparedUrl = _withNoCacheIfNeeded(normalizedUrl);
        try {
          final options = js_util.jsify({
            'config': {
              'streaming': {
                'lowLatencyMode': false,
              },
            },
          });
          final result = js_util.callMethod(
            js_util.globalThis,
            'createShakaPlayer',
            [videoElement, preparedUrl, options],
          );
          _shakaPlayer = result;
          if (_shakaPlayer == null) {
            videoElement
              ..src = preparedUrl
              ..load();
            loadStart = DateTime.now();
          } else {
            loadStart = DateTime.now();
          }
        } catch (e) {
          print('💥 Erreur initialisation Shaka Player: $e');
          videoElement
            ..src = preparedUrl
            ..load();
          loadStart = DateTime.now();
        }

        _schedulePlaybackWatchdog();
        
        // Écouter l'événement de fin de lecture
        videoElement.onEnded.listen((event) async {
          print('✅ Vidéo terminée, chargement de la prochaine vidéo...');
          
          if (isUnifiedSource) {
            print('ℹ️ Flux unifié terminé - lecture continue sans next-vod');
            return;
          }

          try {
            // Récupérer la prochaine vidéo VoD
            final response = await http.get(
              Uri.parse('https://tv.embmission.com/api/webtv-auto-playlist/next-vod'),
            );
            
            final data = json.decode(response.body);
            
            if (data['success'] == true && data['data'] != null) {
              final nextUrl = data['data']['url'];
              final rawUrl = nextUrl is String ? nextUrl : nextUrl?.toString() ?? '';
              final urlString = _withNoCacheIfNeeded(rawUrl);
              print('🎬 Prochaine vidéo: $urlString');
              
              if (_shakaPlayer != null) {
                try {
                  await js_util.promiseToFuture(
                    js_util.callMethod(_shakaPlayer!, 'load', [urlString]),
                  );
                } catch (e) {
                  print('💥 Erreur chargement prochaine vidéo via Shaka: $e');
                  videoElement
                    ..src = urlString
                    ..load();
                  loadStart = DateTime.now();
                }
              } else {
                videoElement
                  ..src = urlString
                  ..load();
                loadStart = DateTime.now();
              }
            } else {
              print('⚠️ Aucune vidéo suivante disponible');
            }
          } catch (e) {
            print('💥 Erreur lors du chargement de la prochaine vidéo: $e');
          }
        });
        
        return videoElement;
      },
    );
    
    setState(() {
      _isInitialized = true;
    });
  }

  Future<void> _initializeVideoPlayer() async {
    try {
      final normalizedUrl = _normalizeUrl(widget.videoUrl);
      print('🎬 Début initialisation vidéo: $normalizedUrl');
      
      final controller = VideoPlayerController.networkUrl(
        Uri.parse(normalizedUrl),
      );
      
      print('⏳ Initialisation du contrôleur...');
      
      await controller.initialize();
      
      print('✅ Contrôleur initialisé avec succès');
      
      if (mounted) {
        setState(() {
          _isInitialized = true;
        });
      }
    } catch (e) {
      print('💥 Erreur initialisation vidéo: $e');
      if (mounted) {
        setState(() {
          _isInitialized = true; // On affiche quand même l'iframe
        });
      }
    }
  }

  @override
  void dispose() {
    _cancelPlaybackWatchdog();
    _detachAudioUnlockListener();
    if (_shakaPlayer != null) {
      try {
        js_util.callMethod(js_util.globalThis, 'destroyShakaPlayer', [_shakaPlayer]);
      } catch (e) {
        print('⚠️ Erreur nettoyage Shaka Player: $e');
      }
      _shakaPlayer = null;
    }
    _videoElement = null;
    super.dispose();
  }

  void _schedulePlaybackWatchdog() {
    _cancelPlaybackWatchdog();
    _playbackWatchdog = Timer(_playbackTimeout, () {
      if (!mounted) {
        return;
      }
      final element = _videoElement;
      final isPlaying = element != null && !element.paused && element.currentTime > 0;
      if (isPlaying) {
        return;
      }
      if (_autoReloadAttempts >= _maxAutoReloadAttempts) {
        print('⚠️ Nombre maximum de tentatives auto atteint, abandon.');
        return;
      }
      _autoReloadAttempts++;
      print('⚠️ Lecture bloquée, tentative auto-reload ${_autoReloadAttempts}/$_maxAutoReloadAttempts');
      _retryPlayback();
    });
  }

  void _cancelPlaybackWatchdog() {
    _playbackWatchdog?.cancel();
    _playbackWatchdog = null;
  }

  Future<void> _retryPlayback() async {
    final normalizedUrl = _normalizeUrl(widget.videoUrl);
    final refreshedUrl = _withNoCacheIfNeeded(normalizedUrl);
    try {
      if (_shakaPlayer != null) {
        await js_util.promiseToFuture(
          js_util.callMethod(_shakaPlayer!, 'load', [refreshedUrl]),
        );
      } else if (_videoElement != null) {
        _videoElement!
          ..src = refreshedUrl
          ..load();
      }
    } catch (e) {
      print('💥 Erreur lors de la relance automatique: $e');
      if (_videoElement != null) {
        _videoElement!
          ..src = refreshedUrl
          ..load();
      }
    } finally {
      _schedulePlaybackWatchdog();
    }
  }

  bool _handleAutoplayRejection(Object error, html.VideoElement element) {
    final errorText = error.toString();
    if (errorText.contains('NotAllowedError') || errorText.contains('NotSupportedError')) {
      _attemptMutedAutoplay(element);
      return true;
    }
    return false;
  }

  void _attemptMutedAutoplay(html.VideoElement element) {
    element
      ..muted = true
      ..volume = 0;
    element.play().then((_) {
      print('🔇 Lecture muette acceptée, en attente d’un geste utilisateur pour réactiver le son');
      if (mounted) {
        setState(() {
          _autoplayBlocked = false;
          _awaitingAudioUnlock = true;
        });
      }
      _attachAudioUnlockListener();
    }).catchError((innerError) {
      print('💥 Même la lecture muette a échoué: $innerError');
      if (mounted) {
        setState(() {
          _autoplayBlocked = true;
          _awaitingAudioUnlock = false;
        });
      }
    });
  }

  void _restoreAudioVolume() {
    final element = _videoElement;
    if (element == null) {
      return;
    }
    element
      ..muted = false
      ..volume = 1.0;
    element.play().then((_) {
      if (mounted) {
        setState(() {
          _awaitingAudioUnlock = false;
          _autoplayBlocked = false;
        });
      }
    }).catchError((error) {
      print('⚠️ Impossible de réactiver le son automatiquement: $error');
      if (mounted) {
        setState(() {
          _autoplayBlocked = true;
          _awaitingAudioUnlock = false;
        });
      }
    }).whenComplete(() {
      _detachAudioUnlockListener();
    });
  }

  void _attachAudioUnlockListener() {
    if (_audioUnlockListener != null) {
      return;
    }
    _audioUnlockListener = (event) {
      _restoreAudioVolume();
    };
    html.window.addEventListener('pointerdown', _audioUnlockListener!, true);
  }

  void _detachAudioUnlockListener() {
    if (_audioUnlockListener == null) {
      return;
    }
    html.window.removeEventListener('pointerdown', _audioUnlockListener!, true);
    _audioUnlockListener = null;
  }

  @override
  Widget build(BuildContext context) {
    // Sur le web, utiliser un élément HTML vidéo natif
    if (kIsWeb) {
      return SizedBox(
        width: double.infinity,
        height: widget.height,
        child: Container(
          decoration: BoxDecoration(
            color: Colors.black,
            borderRadius: BorderRadius.circular(12),
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Stack(
              children: [
                // Élément vidéo HTML natif
                HtmlElementView(viewType: _viewId),
                // Contrôles personnalisés
                if (_isInitialized)
                  Positioned.fill(
                    child: _VideoControlsWeb(
                      title: widget.title,
                      playlistName: widget.playlistName,
                    ),
                  ),
                if (_awaitingAudioUnlock)
                  Positioned.fill(
                    child: Container(
                      color: Colors.black.withOpacity(0.45),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.volume_off, color: Colors.white, size: 48),
                          const Gap(12),
                          const Text(
                            'Appuyez pour activer le son',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.w600,
                            ),
                            textAlign: TextAlign.center,
                          ),
                          const Gap(16),
                          ElevatedButton.icon(
                            onPressed: _restoreAudioVolume,
                            icon: const Icon(Icons.volume_up),
                            label: const Text('Activer le son'),
                          ),
                        ],
                      ),
                    ),
                  )
                else if (_autoplayBlocked)
                  Positioned.fill(
                    child: Container(
                      color: Colors.black.withOpacity(0.55),
                      child: Center(
                        child: ElevatedButton.icon(
                          onPressed: _handleUserPlay,
                          icon: const Icon(Icons.play_arrow),
                          label: const Text('Lire la vidéo'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.white,
                            foregroundColor: Colors.black87,
                            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      );
    }
    
    // Sur mobile/desktop, garder l'ancien code
    return SizedBox(
      width: double.infinity,
      height: widget.height,
      child: Container(
        decoration: BoxDecoration(
          color: Colors.black,
          borderRadius: BorderRadius.circular(12),
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(
                  Icons.play_circle_filled,
                  size: 64,
                  color: Colors.white,
                ),
                const Gap(16),
                Text(
                  widget.title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.w600,
                  ),
                  textAlign: TextAlign.center,
                ),
                const Gap(8),
                Text(
                  widget.playlistName,
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 14,
                  ),
                  textAlign: TextAlign.center,
                ),
                const Gap(16),
                InkWell(
                  onTap: () {
                    Clipboard.setData(ClipboardData(text: _normalizeUrl(widget.videoUrl)));
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('URL copiée dans le presse-papiers'),
                        duration: Duration(seconds: 2),
                        backgroundColor: Colors.green,
                      ),
                    );
                  },
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.2),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.copy, color: Colors.white, size: 16),
                        Gap(8),
                        Text(
                          'Copier l\'URL',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

}

/// Widget contrôles vidéo personnalisés pour le web
class _VideoControlsWeb extends StatelessWidget {
  const _VideoControlsWeb({
    required this.title,
    required this.playlistName,
  });
  
  final String title;
  final String playlistName;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        // Gradient overlay
        Positioned.fill(
          child: Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  Colors.transparent,
                  Colors.black.withOpacity(0.7),
                ],
              ),
            ),
          ),
        ),
        // Informations en bas
        Positioned(
          bottom: 0,
          left: 0,
          right: 0,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                if (playlistName.isNotEmpty) ...[
                  const Gap(4),
                  Text(
                    playlistName,
                    style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 12,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }
}
