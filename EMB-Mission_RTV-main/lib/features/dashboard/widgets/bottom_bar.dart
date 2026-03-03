import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/utils/responsive_utils.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';

/// Bottom action bar displayed on the dashboard screen.
class BottomBar extends StatelessWidget {
  const BottomBar({super.key});

  @override
  Widget build(BuildContext context) {
    final isMobile = ResponsiveUtils.isMobile(context);
    final isTablet = ResponsiveUtils.isTablet(context);
    final padding = ResponsiveUtils.getHorizontalPadding(context);
    
    if (isMobile) {
      return Container(
        padding: EdgeInsets.symmetric(
          horizontal: padding.horizontal,
          vertical: 12,
        ),
        decoration: const BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: Color(0xFFE2E8F0))),
          boxShadow: [
            BoxShadow(
              color: Color(0x0F000000),
              blurRadius: 8,
              offset: Offset(0, -2),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () {},
                icon: const Icon(Icons.live_tv, size: 20),
                label: const Text(
                  'Démarrer diffusion Live',
                  style: TextStyle(fontSize: 14),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.redColor,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                ),
              ),
            ),
          ],
        ),
      );
    }

    // Desktop/Tablet layout
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: padding.horizontal,
        vertical: 10,
      ),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: Color(0xFFE2E8F0))),
        boxShadow: [
          BoxShadow(
            color: Color(0x0F000000),
            blurRadius: 8,
            offset: Offset(0, -2),
          ),
        ],
      ),
      child: Row(
        children: [
          // Start Live Button
          const StartLiveButton(),
        ],
      ),
    );
  }
}
