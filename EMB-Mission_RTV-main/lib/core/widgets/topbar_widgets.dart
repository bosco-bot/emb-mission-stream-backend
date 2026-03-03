import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// A reusable button for starting a live broadcast session.
///
/// Use anywhere a prominent call-to-action is needed to start the live stream.
class StartLiveButton extends StatelessWidget {
  const StartLiveButton({
    super.key,
    this.label = 'Démarrer Diffusion Live',
  });

  /// The text label displayed on the button.
  final String label;

  @override
  Widget build(BuildContext context) {
    return ElevatedButton.icon(
      onPressed: () {
        context.go('/diffusionlive');
      },
      icon: const Icon(Icons.podcasts_outlined),
      label: Text(label),
      style: ElevatedButton.styleFrom(
        backgroundColor: const Color(0xFFEF4444),
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        elevation: 2,
        shadowColor: const Color(0xFFEF4444).withValues(alpha: 0.25),
      ),
    );
  }
}

/// A responsive profile chip showing the current user's avatar, name and role.
class UserProfileChip extends StatelessWidget {
  const UserProfileChip({
    super.key,
    this.onTap,
  });

  /// Optional callback when the chip is tapped.
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final userService = UserService.instance;
    final screenWidth = MediaQuery.of(context).size.width;
    final isMobile = screenWidth < 768;
    final isTablet = screenWidth >= 768 && screenWidth < 1024;
    
    // Récupérer les données utilisateur
    final currentUser = userService.currentUser;
    final userName = currentUser?['name'] as String? ?? 'Admin';
    final userRole = 'Admin';
    final avatarUrl = 'https://ui-avatars.com/api/?name=${Uri.encodeComponent(userName)}&background=0F172A&color=fff&size=128';
        
    if (isMobile) {
      // Mode mobile : avatar seul avec menu
      return Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => _showUserMenu(context),
          borderRadius: BorderRadius.circular(20),
          child: Container(
            padding: const EdgeInsets.all(4),
            decoration: BoxDecoration(
              color: Colors.white,
              border: Border.all(color: const Color(0xFFE5E7EB)),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                CircleAvatar(
                  radius: 14,
                  backgroundImage: NetworkImage(avatarUrl),
                ),
                const SizedBox(width: 4),
                const Icon(Icons.keyboard_arrow_down_rounded, size: 16),
              ],
            ),
          ),
        ),
      );
    }
    
    if (isTablet) {
      // Mode tablette : avatar + nom court
      return Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => _showUserMenu(context),
          borderRadius: BorderRadius.circular(20),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white,
              border: Border.all(color: const Color(0xFFE5E7EB)),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                CircleAvatar(
                  radius: 12,
                  backgroundImage: NetworkImage(avatarUrl),
                ),
                const SizedBox(width: 6),
                Text(
                  userName.split(' ').first, // Prénom seulement
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(width: 4),
                const Icon(Icons.keyboard_arrow_down_rounded, size: 16),
              ],
            ),
          ),
        ),
      );
    }
    
    // Mode desktop : version complète
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () => _showUserMenu(context),
        borderRadius: BorderRadius.circular(24),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            color: Colors.white,
            border: Border.all(color: const Color(0xFFE5E7EB)),
            borderRadius: BorderRadius.circular(24),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              CircleAvatar(
                radius: 12,
                backgroundImage: NetworkImage(avatarUrl),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    userName,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  Text(
                    userRole,
                    style: const TextStyle(
                      fontSize: 10,
                      color: Color(0xFF9CA3AF),
                    ),
                  ),
                ],
              ),
              const SizedBox(width: 6),
              const Icon(Icons.keyboard_arrow_down_rounded, size: 18),
            ],
          ),
        ),
      ),
    );
  }

  /// Affiche le menu utilisateur avec les options
  void _showUserMenu(BuildContext context) {
    final userService = UserService.instance;
    
    showMenu<String>(
      context: context,
      position: const RelativeRect.fromLTRB(100, 100, 0, 0),
      items: [
        PopupMenuItem<String>(
          value: 'profile',
          child: Row(
            children: [
              const Icon(Icons.person, size: 18),
              const SizedBox(width: 8),
              Text('Profil'),
            ],
          ),
        ),
        PopupMenuItem<String>(
          value: 'settings',
          child: Row(
            children: [
              const Icon(Icons.settings, size: 18),
              const SizedBox(width: 8),
              Text('Paramètres'),
            ],
          ),
        ),
        const PopupMenuDivider(),
        PopupMenuItem<String>(
          value: 'logout',
          child: Row(
            children: [
              const Icon(Icons.logout, size: 18, color: Colors.red),
              const SizedBox(width: 8),
              Text('Déconnexion', style: TextStyle(color: Colors.red)),
            ],
          ),
        ),
      ],
    ).then((value) {
      if (value != null) {
        _handleMenuAction(context, value);
      }
    });
  }

  /// Gère les actions du menu utilisateur
  void _handleMenuAction(BuildContext context, String action) {
    switch (action) {
      case 'profile':
        // Naviguer vers la page de paramètres avec l'onglet Profil
        context.go('/settings?tab=profile');
        break;
      case 'settings':
        // Naviguer vers les paramètres
        context.go('/settings');
        break;
      case 'logout':
        _showLogoutDialog(context);
        break;
    }
  }

  /// Affiche la boîte de dialogue de confirmation de déconnexion
  void _showLogoutDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Déconnexion'),
          content: const Text('Êtes-vous sûr de vouloir vous déconnecter ?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Annuler'),
            ),
            TextButton(
              onPressed: () async {
                Navigator.of(context).pop();
                await UserService.instance.logout();
                if (context.mounted) {
                  context.go('/auth/login');
                }
              },
              child: const Text('Déconnexion', style: TextStyle(color: Colors.red)),
            ),
          ],
        );
      },
    );
  }
}
