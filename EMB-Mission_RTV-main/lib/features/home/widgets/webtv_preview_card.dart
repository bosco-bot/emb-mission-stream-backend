import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import '../../../core/services/webtv_service.dart';
import 'package:go_router/go_router.dart';
import '../../../core/services/user_service.dart';

class WebTvPreviewCard extends StatefulWidget {
  const WebTvPreviewCard({super.key});

  @override
  State<WebTvPreviewCard> createState() => _WebTvPreviewCardState();
}

class _WebTvPreviewCardState extends State<WebTvPreviewCard> {
  bool _isLive = false;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadStatus();
    _startPeriodicRefresh();
  }

  Future<void> _loadStatus() async {
    try {
      final status = await WebTvService.instance.getAutoPlaylistStatus();
      if (mounted) {
        setState(() {
          _isLive = status['is_live'] == true;
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

  void _startPeriodicRefresh() {
    Future.delayed(const Duration(seconds: 30), () {
      if (mounted) {
        _loadStatus();
        _startPeriodicRefresh();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return MouseRegion(
      cursor: SystemMouseCursors.click,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () {
            final isLoggedIn = UserService.instance.isLoggedIn;
            if (isLoggedIn) {
              context.go('/webtv/player');
            } else {
              context.go('/auth');
            }
          },
          child: Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.1),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: AppTheme.redColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Icon(Icons.tv, color: AppTheme.redColor, size: 16),
              ),
              const Gap(8),
              const Text(
                'Aperçu Lecteur WebTV',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              const Spacer(),
              if (!_isLoading)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: _isLive ? AppTheme.redColor : const Color(0xFF6B7280),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (_isLive)
                        Container(
                          width: 6,
                          height: 6,
                          margin: const EdgeInsets.only(right: 4),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                          ),
                        ),
                      Text(
                        _isLive ? 'LIVE' : 'OFFLINE',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                )
              else
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.grey[300]!,
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: const Text(
                    '...',
                    style: TextStyle(
                      color: Colors.grey,
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                    ),
                  ),
                ),
            ],
          ),
          const Gap(12),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: Stack(
              children: [
                AspectRatio(
                  aspectRatio: 16 / 9,
                  child: Image.asset(
                    'assets/webtv.jpg',
                    fit: BoxFit.cover,
                  ),
                ),
                const Positioned.fill(
                  child: Center(
                    child: CircleAvatar(
                      radius: 30,
                      backgroundColor: Colors.white70,
                      child: Icon(
                        Icons.play_arrow,
                        size: 36,
                        color: Colors.black87,
                      ),
                    ),
                  ),
                ),
                const Positioned(
                  left: 12,
                  bottom: 12,
                  child: Text(
                    'EMB Live Session',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      shadows: [
                        Shadow(
                          color: Colors.black87,
                          blurRadius: 4,
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
          ),
        ),
      ),
    );
  }
}
