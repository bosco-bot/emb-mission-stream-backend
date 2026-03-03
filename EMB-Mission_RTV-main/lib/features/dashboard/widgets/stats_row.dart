import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/utils/responsive_utils.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;

/// Row (or wrapped grid) of KPI statistic cards on the dashboard.
class StatsRow extends StatefulWidget {
  const StatsRow({super.key});

  @override
  State<StatsRow> createState() => _StatsRowState();
}

class _StatsRowState extends State<StatsRow> {
  // État initial des données (chargement)
  Map<String, String> _stats = {
    'live_audience': '...',
    'total_views': '...',
    'broadcast_duration': '...',
    'engagement': '...',
  };
  
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadStats();
    _startPeriodicRefresh();
  }

  @override
  void dispose() {
    // Nettoyer le timer si nécessaire (futur.delayed n'a pas besoin de nettoyage explicite)
    super.dispose();
  }

  /// Charge toutes les statistiques depuis l'API unifiée
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
          
          if (mounted) {
            setState(() {
              // Audience live : data.live_audience.total.audience
              final liveAudienceData = statsData['live_audience'];
              final liveAudience = liveAudienceData is Map && liveAudienceData['total'] != null
                  ? (liveAudienceData['total']['audience'] ?? 0)
                  : 0;
              _stats['live_audience'] = _formatNumber(liveAudience);
              
              // Vues totales : data.total_views.total
              final totalViewsData = statsData['total_views'];
              final totalViews = totalViewsData is Map
                  ? (totalViewsData['total'] ?? 0)
                  : 0;
              _stats['total_views'] = _formatNumber(totalViews);
              
              // Durée de diffusion : data.broadcast_duration.total
              String duration = '0h 0m';
              final broadcastDurationData = statsData['broadcast_duration'];
              if (broadcastDurationData is Map && broadcastDurationData['total'] != null) {
                final totalData = broadcastDurationData['total'];
                // Utiliser 'formatted' si disponible, sinon construire depuis hours/minutes
                if (totalData['formatted'] != null) {
                  duration = totalData['formatted'].toString();
                } else {
                  final hours = totalData['hours'] ?? 0;
                  final minutes = totalData['minutes'] ?? 0;
                  if (hours == 0) {
                    duration = '${minutes}m';
                  } else {
                    duration = '${hours}h ${minutes}m';
                  }
                }
              }
              _stats['broadcast_duration'] = duration;
              
              // Engagement : data.engagement.total
              final engagementData = statsData['engagement'];
              final engagement = engagementData is Map
                  ? (engagementData['total'] ?? 0)
                  : 0;
              _stats['engagement'] = _formatNumber(engagement);
              
              if (!silent) {
                _isLoading = false;
              }
            });
          }
          return;
        }
      }
      
      // Si erreur, utiliser les valeurs par défaut uniquement au premier chargement
      if (!silent && mounted) {
        setState(() {
          _stats['live_audience'] = '0';
          _stats['total_views'] = '0';
          _stats['broadcast_duration'] = '0h 0m';
          _stats['engagement'] = '0';
          _isLoading = false;
        });
      }
    } catch (e) {
      print('Erreur chargement stats unifiées: $e');
      // En mode silencieux, on garde les valeurs existantes en cas d'erreur
      if (!silent && mounted) {
        setState(() {
          _stats['live_audience'] = '0';
          _stats['total_views'] = '0';
          _stats['broadcast_duration'] = '0h 0m';
          _stats['engagement'] = '0';
          _isLoading = false;
        });
      }
    }
  }

  /// Démarre le rafraîchissement périodique des statistiques
  void _startPeriodicRefresh() {
    // Rafraîchir les données toutes les 30 secondes pour avoir des stats à jour
    // sans surcharger l'API (les stats changent moins souvent que l'audience live)
    Future.delayed(const Duration(seconds: 30), () {
      if (mounted) {
        _loadStats(silent: true); // Refresh silencieux (pas de loader, garde les valeurs existantes si erreur)
        _startPeriodicRefresh(); // Réenclencher le timer
      }
    });
  }

  /// Formate un nombre avec k/M
  String _formatNumber(dynamic value) {
    if (value is String) value = int.tryParse(value) ?? 0;
    if (value is! int) value = 0;
    
    if (value >= 1000000) {
      return '${(value / 1000000).toStringAsFixed(1)}M';
    } else if (value >= 1000) {
      return '${(value / 1000).toStringAsFixed(1)}k';
    }
    return value.toString();
  }

  @override
  Widget build(BuildContext context) {
    final isMobile = ResponsiveUtils.isMobile(context);
    final isTablet = ResponsiveUtils.isTablet(context);
    final spacing = ResponsiveUtils.getSpacing(context);
    
    final items = [
      _StatData(
        icon: Icons.groups, 
        label: 'Audience live', 
        value: _stats['live_audience']!,
        color: const Color(0xFFDC2626),
      ),
      _StatData(
        icon: Icons.remove_red_eye,
        label: 'Vues totales (30j)',
        value: _stats['total_views']!,
        color: const Color(0xFF3B82F6),
      ),
      _StatData(
        icon: Icons.timer,
        label: 'Durée diffusion (30j)',
        value: _stats['broadcast_duration']!,
        color: const Color(0xFF10B981),
      ),
      _StatData(
        icon: Icons.favorite, 
        label: 'Engagement', 
        value: _stats['engagement']!,
        color: const Color(0xFF8B5CF6),
      ),
    ];

    // Mobile : 2 colonnes
    if (isMobile) {
      return Wrap(
        spacing: spacing,
        runSpacing: spacing,
        children: items
            .map((e) => _StatCard(
              data: e, 
              width: (MediaQuery.sizeOf(context).width - spacing * 3) / 2,
            ))
            .toList(),
      );
    }

    // Tablet : 2 colonnes
    if (isTablet) {
      return Wrap(
        spacing: spacing,
        runSpacing: spacing,
        children: items
            .map((e) => _StatCard(
              data: e, 
              width: (MediaQuery.sizeOf(context).width - spacing * 3) / 2,
            ))
            .toList(),
      );
    }

    // Desktop : 4 colonnes en ligne
    return Row(
      children: [
        for (final d in items) ...[
          Expanded(child: _StatCard(data: d)),
          if (d != items.last) Gap(spacing),
        ],
      ],
    );
  }
}

class _StatData {
  const _StatData({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });
  final IconData icon;
  final String label;
  final String value;
  final Color color;
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.data, this.width});
  final _StatData data;
  final double? width;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isMobile = ResponsiveUtils.isMobile(context);
    final isTablet = ResponsiveUtils.isTablet(context);
    
    return Container(
      width: width,
      padding: EdgeInsets.symmetric(
        horizontal: isMobile ? 12 : 16,
        vertical: isMobile ? 14 : 18,
      ),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: const [
          BoxShadow(
            color: Color(0x14000000),
            blurRadius: 12,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: EdgeInsets.all(isMobile ? 8 : 10),
            decoration: BoxDecoration(
              color: data.color.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              data.icon, 
              color: data.color,
              size: isMobile ? 20 : 24,
            ),
          ),
          Gap(isMobile ? 12 : 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  data.value,
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: data.color,
                    fontSize: isMobile ? 18 : 20,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const Gap(4),
                Text(
                  data.label,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: const Color(0xFF64748B),
                    fontSize: isMobile ? 12 : 14,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

