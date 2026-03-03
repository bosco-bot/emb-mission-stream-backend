import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'dart:html' as html;

/// A filled social button that opens the sharing URL for the specific platform.
class SocialButton extends StatelessWidget {
  const SocialButton({
    required this.color,
    required this.icon,
    required this.label,
    this.link,
    super.key,
  });

  final Color color;
  final IconData icon;
  final String label;
  final String? link;

  String _getShareUrl(String platform, String link) {
    final encodedLink = Uri.encodeComponent(link);
    final shareText = Uri.encodeComponent('Regardez ce stream en direct sur EMB Mission WebTV');
    
    switch (platform.toLowerCase()) {
      case 'facebook':
        return 'https://www.facebook.com/sharer/sharer.php?u=$encodedLink';
      case 'twitter / x':
        return 'https://twitter.com/intent/tweet?url=$encodedLink&text=$shareText';
      case 'whatsapp':
        return 'https://wa.me/?text=$shareText%20$encodedLink';
      default:
        return link;
    }
  }

  @override
  Widget build(BuildContext context) {
    final scaffoldMessenger = ScaffoldMessenger.of(context);
    return SizedBox(
      height: 44,
      child: ElevatedButton.icon(
        onPressed: () async {
          final shareLink = link ?? '';
          if (shareLink.isNotEmpty) {
            // Ouvrir l'URL de partage dans un nouvel onglet
            final shareUrl = _getShareUrl(label, shareLink);
            html.window.open(shareUrl, '_blank');
            
            // Afficher une confirmation
            scaffoldMessenger.showSnackBar(
              SnackBar(
                content: Text('Ouverture du partage sur $label'),
                behavior: SnackBarBehavior.floating,
                duration: const Duration(seconds: 2),
              ),
            );
          } else {
            // Si pas de lien, afficher une erreur
            scaffoldMessenger.showSnackBar(
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
          padding: const EdgeInsets.symmetric(horizontal: 18),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
        ),
        icon: Icon(icon),
        label: Text(label),
      ),
    );
  }
}
