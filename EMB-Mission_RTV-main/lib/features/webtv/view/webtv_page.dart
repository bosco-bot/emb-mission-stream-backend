import 'package:emb_mission_dashboard/core/models/webtv_models.dart';
import 'package:emb_mission_dashboard/core/services/webtv_service.dart';
import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:emb_mission_dashboard/features/webtv/widgets/copy_field.dart';
import 'package:emb_mission_dashboard/features/webtv/widgets/player_preview.dart';
import 'package:emb_mission_dashboard/features/webtv/widgets/section_card.dart';
import 'package:emb_mission_dashboard/features/webtv/widgets/social_button.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

class WebtvPage extends StatefulWidget {
  const WebtvPage({super.key});

  @override
  State<WebtvPage> createState() => _WebtvPageState();
}

class _WebtvPageState extends State<WebtvPage> {
  WebTvStream? _currentStream;
  bool _isLoading = true;
  String? _errorMessage;
  Map<String, dynamic>? _autoPlaylistStatus;
  Map<String, dynamic>? _autoPlaylistCurrentUrl;

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
      // Essayer d'abord les nouvelles APIs recommandées
      try {
        final status = await WebTvService.instance.getAutoPlaylistStatus();
        final currentUrl = await WebTvService.instance.getAutoPlaylistCurrentUrl();
        
        if (mounted) {
          setState(() {
            _autoPlaylistStatus = status;
            _autoPlaylistCurrentUrl = currentUrl;
            _isLoading = false;
            _errorMessage = null;
          });
        }
      } catch (e) {
        // Si les nouvelles APIs échouent, essayer l'ancienne API
        print('⚠️ Nouvelles APIs non disponibles, tentative avec l\'ancienne API...');
        final stream = await WebTvService.instance.getCurrentStream();
        if (mounted) {
          setState(() {
            _currentStream = stream;
            _isLoading = false;
            _errorMessage = null;
          });
        }
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

  void _startPeriodicRefresh() {
    Future.delayed(const Duration(seconds: 10), () {
      if (mounted) {
        _loadData(showLoader: false); // Refresh silencieux
        _startPeriodicRefresh();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    // Valeurs par défaut si pas de stream
    final embedCode = _currentStream?.embedCode ?? 
        '<iframe src="https://tv.embmission.com/watch" width="800" height="450" frameborder="0" allowfullscreen="true" allow="autoplay; encrypted-media; fullscreen"></iframe>';
    final directLink = _currentStream?.playbackUrl ?? 
        'https://tv.embmission.com/watch';

    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        final isTablet = constraints.maxWidth >= 768 && constraints.maxWidth < 1024;
        
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
                    // Boutons de navigation
                    Wrap(
                      spacing: 12,
                      runSpacing: 8,
                      children: [
                        ElevatedButton.icon(
                          onPressed: () => context.go('/webtv/player'),
                          icon: const Icon(Icons.play_circle, size: 18),
                          label: const Text('Lecteur WebTV'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppTheme.blueColor,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                        ),
                        ElevatedButton.icon(
                          onPressed: () => context.go('/webtv/sources'),
                          icon: const Icon(Icons.video_library, size: 18),
                          label: const Text('Sources Vidéo'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppTheme.blueColor,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                        ),
                        ElevatedButton.icon(
                          onPressed: () => context.go('/webtv/control'),
                          icon: const Icon(Icons.settings, size: 18),
                          label: const Text('Contrôle WebTV'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF6B7280),
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const Gap(16),
                    if (_isLoading)
                      const Center(
                        child: Padding(
                          padding: EdgeInsets.all(40.0),
                          child: CircularProgressIndicator(),
                        ),
                      )
                    else if (_errorMessage != null)
                      SectionCard(
                        child: Column(
                          children: [
                            const Icon(Icons.error_outline, size: 48, color: Colors.red),
                            const Gap(16),
                            Text(
                              'Erreur de chargement',
                              style: const TextStyle(
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
                          ],
                        ),
                      )
                    else
                      SectionCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                          const Text(
                            'Aperçu du Lecteur WebTV',
                            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                          ),
                          const Gap(12),
                          PlayerPreview(
                            thumbnailUrl: _currentStream?.thumbnailUrl,
                            title: _currentStream?.title,
                            description: _currentStream?.description,
                            isLive: _autoPlaylistStatus?['is_live'] ?? _currentStream?.isLive ?? false,
                          ),
                            const Gap(20),
                            const Text(
                              'Intégrer sur votre site (iFrame)',
                              style: TextStyle(fontWeight: FontWeight.w700),
                            ),
                            const Gap(8),
                            CopyField(
                              value: embedCode,
                              buttonLabel: 'Copier code',
                              icon: Icons.copy,
                            ),
                            const Gap(16),
                            const Text(
                              'Partager le lien direct',
                              style: TextStyle(fontWeight: FontWeight.w700),
                            ),
                            const Gap(8),
                            CopyField(
                              value: directLink,
                              buttonLabel: 'Copier lien',
                              icon: Icons.link,
                            ),
                            const Gap(16),
                            const Text(
                              'Partager sur les réseaux sociaux',
                              style: TextStyle(fontWeight: FontWeight.w700),
                            ),
                            const Gap(12),
                            if (isMobile)
                              Column(
                                children: [
                                  SocialButton(
                                    color: const Color(0xFF1877F2),
                                    icon: Icons.facebook,
                                    label: 'Facebook',
                                    link: directLink,
                                  ),
                                  const Gap(8),
                                  SocialButton(
                                    color: const Color(0xFF1DA1F2),
                                    icon: Icons.alternate_email,
                                    label: 'Twitter / X',
                                    link: directLink,
                                  ),
                                  const Gap(8),
                                  SocialButton(
                                    color: const Color(0xFF25D366),
                                    icon: Icons.chat,
                                    label: 'WhatsApp',
                                    link: directLink,
                                  ),
                                ],
                              )
                            else
                              Wrap(
                                spacing: 16,
                                runSpacing: 10,
                                children: [
                                  SocialButton(
                                    color: const Color(0xFF1877F2),
                                    icon: Icons.facebook,
                                    label: 'Facebook',
                                    link: directLink,
                                  ),
                                  SocialButton(
                                    color: const Color(0xFF1DA1F2),
                                    icon: Icons.alternate_email,
                                    label: 'Twitter / X',
                                    link: directLink,
                                  ),
                                  SocialButton(
                                    color: const Color(0xFF25D366),
                                    icon: Icons.chat,
                                    label: 'WhatsApp',
                                    link: directLink,
                                  ),
                                ],
                              ),
                          ],
                        ),
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

/// Header avec titre et actions
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
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: AppTheme.blueColor,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Text(
                    'EMB-MISSION',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                    ),
                  ),
                ),
                const Spacer(),
              ],
            ),
            const Gap(12),
            const Text(
              'Partage & Intégration',
              style: TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.w800,
                color: Color(0xFF111827),
              ),
            ),
            const Gap(12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const UserProfileChip(),
                ElevatedButton.icon(
                  onPressed: () => context.go('/webtv/diffusionlive'),
                  icon: const Icon(Icons.live_tv),
                  label: const Text('Démarrer Diffusion Live'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.redColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  ),
                ),
              ],
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
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: AppTheme.blueColor,
              borderRadius: BorderRadius.circular(6),
            ),
            child: const Text(
              'EMB-MISSION',
              style: TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w700,
                fontSize: 12,
              ),
            ),
          ),
          const Gap(24),
          const Text(
            'Partage & Intégration',
            style: TextStyle(
              fontSize: 32,
              fontWeight: FontWeight.w800,
              color: Color(0xFF111827),
            ),
          ),
          const Spacer(),
          const UserProfileChip(),
          const Gap(16),
          ElevatedButton.icon(
            onPressed: () => context.go('/webtv/diffusionlive'),
            icon: const Icon(Icons.live_tv),
            label: const Text('Démarrer Diffusion Live'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.redColor,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            ),
          ),
        ],
      ),
    );
  }
}
