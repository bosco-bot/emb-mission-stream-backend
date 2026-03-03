import 'package:emb_mission_dashboard/features/dashboard/widgets/bottom_bar.dart';
import 'package:emb_mission_dashboard/features/dashboard/widgets/header_section.dart';
import 'package:emb_mission_dashboard/features/dashboard/widgets/main_grid.dart';
import 'package:emb_mission_dashboard/features/dashboard/widgets/stats_row.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';

class DashboardPage extends StatelessWidget {
  const DashboardPage({super.key});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        
        return Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: EdgeInsets.symmetric(
                  horizontal: isMobile ? 16 : 24,
                  vertical: isMobile ? 16 : 20,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    HeaderSection(isMobile: isMobile),
                    Gap(isMobile ? 16 : 20),
                    StatsRow(),
                    Gap(isMobile ? 16 : 20),
                    MainGrid(),
                  ],
                ),
              ),
            ),
            BottomBar(),
          ],
        );
      },
    );
  }
}
