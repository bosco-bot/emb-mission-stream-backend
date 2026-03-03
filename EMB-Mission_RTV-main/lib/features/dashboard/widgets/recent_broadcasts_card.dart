import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;

class RecentBroadcastsCard extends StatefulWidget {
  const RecentBroadcastsCard({super.key});

  @override
  State<RecentBroadcastsCard> createState() => _RecentBroadcastsCardState();
}

class _RecentBroadcastsCardState extends State<RecentBroadcastsCard> {
  List<_Recent> _items = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadRecentBroadcasts();
  }

  /// Charge les diffusions récentes depuis l'API
  Future<void> _loadRecentBroadcasts() async {
    try {
      final response = await http.get(
        Uri.parse('https://tv.embmission.com/api/webtv/recent-broadcasts?limit=3'),
        headers: {'Content-Type': 'application/json'},
      );
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true && data['data'] != null) {
          final broadcasts = data['data'] as List;
          
          final items = broadcasts.map((broadcast) {
            final title = broadcast['title'] ?? 'Diffusion sans titre';
            final startTime = broadcast['start_time'] ?? '';
            final status = broadcast['status'] ?? 'finished';
            
            // Formater la date
            final timeAgo = _formatTimeAgo(startTime);
            
            // Déterminer l'icône et la couleur selon le statut
            final iconAndColor = _getIconAndColor(status);
            
            return _Recent(
              title,
              timeAgo,
              iconAndColor['icon'] as IconData,
              iconAndColor['color'] as Color,
            );
          }).toList();
          
          if (mounted) {
            setState(() {
              _items = items;
              _isLoading = false;
            });
          }
          return;
        }
      }
    } catch (e) {
      print('Erreur chargement diffusions récentes: $e');
    }
    
    if (mounted) {
      setState(() {
        _items = [];
        _isLoading = false;
      });
    }
  }

  /// Formate la date en "Il y a X jours/heures"
  String _formatTimeAgo(String dateString) {
    // Vérifier si la date est vide
    if (dateString.isEmpty) {
      return 'Date inconnue';
    }
    
    try {
      DateTime date;
      
      // Essayer différents formats de date
      try {
        // Format ISO standard
        date = DateTime.parse(dateString);
      } catch (e) {
        // Essayer le format avec timezone
        if (dateString.contains('+') || dateString.contains('Z')) {
          date = DateTime.parse(dateString.replaceAll('Z', '').replaceAll(RegExp(r'[+-]\d{2}:\d{2}$'), ''));
        } else {
          // Essayer d'autres formats courants
          final cleanDate = dateString.replaceAll(RegExp(r'[^\d\-T: ]'), '');
          date = DateTime.parse(cleanDate);
        }
      }
      
      final now = DateTime.now();
      final difference = now.difference(date);
      
      if (difference.inDays > 0) {
        return 'Il y a ${difference.inDays} jour${difference.inDays > 1 ? 's' : ''}';
      } else if (difference.inHours > 0) {
        return 'Il y a ${difference.inHours} heure${difference.inHours > 1 ? 's' : ''}';
      } else if (difference.inMinutes > 0) {
        return 'Il y a ${difference.inMinutes} minute${difference.inMinutes > 1 ? 's' : ''}';
      } else {
        return 'À l\'instant';
      }
    } catch (e) {
      // En dernier recours, afficher la date brute si possible
      print('Erreur parsing date: $dateString - $e');
      return dateString.isNotEmpty ? dateString : 'Date inconnue';
    }
  }

  /// Détermine l'icône et la couleur selon le statut
  Map<String, dynamic> _getIconAndColor(String status) {
    switch (status.toLowerCase()) {
      case 'live':
        return {
          'icon': Icons.stop_circle,
          'color': const Color(0xFFEF4444),
        };
      case 'finished':
      case 'completed':
      case 'offline':  // 🔥 Nouveau cas ajouté
        return {
          'icon': Icons.smart_display,
          'color': const Color(0xFF60A5FA),
        };
      case 'scheduled':
        return {
          'icon': Icons.calendar_today,
          'color': const Color(0xFFF59E0B),
        };
      default:
        return {
          'icon': Icons.smart_display,  // Changé de radio à smart_display
          'color': const Color(0xFF60A5FA),
        };
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
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
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.history, color: Color(0xFF38BDF8)),
              Gap(8),
              Text(
                'Diffusions récentes',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
            ],
          ),
          const Gap(12),
          if (_isLoading)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(20),
                child: CircularProgressIndicator(),
              ),
            )
          else if (_items.isEmpty)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(20),
                child: Text(
                  'Aucune diffusion récente',
                  style: TextStyle(color: Color(0xFF64748B)),
                ),
              ),
            )
          else
            for (final it in _items) ...[
              _RecentTile(item: it),
              if (it != _items.last) const Divider(height: 20),
            ],
        ],
      ),
    );
  }
}

class _Recent {
  const _Recent(this.title, this.subtitle, this.icon, this.color);
  final String title;
  final String subtitle;
  final IconData icon;
  final Color color;
}

class _RecentTile extends StatelessWidget {
  const _RecentTile({required this.item});
  final _Recent item;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: item.color.withValues(alpha: 0.15),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(item.icon, color: item.color),
        ),
        const Gap(12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.title,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              const Gap(4),
              Text(
                item.subtitle,
                style: const TextStyle(color: Color(0xFF64748B)),
              ),
            ],
          ),
        ),
        const Icon(Icons.chevron_right, color: Color(0xFF94A3B8)),
      ],
    );
  }
}
