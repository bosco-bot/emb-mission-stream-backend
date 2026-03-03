import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/emb_logo.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

/// Page d'édition des métadonnées
/// 
/// Interface pour éditer les métadonnées d'un contenu (titre, vignette, description, durée)
class MetadataEditPage extends StatefulWidget {
  const MetadataEditPage({super.key});

  @override
  State<MetadataEditPage> createState() => _MetadataEditPageState();
}

class _MetadataEditPageState extends State<MetadataEditPage> {
  final _titleController = TextEditingController(text: 'Session Live - Louange & Adoration');
  final _descriptionController = TextEditingController(
    text: 'Concert exceptionnel enregistré en direct depuis nos studios. Un moment de partage et de foi avec le groupe \'Céleste\'.',
  );
  final _durationController = TextEditingController(text: '01:25:42');

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _durationController.dispose();
    super.dispose();
  }

  void _saveChanges() {
    // TODO: Implémenter la sauvegarde
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Métadonnées sauvegardées avec succès'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  void _cancel() {
    context.pop();
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        final isTablet = constraints.maxWidth >= 768 && constraints.maxWidth < 1024;
        
        return Column(
          children: [
            // Header avec titre et actions
            _Header(isMobile: isMobile),
            
            // Contenu principal
            Expanded(
              child: SingleChildScrollView(
                padding: EdgeInsets.symmetric(
                  horizontal: isMobile ? 16 : 24,
                  vertical: isMobile ? 16 : 20,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Titre de la page
                    Text(
                      'Édition des métadonnées',
                      style: TextStyle(
                        fontSize: isMobile ? 24 : 32,
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF111827),
                      ),
                    ),
                    const Gap(24),
                    
                    // Formulaire d'édition
                    _MetadataForm(
                      titleController: _titleController,
                      descriptionController: _descriptionController,
                      durationController: _durationController,
                      isMobile: isMobile,
                    ),
                    
                    const Gap(24),
                    
                    // Boutons d'action
                    _ActionButtons(
                      onSave: _saveChanges,
                      onCancel: _cancel,
                      isMobile: isMobile,
                    ),
                  ],
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

/// Header avec titre et actions
class _Header extends StatelessWidget {
  const _Header({required this.isMobile});
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    if (isMobile) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.1),
              blurRadius: 4,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          children: [
            Row(
              children: [
                const EmbLogo(size: 32),
                const Gap(8),
                const Text(
                  'EMB-MISSION',
                  style: TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                    color: Color(0xFF0F172A),
                  ),
                ),
                const Spacer(),
              ],
            ),
            const Gap(12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const UserProfileChip(),
                ElevatedButton.icon(
                  onPressed: () {},
                  icon: const Icon(Icons.videocam, size: 18),
                  label: const Text('Démarrer Diffusion Live'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.redColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.1),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          const EmbLogo(size: 40),
          const Gap(12),
          const Text(
            'EMB-MISSION',
            style: TextStyle(
              fontWeight: FontWeight.w900,
              fontSize: 20,
              color: Color(0xFF0F172A),
            ),
          ),
          const Spacer(),
          ElevatedButton.icon(
            onPressed: () {},
            icon: const Icon(Icons.videocam),
            label: const Text('Démarrer Diffusion Live'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.redColor,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ),
          const Gap(16),
          const UserProfileChip(),
        ],
      ),
    );
  }
}

/// Formulaire d'édition des métadonnées
class _MetadataForm extends StatelessWidget {
  const _MetadataForm({
    required this.titleController,
    required this.descriptionController,
    required this.durationController,
    required this.isMobile,
  });

  final TextEditingController titleController;
  final TextEditingController descriptionController;
  final TextEditingController durationController;
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Champ Titre
          _FormField(
            label: 'Titre',
            controller: titleController,
            isMobile: isMobile,
          ),
          
          const Gap(20),
          
          // Champ Vignette
          _ThumbnailField(isMobile: isMobile),
          
          const Gap(20),
          
          // Champ Description
          _FormField(
            label: 'Description',
            controller: descriptionController,
            isMobile: isMobile,
            maxLines: 4,
          ),
          
          const Gap(20),
          
          // Champ Durée
          _DurationField(
            controller: durationController,
            isMobile: isMobile,
          ),
        ],
      ),
    );
  }
}

/// Champ de formulaire générique
class _FormField extends StatelessWidget {
  const _FormField({
    required this.label,
    required this.controller,
    required this.isMobile,
    this.maxLines = 1,
  });

  final String label;
  final TextEditingController controller;
  final bool isMobile;
  final int maxLines;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w600,
            color: Color(0xFF111827),
          ),
        ),
        const Gap(8),
        TextField(
          controller: controller,
          maxLines: maxLines,
          decoration: InputDecoration(
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          ),
        ),
      ],
    );
  }
}

/// Champ de vignette avec aperçu
class _ThumbnailField extends StatelessWidget {
  const _ThumbnailField({required this.isMobile});
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Vignette',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w600,
            color: Color(0xFF111827),
          ),
        ),
        const Gap(8),
        
        // Aperçu de la vignette
        Container(
          width: double.infinity,
          height: isMobile ? 120 : 160,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: Image.network(
              'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=450&fit=crop&auto=format&q=80',
              fit: BoxFit.cover,
              loadingBuilder: (context, child, progress) {
                if (progress == null) return child;
                return const Center(child: CircularProgressIndicator());
              },
              errorBuilder: (context, error, stackTrace) {
                return Container(
                  color: const Color(0xFFF3F4F6),
                  child: const Center(
                    child: Icon(
                      Icons.image,
                      color: Color(0xFF9CA3AF),
                      size: 48,
                    ),
                  ),
                );
              },
            ),
          ),
        ),
        
        const Gap(8),
        
        const Text(
          'Fichiers recommandés : JPG, PNG. Ratio 16:9.',
          style: TextStyle(
            fontSize: 12,
            color: Color(0xFF6B7280),
          ),
        ),
      ],
    );
  }
}

/// Champ de durée avec icône horloge
class _DurationField extends StatelessWidget {
  const _DurationField({
    required this.controller,
    required this.isMobile,
  });

  final TextEditingController controller;
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Durée',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w600,
            color: Color(0xFF111827),
          ),
        ),
        const Gap(8),
        TextField(
          controller: controller,
          decoration: InputDecoration(
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            prefixIcon: const Icon(
              Icons.access_time,
              color: Color(0xFF6B7280),
            ),
          ),
        ),
      ],
    );
  }
}

/// Boutons d'action
class _ActionButtons extends StatelessWidget {
  const _ActionButtons({
    required this.onSave,
    required this.onCancel,
    required this.isMobile,
  });

  final VoidCallback onSave;
  final VoidCallback onCancel;
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    if (isMobile) {
      return Column(
        children: [
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: onSave,
              icon: const Icon(Icons.check),
              label: const Text('Sauvegarder les modifications'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.redColor,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            ),
          ),
          const Gap(12),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton(
              onPressed: onCancel,
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF6B7280),
                side: const BorderSide(color: Color(0xFFD1D5DB)),
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              child: const Text('Annuler'),
            ),
          ),
        ],
      );
    }

    return Row(
      children: [
        OutlinedButton(
          onPressed: onCancel,
          style: OutlinedButton.styleFrom(
            foregroundColor: const Color(0xFF6B7280),
            side: const BorderSide(color: Color(0xFFD1D5DB)),
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
          child: const Text('Annuler'),
        ),
        const Spacer(),
        ElevatedButton.icon(
          onPressed: onSave,
          icon: const Icon(Icons.check),
          label: const Text('Sauvegarder les modifications'),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppTheme.redColor,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
        ),
      ],
    );
  }
}
