import 'package:emb_mission_dashboard/core/models/webradio_models.dart';
import 'package:emb_mission_dashboard/core/services/azuracast_service.dart';
import 'package:emb_mission_dashboard/core/services/webradio_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:gap/gap.dart';

/// Page de contrôle WebRadio
/// 
/// Interface pour contrôler la diffusion WebRadio avec informations de connexion
class WebradioControlPage extends StatefulWidget {
  const WebradioControlPage({super.key});

  @override
  State<WebradioControlPage> createState() => _WebradioControlPageState();
}

class _WebradioControlPageState extends State<WebradioControlPage> {
  NowPlayingData? _nowPlayingData;
  BroadcastInfo? _broadcastInfo;
  StreamingSettings? _streamingSettings;
  bool _isLoading = true;
  String? _errorMessage;
  String? _lastBroadcastAction; // ⭐ État remonté au niveau parent pour survivre aux refreshes

  @override
  void initState() {
    super.initState();
    _loadData();
    _startPeriodicRefresh();
  }

  Future<void> _loadData({bool showLoader = true}) async {
    if (showLoader) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final results = await Future.wait([
        AzuraCastService.instance.getNowPlaying(),
        WebRadioService.instance.getBroadcastInfo(),
        WebRadioService.instance.getStreamingSettings(),
      ]);

      if (mounted) {
        final nowPlaying = results[0] as NowPlayingData;
        final broadcastInfo = results[1] as BroadcastInfo;
        final streamingSettings = results[2] as StreamingSettings;
        
        // ⭐ Initialiser l'état des boutons selon l'état de la diffusion
        if (_lastBroadcastAction == null) {
          // Premier chargement : détecter l'état actuel
          _lastBroadcastAction = nowPlaying.isOnline ? 'start' : 'stop';
        }
        
        setState(() {
          _nowPlayingData = nowPlaying;
          _broadcastInfo = broadcastInfo;
          _streamingSettings = streamingSettings;
          if (showLoader) {
            _isLoading = false;
          }
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          if (showLoader) {
            _errorMessage = e.toString();
            _isLoading = false;
          }
          // En refresh silencieux, on garde les anciennes données
        });
      }
    }
  }

  void _startPeriodicRefresh() {
    Future.delayed(const Duration(seconds: 10), () {
      if (mounted) {
        _loadData(showLoader: false); // ✅ Refresh silencieux sans loader
        _startPeriodicRefresh();
      }
    });
  }

  // ⭐ Callback pour mettre à jour l'état depuis l'enfant
  void _onBroadcastActionChanged(String? action) {
    // ⭐ Utiliser setState pour mettre à jour l'autre bouton immédiatement
    if (mounted) {
      setState(() {
        _lastBroadcastAction = action;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        final isTablet = constraints.maxWidth >= 768 && constraints.maxWidth < 1024;
        
        return Padding(
          padding: EdgeInsets.symmetric(
            horizontal: isMobile ? 16 : 24,
            vertical: 16,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Top bar avec titre et profil
              _TopBar(isMobile: isMobile),
              const Divider(height: 32),
              
              // Contenu principal responsive
              Expanded(
                child: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Lecteur de contrôle principal
                      _ControlPlayerCard(
                        key: const ValueKey('control_player_card_stable'), // ⭐ Clé CONSTANTE pour préserver l'état pendant les rebuilds
                        isMobile: isMobile,
                        nowPlayingData: _nowPlayingData,
                        isLoading: _isLoading,
                        onRefresh: _loadData,
                        lastAction: _lastBroadcastAction, // ⭐ État du parent
                        onActionChanged: _onBroadcastActionChanged, // ⭐ Callback vers le parent
                      ),
                      const Gap(24),
                      
                      // Sections d'informations
                      if (isMobile)
                        _MobileInfoSections(
                          broadcastInfo: _broadcastInfo,
                          streamingSettings: _streamingSettings,
                        )
                      else
                        _DesktopInfoSections(
                          broadcastInfo: _broadcastInfo,
                          streamingSettings: _streamingSettings,
                        ),
                    ],
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

/// Barre supérieure avec titre et profil utilisateur
class _TopBar extends StatelessWidget {
  const _TopBar({required this.isMobile});
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    if (isMobile) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Contrôle WebRadio',
            style: TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.w800,
              color: Color(0xFF111827),
            ),
          ),
          const Gap(16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const UserProfileChip(),
            ],
          ),
        ],
      );
    }

    return Row(
      children: [
        const Text(
          'Contrôle WebRadio',
          style: TextStyle(
            fontSize: 32,
            fontWeight: FontWeight.w800,
            color: Color(0xFF111827),
          ),
        ),
        const Spacer(),
        const UserProfileChip(),
      ],
    );
  }
}

/// Carte du lecteur de contrôle principal
class _ControlPlayerCard extends StatefulWidget {
  const _ControlPlayerCard({
    super.key, // ⭐ Ajouter la clé pour préserver l'état
    required this.isMobile,
    required this.nowPlayingData,
    required this.isLoading,
    required this.onRefresh,
    required this.lastAction, // ⭐ État du parent
    required this.onActionChanged, // ⭐ Callback vers le parent
  });
  
  final bool isMobile;
  final NowPlayingData? nowPlayingData;
  final bool isLoading;
  final VoidCallback onRefresh;
  final String? lastAction; // ⭐ État du parent (survit aux refreshes)
  final ValueChanged<String?> onActionChanged; // ⭐ Callback vers le parent

  @override
  State<_ControlPlayerCard> createState() => _ControlPlayerCardState();
}

class _ControlPlayerCardState extends State<_ControlPlayerCard> {
  bool _isProcessing = false;
  String? _currentAction; // Track action being processed right now

  Future<void> _handleBroadcastControl(String action) async {
    // ✅ Protection contre les doubles clics pendant le traitement
    if (_isProcessing) {
      print('⚠️ Action déjà en cours, ignorée : $action');
      return;
    }

    // ✅ Protection contre les clics répétés sur le même bouton (état du parent)
    if (widget.lastAction == action) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('La diffusion est déjà ${action == "stop" ? "arrêtée" : "démarrée"}'),
            backgroundColor: Colors.orange,
            duration: const Duration(seconds: 2),
          ),
        );
      }
      return;
    }

    // ✅ Désactiver UNIQUEMENT localement (pour le spinner et empêcher doubles clics)
    setState(() {
      _isProcessing = true;
      _currentAction = action;
    });
    
    try {
      final message = await WebRadioService.instance.controlBroadcast(action);
      
      if (mounted) {
        // ⭐ SUCCÈS : Notifier le parent MAINTENANT pour changer les boutons
        widget.onActionChanged(action);
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(message),
            backgroundColor: Colors.green,
          ),
        );
        
        // ✅ Attendre 2 secondes avant le refresh
        await Future.delayed(const Duration(seconds: 2));
        
        // ✅ Réinitialiser l'état local
        setState(() {
          _isProcessing = false;
          _currentAction = null;
        });
        
        // ✅ Refresh data
        widget.onRefresh();
      }
    } catch (e) {
      // ❌ ERREUR : Ne PAS notifier le parent, juste réinitialiser l'état local
      if (mounted) {
        setState(() {
          _isProcessing = false;
          _currentAction = null;
        });
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final isOnline = widget.nowPlayingData?.isOnline ?? false;
    final songTitle = widget.nowPlayingData?.nowPlaying.song.title ?? 'Aucune chanson';
    final songArtist = widget.nowPlayingData?.nowPlaying.song.artist ?? 'Aucun artiste';
    final stationName = widget.nowPlayingData?.station.name ?? 'Radio';
    final statusText = isOnline ? 'En Ligne' : 'Hors Ligne';
    final statusColor = isOnline ? const Color(0xFF10B981) : const Color(0xFF6B7280);
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          children: [
            // Badge statut et logo
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: statusColor,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.circle, size: 8, color: Colors.white),
                      const Gap(6),
                      Text(
                        statusText,
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w600,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                const Spacer(),
              ],
            ),
            
            const Gap(32),
            
            // Logo station
            Container(
              width: 120,
              height: 120,
              decoration: BoxDecoration(
                color: const Color(0xFF1E3A8A),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(
                      Icons.radio,
                      color: Colors.white,
                      size: 32,
                    ),
                    const Gap(8),
                    Text(
                      stationName.length > 12 
                        ? '${stationName.substring(0, 12)}...' 
                        : stationName,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            ),
            
            const Gap(24),
            
            // Informations de la chanson
            if (widget.isLoading)
              const CircularProgressIndicator()
            else ...[
              Text(
                songTitle,
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
                songArtist,
                style: const TextStyle(
                  fontSize: 18,
                  color: Color(0xFF6B7280),
                ),
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
            
            const Gap(32),
            
            // Boutons de contrôle (responsive)
            if (widget.isMobile) ...[
              // Mode mobile : boutons empilés
              Column(
                children: [
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      // ✅ Désactivé si : en cours de traitement OU déjà arrêté (état du parent)
                      onPressed: (_isProcessing || widget.lastAction == 'stop') 
                        ? null 
                        : () => _handleBroadcastControl('stop'),
                      icon: _currentAction == 'stop'
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.pause, size: 18),
                      label: const Text('Arrêter la diffusion', style: TextStyle(fontSize: 14)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF6B7280),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                    ),
                  ),
                  const Gap(12),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      // ✅ Désactivé si : en cours de traitement OU déjà démarré (état du parent)
                      onPressed: (_isProcessing || widget.lastAction == 'start') 
                        ? null 
                        : () => _handleBroadcastControl('start'),
                      icon: _currentAction == 'start'
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.play_arrow, size: 18),
                      label: const Text('Démarrer la diffusion', style: TextStyle(fontSize: 14)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.redColor,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ] else ...[
              // Mode desktop : boutons en ligne
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  ElevatedButton.icon(
                    // ✅ Désactivé si : en cours de traitement OU déjà arrêté (état du parent)
                    onPressed: (_isProcessing || widget.lastAction == 'stop') 
                      ? null 
                      : () => _handleBroadcastControl('stop'),
                    icon: _currentAction == 'stop'
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.pause, size: 20),
                    label: const Text('Arrêter la diffusion'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF6B7280),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                  ),
                  const Gap(16),
                  ElevatedButton.icon(
                    // ✅ Désactivé si : en cours de traitement OU déjà démarré (état du parent)
                    onPressed: (_isProcessing || widget.lastAction == 'start') 
                      ? null 
                      : () => _handleBroadcastControl('start'),
                    icon: _currentAction == 'start'
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.play_arrow, size: 20),
                    label: const Text('Démarrer la diffusion'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.redColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Sections d'informations pour mobile
class _MobileInfoSections extends StatelessWidget {
  const _MobileInfoSections({
    required this.broadcastInfo,
    required this.streamingSettings,
  });
  final BroadcastInfo? broadcastInfo;
  final StreamingSettings? streamingSettings;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _ConnectionInfoCard(streamingSettings: streamingSettings),
        const Gap(16),
        _PublicLinkCard(broadcastInfo: broadcastInfo),
      ],
    );
  }
}

/// Sections d'informations pour desktop
class _DesktopInfoSections extends StatelessWidget {
  const _DesktopInfoSections({
    required this.broadcastInfo,
    required this.streamingSettings,
  });
  final BroadcastInfo? broadcastInfo;
  final StreamingSettings? streamingSettings;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: _ConnectionInfoCard(streamingSettings: streamingSettings)),
        const Gap(16),
        Expanded(child: _PublicLinkCard(broadcastInfo: broadcastInfo)),
      ],
    );
  }
}

/// Carte des informations de connexion DJ (Mixxx)
class _ConnectionInfoCard extends StatelessWidget {
  const _ConnectionInfoCard({required this.streamingSettings});
  final StreamingSettings? streamingSettings;

  @override
  Widget build(BuildContext context) {
    final server = streamingSettings?.serverAddress ?? 'radio.embmission.com';
    final port = streamingSettings?.port.toString() ?? '8005';
    final mountPoint = streamingSettings?.mountPoint ?? '/';
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
                Icon(Icons.wifi_tethering, color: AppTheme.blueColor, size: 20),
                const Gap(8),
                const Text(
                  'Informations de Connexion',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF111827),
                  ),
                ),
              ],
            ),
            const Gap(16),
            _InfoRow(label: 'Serveur:', value: server),
            const Gap(12),
            _InfoRow(label: 'Port:', value: port),
            const Gap(12),
            _InfoRow(label: 'Point de montage:', value: mountPoint),
            const Gap(12),
            _InfoRow(
              label: 'Nom d\'utilisateur:', 
              value: streamingSettings?.username ?? 'dj_mixxx',
            ),
            const Gap(12),
            _InfoRow(
              label: 'Mot de passe:', 
              value: '••••••••••••••',
              showCopyButton: true,
              copyValue: streamingSettings?.password,
            ),
          ],
        ),
      ),
    );
  }
}

/// Carte du lien d'écoute public
class _PublicLinkCard extends StatelessWidget {
  const _PublicLinkCard({required this.broadcastInfo});
  final BroadcastInfo? broadcastInfo;

  void _copyToClipboard(BuildContext context, String text) {
    Clipboard.setData(ClipboardData(text: text));
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Lien copié !'),
        backgroundColor: Colors.green,
        duration: Duration(seconds: 2),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final publicUrl = broadcastInfo?.m3uUrl ?? 'https://radio.emb-mission.com/stream.m3u';
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
                Icon(Icons.link, color: AppTheme.blueColor, size: 20),
                const Gap(8),
                const Text(
                  'Lien d\'écoute public',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF111827),
                  ),
                ),
              ],
            ),
            const Gap(16),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFFF3F4F6),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      publicUrl,
                      style: const TextStyle(
                        fontSize: 14,
                        color: Color(0xFF111827),
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  IconButton(
                    onPressed: () => _copyToClipboard(context, publicUrl),
                    icon: const Icon(Icons.copy, size: 16),
                    style: IconButton.styleFrom(
                      backgroundColor: const Color(0xFFE5E7EB),
                      foregroundColor: const Color(0xFF6B7280),
                      padding: const EdgeInsets.all(8),
                    ),
                    tooltip: 'Copier le lien',
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Ligne d'information
class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.label,
    required this.value,
    this.showCopyButton = false,
    this.copyValue,
  });

  final String label;
  final String value;
  final bool showCopyButton;
  final String? copyValue;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        SizedBox(
          width: 140,
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
          ),
        ),
        if (showCopyButton)
          IconButton(
            onPressed: () {
              Clipboard.setData(ClipboardData(text: copyValue ?? value));
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Copié dans le presse-papiers'),
                  backgroundColor: Colors.green,
                  duration: Duration(seconds: 2),
                ),
              );
            },
            icon: const Icon(Icons.copy, size: 16),
            tooltip: 'Copier',
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(),
          ),
      ],
    );
  }
}
