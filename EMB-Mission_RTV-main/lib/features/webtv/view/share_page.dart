import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:gap/gap.dart';
import 'dart:html' as html;

/// Page dédiée pour le partage WebTV
class SharePage extends StatelessWidget {
  const SharePage({
    super.key,
    required this.title,
    required this.subtitle,
    required this.shareUrl,
  });

  final String title;
  final String subtitle;
  final String shareUrl;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          onPressed: () => Navigator.of(context).pop(),
          icon: const Icon(Icons.arrow_back, color: Colors.black),
        ),
        title: const Text(
          'Partage rapide',
          style: TextStyle(
            color: Colors.black,
            fontWeight: FontWeight.w700,
          ),
        ),
        centerTitle: true,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Aperçu du contenu
              _ContentPreview(
                title: title,
                subtitle: subtitle,
              ),
              
              const Gap(32),
              
              // Section partage réseaux sociaux
              const Text(
                'Partager sur les réseaux sociaux',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF111827),
                ),
              ),
              
              const Gap(16),
              
              // Boutons réseaux sociaux
              Column(
                children: [
                  _SocialButton(
                    color: const Color(0xFF1877F2),
                    icon: Icons.facebook,
                    label: 'Facebook',
                    shareUrl: shareUrl,
                  ),
                  const Gap(12),
                  _SocialButton(
                    color: const Color(0xFF000000),
                    icon: Icons.alternate_email,
                    label: 'X (Twitter)',
                    shareUrl: shareUrl,
                  ),
                  const Gap(12),
                  _SocialButton(
                    color: const Color(0xFF25D366),
                    icon: Icons.chat,
                    label: 'WhatsApp',
                    shareUrl: shareUrl,
                  ),
                ],
              ),
              
              const Gap(32),
              
              // Section copie de lien
              const Text(
                'Ou copier le lien',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF111827),
                ),
              ),
              
              const Gap(16),
              
              // Champ de copie
              _CopyLinkField(shareUrl: shareUrl),
            ],
          ),
        ),
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
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        children: [
          // Icône vidéo
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: const Color(0xFFDC2626),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.play_circle_filled,
              color: Colors.white,
              size: 28,
            ),
          ),
          
          const Gap(16),
          
          // Informations
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF111827),
                  ),
                ),
                const Gap(4),
                Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 16,
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
      case 'x (twitter)':
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
      width: double.infinity,
      height: 56,
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
          padding: const EdgeInsets.symmetric(horizontal: 20),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        icon: Icon(icon, size: 24),
        label: Text(
          label,
          style: const TextStyle(
            fontSize: 16,
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
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              border: Border.all(color: const Color(0xFFE2E8F0)),
              borderRadius: BorderRadius.circular(12),
            ),
            child: SelectableText(
              shareUrl,
              style: const TextStyle(
                color: Color(0xFF334155),
                fontSize: 16,
              ),
            ),
          ),
        ),
        const Gap(16),
        SizedBox(
          height: 56,
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
              padding: const EdgeInsets.symmetric(horizontal: 20),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            icon: const Icon(Icons.copy, size: 20),
            label: const Text(
              'Copier',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ),
      ],
    );
  }
}






