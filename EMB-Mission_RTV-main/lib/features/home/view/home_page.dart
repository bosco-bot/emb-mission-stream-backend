import 'package:emb_mission_dashboard/features/home/widgets/home_footer.dart';
import 'package:emb_mission_dashboard/features/home/widgets/home_header.dart';
import 'package:emb_mission_dashboard/features/home/widgets/home_hero_section.dart';
import 'package:emb_mission_dashboard/features/home/widgets/webradio_preview_card.dart';
import 'package:emb_mission_dashboard/features/home/widgets/webtv_preview_card.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        final isTablet = constraints.maxWidth >= 768 && constraints.maxWidth < 1024;
        
        return Scaffold(
          backgroundColor: const Color(0xFFE0F2F7), // Fond bleu exact de la maquette
          body: SafeArea(
            child: SingleChildScrollView(
              child: Column(
                children: [
                  const HomeHeader(),
                  
                  // Hero section avec fond bleu
                  Container(
                    width: double.infinity,
                    color: const Color(0xFFE0F2F7),
                    child: Column(
                      children: [
                        const Gap(40),
                        Padding(
                          padding: EdgeInsets.symmetric(
                            horizontal: isMobile ? 16 : 24,
                          ),
                          child: const HomeHeroSection(),
                        ),
                        const Gap(40),
                      ],
                    ),
                  ),
                  
                  // Section des cartes avec fond blanc
                  Container(
                    width: double.infinity,
                    color: Colors.white,
                    child: Column(
                      children: [
                        Padding(
                          padding: EdgeInsets.symmetric(
                            horizontal: isMobile ? 16 : isTablet ? 32 : 96,
                            vertical: 40,
                          ),
                          child: const PreviewSection(),
                        ),
                        const HomeFooter(),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

class PreviewSection extends StatelessWidget {
  const PreviewSection({super.key});
  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isWide = constraints.maxWidth > 900;
        if (isWide) {
          return const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(flex: 3, child: WebTvPreviewCard()),
              Gap(24),
              Expanded(flex: 2, child: WebRadioPreviewCard()),
            ],
          );
        }
        return const Column(
          children: [
            WebTvPreviewCard(),
            Gap(24),
            WebRadioPreviewCard(),
          ],
        );
      },
    );
  }
}
