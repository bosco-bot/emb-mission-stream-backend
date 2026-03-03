import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:emb_mission_dashboard/core/services/webtv_service.dart';
import 'package:emb_mission_dashboard/core/services/azuracast_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';

/// Top greeting banner for the dashboard.
class HeaderSection extends StatefulWidget {
  const HeaderSection({required this.isMobile});
  final bool isMobile;

  @override
  State<HeaderSection> createState() => _HeaderSectionState();
}

class _HeaderSectionState extends State<HeaderSection> {
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
    final theme = Theme.of(context);
    return Container(
      decoration: BoxDecoration(
        color: AppTheme.blueColor.withValues(alpha: 0.25),
        borderRadius: BorderRadius.circular(16),
      ),
      padding: EdgeInsets.symmetric(
        horizontal: widget.isMobile ? 16 : 20,
        vertical: widget.isMobile ? 20 : 24,
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Builder(
                  builder: (context) {
                    final userService = UserService.instance;
                    final currentUser = userService.currentUser;
                    final userName = currentUser?['name'] as String? ?? 'Admin';
                    return Text(
                      'Bienvenue, $userName !',
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        fontSize: widget.isMobile ? 20 : null,
                      ),
                    );
                  },
                ),
                Gap(widget.isMobile ? 6 : 8),
                Row(
                  children: [
                    Icon(
                      Icons.circle, 
                      size: 10, 
                      color: _isLoading 
                          ? const Color(0xFF94A3B8)
                          : (_isRadioLive || _isWebTvLive 
                              ? AppTheme.redColor 
                              : const Color(0xFF94A3B8))
                    ),
                    const Gap(8),
                    Text(
                      _isLoading 
                          ? 'Chargement du statut...'
                          : (_isRadioLive || _isWebTvLive 
                              ? 'Vous êtes EN DIRECT' 
                              : 'Vous êtes HORS LIGNE'),
                      style: TextStyle(
                        fontSize: widget.isMobile ? 14 : null,
                        color: _isLoading 
                            ? const Color(0xFF94A3B8)
                            : (_isRadioLive || _isWebTvLive 
                                ? AppTheme.redColor 
                                : const Color(0xFF94A3B8)),
                        fontWeight: (_isRadioLive || _isWebTvLive) 
                            ? FontWeight.w600 
                            : FontWeight.normal,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const Gap(16),
          const UserProfileChip(),
        ],
      ),
    );
  }
}
