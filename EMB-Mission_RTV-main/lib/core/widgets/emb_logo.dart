import 'package:flutter/material.dart';

/// Widget logo EMB-MISSION réutilisable
/// 
/// Utilise l'image logo fournie par l'utilisateur
class EmbLogo extends StatelessWidget {
  const EmbLogo({
    super.key,
    this.size = 40,
  });

  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      child: Image.asset(
        'assets/LOGO_EMB MISSION_02 (3).png',
        width: size,
        height: size,
        fit: BoxFit.contain,
        errorBuilder: (context, error, stackTrace) {
          // Fallback temporaire avec logo_2.png
          return Image.asset(
            'assets/logo_2.png',
            width: size,
            height: size,
            fit: BoxFit.contain,
          );
        },
      ),
    );
  }
}
