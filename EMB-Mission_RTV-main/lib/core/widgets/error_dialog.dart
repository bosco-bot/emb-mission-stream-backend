import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';

/// Boîte de dialogue d'erreur de connexion
/// 
/// Widget réutilisable pour afficher les erreurs de connexion
/// conforme à la maquette fournie.
class ErrorDialog extends StatelessWidget {
  const ErrorDialog({
    super.key,
    this.title = 'Erreur',
    this.description = 'Erreur de connexion au serveur de streaming.',
    this.buttonText = 'Réessayer',
    this.onRetry,
  });

  final String title;
  final String description;
  final String buttonText;
  final VoidCallback? onRetry;

  /// Affiche la boîte de dialogue d'erreur
  static Future<void> show(
    BuildContext context, {
    String title = 'Erreur',
    String description = 'Erreur de connexion au serveur de streaming.',
    String buttonText = 'Réessayer',
    VoidCallback? onRetry,
  }) {
    return showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (context) => ErrorDialog(
        title: title,
        description: description,
        buttonText: buttonText,
        onRetry: onRetry ?? () => Navigator.of(context).pop(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        
        return Dialog(
          backgroundColor: Colors.transparent,
          child: Container(
            constraints: BoxConstraints(
              maxWidth: isMobile ? double.infinity : 400,
            ),
            margin: EdgeInsets.symmetric(
              horizontal: isMobile ? 16 : 0,
            ),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.1),
                  blurRadius: 20,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: Padding(
              padding: EdgeInsets.all(isMobile ? 24 : 32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Icône d'erreur (triangle rouge avec point d'exclamation)
                  Container(
                    width: isMobile ? 64 : 80,
                    height: isMobile ? 64 : 80,
                    decoration: BoxDecoration(
                      color: AppTheme.redColor,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.error,
                      color: Colors.white,
                      size: 40,
                    ),
                  ),
                  const Gap(24),
                  
                  // Titre
                  Text(
                    title,
                    style: TextStyle(
                      fontSize: isMobile ? 20 : 24,
                      fontWeight: FontWeight.bold,
                      color: const Color(0xFF111827),
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const Gap(12),
                  
                  // Description
                  Text(
                    description,
                    style: TextStyle(
                      fontSize: isMobile ? 14 : 16,
                      color: const Color(0xFF6B7280),
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const Gap(32),
                  
                  // Bouton Réessayer
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: onRetry,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.redColor,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      child: Text(
                        buttonText,
                        style: const TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 16,
                        ),
                      ),
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
