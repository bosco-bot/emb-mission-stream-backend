import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/utils/country_utils.dart';
import 'package:emb_mission_dashboard/core/utils/responsive_utils.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;

/// Page Analytics avec statistiques géographiques
class AnalyticsPage extends StatefulWidget {
  const AnalyticsPage({super.key});

  @override
  State<AnalyticsPage> createState() => _AnalyticsPageState();
}

class _AnalyticsPageState extends State<AnalyticsPage> {
  bool _isLoading = true;
  Map<String, int> _webRadioStats = {};
  Map<String, int> _webTvStats = {};
  String _errorMessage = '';

  @override
  void initState() {
    super.initState();
    _loadStats();
    _startPeriodicRefresh();
  }

  @override
  void dispose() {
    // Le Future.delayed se nettoie automatiquement quand le widget est démonté
    super.dispose();
  }

  /// Charge les statistiques depuis l'API unifiée
  /// [silent] : si true, ne change pas l'état de chargement (pour refresh périodique)
  Future<void> _loadStats({bool silent = false}) async {
    try {
      final response = await http.get(
        Uri.parse('https://rtv.embmission.com/api/webtv/stats/all'),
        headers: {'Content-Type': 'application/json'},
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        
        if (data['success'] == true && data['data'] != null) {
          final statsData = data['data'];
          final ga4Stats = statsData['ga4_stats'];
          
          if (ga4Stats != null) {
            // Récupérer les stats WebRadio par pays
            if (ga4Stats['webradio'] != null) {
              final webRadioData = ga4Stats['webradio'];
              final byCountry = webRadioData['by_country'];
              if (byCountry != null && byCountry is Map) {
                _webRadioStats = Map<String, int>.from(byCountry);
              }
            }
            
            // Récupérer les stats WebTV par pays
            if (ga4Stats['webtv'] != null) {
              final webTvData = ga4Stats['webtv'];
              final byCountry = webTvData['by_country'];
              if (byCountry != null && byCountry is Map) {
                _webTvStats = Map<String, int>.from(byCountry);
              }
            }
          }
          
          if (mounted) {
            setState(() {
              if (!silent) {
                _isLoading = false;
              }
              // Réinitialiser le message d'erreur si on a des données
              if (_errorMessage.isNotEmpty) {
                _errorMessage = '';
              }
            });
          }
          return;
        }
      }
      
      // Si erreur, seulement mettre à jour en mode non-silencieux
      if (!silent && mounted) {
        setState(() {
          _errorMessage = 'Erreur lors du chargement des données';
          _isLoading = false;
        });
      }
    } catch (e) {
      print('Erreur chargement analytics: $e');
      // En mode silencieux, on garde les valeurs existantes en cas d'erreur
      if (!silent && mounted) {
        setState(() {
          _errorMessage = 'Erreur de connexion';
          _isLoading = false;
        });
      }
    }
  }

  /// Démarre le rafraîchissement périodique des statistiques
  void _startPeriodicRefresh() {
    // Rafraîchir les données toutes les 30 secondes pour avoir des stats géographiques à jour
    // sans surcharger l'API (les stats par pays changent moins souvent)
    Future.delayed(const Duration(seconds: 30), () {
      if (mounted) {
        _loadStats(silent: true); // Refresh silencieux (pas de loader, garde les valeurs existantes si erreur)
        _startPeriodicRefresh(); // Réenclencher le timer
      }
    });
  }

  /// Formate un nombre avec séparateur de milliers
  String _formatNumber(int value) {
    return value.toString().replaceAllMapped(
      RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
      (Match m) => '${m[1]},',
    );
  }

  @override
  Widget build(BuildContext context) {
    final spacing = ResponsiveUtils.getSpacing(context);
    
    return Scaffold(
      appBar: AppBar(
        title: const Text('Analytics Géographiques'),
        backgroundColor: Colors.transparent,
        foregroundColor: const Color(0xFF111827),
        elevation: 0,
      ),
      body: Container(
        width: double.infinity,
        height: double.infinity,
        decoration: const BoxDecoration(
          image: DecorationImage(
            image: AssetImage('assets/worldmap.jpg'),
            fit: BoxFit.cover,
          ),
        ),
        child: Container(
          width: double.infinity,
          height: double.infinity,
          // Overlay semi-transparent pour la lisibilité
          color: Colors.black.withOpacity(0.3),
          child: _isLoading
              ? const Center(child: CircularProgressIndicator())
              : _errorMessage.isNotEmpty
                  ? Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.error_outline, size: 60, color: Colors.red[300]),
                          const Gap(16),
                          Text(
                            _errorMessage,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              shadows: [
                                Shadow(
                                  offset: Offset(1, 1),
                                  blurRadius: 3,
                                  color: Colors.black54,
                                ),
                              ],
                            ),
                          ),
                          const Gap(16),
                          ElevatedButton(
                            onPressed: _loadStats,
                            child: const Text('Réessayer'),
                          ),
                        ],
                      ),
                    )
                  : SingleChildScrollView(
                      padding: EdgeInsets.all(spacing),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Titre
                          Container(
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.3),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.analytics, size: 32, color: Color(0xFF111827)),
                                const Gap(12),
                                const Expanded(
                                  child: Text(
                                    'Répartition Géographique des Audiences',
                                    style: TextStyle(
                                      fontSize: 24,
                                      fontWeight: FontWeight.bold,
                                      color: Color(0xFF111827),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const Gap(20),
                          
                          // Section WebRadio
                          _buildSection(
                            title: 'WebRadio - Auditeurs par Pays',
                            icon: Icons.radio,
                            color: Colors.blue,
                            stats: _webRadioStats,
                            isEmpty: _webRadioStats.isEmpty,
                          ),
                          const Gap(20),
                          
                          // Section WebTV
                          _buildSection(
                            title: 'WebTV - Spectateurs par Pays',
                            icon: Icons.tv,
                            color: Colors.red,
                            stats: _webTvStats,
                            isEmpty: _webTvStats.isEmpty,
                          ),
                        ],
                      ),
                    ),
        ),
      ),
    );
  }

  /// Construit une section d'affichage des stats par pays
  Widget _buildSection({
    required String title,
    required IconData icon,
    required Color color,
    required Map<String, int> stats,
    required bool isEmpty,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.3),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // En-tête de section
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: color.withOpacity(0.1),
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(12),
                topRight: Radius.circular(12),
              ),
            ),
            child: Row(
              children: [
                Icon(icon, color: color, size: 28),
                const Gap(12),
                Expanded(
                  child: Text(
                    title,
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: color,
                      shadows: const [
                        Shadow(
                          offset: Offset(1, 1),
                          blurRadius: 2,
                          color: Colors.white70,
                        ),
                      ],
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: color,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    isEmpty ? '0' : _formatNumber(stats.values.reduce((a, b) => a + b)),
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                  ),
                ),
              ],
            ),
          ),
          
          // Liste des pays
          if (isEmpty)
            const Padding(
              padding: EdgeInsets.all(32.0),
              child: Center(
                child: Text(
                  'Aucune donnée disponible',
                  style: TextStyle(
                    fontSize: 16,
                    color: Colors.white70,
                    fontStyle: FontStyle.italic,
                    shadows: const [
                      Shadow(
                        offset: Offset(1, 1),
                        blurRadius: 2,
                        color: Colors.black54,
                      ),
                    ],
                  ),
                ),
              ),
            )
          else
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              child: Column(
                children: (() {
                  final sortedEntries = stats.entries.toList()..sort((a, b) => b.value.compareTo(a.value));
                  return sortedEntries.map((MapEntry<String, int> entry) {
                    final countryName = entry.key;
                    final count = entry.value;
                    final countryCode = CountryUtils.countryNameToIsoCode(countryName);
                    
                    return Container(
                        margin: const EdgeInsets.only(bottom: 8),
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: color.withOpacity(0.05),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: color.withOpacity(0.2)),
                        ),
                        child: Row(
                          children: [
                            // Drapeau du pays
                            if (countryCode != 'unknown')
                              ClipRRect(
                                borderRadius: BorderRadius.circular(4),
                                child: Image.network(
                                  'https://flagcdn.com/24x18/${countryCode}.png',
                                  width: 36,
                                  height: 24,
                                  fit: BoxFit.cover,
                                  errorBuilder: (context, error, stackTrace) {
                                    return Container(
                                      width: 36,
                                      height: 24,
                                      decoration: BoxDecoration(
                                        color: Colors.grey[300],
                                        borderRadius: BorderRadius.circular(4),
                                        border: Border.all(color: Colors.grey[400]!),
                                      ),
                                      child: Center(
                                        child: Text(
                                          countryCode.toUpperCase(),
                                          style: const TextStyle(
                                            fontSize: 8,
                                            color: Colors.grey,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                    );
                                  },
                                ),
                              )
                            else
                              Container(
                                width: 36,
                                height: 24,
                                decoration: BoxDecoration(
                                  color: Colors.grey[300],
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: const Icon(Icons.flag, color: Colors.grey, size: 16),
                              ),
                            const Gap(12),
                            
                            // Nom du pays et compteur
                            Expanded(
                              child: Text(
                                countryName,
                                style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w500,
                                  color: Colors.white,
                                  shadows: [
                                    Shadow(
                                      offset: Offset(1, 1),
                                      blurRadius: 2,
                                      color: Colors.black54,
                                    ),
                                  ],
                                ),
                              ),
                            ),
                            
                            // Badge avec le nombre
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: color,
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: Text(
                                _formatNumber(count),
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                          ],
                        ),
                      );
                    }).toList();
                })(),
              ),
            ),
        ],
      ),
    );
  }
}
