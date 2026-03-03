import 'package:emb_mission_dashboard/core/services/azuracast_service.dart';
import 'package:emb_mission_dashboard/core/services/webtv_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/features/dashboard/widgets/media_library_card.dart';
import 'package:emb_mission_dashboard/features/dashboard/widgets/recent_broadcasts_card.dart';
import 'package:emb_mission_dashboard/features/dashboard/widgets/service_card.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

/// The main three-column grid: WebTV, WebRadio, and the right sidebar.
class MainGrid extends StatefulWidget {
  const MainGrid({super.key});

  @override
  State<MainGrid> createState() => _MainGridState();
}

class _MainGridState extends State<MainGrid> {
  bool _isRadioLive = false;
  bool _isWebTvLive = false;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadStatuses();
    _startPeriodicRefresh();
  }

  void _startPeriodicRefresh() {
    Future.delayed(const Duration(seconds: 10), () {
      if (mounted) {
        _loadStatuses();
        _startPeriodicRefresh();
      }
    });
  }

  Future<void> _loadStatuses() async {
    try {
      // Charger les statuts en parallèle
      final results = await Future.wait([
        AzuraCastService.instance.getNowPlaying(),
        WebTvService.instance.getAutoPlaylistStatus(),
      ]);

      final nowPlaying = results[0] as dynamic;
      final webTvStatus = results[1] as Map<String, dynamic>;

      if (mounted) {
        setState(() {
          _isRadioLive = nowPlaying.live.isLive;
          _isWebTvLive = webTvStatus['is_live'] == true;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isRadioLive = false;
          _isWebTvLive = false;
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    // Badges WebTV
    final webTvBadgeText = _isLoading 
        ? 'CHARGEMENT...' 
        : (_isWebTvLive ? 'EN DIRECT' : 'HORS LIGNE');
    final webTvBadgeColor = _isLoading
        ? const Color(0xFF94A3B8)
        : (_isWebTvLive ? AppTheme.redColor : const Color(0xFF94A3B8));
    
    // Badges WebRadio
    final radioBadgeText = _isLoading 
        ? 'CHARGEMENT...' 
        : (_isRadioLive ? 'EN DIRECT' : 'HORS LIGNE');
    final radioBadgeColor = _isLoading
        ? const Color(0xFF94A3B8)
        : (_isRadioLive ? AppTheme.redColor : const Color(0xFF94A3B8));
    return LayoutBuilder(
      builder: (context, constraints) {
        final isWide = constraints.maxWidth >= 1100;
        if (isWide) {
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: ServiceCard(
                  badgeText: webTvBadgeText,
                  badgeColor: webTvBadgeColor,
                  title: 'WebTV',
                  description:
                      '''Votre chaîne WebTV est active. Gérez vos diffusions, playlists et monétisation.''',
                  image: Image.asset(
                    'assets/webtv.jpg',
                    height: 140,
                    width: double.infinity,
                    fit: BoxFit.cover,
                  ),
                  buttonText: 'Gérer la WebTV',
                  onPressed: () => context.go('/webtv'),
                ),
              ),
              const Gap(16),
              Expanded(
                child: ServiceCard(
                  badgeText: radioBadgeText,
                  badgeColor: radioBadgeColor,
                  title: 'WebRadio',
                  description:
                      '''Votre station WebRadio est prête. Configurez vos programmes et lancez le direct.''',
                  image: _networkImage(
                    'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=800&q=80&auto=format&fit=crop',
                  ),
                  buttonText: 'Gérer la WebRadio',
                  onPressed: () => context.go('/webradio'),
                ),
              ),
              const Gap(16),
              const Expanded(
                child: Column(
                  children: [
                    MediaLibraryCard(),
                    Gap(16),
                    RecentBroadcastsCard(),
                  ],
                ),
              ),
            ],
          );
        }

        // Narrow layout: stack vertically
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ServiceCard(
              badgeText: webTvBadgeText,
              badgeColor: webTvBadgeColor,
              title: 'WebTV',
              description:
                  '''Votre chaîne WebTV est active. Gérez vos diffusions, playlists et monétisation.''',
              image: Image.asset(
                'assets/webtv.jpg',
                height: 140,
                width: double.infinity,
                fit: BoxFit.cover,
              ),
              buttonText: 'Gérer la WebTV',
              onPressed: () => context.go('/webtv'),
            ),
            const Gap(16),
            ServiceCard(
              badgeText: radioBadgeText,
              badgeColor: radioBadgeColor,
              title: 'WebRadio',
              description:
                  '''Votre station WebRadio est prête. Configurez vos programmes et lancez le direct.''',
              image: _networkImage(
                'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=800&q=80&auto=format&fit=crop',
              ),
              buttonText: 'Gérer la WebRadio',
              onPressed: () => context.go('/webradio'),
            ),
            const Gap(16),
            const MediaLibraryCard(),
            const Gap(16),
            const RecentBroadcastsCard(),
          ],
        );
      },
    );
  }
}

Image _networkImage(String url) {
  return Image.network(
    url,
    height: 140,
    width: double.infinity,
    fit: BoxFit.cover,
    loadingBuilder: (context, child, progress) {
      if (progress == null) return child;
      return const SizedBox(
        height: 140,
        child: Center(child: CircularProgressIndicator()),
      );
    },
    errorBuilder: (context, error, stackTrace) => const SizedBox(
      height: 140,
      child: Center(child: Icon(Icons.error)),
    ),
  );
}
