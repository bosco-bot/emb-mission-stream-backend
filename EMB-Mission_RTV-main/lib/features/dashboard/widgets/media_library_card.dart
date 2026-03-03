import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;

/// Médiathèque summary card with count and CTA.
class MediaLibraryCard extends StatefulWidget {
  const MediaLibraryCard({super.key});

  @override
  State<MediaLibraryCard> createState() => _MediaLibraryCardState();
}

class _MediaLibraryCardState extends State<MediaLibraryCard> {
  String _fileCount = '...';
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadStats();
  }

  /// Charge les statistiques de la médiathèque
  Future<void> _loadStats() async {
    try {
      final response = await http.get(
        Uri.parse('https://tv.embmission.com/api/media/library/stats'),
        headers: {'Content-Type': 'application/json'},
      );
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true && data['data'] != null) {
          final totalFiles = data['data']['total_files'] ?? 0;
          
          if (mounted) {
            setState(() {
              _fileCount = totalFiles.toString();
              _isLoading = false;
            });
          }
          return;
        }
      }
    } catch (e) {
      print('Erreur chargement stats médiathèque: $e');
    }
    
    if (mounted) {
      setState(() {
        _fileCount = '0';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
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
              Icon(Icons.perm_media, color: Color(0xFF38BDF8)),
              Gap(8),
              Text(
                'Médiathèque',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
            ],
          ),
          const Gap(14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                _fileCount,
                style: theme.textTheme.displaySmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const Gap(8),
              Text(
                'fichiers stockés',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: const Color(0xFF64748B),
                ),
              ),
            ],
          ),
          const Gap(10),
          Text(
            'Gérez vos vidéos, audios et images pour vos diffusions.',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: const Color(0xFF64748B),
            ),
          ),
          const Gap(16),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton(
              onPressed: () => context.go('/contents'),
              style: OutlinedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 14),
                side: const BorderSide(color: Color(0xFFE2E8F0)),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
                backgroundColor: Colors.white,
              ),
              child: const Text(
                'Ouvrir la médiathèque',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
