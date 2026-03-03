import 'package:flutter/material.dart';
import '../../../core/services/webtv_service.dart';
import '../../../core/models/webtv_models.dart';
import '../../../core/shared/theme/app_theme.dart';
import '../../../core/utils/responsive_utils.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;

class WebTvDiffusionLivePage extends StatefulWidget {
  const WebTvDiffusionLivePage({super.key});

  @override
  State<WebTvDiffusionLivePage> createState() => _WebTvDiffusionLivePageState();
}

class _WebTvDiffusionLivePageState extends State<WebTvDiffusionLivePage> {
  String _currentTitle = 'Aucun titre';
  String _currentStreamName = 'Aucune diffusion';
  int _viewersCount = 0;
  int _maxViewers = 0;
  Duration _sessionDuration = Duration.zero;
  bool _isLive = false;
  bool _isPaused = false;
  Map<String, dynamic>? _obsParams;
  
  @override
  void initState() {
    super.initState();
    _loadData();
    _startTimer();
    _startPeriodicRefresh();
  }

  Future<void> _loadData() async {
    try {
      // Charger les informations actuelles depuis les APIs WebTV
      final futures = await Future.wait([
        WebTvService.instance.getAutoPlaylistStatus(),
        WebTvService.instance.getAutoPlaylistCurrentUrl(),
        WebTvService.instance.getObsParams(),
      ]);

      final status = futures[0] as Map<String, dynamic>;
      final currentUrl = futures[1] as Map<String, dynamic>;
      final obsParams = futures[2] as WebTvObsParams;

      // Charger le nombre de spectateurs depuis l'API unifiée (WebTV uniquement)
      int webTvAudience = 0;
      try {
        final statsResponse = await http.get(
          Uri.parse('https://rtv.embmission.com/api/webtv/stats/all'),
          headers: {'Content-Type': 'application/json'},
        );
        
        print('🔍 Debug API stats/all - Status: ${statsResponse.statusCode}');
        
        if (statsResponse.statusCode == 200) {
          final statsData = jsonDecode(statsResponse.body);
          print('📊 Réponse API: $statsData');
          
          if (statsData['success'] == true && statsData['data'] != null) {
            final liveAudienceData = statsData['data']['live_audience'];
            print('👥 live_audience data: $liveAudienceData');
            
            if (liveAudienceData != null && liveAudienceData['webtv'] != null) {
              final webtvData = liveAudienceData['webtv'];
              print('📺 webtv data: $webtvData');
              
              final audience = webtvData['audience'];
              print('🎯 Audience value: $audience (type: ${audience.runtimeType})');
              
              // Conversion sécurisée vers int
              if (audience is int) {
                webTvAudience = audience;
              } else if (audience is num) {
                webTvAudience = audience.toInt();
              } else {
                webTvAudience = 0;
              }
              
              print('✅ Audience finale: $webTvAudience');
            } else {
              print('⚠️ webtv data is null');
            }
          } else {
            print('⚠️ API response success is false or data is null');
          }
        } else {
          print('❌ API returned status code: ${statsResponse.statusCode}');
          print('Response body: ${statsResponse.body}');
        }
      } catch (e, stackTrace) {
        // En cas d'erreur avec l'API unifiée, utiliser 0 comme fallback
        print('❌ Erreur chargement audience unifiée: $e');
        print('Stack trace: $stackTrace');
        webTvAudience = 0;
      }

      if (mounted) {
        setState(() {
          _isLive = status['is_live'] == true;
          _isPaused = status['mode'] == 'paused';
          
          if (_isLive) {
            // live_stream est une String (ID), live_name contient le nom
            _currentStreamName = status['live_name'] ?? status['live_stream'] ?? 'Diffusion en direct';
            _currentTitle = _currentStreamName;
          } else {
            _currentTitle = currentUrl['item_title'] ?? currentUrl['stream_name'] ?? 'Aucun titre';
            _currentStreamName = 'Mode VoD';
          }
          
          // Utiliser l'API unifiée pour le nombre de spectateurs WebTV
          _viewersCount = webTvAudience;
          // Pic non modifié (reste tel quel)
          _maxViewers = 150;
          
          _obsParams = {
            'server_url': obsParams.serverUrl,
            'port': obsParams.port,
            'application': obsParams.application,
            'resolution_recommended': obsParams.resolutionRecommended,
            'bitrate_recommended': obsParams.bitrateRecommended,
          };
        });
      }
    } catch (e) {
      // Erreur lors du chargement des données (silencieuse)
      // On garde les anciennes données en cas d'erreur
      print('Erreur lors du chargement WebTV: $e');
    }
  }

  void _startPeriodicRefresh() {
    // Rafraîchir les données toutes les 10 secondes pour éviter le rate limit
    Future.delayed(const Duration(seconds: 10), () {
      if (mounted) {
        _loadData(); // Refresh silencieux (pas de loader)
        _startPeriodicRefresh();
      }
    });
  }

  void _startTimer() {
    Future.delayed(const Duration(seconds: 1), () {
      if (mounted) {
        setState(() {
          _sessionDuration = Duration(seconds: _sessionDuration.inSeconds + 1);
        });
        _startTimer();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final isMobile = ResponsiveUtils.isMobile(context);
    final isTablet = ResponsiveUtils.isTablet(context);
    
    return Scaffold(
      body: Padding(
        padding: EdgeInsets.all(isMobile ? 12 : 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // En-tête responsive
            isMobile 
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.videocam, color: AppTheme.redColor, size: 24),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            'Diffusion Live WebTV',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                              color: const Color(0xFF0F172A),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    // Badge LIVE / OFFLINE / PAUSE
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: _isPaused 
                            ? const Color(0xFFF59E0B) // Orange pour pause
                            : (_isLive ? const Color(0xFFEF4444) : const Color(0xFF6B7280)),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 8,
                            height: 8,
                            decoration: BoxDecoration(
                              color: Colors.white,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            _isPaused ? 'PAUSE' : (_isLive ? 'LIVE' : 'OFFLINE'),
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                              fontSize: 12,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                )
              : Row(
                  children: [
                    Icon(Icons.videocam, color: AppTheme.redColor, size: 32),
                    const SizedBox(width: 12),
                    Text(
                      'Diffusion Live WebTV',
                      style: TextStyle(
                        fontSize: 28,
                        fontWeight: FontWeight.bold,
                        color: const Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(width: 16),
                    // Badge LIVE / OFFLINE / PAUSE
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: _isPaused 
                            ? const Color(0xFFF59E0B) // Orange pour pause
                            : (_isLive ? const Color(0xFFEF4444) : const Color(0xFF6B7280)),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 8,
                            height: 8,
                            decoration: BoxDecoration(
                              color: Colors.white,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            _isPaused ? 'PAUSE' : (_isLive ? 'LIVE' : 'OFFLINE'),
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                              fontSize: 12,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
            SizedBox(height: isMobile ? 16 : 24),
            
            // Grille responsive
            Expanded(
              child: GridView.count(
                crossAxisCount: isMobile ? 1 : 2,
                crossAxisSpacing: isMobile ? 12 : 16,
                mainAxisSpacing: isMobile ? 12 : 16,
                childAspectRatio: isMobile ? 1.2 : (isTablet ? 2.0 : 2.5),
                children: [
                  // 1. Informations de connexion OBS
                  _buildConnectionInfoCard(),
                  
                  // 2. Informations du flux vidéo
                  _buildStreamInfoCard(),
                  
                  // 3. Statistiques d'audience
                  _buildViewersStatsCard(),
                  
                  // 4. Configuration rapide
                  _buildQuickConfigCard(),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildConnectionInfoCard() {
    final isMobile = ResponsiveUtils.isMobile(context);
    
    return Card(
      elevation: 4,
      color: Colors.white,
      child: Padding(
        padding: EdgeInsets.all(isMobile ? 12 : 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.link, color: AppTheme.redColor, size: isMobile ? 20 : 24),
                SizedBox(width: isMobile ? 6 : 8),
                Text(
                  'Connexion OBS',
                  style: TextStyle(
                    fontSize: isMobile ? 16 : 18,
                    fontWeight: FontWeight.bold,
                    color: const Color(0xFF0F172A),
                  ),
                ),
              ],
            ),
            SizedBox(height: isMobile ? 8 : 12),
            _buildInfoRow('Serveur', _obsParams?['server_url'] ?? 'Chargement...'),
            _buildInfoRow('Port', _obsParams?['port']?.toString() ?? 'Chargement...'),
            _buildInfoRow('Application', _obsParams?['application'] ?? 'Chargement...'),
            _buildInfoRow('Durée', _formatDuration(_sessionDuration)),
          ],
        ),
      ),
    );
  }

  Widget _buildStreamInfoCard() {
    final isMobile = ResponsiveUtils.isMobile(context);
    
    return Card(
      elevation: 4,
      color: Colors.white,
      child: Padding(
        padding: EdgeInsets.all(isMobile ? 12 : 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.videocam, color: AppTheme.redColor, size: isMobile ? 20 : 24),
                SizedBox(width: isMobile ? 6 : 8),
                Text(
                  'Flux Vidéo',
                  style: TextStyle(
                    fontSize: isMobile ? 16 : 18,
                    fontWeight: FontWeight.bold,
                    color: const Color(0xFF0F172A),
                  ),
                ),
              ],
            ),
            SizedBox(height: isMobile ? 8 : 12),
            _buildInfoRow('Titre', _currentTitle),
            _buildInfoRow('Mode', _currentStreamName),
            _buildInfoRow('Qualité', _obsParams?['resolution_recommended'] ?? 'Chargement...'),
            _buildInfoRow('Codec', 'H.264'),
          ],
        ),
      ),
    );
  }

  Widget _buildViewersStatsCard() {
    final isMobile = ResponsiveUtils.isMobile(context);
    
    return Card(
      elevation: 4,
      color: Colors.white,
      child: Padding(
        padding: EdgeInsets.all(isMobile ? 12 : 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.people, color: AppTheme.redColor, size: isMobile ? 20 : 24),
                SizedBox(width: isMobile ? 6 : 8),
                Text(
                  'Audience',
                  style: TextStyle(
                    fontSize: isMobile ? 16 : 18,
                    fontWeight: FontWeight.bold,
                    color: const Color(0xFF0F172A),
                  ),
                ),
              ],
            ),
            SizedBox(height: isMobile ? 8 : 12),
            _buildInfoRow('Spectateurs', _viewersCount.toString()),
            _buildInfoRow('Pic', _maxViewers.toString()),
            _buildInfoRow('Total', _formatDuration(_sessionDuration)),
            SizedBox(height: isMobile ? 6 : 8),
            LinearProgressIndicator(
              value: _maxViewers > 0 ? _viewersCount / _maxViewers : 0,
              backgroundColor: Colors.grey[300],
              valueColor: AlwaysStoppedAnimation<Color>(AppTheme.redColor),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildQuickConfigCard() {
    final isMobile = ResponsiveUtils.isMobile(context);
    
    return Card(
      elevation: 4,
      color: Colors.white,
      child: Padding(
        padding: EdgeInsets.all(isMobile ? 12 : 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.settings, color: AppTheme.redColor, size: isMobile ? 20 : 24),
                SizedBox(width: isMobile ? 6 : 8),
                Text(
                  'Configuration',
                  style: TextStyle(
                    fontSize: isMobile ? 16 : 18,
                    fontWeight: FontWeight.bold,
                    color: const Color(0xFF0F172A),
                  ),
                ),
              ],
            ),
            SizedBox(height: isMobile ? 12 : 16),
            // Message d'instruction professionnel
            Container(
              padding: EdgeInsets.all(isMobile ? 12 : 18),
              decoration: BoxDecoration(
                color: const Color(0xFFF9FAFB),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: const Color(0xFFE5E7EB),
                  width: 1,
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(
                        Icons.info_outline,
                        color: AppTheme.redColor,
                        size: isMobile ? 18 : 22,
                      ),
                      SizedBox(width: isMobile ? 8 : 10),
                      Flexible(
                        child: Text(
                          'Source externe',
                          style: TextStyle(
                            fontWeight: FontWeight.w600,
                            fontSize: isMobile ? 14 : 16,
                            color: const Color(0xFF0F172A),
                          ),
                        ),
                      ),
                    ],
                  ),
                  SizedBox(height: isMobile ? 8 : 12),
                  Text(
                    'Pour démarrer votre diffusion vidéo en direct, veuillez connecter OBS Studio en utilisant les paramètres de connexion affichés ci-dessus. Configurez votre résolution et bitrate selon les recommandations.',
                    style: TextStyle(
                      fontSize: isMobile ? 13 : 15,
                      color: const Color(0xFF64748B),
                      height: 1.6,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    final isMobile = ResponsiveUtils.isMobile(context);
    
    return Padding(
      padding: EdgeInsets.symmetric(vertical: isMobile ? 1.5 : 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: isMobile ? 60 : 80,
            child: Text(
              '$label:',
              style: TextStyle(
                fontWeight: FontWeight.w500,
                fontSize: isMobile ? 13 : 14,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                color: Colors.grey,
                fontSize: isMobile ? 13 : 14,
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _formatDuration(Duration duration) {
    final hours = duration.inHours;
    final minutes = duration.inMinutes.remainder(60);
    final seconds = duration.inSeconds.remainder(60);
    
    if (hours > 0) {
      return '${hours}h ${minutes}m ${seconds}s';
    } else if (minutes > 0) {
      return '${minutes}m ${seconds}s';
    } else {
      return '${seconds}s';
    }
  }
}
