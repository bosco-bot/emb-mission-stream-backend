import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';

/// A reusable left-side panel used across Auth screens.
/// Displays the brand logo, name, and tagline over a light blue background.
class AuthLeftPanel extends StatelessWidget {
  const AuthLeftPanel({super.key});

  @override
  Widget build(BuildContext context) {
    return const Expanded(
      flex: 5,
      child: ColoredBox(
        color: Color(0xFFD7EBF9),
        child: Center(
          child: _BrandBlock(),
        ),
      ),
    );
  }
}

class _BrandBlock extends StatelessWidget {
  const _BrandBlock();
  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        _Logo(),
        const Gap(24),
        const Text(
          'EMB-MISSION-RTV',
          style: TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.bold,
            color: Color(0xFF0F172A),
          ),
        ),
        const Gap(16),
        const Text(
          'Votre plateforme tout-en-un pour la\ndiffusion WebTV & WebRadio.',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 16,
            color: Color(0xFF64748B),
            height: 1.5,
          ),
        ),
      ],
    );
  }
}

class _Logo extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Image.asset(
      'assets/logo.png',
      width: 120,
      height: 120,
      errorBuilder: (context, error, stackTrace) => Container(
        width: 120,
        height: 120,
        color: AppTheme.redColor,
        child: const Center(
          child: Text(
            'EMB',
            style: TextStyle(
              color: Colors.white,
              fontSize: 32,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
      ),
    );
  }
}
