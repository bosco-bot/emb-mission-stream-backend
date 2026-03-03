import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';

/// Boîte de dialogue de confirmation de suppression
/// 
/// Widget réutilisable pour confirmer les actions de suppression
/// conforme à la maquette fournie.
class ConfirmationDialog extends StatelessWidget {
  const ConfirmationDialog({
    super.key,
    this.title = 'Voulez-vous vraiment supprimer ?',
    this.description = 'Cette action est irréversible. Toutes les données associées seront définitivement perdues.',
    this.confirmText = 'Oui',
    this.cancelText = 'Non',
    this.onConfirm,
    this.onCancel,
  });

  final String title;
  final String description;
  final String confirmText;
  final String cancelText;
  final VoidCallback? onConfirm;
  final VoidCallback? onCancel;

  /// Affiche la boîte de dialogue de confirmation
  static Future<bool?> show(
    BuildContext context, {
    String title = 'Voulez-vous vraiment supprimer ?',
    String description = 'Cette action est irréversible. Toutes les données associées seront définitivement perdues.',
    String confirmText = 'Oui',
    String cancelText = 'Non',
  }) {
    return showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (context) => ConfirmationDialog(
        title: title,
        description: description,
        confirmText: confirmText,
        cancelText: cancelText,
        onConfirm: () => Navigator.of(context).pop(true),
        onCancel: () => Navigator.of(context).pop(false),
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
                  // Icône d'avertissement
                  Container(
                    width: isMobile ? 48 : 56,
                    height: isMobile ? 48 : 56,
                    decoration: BoxDecoration(
                      color: AppTheme.redColor,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.warning,
                      color: Colors.white,
                      size: 28,
                    ),
                  ),
                  const Gap(24),
                  
                  // Titre
                  Text(
                    title,
                    style: TextStyle(
                      fontSize: isMobile ? 18 : 20,
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
                  
                  // Boutons d'action
                  if (isMobile)
                    _MobileButtons(
                      confirmText: confirmText,
                      cancelText: cancelText,
                      onConfirm: onConfirm,
                      onCancel: onCancel,
                    )
                  else
                    _DesktopButtons(
                      confirmText: confirmText,
                      cancelText: cancelText,
                      onConfirm: onConfirm,
                      onCancel: onCancel,
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

/// Boutons d'action pour mobile (empilés verticalement)
class _MobileButtons extends StatelessWidget {
  const _MobileButtons({
    required this.confirmText,
    required this.cancelText,
    required this.onConfirm,
    required this.onCancel,
  });

  final String confirmText;
  final String cancelText;
  final VoidCallback? onConfirm;
  final VoidCallback? onCancel;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Bouton "Oui" (rouge)
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: onConfirm,
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.redColor,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            child: Text(
              confirmText,
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: 16,
              ),
            ),
          ),
        ),
        const Gap(12),
        
        // Bouton "Non" (gris)
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: onCancel,
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFF3F4F6),
              foregroundColor: const Color(0xFF374151),
              padding: const EdgeInsets.symmetric(vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            child: Text(
              cancelText,
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: 16,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

/// Boutons d'action pour desktop (côte à côte)
class _DesktopButtons extends StatelessWidget {
  const _DesktopButtons({
    required this.confirmText,
    required this.cancelText,
    required this.onConfirm,
    required this.onCancel,
  });

  final String confirmText;
  final String cancelText;
  final VoidCallback? onConfirm;
  final VoidCallback? onCancel;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        // Bouton "Non" (gris)
        Expanded(
          child: ElevatedButton(
            onPressed: onCancel,
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFF3F4F6),
              foregroundColor: const Color(0xFF374151),
              padding: const EdgeInsets.symmetric(vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            child: Text(
              cancelText,
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: 16,
              ),
            ),
          ),
        ),
        const Gap(12),
        
        // Bouton "Oui" (rouge)
        Expanded(
          child: ElevatedButton(
            onPressed: onConfirm,
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.redColor,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            child: Text(
              confirmText,
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: 16,
              ),
            ),
          ),
        ),
      ],
    );
  }
}
