import 'dart:convert';
import 'package:emb_mission_dashboard/core/models/webtv_models.dart';
import 'package:emb_mission_dashboard/core/services/webtv_service.dart';
import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/emb_logo.dart';
import 'package:emb_mission_dashboard/core/widgets/error_dialog.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:emb_mission_dashboard/features/webtv/widgets/ant_media_player.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'dart:html' as html;
import 'dart:ui_web' as ui_web;
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';
import 'package:http/http.dart' as http;
import 'package:video_player/video_player.dart';
import 'dart:js_util' as js_util;

/// Page de contrôle WebTV
/// 
/// Interface pour contrôler la diffusion WebTV avec lecteur vidéo,
/// sources actives et file d'attente, conforme à la maquette fournie.
class WebtvControlPage extends StatefulWidget {
  const WebtvControlPage({super.key});

  @override
  State<WebtvControlPage> createState() => _WebtvControlPageState();
}

class _WebtvControlPageState extends State<WebtvControlPage> {
  Map<String, dynamic>? _autoPlaylistStatus;
  Map<String, dynamic>? _autoPlaylistCurrentUrl;
  WebTvStream? _currentStream;
  bool _isLoading = true;
  String? _errorMessage;
  bool _isBroadcasting = false;
  int? _activePlaylistId;
  List<Map<String, dynamic>> _playlistItems = [];
  bool _isLoadingPlaylist = false;
  static const String _unifiedUrl = 'https://tv.embmission.com/hls/streams/unified.m3u8';

  @override
  void initState() {
    super.initState();
    _loadStatus();
    _loadPlaylist();
    _startPeriodicRefresh();
  }

  Future<void> _loadStatus({bool showLoader = true}) async {
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
        print('⚠️ Impossible de récupérer l\'URL actuelle (control): $e');
      }

      if (mounted) {
        setState(() {
          _autoPlaylistStatus = status;
          _autoPlaylistCurrentUrl = currentUrl;
          _currentStream = null;
          _isLoading = false;
          _errorMessage = null;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = e.toString();
          _isLoading = false;
        });
      }
    }
  }

  Map<String, dynamic>? _sanitizeAutoPlaylistStatus(Map<String, dynamic>? data) {
    if (data == null) return null;
    return _sanitizeMap(data);
  }

  Map<String, dynamic>? _sanitizeAutoPlaylistCurrentUrl(Map<String, dynamic>? data) {
    if (data == null) return null;
    return _sanitizeMap(data);
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

  void _startPeriodicRefresh() {
    Future.delayed(const Duration(seconds: 10), () {
      if (mounted) {
        _loadStatus(showLoader: false); // Refresh silencieux
        _startPeriodicRefresh();
      }
    });
  }

  /// Détermine si on est en mode live
  bool _isLiveMode() {
    return _autoPlaylistStatus?['is_live'] ?? _currentStream?.isLive ?? false;
  }

  /// Récupère le streamId pour le lecteur Ant Media
  String? _getStreamId() {
    return _autoPlaylistStatus?['live_stream'] ?? _currentStream?.streamId;
  }

  /// Détermine si la diffusion est en pause
  bool _isPaused() {
    return _autoPlaylistStatus?['mode'] == 'paused';
  }

  /// Démarre ou arrête la diffusion selon l'état actuel
  Future<void> toggleBroadcast() async {
    try {
      String apiUrl;
      String successMessage;
      
      if (_isPaused()) {
        // Reprendre la diffusion
        apiUrl = 'https://tv.embmission.com/api/webtv-auto-playlist/resume';
        successMessage = 'Diffusion reprise avec succès';
      } else {
        // Arrêter la diffusion
        apiUrl = 'https://tv.embmission.com/api/webtv-auto-playlist/stop';
        successMessage = 'Diffusion arrêtée avec succès';
      }
      
      final response = await http.post(
        Uri.parse(apiUrl),
        headers: {'Content-Type': 'application/json'},
      );
      
      if (response.statusCode == 200) {
        // Recharger le statut pour voir les changements
        await _loadStatus(showLoader: false);
        
        // Afficher un message de succès
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(successMessage),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        throw Exception('Erreur lors de la modification de la diffusion: ${response.statusCode}');
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  /// Méthode legacy pour compatibilité
  Future<void> startBroadcast() async {
    await toggleBroadcast();
  }

  Future<void> _loadPlaylist() async {
    setState(() {
      _isLoadingPlaylist = true;
    });

    try {
      final response = await http.get(
        Uri.parse('https://tv.embmission.com/api/webtv-playlists'),
        headers: {'Content-Type': 'application/json'},
      );
      
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true && data['data'] != null && data['data'].isNotEmpty) {
          // Récupérer la première playlist active
          final activePlaylist = data['data'].firstWhere(
            (playlist) => playlist['is_active'] == true,
            orElse: () => data['data'].first,
          );
          
          if (mounted) {
            setState(() {
              _playlistItems = List<Map<String, dynamic>>.from(activePlaylist['items'] ?? []);
              _activePlaylistId = activePlaylist['id'];
              _isLoadingPlaylist = false;
            });
          }
        } else {
          if (mounted) {
            setState(() {
              _playlistItems = [];
              _isLoadingPlaylist = false;
            });
          }
        }
      } else {
        throw Exception('Erreur lors du chargement de la playlist: ${response.statusCode}');
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _playlistItems = [];
          _isLoadingPlaylist = false;
        });
        print('Erreur chargement playlist: $e');
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        
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
                    const Text(
                      'Contrôle de la WebTV',
                      style: TextStyle(
                        fontSize: 32,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF111827),
                      ),
                    ),
                    const Gap(4),
                    const Text(
                      'Gérez votre diffusion en direct et vos sources vidéo.',
                      style: TextStyle(
                        fontSize: 16,
                        color: Color(0xFF6B7280),
                      ),
                    ),
                    const Gap(32),
                    
                    // Contenu principal responsive
                    if (_isLoading)
                      const Center(
                        child: Padding(
                          padding: EdgeInsets.all(40.0),
                          child: CircularProgressIndicator(),
                        ),
                      )
                    else if (_errorMessage != null)
                      Container(
                        padding: const EdgeInsets.all(24),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFE5E7EB)),
                        ),
                        child: Column(
                          children: [
                            const Icon(Icons.error_outline, size: 48, color: Colors.red),
                            const Gap(16),
                            const Text(
                              'Erreur de chargement du statut',
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
                            const Gap(16),
                            ElevatedButton(
                              onPressed: () => _loadStatus(),
                              child: const Text('Réessayer'),
                            ),
                          ],
                        ),
                      )
                    else
                    if (isMobile)
                        _MobileLayout(
                          autoPlaylistStatus: _autoPlaylistStatus,
                          autoPlaylistCurrentUrl: _autoPlaylistCurrentUrl,
                          currentStream: _currentStream,
                          onStartBroadcast: startBroadcast,
                          isPaused: _isPaused(),
                          playlistItems: _playlistItems,
                          isLoadingPlaylist: _isLoadingPlaylist,
                        )
                      else
                        _DesktopLayout(
                          autoPlaylistStatus: _autoPlaylistStatus,
                          autoPlaylistCurrentUrl: _autoPlaylistCurrentUrl,
                          currentStream: _currentStream,
                          onStartBroadcast: startBroadcast,
                          isPaused: _isPaused(),
                          playlistItems: _playlistItems,
                          isLoadingPlaylist: _isLoadingPlaylist,
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

/// Header avec logo et profil utilisateur
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
              color: Colors.black.withValues(alpha: 0.1),
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
            // Profil utilisateur
            const UserProfileChip(),
            const Gap(12),
            // Bouton
            ElevatedButton.icon(
              onPressed: () => context.go('/webtv/diffusionlive'),
              icon: const Icon(Icons.live_tv, size: 20),
              label: const Text('Démarrer Diffusion Live'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.redColor,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
              ),
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
            color: Colors.black.withValues(alpha: 0.1),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final isMobile = constraints.maxWidth < 768;
          
          if (isMobile) {
            // Layout mobile : éléments empilés
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Première ligne : Logo + Titre + Notification
                Row(
                  children: [
                    const EmbLogo(size: 28),
                    const Gap(8),
                    const Expanded(
                      child: Text(
                        'EMB-MISSION',
                        style: TextStyle(
                          fontWeight: FontWeight.w900,
                          fontSize: 16,
                          color: Color(0xFF0F172A),
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                const Gap(12),
                // Deuxième ligne : Profil utilisateur
                Row(
                  children: [
                    // Profil utilisateur
                    const UserProfileChip(),
                  ],
                ),
                const Gap(12),
                // Troisième ligne : Bouton Démarrer Diffusion Live (pleine largeur)
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: () => context.go('/webtv/diffusionlive'),
                    icon: const Icon(Icons.live_tv, size: 18),
                    label: const Text('Démarrer Diffusion Live', style: TextStyle(fontSize: 14)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.redColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    ),
                  ),
                ),
              ],
            );
          }
          
          // Layout desktop : éléments en ligne
          return Row(
        children: [
          const EmbLogo(size: 32),
          const Gap(8),
              const Expanded(
                child: Text(
            'EMB-MISSION',
            style: TextStyle(
              fontWeight: FontWeight.w900,
              fontSize: 16,
              color: Color(0xFF0F172A),
                  ),
                  overflow: TextOverflow.ellipsis,
            ),
          ),
          const Spacer(),
              // Notification, profil utilisateur et boutons à l'extrême droite
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Profil utilisateur
                  const UserProfileChip(),
          const Gap(16),
                  // Bouton
                  ElevatedButton.icon(
                    onPressed: () => context.go('/webtv/diffusionlive'),
                    icon: const Icon(Icons.live_tv, size: 20),
                    label: const Text('Démarrer Diffusion Live'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.redColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                    ),
                  ),
                ],
              ),
        ],
      );
        },
      ),
    );
  }
}

/// Layout mobile (empilé verticalement)
class _MobileLayout extends StatelessWidget {
  const _MobileLayout({
    required this.autoPlaylistStatus,
    required this.autoPlaylistCurrentUrl,
    required this.currentStream,
    required this.onStartBroadcast,
    required this.isPaused,
    required this.playlistItems,
    required this.isLoadingPlaylist,
  });
  
  final Map<String, dynamic>? autoPlaylistStatus;
  final Map<String, dynamic>? autoPlaylistCurrentUrl;
  final WebTvStream? currentStream;
  final Future<void> Function() onStartBroadcast;
  final bool isPaused;
  final List<Map<String, dynamic>> playlistItems;
  final bool isLoadingPlaylist;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Lecteur vidéo
        _VideoPlayerCard(
          autoPlaylistStatus: autoPlaylistStatus,
          autoPlaylistCurrentUrl: autoPlaylistCurrentUrl,
          currentStream: currentStream,
          onStartBroadcast: onStartBroadcast,
          isPaused: isPaused,
        ),
        const Gap(24),
        
        // Source Active
        _ActiveSourceCard(),
        const Gap(24),
        
        // File d'attente
        _QueueCard(
          playlistItems: playlistItems,
          isLoadingPlaylist: isLoadingPlaylist,
        ),
      ],
    );
  }
}

/// Layout desktop (côte à côte)
class _DesktopLayout extends StatelessWidget {
  const _DesktopLayout({
    required this.autoPlaylistStatus,
    required this.autoPlaylistCurrentUrl,
    required this.currentStream,
    required this.onStartBroadcast,
    required this.isPaused,
    required this.playlistItems,
    required this.isLoadingPlaylist,
  });
  
  final Map<String, dynamic>? autoPlaylistStatus;
  final Map<String, dynamic>? autoPlaylistCurrentUrl;
  final WebTvStream? currentStream;
  final Future<void> Function() onStartBroadcast;
  final bool isPaused;
  final List<Map<String, dynamic>> playlistItems;
  final bool isLoadingPlaylist;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Lecteur vidéo (gauche)
        Expanded(
          flex: 2,
          child: _VideoPlayerCard(
            autoPlaylistStatus: autoPlaylistStatus,
            autoPlaylistCurrentUrl: autoPlaylistCurrentUrl,
            currentStream: currentStream,
            onStartBroadcast: onStartBroadcast,
            isPaused: isPaused,
          ),
        ),
        const Gap(24),
        
        // Panneau droit (sources et file d'attente)
        Expanded(
          flex: 1,
          child: Column(
            children: [
              _ActiveSourceCard(),
              const Gap(24),
              _QueueCard(
                playlistItems: playlistItems,
                isLoadingPlaylist: isLoadingPlaylist,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Carte du lecteur vidéo
class _VideoPlayerCard extends StatelessWidget {
  const _VideoPlayerCard({
    required this.autoPlaylistStatus,
    required this.autoPlaylistCurrentUrl,
    required this.currentStream,
    required this.onStartBroadcast,
    required this.isPaused,
  });
  
  final Map<String, dynamic>? autoPlaylistStatus;
  final Map<String, dynamic>? autoPlaylistCurrentUrl;
  final WebTvStream? currentStream;
  final Future<void> Function() onStartBroadcast;
  final bool isPaused;

  /// Détermine si on est en mode live
  bool _isLiveMode() {
    return autoPlaylistStatus?['is_live'] ?? currentStream?.isLive ?? false;
  }

  /// Récupère le streamId pour le lecteur Ant Media
  String? _getStreamId() {
    return autoPlaylistStatus?['live_stream'] ?? currentStream?.streamId;
  }

  @override
  Widget build(BuildContext context) {
    // Déterminer le statut en fonction de l'API
    final isLive = autoPlaylistStatus?['is_live'] == true;
    final statusText = isLive ? 'LIVE' : 'HORS LIGNE';
    final statusColor = isLive ? const Color(0xFF10B981) : const Color(0xFFDC2626);
    final statusIcon = isLive ? Icons.live_tv : Icons.videocam_off;
    final statusMessage = isLive 
        ? 'Diffusion en direct en cours'
        : 'Le flux vidéo est actuellement hors ligne';
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Lecteur vidéo dynamique
          Container(
            width: double.infinity,
            height: 300,
            decoration: BoxDecoration(
              color: const Color(0xFF1F2937),
              borderRadius: BorderRadius.circular(8),
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(8),
            child: Stack(
              children: [
                  // Lecteur vidéo selon le mode et l'état de diffusion
                  if (!isPaused && _isLiveMode() && _getStreamId() != null)
                    // Mode Live: Utiliser AntMediaPlayer
                    AntMediaPlayer(
                      streamId: _getStreamId()!,
                      isLive: true,
                      width: double.infinity,
                      height: 300,
                    )
                  else if (!isPaused && _isLiveMode() && _getStreamId() != null)
                    AntMediaPlayer(
                      streamId: _getStreamId()!,
                      isLive: true,
                      width: double.infinity,
                      height: 300,
                    )
                  else if (!isPaused)
                    _VideoPlayerWidget(
                      videoUrl: 'https://tv.embmission.com/hls/streams/unified.m3u8',
                      title: 'Playlist WebTV',
                      playlistName: 'Diffusion automatique',
                      height: 300,
                    )
                  else
                    Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(
                            Icons.pause_circle_outline,
                            size: 64,
                            color: Color(0xFF6B7280),
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
                  
                  // Badge statut dynamique
                Positioned(
                  top: 16,
                  left: 16,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: const Color(0xFF374151),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                            decoration: BoxDecoration(
                              color: isPaused 
                                  ? const Color(0xFFF59E0B)  // Orange pour pause
                                  : (_isLiveMode() ? const Color(0xFF10B981) : const Color(0xFFDC2626)),
                            shape: BoxShape.circle,
                          ),
                        ),
                        const Gap(6),
                          Text(
                            isPaused 
                                ? 'PAUSE' 
                                : (_isLiveMode() ? 'LIVE' : 'HORS LIGNE'),
                            style: const TextStyle(
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
          const Gap(24),
          
          // Bouton et statut (responsive)
          LayoutBuilder(
            builder: (context, constraints) {
              final isMobile = constraints.maxWidth < 768;
              
              if (isMobile) {
                // Mode mobile : éléments empilés
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: onStartBroadcast,
                        icon: Icon(
                          isPaused ? Icons.play_arrow : Icons.stop, 
                          size: 20
                        ),
                        label: Text(isPaused ? 'Démarrer la diffusion' : 'Arrêter la diffusion'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: isPaused ? const Color(0xFFDC2626) : const Color(0xFF10B981),
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                      ),
                    ),
                    const Gap(12),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Statut de la connexion',
                          style: TextStyle(
                            color: Color(0xFF6B7280),
                            fontSize: 14,
                          ),
                        ),
                        const Gap(4),
                        Row(
                          children: [
                            Container(
                              width: 8,
                              height: 8,
                              decoration: const BoxDecoration(
                                color: Color(0xFF10B981),
                                shape: BoxShape.circle,
                              ),
                            ),
                            const Gap(6),
                            const Text(
                              'Excellente',
                              style: TextStyle(
                                color: Color(0xFF10B981),
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ],
                );
              }
              
              // Mode desktop : disposition d'origine (bouton à gauche, statut à droite)
              return Row(
            children: [
              ElevatedButton.icon(
                onPressed: onStartBroadcast,
                icon: Icon(
                  isPaused ? Icons.play_arrow : Icons.stop, 
                  size: 20
                ),
                label: Text(isPaused ? 'Démarrer la diffusion' : 'Arrêter la diffusion'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: isPaused ? const Color(0xFFDC2626) : const Color(0xFF10B981),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
              const Spacer(),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                    mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    'Statut de la connexion',
                    style: TextStyle(
                      color: Color(0xFF6B7280),
                      fontSize: 14,
                    ),
                  ),
                  const Gap(4),
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 8,
                        height: 8,
                        decoration: const BoxDecoration(
                          color: Color(0xFF10B981),
                          shape: BoxShape.circle,
                        ),
                      ),
                      const Gap(6),
                      const Text(
                        'Excellente',
                        style: TextStyle(
                          color: Color(0xFF10B981),
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ],
              );
            },
          ),
        ],
      ),
    );
  }
}

/// Carte Source Active
class _ActiveSourceCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Source Active',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: Color(0xFF111827),
            ),
          ),
          const Gap(16),
          
          // Dropdown
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            decoration: BoxDecoration(
              color: const Color(0xFFF9FAFB),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFE5E7EB)),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.videocam,
                  color: Color(0xFF6B7280),
                  size: 20,
                ),
                const Gap(8),
                const Expanded(
                  child: Text(
                    'OBS Studio',
                    style: TextStyle(
                      color: Color(0xFF111827),
                      fontSize: 14,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Carte File d'attente
class _QueueCard extends StatelessWidget {
  const _QueueCard({
    required this.playlistItems,
    required this.isLoadingPlaylist,
  });
  
  final List<Map<String, dynamic>> playlistItems;
  final bool isLoadingPlaylist;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Titre File d'attente - mis en commentaire
          // const Text(
          //   'File d\'attente',
          //   style: TextStyle(
          //     fontSize: 18,
          //     fontWeight: FontWeight.w700,
          //     color: Color(0xFF111827),
          //   ),
          // ),
          // const Gap(16),
          
          // Contenu de la file d'attente - mis en commentaire
          // if (isLoadingPlaylist)
          //   const Center(
          //     child: Padding(
          //       padding: EdgeInsets.all(20),
          //       child: CircularProgressIndicator(),
          //     ),
          //   )
          // else if (playlistItems.isEmpty)
          //   const Center(
          //     child: Padding(
          //       padding: EdgeInsets.all(20),
          //       child: Column(
          //         children: [
          //           Icon(
          //             Icons.queue_music,
          //             size: 48,
          //             color: Color(0xFF6B7280),
          //           ),
          //           Gap(8),
          //           Text(
          //             'Aucun élément dans la playlist',
          //             style: TextStyle(
          //               color: Color(0xFF6B7280),
          //               fontSize: 14,
          //             ),
          //           ),
          //         ],
          //       ),
          //     ),
          //   )
          // else
          //   // Liste des éléments de la playlist
          //   Column(
          //     children: playlistItems.asMap().entries.map((entry) {
          //       final index = entry.key;
          //       final item = entry.value;
          //       return Padding(
          //         padding: EdgeInsets.only(bottom: index < playlistItems.length - 1 ? 12 : 0),
          //         child: _QueueItem(
          //           icon: _getIconForItem(item),
          //           title: item['title'] ?? 'Titre inconnu',
          //           duration: _formatDuration(item['duration'] ?? 0),
          //           iconColor: _getColorForItem(item),
          //         ),
          //       );
          //     }).toList(),
          //   ),
          // const Gap(20),
          
          // Bouton Gérer les Playlists
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () => context.go('/contents/playlist-builder'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.blueColor,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              child: const Text(
                'Gérer les Playlists',
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  IconData _getIconForItem(Map<String, dynamic> item) {
    final title = (item['title'] ?? '').toLowerCase();
    if (title.contains('interview') || title.contains('pasteur')) {
      return Icons.mic;
    } else if (title.contains('clip') || title.contains('louange') || title.contains('hillsong')) {
      return Icons.music_note;
    } else if (item['is_live_stream'] == true) {
      return Icons.live_tv;
    } else {
      return Icons.videocam;
    }
  }

  Color _getColorForItem(Map<String, dynamic> item) {
    final title = (item['title'] ?? '').toLowerCase();
    if (title.contains('interview') || title.contains('pasteur')) {
      return const Color(0xFF3B82F6);
    } else if (title.contains('clip') || title.contains('louange') || title.contains('hillsong')) {
      return const Color(0xFF8B5CF6);
    } else if (item['is_live_stream'] == true) {
      return const Color(0xFFEF4444);
    } else {
      return const Color(0xFF6B7280);
    }
  }

  String _formatDuration(int durationInSeconds) {
    if (durationInSeconds == 0) return '--:--';
    
    final hours = durationInSeconds ~/ 3600;
    final minutes = (durationInSeconds % 3600) ~/ 60;
    final seconds = durationInSeconds % 60;
    
    if (hours > 0) {
      return '${hours.toString().padLeft(2, '0')}:${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
    } else {
      return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
    }
  }
}

/// Élément de la file d'attente
class _QueueItem extends StatelessWidget {
  const _QueueItem({
    required this.icon,
    required this.title,
    required this.duration,
    required this.iconColor,
  });

  final IconData icon;
  final String title;
  final String duration;
  final Color iconColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF9FAFB),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: iconColor.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(6),
            ),
            child: Icon(
              icon,
              color: iconColor,
              size: 16,
            ),
          ),
          const Gap(12),
          Expanded(
            child: Text(
              title,
              style: const TextStyle(
                color: Color(0xFF111827),
                fontSize: 14,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          Text(
            duration,
            style: const TextStyle(
              color: Color(0xFF6B7280),
              fontSize: 12,
            ),
          ),
        ],
      ),
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
  static const String _unifiedUrl = 'https://tv.embmission.com/hls/streams/unified.m3u8';

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
    element.play().then((_) {
      if (mounted) {
        setState(() {
          _autoplayBlocked = false;
        });
      }
    }).catchError((error) {
      print('⚠️ Lecture manuelle impossible (control): $error');
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
        print('🎬 Lecture URL HLS (control): $normalizedUrl');
        
        DateTime loadStart = DateTime.now();
        final videoElement = html.VideoElement()
          ..autoplay = false
          ..controls = true  // Activer les contrôles natifs du navigateur
          ..preload = 'auto'
          ..style.width = '100%'
          ..style.height = '100%'
          ..style.objectFit = 'contain';
        _videoElement = videoElement;
        if (_autoplayBlocked && mounted) {
          setState(() {
            _autoplayBlocked = false;
          });
        }

        videoElement.onWaiting.listen((_) => print('⌛ WAITING (control)'));
        videoElement.onStalled.listen((_) => print('⚠️ STALLED (control)'));
        videoElement.onPlaying.listen((_) {
          print('▶️ lecture (control)');
          if (_autoplayBlocked && mounted) {
            setState(() {
              _autoplayBlocked = false;
            });
          }
        });
        videoElement.onError.listen((event) {
          print('❌ ERROR (control): ${videoElement.error}');
        });

        videoElement.onCanPlay.listen((_) {
          final readyIn = DateTime.now().difference(loadStart).inMilliseconds;
          print('▶️ prêt en ${readyIn} ms (control)');
          if (videoElement.paused) {
            videoElement.play().catchError((error) {
              print('⚠️ Autoplay bloqué (control): $error');
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
        
        // 🔥 Écouter la fin de la vidéo pour charger la suivante automatiquement
        videoElement.onEnded.listen((_) async {
          if (isUnifiedSource) {
            print('ℹ️ Flux unifié terminé (control) - pas de next-vod');
            return;
          }

          print('📺 Fin de la vidéo, récupération de la suivante...');
          await _loadNextVideo(videoElement, () {
            loadStart = DateTime.now();
          });
        });
        
        return videoElement;
      },
    );
    
    setState(() {
      _isInitialized = true;
    });
  }
  
  /// Charge automatiquement la vidéo suivante depuis l'API
  Future<void> _loadNextVideo(
    html.VideoElement videoElement,
    void Function() onReload,
  ) async {
    final normalizedUrl = _normalizeUrl(widget.videoUrl);
    if (normalizedUrl == _unifiedUrl) {
      print('ℹ️ Flux unifié actif (control) - on ignore next-vod');
      return;
    }

    try {
      print('🔍 Appel API pour la vidéo suivante...');
      
      final response = await http.get(
        Uri.parse('https://tv.embmission.com/api/webtv-auto-playlist/next-vod'),
        headers: {'Content-Type': 'application/json'},
      );
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        
        if (data['success'] == true && data['data'] != null) {
          final nextUrl = data['data']['url'];
          final nextTitle = data['data']['item_title'] ?? 'Playlist TV';
          
          print('✅ Prochaine vidéo trouvée: $nextTitle');
          print('📺 URL: $nextUrl');
          
          final rawUrl = nextUrl is String ? nextUrl : nextUrl?.toString() ?? '';
          final preparedUrl = _withNoCacheIfNeeded(rawUrl);
          
          if (_shakaPlayer != null) {
            try {
              await js_util.promiseToFuture(
                js_util.callMethod(_shakaPlayer!, 'load', [preparedUrl]),
              );
              onReload();
            } catch (e) {
              print('💥 Erreur chargement prochaine vidéo via Shaka: $e');
              videoElement
                ..src = preparedUrl
                ..load();
              onReload();
            }
          } else {
            videoElement
              ..src = preparedUrl
              ..load();
            onReload();
          }
          
          // Afficher une notification à l'utilisateur (optionnel)
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Lecture: $nextTitle'),
                duration: const Duration(seconds: 2),
                backgroundColor: Colors.green,
              ),
            );
          }
        } else {
          print('⚠️ Aucune vidéo suivante disponible');
          // Optionnel : afficher un message à l'utilisateur
        }
      } else {
        print('❌ Erreur API next-vod: ${response.statusCode}');
      }
    } catch (e) {
      print('💥 Erreur lors du chargement de la vidéo suivante: $e');
    }
  }

  Future<void> _initializeVideoPlayer() async {
    try {
      final normalizedUrl = _normalizeUrl(widget.videoUrl);
      print('🎬 Début initialisation vidéo (control): $normalizedUrl');
      
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
                // Élément vidéo HTML natif avec contrôles natifs
                HtmlElementView(viewType: _viewId),
                    if (_autoplayBlocked)
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
                const CircularProgressIndicator(
                  color: Colors.white,
                ),
                const Gap(16),
                Text(
                  'Chargement de la vidéo...',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 16,
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

/// Contrôles vidéo pour le web
class _VideoControlsWeb extends StatelessWidget {
  const _VideoControlsWeb({
    required this.title,
    required this.playlistName,
  });
  
  final String title;
  final String playlistName;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            Colors.black.withValues(alpha: 0.7),
            Colors.transparent,
            Colors.black.withValues(alpha: 0.7),
          ],
        ),
      ),
      child: Column(
        children: [
          // Titre en haut
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Expanded(
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
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      if (playlistName.isNotEmpty)
                        Text(
                          playlistName,
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 12,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const Spacer(),
          // Contrôles en bas
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                const Icon(
                  Icons.play_arrow,
                  color: Colors.white,
                  size: 32,
                ),
                const Gap(16),
                Expanded(
                  child: Container(
                    height: 4,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.3),
                      borderRadius: BorderRadius.circular(2),
                    ),
                    child: FractionallySizedBox(
                      alignment: Alignment.centerLeft,
                      widthFactor: 0.3, // Progression simulée
                      child: Container(
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),
                  ),
                ),
                const Gap(16),
                Text(
                  '2:30 / 8:45',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
