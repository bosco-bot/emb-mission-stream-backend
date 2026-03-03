import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

class HomeHeroSection extends StatelessWidget {
  const HomeHeroSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          'Créez votre WebTV et WebRadio\nen quelques clics',
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.headlineMedium?.copyWith(
            fontWeight: FontWeight.bold,
            color: const Color(0xFF0F172A),
            height: 1.2,
          ),
        ),
        const Gap(16),
        const Text(
          '''La plateforme tout-en-un pour diffuser vos contenus au monde entier, sans\naucune compétence technique.''',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 16,
            color: Color(0xFF64748B),
            height: 1.5,
          ),
        ),
        const Gap(24),
        ElevatedButton(
          onPressed: () => context.go('/auth'),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppTheme.redColor,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(30),
            ),
            elevation: 4,
            shadowColor: AppTheme.redColor.withValues(alpha: 0.4),
          ),
          child: const Text(
            'Lancez votre Média Gratuitement',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
          ),
        ),
      ],
    );
  }
}
