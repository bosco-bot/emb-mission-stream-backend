import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:gap/gap.dart';
import 'dart:html' as html;

/// Boîte de dialogue de partage rapide réutilisable
/// 
/// Affiche une interface de partage avec aperçu du contenu,
/// boutons de réseaux sociaux et copie de lien
class QuickShareDialog extends StatelessWidget {
  const QuickShareDialog({
    super.key,
    required this.title,
    required this.subtitle,
    required this.shareUrl,
    this.onClose,
  });

  final String title;
  final String subtitle;
  final String shareUrl;
  final VoidCallback? onClose;

  /// Affiche la boîte de dialogue de partage rapide
  static Future<void> show(
    BuildContext context, {
    required String title,
    required String subtitle,
    required String shareUrl,
    VoidCallback? onClose,
  }) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => QuickShareDialog(
        title: title,
        subtitle: subtitle,
        shareUrl: shareUrl,
        onClose: onClose,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      padding: EdgeInsets.only(
        left: 24,
        right: 24,
        top: 24,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Poignée de glissement
          Center(
            child: Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.only(bottom: 16),
              decoration: BoxDecoration(
                color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          
          // Header avec titre et bouton fermer
          Row(
            children: [
              const Text(
                'Partage rapide',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF111827),
                ),
              ),
              const Spacer(),
              IconButton(
                onPressed: () {
                  Navigator.of(context).pop();
                  onClose?.call();
                },
                icon: const Icon(
                  Icons.close,
                  color: Color(0xFF6B7280),
                ),
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
              ),
            ],
          ),
          
          const Gap(16),
          
          // Aperçu du contenu
          _ContentPreview(
            title: title,
            subtitle: subtitle,
          ),
          
          const Gap(24),
          
          // Section partage réseaux sociaux
          const Text(
            'Partager sur les réseaux sociaux',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: Color(0xFF111827),
            ),
          ),
          
          const Gap(12),
          
          // Boutons réseaux sociaux - toujours en colonne pour Bottom Sheet
          Column(
            children: [
              _SocialButton(
                color: const Color(0xFF1877F2),
                icon: Icons.facebook,
                label: 'Facebook',
                shareUrl: shareUrl,
              ),
              const Gap(8),
              _SocialButton(
                color: const Color(0xFF000000),
                icon: Icons.alternate_email,
                label: 'X',
                shareUrl: shareUrl,
              ),
              const Gap(8),
              _SocialButton(
                color: const Color(0xFF25D366),
                icon: Icons.chat,
                label: 'WhatsApp',
                shareUrl: shareUrl,
              ),
            ],
          ),
          
          const Gap(24),
          
          // Section copie de lien
          const Text(
            'Ou copier le lien',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: Color(0xFF111827),
            ),
          ),
          
          const Gap(12),
          
          // Champ de copie
          _CopyLinkField(shareUrl: shareUrl),
        ],
      ),
    );
  }
}

/// Aperçu du contenu à partager
class _ContentPreview extends StatelessWidget {
  const _ContentPreview({
    required this.title,
    required this.subtitle,
  });

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        children: [
          // Icône audio
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: const Color(0xFFDC2626),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Icon(
              Icons.headphones,
              color: Colors.white,
              size: 24,
            ),
          ),
          
          const Gap(12),
          
          // Informations
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF111827),
                  ),
                ),
                const Gap(2),
                Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 14,
                    color: Color(0xFF6B7280),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Bouton de réseau social
class _SocialButton extends StatelessWidget {
  const _SocialButton({
    required this.color,
    required this.icon,
    required this.label,
    required this.shareUrl,
  });

  final Color color;
  final IconData icon;
  final String label;
  final String shareUrl;

  String _getShareUrl(String platform, String link) {
    final encodedLink = Uri.encodeComponent(link);
    final shareText = Uri.encodeComponent('Regardez ce stream en direct sur EMB Mission WebTV');
    
    switch (platform.toLowerCase()) {
      case 'facebook':
        return 'https://www.facebook.com/sharer/sharer.php?u=$encodedLink';
      case 'x':
        return 'https://twitter.com/intent/tweet?url=$encodedLink&text=$shareText';
      case 'whatsapp':
        return 'https://wa.me/?text=$shareText%20$encodedLink';
      default:
        return link;
    }
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 44,
      child: ElevatedButton.icon(
        onPressed: () {
          if (shareUrl.isNotEmpty) {
            // Ouvrir l'URL de partage dans un nouvel onglet
            final shareLink = _getShareUrl(label, shareUrl);
            html.window.open(shareLink, '_blank');
            
            // Afficher une confirmation
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Ouverture du partage sur $label'),
                behavior: SnackBarBehavior.floating,
                duration: const Duration(seconds: 2),
              ),
            );
          } else {
            // Si pas de lien, afficher une erreur
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Aucun lien disponible pour le partage'),
                behavior: SnackBarBehavior.floating,
                backgroundColor: Colors.red,
              ),
            );
          }
        },
        style: ElevatedButton.styleFrom(
          backgroundColor: color,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
        icon: Icon(icon, size: 20),
        label: Text(
          label,
          style: const TextStyle(
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }
}

/// Champ de copie de lien
class _CopyLinkField extends StatelessWidget {
  const _CopyLinkField({required this.shareUrl});
  
  final String shareUrl;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              border: Border.all(color: const Color(0xFFE2E8F0)),
              borderRadius: BorderRadius.circular(8),
            ),
            child: SelectableText(
              shareUrl,
              style: const TextStyle(
                color: Color(0xFF334155),
                fontSize: 14,
              ),
            ),
          ),
        ),
        const Gap(12),
        SizedBox(
          height: 44,
          child: ElevatedButton.icon(
            onPressed: () async {
              await Clipboard.setData(ClipboardData(text: shareUrl));
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Lien copié !'),
                  behavior: SnackBarBehavior.floating,
                ),
              );
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF3B82F6),
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            icon: const Icon(Icons.copy, size: 18),
            label: const Text(
              'Copier',
              style: TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ),
      ],
    );
  }
}
