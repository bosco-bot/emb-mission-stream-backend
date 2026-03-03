import 'package:flutter/material.dart';
import '../../../core/services/webradio_service.dart';
import '../../../core/services/azuracast_service.dart';
import '../../../core/shared/theme/app_theme.dart';
import '../../../core/utils/responsive_utils.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;

class DiffusionlivePage extends StatefulWidget {
  const DiffusionlivePage({super.key});

  @override
  State<DiffusionlivePage> createState() => _DiffusionlivePageState();
}

class _DiffusionlivePageState extends State<DiffusionlivePage> {
  String _currentTitle = 'Aucun titre';
  String _currentArtist = 'Aucun artiste';
  int _listenersCount = 0;
  int _maxListeners = 0;
  Duration _sessionDuration = Duration.zero;
  bool _isLive = false;
  
  @override
  void initState() {
    super.initState();
    _loadData();
    _startTimer();
    _startPeriodicRefresh();
  }

  Future<void> _loadData() async {
    try {
      // Charger les informations actuelles depuis AzuraCast
      final nowPlaying = await AzuraCastService.instance.getNowPlaying();
      
      // Charger le nombre d'auditeurs depuis l'API unifiée (WebRadio uniquement)
      int webRadioAudience = 0;
      try {
        final statsResponse = await http.get(
          Uri.parse('https://rtv.embmission.com/api/webtv/stats/all'),
          headers: {'Content-Type': 'application/json'},
        );
        
        if (statsResponse.statusCode == 200) {
          final statsData = jsonDecode(statsResponse.body);
          if (statsData['success'] == true && statsData['data'] != null) {
            final liveAudienceData = statsData['data']['live_audience'];
            if (liveAudienceData != null && liveAudienceData['webradio'] != null) {
              webRadioAudience = liveAudienceData['webradio']['audience'] ?? 0;
            }
          }
        }
      } catch (e) {
        // En cas d'erreur avec l'API unifiée, utiliser la valeur AzuraCast comme fallback
        print('Erreur chargement audience unifiée: $e');
        webRadioAudience = nowPlaying.listeners.total;
      }
      
      if (mounted) {
        setState(() {
          _currentTitle = nowPlaying.nowPlaying.song.title;
          _currentArtist = nowPlaying.nowPlaying.song.artist;
          _listenersCount = webRadioAudience; // Utiliser l'API unifiée pour l'audience WebRadio
          _isLive = nowPlaying.live.isLive;
        });
      }
    } catch (e) {
      // Erreur lors du chargement des données (silencieuse)
      // On garde les anciennes données en cas d'erreur
      print('Erreur lors du chargement: $e');
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
                        Icon(Icons.radio, color: AppTheme.blueColor, size: 24),
                        const SizedBox(width: 8),
                        Text(
                          'Diffusion Live',
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: const Color(0xFF0F172A),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    // Badge LIVE / OFFLINE
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: _isLive ? const Color(0xFFEF4444) : const Color(0xFF6B7280),
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
                            _isLive ? 'LIVE' : 'OFFLINE',
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
                    Icon(Icons.radio, color: AppTheme.blueColor, size: 32),
                    const SizedBox(width: 12),
                    Text(
                      'Diffusion Live',
                      style: TextStyle(
                        fontSize: 28,
                        fontWeight: FontWeight.bold,
                        color: const Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(width: 16),
                    // Badge LIVE / OFFLINE
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: _isLive ? const Color(0xFFEF4444) : const Color(0xFF6B7280),
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
                            _isLive ? 'LIVE' : 'OFFLINE',
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
                  // 1. Informations de connexion
                  _buildConnectionInfoCard(),
                  
                  // 2. Informations du flux
                  _buildStreamInfoCard(),
                  
                  // 3. Statistiques d'écoute
                  _buildListenersStatsCard(),
                  
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
                Icon(Icons.link, color: AppTheme.blueColor, size: isMobile ? 20 : 24),
                SizedBox(width: isMobile ? 6 : 8),
                Text(
                  'Connexion',
                  style: TextStyle(
                    fontSize: isMobile ? 16 : 18,
                    fontWeight: FontWeight.bold,
                    color: const Color(0xFF0F172A),
                  ),
                ),
              ],
            ),
            SizedBox(height: isMobile ? 8 : 12),
            _buildInfoRow('Hôte', 'radio.embmission.com'),
            _buildInfoRow('Port', '8005'),
            _buildInfoRow('Mount Point', '/'),
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
                Icon(Icons.music_note, color: AppTheme.blueColor, size: isMobile ? 20 : 24),
                SizedBox(width: isMobile ? 6 : 8),
                Text(
                  'Flux Audio',
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
            _buildInfoRow('Artiste', _currentArtist),
            _buildInfoRow('Qualité', '192 kbps'),
            _buildInfoRow('Format', 'MP3'),
          ],
        ),
      ),
    );
  }

  Widget _buildListenersStatsCard() {
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
                Icon(Icons.people, color: AppTheme.blueColor, size: isMobile ? 20 : 24),
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
            _buildInfoRow('Auditeurs', _listenersCount.toString()),
            _buildInfoRow('Pic', _maxListeners.toString()),
            _buildInfoRow('Total', _formatDuration(_sessionDuration)),
            SizedBox(height: isMobile ? 6 : 8),
            LinearProgressIndicator(
              value: _maxListeners > 0 ? _listenersCount / _maxListeners : 0,
              backgroundColor: Colors.grey[300],
              valueColor: AlwaysStoppedAnimation<Color>(AppTheme.blueColor),
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
                Icon(Icons.settings, color: AppTheme.blueColor, size: isMobile ? 20 : 24),
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
                        color: AppTheme.blueColor,
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
                    'Pour démarrer votre diffusion audio en direct, veuillez connecter votre logiciel de mixage (Mixxx, Virtual DJ, etc.) en utilisant les paramètres de connexion affichés ci-dessus.',
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
