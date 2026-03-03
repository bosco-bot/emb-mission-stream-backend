import 'dart:async';
import 'package:audioplayers/audioplayers.dart';
import 'package:emb_mission_dashboard/core/models/webradio_models.dart';
import 'package:emb_mission_dashboard/core/services/azuracast_service.dart';
import 'package:emb_mission_dashboard/core/services/webradio_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

/// Fonction utilitaire pour corriger l'encodage des caractères
String _fixTextEncoding(String text) {
  if (text.isEmpty) return text;
  
  return text
      // Corriger les caractères d'encodage courants (ISO-8859-1 mal interprété en UTF-8)
      .replaceAll('Ã©', 'é')
      .replaceAll('Ã¨', 'è')
      .replaceAll('Ã§', 'ç')
      .replaceAll('Ã ', 'à')
      .replaceAll('Ã¢', 'â')
      .replaceAll('Ã´', 'ô')
      .replaceAll('Ã®', 'î')
      .replaceAll('Ã¯', 'ï')
      .replaceAll('Ã¹', 'ù')
      .replaceAll('Ã»', 'û')
      .replaceAll('Ã¼', 'ü')
      // Corriger les caractères de remplacement UTF-8
      .replaceAll('', '') // Supprimer les caractères de remplacement
      // Nettoyer les séparateurs et espaces
      .replaceAll('___', ' - ')
      .replaceAll('__', ' - ')
      .replaceAll('_', ' ')
      .replaceAll('  ', ' ') // Supprimer les espaces doubles
      .replaceAll('  ', ' ') // Supprimer les espaces doubles restants
      .trim();
}

/// Lecteur WebRadio
/// 
/// Interface complète de lecteur audio avec contrôles de lecture,
/// informations de diffusion et historique des pistes.
class WebradioPage extends StatelessWidget {
  const WebradioPage({super.key});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        final isTablet = constraints.maxWidth >= 768 && constraints.maxWidth < 1024;
        
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Top bar avec titre et statut LIVE
              _TopBar(),
              const Divider(height: 32),
              
              // Contenu principal responsive
              Expanded(
                child: isMobile 
                  ? _MobileLayout()
                  : isTablet 
                    ? _TabletLayout()
                    : _DesktopLayout(),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Layout mobile : lecteur centré, panneaux empilés
class _MobileLayout extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return const SingleChildScrollView(
      child: Column(
        children: [
          WebRadioPlayer(),
          Gap(20),
          BroadcastInfoCard(),
          Gap(16),
          TrackHistoryCard(),
        ],
      ),
    );
  }
}

/// Layout tablette : lecteur + panneaux côte à côte
class _TabletLayout extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(flex: 2, child: WebRadioPlayer()),
        Gap(16),
        Expanded(
          child: Column(
            children: [
              BroadcastInfoCard(),
              Gap(16),
              TrackHistoryCard(),
            ],
          ),
        ),
      ],
    );
  }
}

/// Layout desktop : lecteur centré, panneaux à droite
class _DesktopLayout extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(flex: 2, child: WebRadioPlayer()),
        Gap(24),
        Expanded(
          child: Column(
            children: [
              BroadcastInfoCard(),
              Gap(16),
              TrackHistoryCard(),
            ],
          ),
        ),
      ],
    );
  }
}

/// Barre supérieure avec titre et statut LIVE (responsive)
class _TopBar extends StatefulWidget {
  @override
  State<_TopBar> createState() => _TopBarState();
}

class _TopBarState extends State<_TopBar> {
  bool _isLive = false;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _checkLiveStatus();
    _startPeriodicCheck();
  }

  Future<void> _checkLiveStatus() async {
    try {
      final nowPlaying = await AzuraCastService.instance.getNowPlaying();
      if (mounted) {
        setState(() {
          _isLive = nowPlaying.live.isLive;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLive = false;
          _isLoading = false;
        });
      }
    }
  }

  void _startPeriodicCheck() {
    Future.delayed(const Duration(seconds: 10), () {
      if (mounted) {
        _checkLiveStatus();
        _startPeriodicCheck();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        final isTablet = constraints.maxWidth >= 768 && constraints.maxWidth < 1024;
        
        if (isMobile) {
          // Layout mobile : titre complet + éléments réorganisés
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Première ligne : Titre complet + Badge + Notifications + Profil
              Row(
                children: [
                  const Text(
                    'Lecteur WebRadio',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF111827),
                    ),
                  ),
                  const Gap(8),
                  // Badge dynamique EN DIRECT
                  if (_isLive && !_isLoading)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                      decoration: BoxDecoration(
                        color: AppTheme.redColor,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 6,
                            height: 6,
                            decoration: const BoxDecoration(
                              color: Colors.white,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const Gap(4),
                          const Text(
                            'EN DIRECT',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                              fontSize: 10,
                            ),
                          ),
                        ],
                      ),
                    ),
                  const Spacer(),
                  // Notifications et profil à droite
                  const UserProfileChip(),
                ],
              ),
              const Gap(12),
              // Deuxième ligne : Boutons Contrôle et Sources
              Row(
                children: [
                  ElevatedButton.icon(
                    onPressed: () => context.go('/webradio/control'),
                    icon: const Icon(Icons.control_camera, size: 16),
                    label: const Text('Contrôle', style: TextStyle(fontSize: 12)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.blueColor,
                      foregroundColor: const Color(0xFF2F3B52),
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                  ),
                  const Gap(8),
                  ElevatedButton.icon(
                    onPressed: () => context.go('/webradio/sources'),
                    icon: const Icon(Icons.settings, size: 16),
                    label: const Text('Sources', style: TextStyle(fontSize: 12)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF8B5CF6),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                  ),
                  const Spacer(),
                ],
              ),
              const Gap(12),
              // Troisième ligne : Bouton Démarrer Diffusion Live (pleine largeur)
              SizedBox(
                width: double.infinity,
                child: const StartLiveButton(),
              ),
            ],
          );
        }
        
        if (isTablet) {
          // Layout tablette : titre complet + éléments optimisés
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Première ligne : Titre + Badge + Notifications + Profil
              Row(
                children: [
                  const Text(
                    'Lecteur WebRadio',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF111827),
                    ),
                  ),
                  const Gap(8),
                  // Badge dynamique EN DIRECT
                  if (_isLive && !_isLoading)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                      decoration: BoxDecoration(
                        color: AppTheme.redColor,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 6,
                            height: 6,
                            decoration: const BoxDecoration(
                              color: Colors.white,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const Gap(4),
                          const Text(
                            'EN DIRECT',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                              fontSize: 10,
                            ),
                          ),
                        ],
                      ),
                    ),
                  const Spacer(),
                  // Notifications et profil à droite
                  const UserProfileChip(),
                ],
              ),
              const Gap(12),
              // Deuxième ligne : Boutons principaux
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  ElevatedButton.icon(
                    onPressed: () => context.go('/webradio/control'),
                    icon: const Icon(Icons.control_camera, size: 16),
                    label: const Text('Contrôle', style: TextStyle(fontSize: 12)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.blueColor,
                      foregroundColor: const Color(0xFF2F3B52),
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                  ),
                  ElevatedButton.icon(
                    onPressed: () => context.go('/webradio/sources'),
                    icon: const Icon(Icons.settings, size: 16),
                    label: const Text('Sources', style: TextStyle(fontSize: 12)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF8B5CF6),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                  ),
                  const StartLiveButton(),
                ],
              ),
            ],
          );
        }
        
        // Layout desktop : tous les éléments
        return Row(
          children: [
            const Text(
              'Lecteur WebRadio',
              style: TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.w800,
                color: Color(0xFF111827),
              ),
            ),
            const Gap(12),
            // Badge dynamique EN DIRECT
            if (_isLive && !_isLoading)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: AppTheme.redColor,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const Gap(6),
                    const Text(
                      'EN DIRECT',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
            const Spacer(),
            
            // Bouton Contrôle WebRadio
            ElevatedButton.icon(
              onPressed: () => context.go('/webradio/control'),
              icon: const Icon(Icons.control_camera, size: 18),
              label: const Text('Contrôle'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.blueColor,
                foregroundColor: const Color(0xFF2F3B52),
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              ),
            ),
            const Gap(8),
            // Bouton Gestion des Sources
            ElevatedButton.icon(
              onPressed: () => context.go('/webradio/sources'),
              icon: const Icon(Icons.settings, size: 18),
              label: const Text('Sources'),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF8B5CF6),
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              ),
            ),
            const Gap(12),
            
            // Boutons d'action
            const StartLiveButton(),
            const Gap(10),
            const UserProfileChip(),
          ],
        );
      },
    );
  }
}

/// Lecteur WebRadio principal
class WebRadioPlayer extends StatefulWidget {
  const WebRadioPlayer({super.key});

  @override
  State<WebRadioPlayer> createState() => _WebRadioPlayerState();
}

class _WebRadioPlayerState extends State<WebRadioPlayer> {
  NowPlayingData? _nowPlayingData;
  BroadcastInfo? _broadcastInfo;
  bool _isLoading = true;
  String? _errorMessage;
  Timer? _refreshTimer;
  
  // Cache intelligent pour éviter les updates inutiles
  String? _lastSongTitle;
  String? _lastArtist;
  bool _lastIsLive = false;
  int _lastListenersCount = 0;
  DateTime? _lastDataUpdate;
  
  // Lecteur audio
  late AudioPlayer _audioPlayer;
  bool _isPlaying = false;
  bool _isLoadingAudio = false;
  double _volume = 0.8; // Volume par défaut (0.0 à 1.0)

  @override
  void initState() {
    super.initState();
    _initializeAudioPlayer();
    _loadData();
    // Rafraîchir toutes les 10 secondes
    _startPeriodicRefresh();
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _audioPlayer.dispose();
    super.dispose();
  }

  /// Initialise le lecteur audio
  void _initializeAudioPlayer() {
    _audioPlayer = AudioPlayer();
    
    // Définir le volume initial
    _audioPlayer.setVolume(_volume);
    
    // Écouter les changements d'état
    _audioPlayer.onPlayerStateChanged.listen((PlayerState state) {
      if (mounted) {
        setState(() {
          _isPlaying = state == PlayerState.playing;
          _isLoadingAudio = false; // Géré manuellement lors du démarrage
        });
      }
    });
    
    // ✅ Écouter les erreurs pour reconnexion automatique
    _audioPlayer.onLog.listen((log) {
      if (log.message.contains('error') || log.message.contains('failed')) {
        print('⚠️ Erreur détectée dans le lecteur: ${log.message}');
        // Si on était en train de jouer, essayer de se reconnecter
        if (_isPlaying) {
          _reconnectStream();
        }
      }
    });
  }
  
  // ✅ Reconnexion automatique du stream
  int _reconnectAttempts = 0;
  static const int _maxReconnectAttempts = 5;
  
  Future<void> _reconnectStream() async {
    if (_reconnectAttempts >= _maxReconnectAttempts) {
      print('❌ Nombre maximum de tentatives de reconnexion atteint');
      if (mounted) {
        setState(() {
          _isPlaying = false;
          _isLoadingAudio = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Impossible de se reconnecter au stream'),
            backgroundColor: Colors.red,
            duration: Duration(seconds: 3),
          ),
        );
      }
      return;
    }
    
    _reconnectAttempts++;
    print('🔄 Tentative de reconnexion $_reconnectAttempts/$_maxReconnectAttempts...');
    
    try {
      // Arrêter le lecteur actuel
      await _audioPlayer.stop();
      
      // Attendre un peu avant de réessayer
      await Future.delayed(Duration(seconds: 1 + _reconnectAttempts));
      
      // Rejouer avec un timestamp pour forcer la reconnexion
      String streamUrl = _buildStreamUrl();
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final urlWithTimestamp = '$streamUrl${streamUrl.contains('?') ? '&' : '?'}t=$timestamp';
      
      await _audioPlayer.play(UrlSource(urlWithTimestamp));
      
      print('✅ Reconnexion réussie');
      _reconnectAttempts = 0; // Réinitialiser le compteur
      
      if (mounted) {
        setState(() {
          _isPlaying = true;
          _isLoadingAudio = false;
        });
      }
    } catch (e) {
      print('⚠️ Échec de la reconnexion: $e');
      // Réessayer après un délai plus long
      Future.delayed(Duration(seconds: 3 * _reconnectAttempts), () {
        if (mounted && _isPlaying) {
          _reconnectStream();
        }
      });
    }
  }

  /// Charge les données depuis les APIs
  Future<void> _loadData({bool silent = false}) async {
    if (!silent) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      // Charger les deux APIs en parallèle
      final results = await Future.wait([
        AzuraCastService.instance.getNowPlaying(),
        WebRadioService.instance.getBroadcastInfo(),
      ]);

      if (mounted) {
        final newNowPlayingData = results[0] as NowPlayingData;
        final newBroadcastInfo = results[1] as BroadcastInfo;

        // Vérifier s'il y a des changements significatifs
        final hasChanges = _hasSignificantChanges(newNowPlayingData, newBroadcastInfo);
        
        if (hasChanges || !silent) {
          setState(() {
            _nowPlayingData = newNowPlayingData;
            _broadcastInfo = newBroadcastInfo;
            _isLoading = false;
          });
          
          // Mettre à jour le cache
          _updateCache(newNowPlayingData);
        } else {
          // Pas de changements, pas de setState (pas de rebuild)
          _lastDataUpdate = DateTime.now();
        }
      }
    } catch (e) {
      if (mounted && !silent) {
        setState(() {
          _errorMessage = e.toString();
          _isLoading = false;
        });
      }
    }
  }

  /// Vérifie s'il y a des changements significatifs
  bool _hasSignificantChanges(NowPlayingData? newData, BroadcastInfo? newBroadcastInfo) {
    if (_nowPlayingData == null || newData == null) return true;
    
    // Vérifier les changements importants
    final currentSong = newData.nowPlaying?.song?.title ?? '';
    final currentArtist = newData.nowPlaying?.song?.artist ?? '';
    final currentIsLive = newData.live?.isLive ?? false;
    final currentListeners = newData.listeners?.current ?? 0;
    
    return currentSong != _lastSongTitle ||
           currentArtist != _lastArtist ||
           currentIsLive != _lastIsLive ||
           currentListeners != _lastListenersCount;
  }

  /// Met à jour le cache des données
  void _updateCache(NowPlayingData? data) {
    if (data != null) {
      _lastSongTitle = data.nowPlaying?.song?.title;
      _lastArtist = data.nowPlaying?.song?.artist;
      _lastIsLive = data.live?.isLive ?? false;
      _lastListenersCount = data.listeners?.current ?? 0;
      _lastDataUpdate = DateTime.now();
    }
  }

  /// Nettoie le titre de la piste
  String _cleanTrackTitle(String title) {
    if (title.isEmpty) return 'Titre inconnu';
    
    // Nettoyer les séparateurs et espaces (sans toucher aux caractères UTF-8)
    String cleaned = title
        .replaceAll('___', ' - ')
        .replaceAll('__', ' - ')
        .replaceAll('_', ' ')
        .replaceAll('  ', ' ') // Supprimer les espaces doubles
        .replaceAll('  ', ' ') // Supprimer les espaces doubles restants
        .trim();
    
    // Limiter la longueur et ajouter des points si nécessaire
    if (cleaned.length > 50) {
      cleaned = '${cleaned.substring(0, 47)}...';
    }
    
    return cleaned;
  }

  /// Nettoie le nom de l'artiste
  String _cleanTrackArtist(String artist) {
    if (artist.isEmpty) return 'Artiste inconnu';
    
    // Corriger l'encodage et nettoyer
    String cleaned = artist
        .replaceAll('___', ' - ')
        .replaceAll('__', ' - ')
        .replaceAll('_', ' ')
        .replaceAll('  ', ' ')
        .trim();
    
    // Limiter la longueur
    if (cleaned.length > 40) {
      cleaned = '${cleaned.substring(0, 37)}...';
    }
    
    return cleaned;
  }

  /// Joue ou met en pause le stream
  Future<void> _togglePlayPause() async {
    if (_broadcastInfo == null) return;

    try {
      if (_isPlaying) {
        await _audioPlayer.pause();
        setState(() => _isLoadingAudio = false);
      } else {
        setState(() => _isLoadingAudio = true);
        
        // Construire l'URL complète du stream
        String streamUrl = _buildStreamUrl();
        print('🎵 Tentative de lecture: $streamUrl');
        
        await _audioPlayer.play(UrlSource(streamUrl));
        // L'état sera mis à jour par le listener
        setState(() => _isLoadingAudio = false);
        _reconnectAttempts = 0; // Réinitialiser le compteur de reconnexion
      }
    } catch (e) {
      setState(() => _isLoadingAudio = false);
      
      print('💥 Erreur de lecture: $e');
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur de lecture: $e'),
            backgroundColor: Colors.red,
            action: SnackBarAction(
              label: 'Détails',
              textColor: Colors.white,
              onPressed: () {
                showDialog(
                  context: context,
                  builder: (context) => AlertDialog(
                    title: const Text('Erreur de lecture audio'),
                    content: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('URL: ${_buildStreamUrl()}'),
                        const Gap(8),
                        Text('Erreur: $e'),
                        const Gap(8),
                        const Text(
                          'Solutions possibles:\n'
                          '• Vérifier la configuration du serveur\n'
                          '• Vérifier que le stream est actif\n'
                          '• Tester avec un autre navigateur',
                          style: TextStyle(fontSize: 12),
                        ),
                      ],
                    ),
                    actions: [
                      TextButton(
                        onPressed: () => Navigator.of(context).pop(),
                        child: const Text('OK'),
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
        );
      }
    }
  }

  /// Construit l'URL complète du stream
  String _buildStreamUrl() {
    if (_broadcastInfo == null) return '';
    
    // URLs possibles dans l'ordre de priorité
    final List<String> possibleUrls = [
      // URL HTTPS publique corrigée (priorité 1) - détection automatique du domaine
      'https://${Uri.base.host}/stream',
      
      // URLs de l'API (si corrigées)
      _broadcastInfo!.listenUrl,
      _broadcastInfo!.streamUrl,
      
      // URL construite avec les infos de l'API (fallback)
      'https://radio.embmission.com:${_broadcastInfo!.port}${_broadcastInfo!.mountPoint}',
    ];
    
    print('🔗 URLs disponibles:');
    for (int i = 0; i < possibleUrls.length; i++) {
      print('  ${i + 1}. ${possibleUrls[i]}');
    }
    
    // Retourner la première URL (HTTPS publique)
    return possibleUrls.first;
  }

  /// Arrête la lecture
  Future<void> _stop() async {
    await _audioPlayer.stop();
  }

  /// Change le volume
  Future<void> _setVolume(double volume) async {
    setState(() {
      _volume = volume;
    });
    await _audioPlayer.setVolume(volume);
  }


  /// Démarre le rafraîchissement périodique intelligent
  void _startPeriodicRefresh() {
    _refreshTimer?.cancel();
    
    // Refresh adaptatif selon l'activité
    _refreshTimer = Timer.periodic(const Duration(seconds: 15), (timer) {
      if (mounted) {
        _loadData(silent: true); // Refresh silencieux
      } else {
        timer.cancel();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.1),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          children: [
            // Artwork de l'album
            Container(
              width: 200,
              height: 200,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.2),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: _buildAlbumArtwork(),
              ),
            ),
            
            const Gap(24),
            
            // Informations de la chanson
            _buildSongInfo(),
            
            const Gap(32),
            
            // Barre de progression
            _buildProgressBar(),
            
            const Gap(32),
            
            // Contrôles de lecture
            _PlaybackControls(
              isPlaying: _isPlaying,
              isLoading: _isLoadingAudio,
              onPlayPause: _togglePlayPause,
              onStop: _stop,
            ),
            
            const Gap(24),
            
            // Contrôle de volume
            _VolumeControl(
              volume: _volume,
              onVolumeChanged: _setVolume,
            ),
          ],
        ),
      ),
    );
  }

  /// Construit l'artwork de l'album
  Widget _buildAlbumArtwork() {
    if (_isLoading) {
      return Container(
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFF3B82F6), Color(0xFF8B5CF6)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(16),
        ),
        child: const Center(
          child: CircularProgressIndicator(color: Colors.white),
        ),
      );
    }

    if (_errorMessage != null || _nowPlayingData == null) {
      return Container(
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFFEF4444), Color(0xFFF97316)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(16),
        ),
        child: const Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.error_outline, size: 48, color: Colors.white),
              Gap(8),
              Text(
                'Erreur de chargement',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
        ),
      );
    }

    final song = _nowPlayingData!.nowPlaying.song;
    
    return Image.network(
      song.art,
      fit: BoxFit.cover,
      errorBuilder: (context, error, stackTrace) => Container(
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFF3B82F6), Color(0xFF8B5CF6), Color(0xFFEF4444)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.music_note, size: 48, color: Colors.white),
            ],
          ),
        ),
      ),
    );
  }

  /// Construit les informations de la chanson
  Widget _buildSongInfo() {
    if (_isLoading) {
      return Column(
        children: [
          Container(
            width: 200,
            height: 20,
            decoration: BoxDecoration(
              color: const Color(0xFFE5E7EB),
              borderRadius: BorderRadius.circular(10),
            ),
          ),
          const Gap(8),
          Container(
            width: 150,
            height: 16,
            decoration: BoxDecoration(
              color: const Color(0xFFE5E7EB),
              borderRadius: BorderRadius.circular(8),
            ),
          ),
        ],
      );
    }

    if (_errorMessage != null || _nowPlayingData == null) {
      return Column(
        children: [
          const Text(
            'Aucune information disponible',
            style: TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w800,
              color: Color(0xFF111827),
            ),
            textAlign: TextAlign.center,
          ),
          const Gap(8),
          const Text(
            'Radio EMB Mission',
            style: TextStyle(
              fontSize: 18,
              color: Color(0xFF6B7280),
            ),
            textAlign: TextAlign.center,
          ),
        ],
      );
    }

    final song = _nowPlayingData!.nowPlaying.song;
    final listeners = _nowPlayingData!.listeners.current;

    return Column(
      children: [
        Text(
          _fixTextEncoding(song.title.isNotEmpty ? song.title : 'Radio EMB Mission'),
          style: const TextStyle(
            fontSize: 28,
            fontWeight: FontWeight.w800,
            color: Color(0xFF111827),
          ),
          textAlign: TextAlign.center,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        const Gap(8),
        Text(
          _fixTextEncoding(song.artist.isNotEmpty ? song.artist : 'EMB Mission'),
          style: const TextStyle(
            fontSize: 18,
            color: Color(0xFF6B7280),
          ),
          textAlign: TextAlign.center,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        const Gap(8),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Indicateur de lecture
            if (_isPlaying) ...[
              Container(
                width: 8,
                height: 8,
                decoration: const BoxDecoration(
                  color: Color(0xFF10B981),
                  shape: BoxShape.circle,
                ),
              ),
              const Gap(8),
              const Text(
                'EN LECTURE',
                style: TextStyle(
                  color: Color(0xFF10B981),
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const Gap(16),
            ],
            
            // Nombre d'auditeurs
            if (listeners > 0)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: BoxDecoration(
                  color: AppTheme.blueColor,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  '$listeners auditeur${listeners > 1 ? 's' : ''}',
                  style: const TextStyle(
                    color: Color(0xFF2F3B52),
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }

  /// Construit la barre de progression
  Widget _buildProgressBar() {
    if (_isLoading || _nowPlayingData == null) {
      return Column(
        children: [
          Container(
            height: 6,
            decoration: BoxDecoration(
              color: const Color(0xFFE5E7EB),
              borderRadius: BorderRadius.circular(3),
            ),
          ),
          const Gap(8),
          const Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                '--:--',
                style: TextStyle(
                  color: Color(0xFF6B7280),
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
              Text(
                '--:--',
                style: TextStyle(
                  color: Color(0xFF6B7280),
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ],
      );
    }

    final nowPlaying = _nowPlayingData!.nowPlaying;
    final isLive = _nowPlayingData!.live.isLive;
    
    return _ProgressBar(
      elapsed: nowPlaying.elapsed,
      duration: nowPlaying.duration,
      progress: nowPlaying.progress,
      isLive: isLive,
      audioPlayer: _audioPlayer,
    );
  }
}

/// Barre de progression
class _ProgressBar extends StatefulWidget {
  const _ProgressBar({
    required this.elapsed,
    required this.duration,
    required this.progress,
    required this.isLive,
    required this.audioPlayer,
  });

  final int elapsed;
  final int duration;
  final double progress;
  final bool isLive;
  final AudioPlayer audioPlayer;

  @override
  State<_ProgressBar> createState() => _ProgressBarState();
}

class _ProgressBarState extends State<_ProgressBar> {
  double? _seekingValue;
  bool _isSeeking = false;

  @override
  Widget build(BuildContext context) {
    // Utiliser la valeur de seeking si l'utilisateur est en train de chercher
    final displayProgress = _isSeeking ? (_seekingValue ?? widget.progress) : widget.progress;
    final displayElapsed = _isSeeking 
        ? ((_seekingValue ?? 0) * widget.duration).toInt()
        : widget.elapsed;

    return Column(
      children: [
        SliderTheme(
          data: SliderTheme.of(context).copyWith(
            activeTrackColor: widget.isLive 
                ? const Color(0xFFEF4444) // Rouge pour LIVE
                : AppTheme.redColor, // Rouge normal pour playlist
            inactiveTrackColor: const Color(0xFFE5E7EB),
            thumbColor: widget.isLive 
                ? const Color(0xFFEF4444)
                : AppTheme.redColor,
            thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 8),
            overlayShape: const RoundSliderOverlayShape(overlayRadius: 16),
            disabledThumbColor: const Color(0xFF9CA3AF),
            disabledActiveTrackColor: const Color(0xFFD1D5DB),
            disabledInactiveTrackColor: const Color(0xFFE5E7EB),
          ),
          child: Slider(
            value: displayProgress.clamp(0.0, 1.0),
            min: 0,
            max: 1,
            onChanged: widget.isLive ? null : (value) {
              // Activer uniquement pour les playlists (pas le live)
              setState(() {
                _isSeeking = true;
                _seekingValue = value;
              });
            },
            onChangeEnd: widget.isLive ? null : (value) async {
              // Tenter de seek à la nouvelle position
              final newPosition = (value * widget.duration).toInt();
              try {
                await widget.audioPlayer.seek(Duration(seconds: newPosition));
                print('✅ Seek à ${_formatTime(newPosition)}');
              } catch (e) {
                print('⚠️ Seek non supporté pour ce stream: $e');
                // Afficher un message à l'utilisateur
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text(
                        'Le saut dans le temps n\'est pas disponible pour ce type de diffusion',
                      ),
                      backgroundColor: Color(0xFFF59E0B),
                      duration: Duration(seconds: 3),
                    ),
                  );
                }
              } finally {
                setState(() {
                  _isSeeking = false;
                  _seekingValue = null;
                });
              }
            },
          ),
        ),
        const Gap(8),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                Text(
                  _formatTime(displayElapsed),
                  style: const TextStyle(
                    color: Color(0xFF6B7280),
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const Gap(8),
                // Indicateur LIVE ou PLAYLIST
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: widget.isLive 
                        ? const Color(0xFFEF4444)
                        : const Color(0xFF059669),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text(
                    widget.isLive ? 'LIVE' : 'PLAYLIST',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            Text(
              _formatTime(widget.duration),
              style: const TextStyle(
                color: Color(0xFF6B7280),
                fontSize: 14,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ],
    );
  }

  /// Formate le temps en MM:SS
  String _formatTime(int seconds) {
    final minutes = seconds ~/ 60;
    final remainingSeconds = seconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
  }
}

/// Contrôles de lecture
class _PlaybackControls extends StatelessWidget {
  const _PlaybackControls({
    required this.isPlaying,
    required this.isLoading,
    required this.onPlayPause,
    required this.onStop,
  });

  final bool isPlaying;
  final bool isLoading;
  final VoidCallback onPlayPause;
  final VoidCallback onStop;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        // Bouton précédent (désactivé pour la radio)
        IconButton(
          onPressed: null, // Désactivé pour la radio
          icon: const Icon(Icons.skip_previous, size: 32),
          style: IconButton.styleFrom(
            backgroundColor: const Color(0xFFF3F4F6),
            foregroundColor: const Color(0xFF9CA3AF), // Couleur grise pour désactivé
            padding: const EdgeInsets.all(12),
          ),
        ),
        const Gap(16),
        
        // Bouton play/pause principal
        Container(
          decoration: BoxDecoration(
            color: AppTheme.blueColor,
            shape: BoxShape.circle,
          ),
          child: IconButton(
            onPressed: isLoading ? null : onPlayPause,
            icon: isLoading
                ? const SizedBox(
                    width: 32,
                    height: 32,
                    child: CircularProgressIndicator(
                      color: Colors.white,
                      strokeWidth: 2,
                    ),
                  )
                : Icon(
                    isPlaying ? Icons.pause : Icons.play_arrow,
                    size: 32,
                    color: Colors.white,
                  ),
            style: IconButton.styleFrom(
              padding: const EdgeInsets.all(16),
            ),
          ),
        ),
        const Gap(16),
        
        // Bouton stop
        IconButton(
          onPressed: isPlaying ? onStop : null,
          icon: const Icon(Icons.stop, size: 32),
          style: IconButton.styleFrom(
            backgroundColor: const Color(0xFFF3F4F6),
            foregroundColor: isPlaying ? const Color(0xFF374151) : const Color(0xFF9CA3AF),
            padding: const EdgeInsets.all(12),
          ),
        ),
      ],
    );
  }
}

/// Contrôle de volume
class _VolumeControl extends StatelessWidget {
  const _VolumeControl({
    required this.volume,
    required this.onVolumeChanged,
  });

  final double volume;
  final ValueChanged<double> onVolumeChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        // Bouton mute/unmute
        IconButton(
          onPressed: () {
            if (volume > 0) {
              onVolumeChanged(0.0); // Mute
            } else {
              onVolumeChanged(0.8); // Unmute à 80%
            }
          },
          icon: Icon(
            volume == 0.0 
                ? Icons.volume_off 
                : volume < 0.5 
                    ? Icons.volume_down 
                    : Icons.volume_up,
            color: const Color(0xFF6B7280),
          ),
          tooltip: volume == 0.0 ? 'Activer le son' : 'Couper le son',
        ),
        const Gap(12),
        
        // Slider de volume
        SizedBox(
          width: 120,
          child: SliderTheme(
            data: SliderTheme.of(context).copyWith(
              activeTrackColor: AppTheme.redColor,
              inactiveTrackColor: const Color(0xFFE5E7EB),
              thumbColor: AppTheme.redColor,
              thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 6),
              overlayShape: const RoundSliderOverlayShape(overlayRadius: 12),
            ),
            child: Slider(
              value: volume,
              min: 0,
              max: 1,
              onChanged: onVolumeChanged,
            ),
          ),
        ),
        const Gap(12),
        
        // Affichage du pourcentage
        SizedBox(
          width: 40,
          child: Text(
            '${(volume * 100).round()}%',
            style: const TextStyle(
              color: Color(0xFF6B7280),
              fontSize: 12,
              fontWeight: FontWeight.w500,
            ),
            textAlign: TextAlign.center,
          ),
        ),
      ],
    );
  }
}

/// Carte d'informations de diffusion
class BroadcastInfoCard extends StatefulWidget {
  const BroadcastInfoCard({super.key});

  @override
  State<BroadcastInfoCard> createState() => _BroadcastInfoCardState();
}

class _BroadcastInfoCardState extends State<BroadcastInfoCard> {
  BroadcastInfo? _broadcastInfo;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadBroadcastInfo();
  }

  /// Charge les informations de diffusion depuis l'API
  Future<void> _loadBroadcastInfo() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final info = await WebRadioService.instance.getBroadcastInfo();
      
      if (mounted) {
        setState(() {
          _broadcastInfo = info;
          _isLoading = false;
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

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Text(
                  'Informations de Diffusion',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF111827),
                  ),
                ),
                const Spacer(),
                if (_errorMessage != null)
                  IconButton(
                    onPressed: _loadBroadcastInfo,
                    icon: const Icon(Icons.refresh, size: 20),
                    tooltip: 'Réessayer',
                    style: IconButton.styleFrom(
                      backgroundColor: AppTheme.blueColor,
                      foregroundColor: const Color(0xFF2F3B52),
                      padding: const EdgeInsets.all(8),
                    ),
                  ),
              ],
            ),
            const Gap(16),
            
            // État de chargement
            if (_isLoading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(20),
                  child: CircularProgressIndicator(),
                ),
              ),
            
            // État d'erreur
            if (_errorMessage != null && !_isLoading)
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF2F2),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFFECACA)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.error_outline, color: Color(0xFFDC2626), size: 20),
                        Gap(8),
                        Text(
                          'Erreur de chargement',
                          style: TextStyle(
                            color: Color(0xFFDC2626),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                    const Gap(8),
                    Text(
                      _errorMessage!,
                      style: const TextStyle(
                        color: Color(0xFF991B1B),
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ),
            
            // Données chargées
            if (_broadcastInfo != null && !_isLoading)
              Column(
                children: [
                  _InfoRow(
                    label: 'Port:',
                    value: _broadcastInfo!.portFormatted,
                  ),
                  const Gap(12),
                  _InfoRow(
                    label: 'Point de montage:',
                    value: _broadcastInfo!.mountPoint,
                  ),
                  const Gap(12),
                  _InfoRow(
                    label: 'Format:',
                    value: '${_broadcastInfo!.format.toUpperCase()} - ${_broadcastInfo!.bitrateFormatted}',
                  ),
                  const Gap(12),
                  _InfoRow(
                    label: 'URL d\'écoute:',
                    value: _broadcastInfo!.listenUrl,
                    showCopyButton: true,
                  ),
                  const Gap(12),
                  _InfoRow(
                    label: 'Lien M3U:',
                    value: _broadcastInfo!.m3uUrl,
                    showCopyButton: true,
                  ),
                  const Gap(12),
                  _InfoRow(
                    label: 'Lecteur public:',
                    value: _broadcastInfo!.publicPlayerUrl,
                    showCopyButton: true,
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

/// Carte d'historique des pistes
class TrackHistoryCard extends StatefulWidget {
  const TrackHistoryCard({super.key});

  @override
  State<TrackHistoryCard> createState() => _TrackHistoryCardState();
}

class _TrackHistoryCardState extends State<TrackHistoryCard> {
  List<SongHistory> _trackHistory = [];
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadTrackHistory();
  }

  /// Charge l'historique des pistes depuis AzuraCast
  Future<void> _loadTrackHistory() async {
    try {
      final data = await AzuraCastService.instance.getNowPlaying();
      
      if (mounted) {
        setState(() {
          _trackHistory = data.songHistory;
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

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Text(
                  'Historique des pistes',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF111827),
                  ),
                ),
                const Spacer(),
                if (_errorMessage != null)
                  IconButton(
                    onPressed: _loadTrackHistory,
                    icon: const Icon(Icons.refresh, size: 20),
                    tooltip: 'Réessayer',
                    style: IconButton.styleFrom(
                      backgroundColor: AppTheme.blueColor,
                      foregroundColor: const Color(0xFF2F3B52),
                      padding: const EdgeInsets.all(8),
                    ),
                  ),
              ],
            ),
            const Gap(16),
            
            // État de chargement
            if (_isLoading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(20),
                  child: CircularProgressIndicator(),
                ),
              ),
            
            // État d'erreur
            if (_errorMessage != null && !_isLoading)
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF2F2),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFFECACA)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.error_outline, color: Color(0xFFDC2626), size: 20),
                        Gap(8),
                        Text(
                          'Erreur de chargement',
                          style: TextStyle(
                            color: Color(0xFFDC2626),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                    const Gap(8),
                    Text(
                      _errorMessage!,
                      style: const TextStyle(
                        color: Color(0xFF991B1B),
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ),
            
            // Historique chargé
            if (_trackHistory.isNotEmpty && !_isLoading)
              ..._trackHistory.take(3).map((track) => _TrackHistoryItem(
                title: _fixTextEncoding(track.song.title),
                artist: _fixTextEncoding(track.song.artist),
                timestamp: track.timeFormatted,
                thumbnailText: _getThumbnailText(track.song.title),
                thumbnailColor: _getTrackColor(track.song.title),
              )),
              
            // Aucun historique
            if (_trackHistory.isEmpty && !_isLoading && _errorMessage == null)
              Container(
                padding: const EdgeInsets.all(20),
                child: const Center(
                  child: Text(
                    'Aucun historique disponible',
                    style: TextStyle(
                      color: Color(0xFF6B7280),
                      fontSize: 14,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  /// Nettoie le titre de la piste
  String _cleanTrackTitle(String title) {
    if (title.isEmpty) return 'Titre inconnu';
    
    // Nettoyer les séparateurs et espaces (sans toucher aux caractères UTF-8)
    String cleaned = title
        .replaceAll('___', ' - ')
        .replaceAll('__', ' - ')
        .replaceAll('_', ' ')
        .replaceAll('  ', ' ') // Supprimer les espaces doubles
        .replaceAll('  ', ' ') // Supprimer les espaces doubles restants
        .trim();
    
    // Limiter la longueur et ajouter des points si nécessaire
    if (cleaned.length > 50) {
      cleaned = '${cleaned.substring(0, 47)}...';
    }
    
    return cleaned;
  }

  /// Nettoie le nom de l'artiste
  String _cleanTrackArtist(String artist) {
    if (artist.isEmpty) return 'Artiste inconnu';
    
    // Corriger l'encodage et nettoyer
    String cleaned = artist
        .replaceAll('___', ' - ')
        .replaceAll('__', ' - ')
        .replaceAll('_', ' ')
        .replaceAll('  ', ' ')
        .trim();
    
    // Limiter la longueur
    if (cleaned.length > 40) {
      cleaned = '${cleaned.substring(0, 37)}...';
    }
    
    return cleaned;
  }

  /// Génère le texte de la miniature
  String _getThumbnailText(String title) {
    final cleanedTitle = _fixTextEncoding(title);
    if (cleanedTitle.isEmpty) return '?';
    
    // Prendre la première lettre significative (pas un espace ou tiret)
    for (int i = 0; i < cleanedTitle.length; i++) {
      final char = cleanedTitle[i];
      if (char != ' ' && char != '-' && char != '–') {
        return char.toUpperCase();
      }
    }
    
    return '?';
  }

  /// Génère une couleur pour la miniature basée sur le titre
  Color _getTrackColor(String title) {
    final colors = [
      const Color(0xFFF59E0B), // Orange
      const Color(0xFF8B5CF6), // Violet
      const Color(0xFFEC4899), // Rose
      const Color(0xFF10B981), // Vert
      const Color(0xFF3B82F6), // Bleu
      const Color(0xFFEF4444), // Rouge
    ];
    
    final hash = title.hashCode.abs();
    return colors[hash % colors.length];
  }
}

/// Ligne d'information
class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.label,
    required this.value,
    this.showCopyButton = false,
  });

  final String label;
  final String value;
  final bool showCopyButton;

  /// Copie le texte dans le presse-papier
  Future<void> _copyToClipboard(BuildContext context) async {
    await Clipboard.setData(ClipboardData(text: value));
    
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Row(
            children: [
              const Icon(Icons.check_circle, color: Colors.white, size: 20),
              const Gap(8),
              Expanded(
                child: Text(
                  'Copié dans le presse-papier',
                  style: const TextStyle(color: Colors.white),
                ),
              ),
            ],
          ),
          backgroundColor: const Color(0xFF10B981),
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 2),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        SizedBox(
          width: 120,
          child: Text(
            label,
            style: const TextStyle(
              color: Color(0xFF6B7280),
              fontSize: 14,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(
              color: Color(0xFF111827),
              fontSize: 14,
              fontWeight: FontWeight.w500,
            ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ),
        if (showCopyButton)
          IconButton(
            onPressed: () => _copyToClipboard(context),
            icon: const Icon(Icons.copy, size: 16),
            tooltip: 'Copier',
            style: IconButton.styleFrom(
              backgroundColor: const Color(0xFFF3F4F6),
              foregroundColor: const Color(0xFF6B7280),
              padding: const EdgeInsets.all(8),
            ),
          ),
      ],
    );
  }
}

/// Élément d'historique de piste
class _TrackHistoryItem extends StatelessWidget {
  const _TrackHistoryItem({
    required this.title,
    required this.artist,
    required this.timestamp,
    required this.thumbnailText,
    required this.thumbnailColor,
  });
  
  final String title;
  final String artist;
  final String timestamp;
  final String thumbnailText;
  final Color thumbnailColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          // Miniature
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(8),
              color: thumbnailColor,
            ),
            child: Center(
              child: Text(
                thumbnailText,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ),
          const Gap(12),
          
          // Informations
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF111827),
                    fontSize: 14,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const Gap(2),
                Text(
                  artist,
                  style: const TextStyle(
                    color: Color(0xFF6B7280),
                    fontSize: 12,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          
          // Timestamp
          Text(
            timestamp,
            style: const TextStyle(
              color: Color(0xFF6B7280),
              fontSize: 12,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}

