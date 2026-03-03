import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/utils/responsive_utils.dart';
import 'package:emb_mission_dashboard/core/widgets/emb_logo.dart';
import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:emb_mission_dashboard/features/analytics/view/analytics_page.dart';
import 'package:emb_mission_dashboard/features/auth/view/forget_password_page.dart';
import 'package:emb_mission_dashboard/features/auth/view/login_page.dart';
import 'package:emb_mission_dashboard/features/auth/view/register_page.dart';
import 'package:emb_mission_dashboard/features/auth/view/reset_password_page.dart';
import 'package:emb_mission_dashboard/features/contents/view/add_content_page.dart';
import 'package:emb_mission_dashboard/features/contents/view/contents_page.dart';
import 'package:emb_mission_dashboard/features/contents/view/playlist_builder_page.dart';
import 'package:emb_mission_dashboard/features/contents/view/radio_playlist_builder_page.dart';
import 'package:emb_mission_dashboard/features/contents/view/metadata_edit_page.dart';
import 'package:emb_mission_dashboard/features/dashboard/view/dashboard_page.dart';
import 'package:emb_mission_dashboard/features/diffusionlive/view/diffusionlive_page.dart';
import 'package:emb_mission_dashboard/features/diffusionlive/view/webtv_diffusionlive_page.dart';
import 'package:emb_mission_dashboard/features/home/view/home_page.dart';
import 'package:emb_mission_dashboard/features/profile/view/profile_page.dart';
import 'package:emb_mission_dashboard/features/settings/view/settings_page.dart';
import 'package:emb_mission_dashboard/features/settings/view/stream_config_page.dart';
import 'package:emb_mission_dashboard/features/webradio/view/webradio_page.dart';
import 'package:emb_mission_dashboard/features/webradio/view/webradio_sources_page.dart';
import 'package:emb_mission_dashboard/features/webradio/view/webradio_control_page.dart';
import 'package:emb_mission_dashboard/features/webtv/view/webtv_page.dart';
import 'package:emb_mission_dashboard/features/webtv/view/webtv_control_page.dart';
import 'package:emb_mission_dashboard/features/webtv/view/webtv_player_page.dart';
import 'package:emb_mission_dashboard/features/webtv/view/webtv_sources_page.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

final GlobalKey<NavigatorState> _shellNavigatorKey = GlobalKey<NavigatorState>(
  debugLabel: 'shell',
);

final router = GoRouter(
  initialLocation: '/',
  redirect: (context, state) {
    // Liste des routes publiques (accessibles sans authentification)
    final publicRoutes = ['/', '/auth', '/auth/register', '/auth/forget-password'];
    final isPublicRoute = publicRoutes.contains(state.uri.path);
    
    // Vérifier si l'utilisateur est connecté
    final isLoggedIn = UserService.instance.isLoggedIn;
    
    // Si la route est publique, permettre l'accès
    if (isPublicRoute) {
      return null;
    }
    
    // Si la route nécessite une authentification et que l'utilisateur n'est pas connecté
    if (!isLoggedIn) {
      return '/auth';
    }
    
    // Permettre l'accès si connecté
    return null;
  },
  routes: [
    GoRoute(
      path: '/',
      builder: (context, state) => const HomePage(),
    ),
    GoRoute(
      path: '/auth',
      builder: (context, state) => const LoginPage(),
      routes: [
        GoRoute(
          path: 'register',
          builder: (context, state) => const RegisterPage(),
        ),
        GoRoute(
          path: 'forget-password',
          builder: (context, state) => const ForgetPasswordPage(),
        ),
        GoRoute(
          path: 'reset-password',
          builder: (context, state) => const ResetPasswordPage(),
        ),
      ],
    ),
    ShellRoute(
      navigatorKey: _shellNavigatorKey,
      builder: (context, state, child) =>
          ScaffoldWithMainNavigationBar(child: child),
      routes: [
        GoRoute(
          path: '/dashboard',
          builder: (context, state) => const DashboardPage(),
        ),
        GoRoute(
          path: '/profile',
          builder: (context, state) => const ProfilePage(),
        ),
        GoRoute(
          path: '/settings',
          builder: (context, state) => const SettingsPage(),
        ),
        GoRoute(
          path: '/contents',
          builder: (context, state) => const ContentsPage(),
          routes: [
            GoRoute(
              path: 'add',
              builder: (context, state) => const AddContentPage(),
            ),
            GoRoute(
              path: 'playlist-builder',
              builder: (context, state) => const PlaylistBuilderPage(),
            ),
            GoRoute(
              path: 'radio-playlist-builder',
              builder: (context, state) => const RadioPlaylistBuilderPage(),
            ),
            GoRoute(
              path: 'metadata-edit',
              builder: (context, state) => const MetadataEditPage(),
            ),
          ],
        ),
        GoRoute(
          path: '/diffusionlive',
          builder: (context, state) => const DiffusionlivePage(),
        ),
        GoRoute(
          path: '/analytics',
          builder: (context, state) => const AnalyticsPage(),
        ),
        GoRoute(
          path: '/webtv',
          builder: (context, state) => const WebtvPage(),
          routes: [
            GoRoute(
              path: 'player',
              builder: (context, state) => const WebtvPlayerPage(),
            ),
            GoRoute(
              path: 'sources',
              builder: (context, state) => const WebtvSourcesPage(),
            ),
            GoRoute(
              path: 'control',
              builder: (context, state) => const WebtvControlPage(),
            ),
            GoRoute(
              path: 'diffusionlive',
              builder: (context, state) => const WebTvDiffusionLivePage(),
            ),
          ],
        ),
        GoRoute(
          path: '/webradio',
          builder: (context, state) => const WebradioPage(),
          routes: [
            GoRoute(
              path: 'sources',
              builder: (context, state) => const WebradioSourcesPage(),
            ),
            GoRoute(
              path: 'control',
              builder: (context, state) => const WebradioControlPage(),
            ),
          ],
        ),
        GoRoute(
          path: '/settings/stream-config',
          builder: (context, state) => const StreamConfigPage(),
        ),
      ],
    ),
  ],
);

class ScaffoldWithMainNavigationBar extends StatelessWidget {
  const ScaffoldWithMainNavigationBar({
    required this.child,
    super.key,
  });

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final isMobile = ResponsiveUtils.isMobile(context);
    final sidebarWidth = ResponsiveUtils.getSidebarWidth(context);

    return Scaffold(
      body: SafeArea(
        child: Row(
          children: [
            // Sidebar (toujours visible)
            Container(
              width: sidebarWidth,
              decoration: const BoxDecoration(
                color: Color(0xFFF2F6FA),
                border: Border(
                  right: BorderSide(color: Color(0xFFE3E8EF)),
                ),
              ),
              child: _buildSidebarContent(context),
            ),
            // Contenu principal
            Expanded(child: child),
          ],
        ),
      ),
    );
  }

  /// Contenu de la sidebar responsive
  Widget _buildSidebarContent(BuildContext context) {
    final isMobile = ResponsiveUtils.isMobile(context);
    
    return Column(
      children: [
        // Header avec logo
        Container(
          decoration: BoxDecoration(
            color: AppTheme.bgBlueColor,
          ),
          child: isMobile 
            ? Center(
                child: Padding(
                  padding: const EdgeInsets.all(8.0),
                  child: EmbLogo(size: 32),
                ),
              )
            : Row(
                children: [
                  Padding(
                    padding: const EdgeInsets.all(8.0),
                    child: EmbLogo(size: 40),
                  ),
                  const Expanded(
                    child: Text(
                      'EMB-MISSION',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
        ),
        const Gap(16),
        
        // Navigation items (icônes sur mobile, texte+icônes sur desktop)
        Expanded(
          child: SingleChildScrollView(
            child: Column(
              children: [
                if (isMobile) ...[
                  // Mode mobile : icônes uniquement
                  _IconOnlyNavTile(
                    icon: Icons.home,
                    route: '/dashboard',
                  ),
                  _IconOnlyNavTile(
                    icon: Icons.live_tv,
                    route: '/webtv',
                  ),
                  _IconOnlyNavTile(
                    icon: Icons.radio,
                    route: '/webradio',
                  ),
                  _IconOnlyNavTile(
                    icon: Icons.trending_up,
                    route: '/analytics',
                  ),
                  _IconOnlyNavTile(
                    icon: Icons.wifi_tethering,
                    route: '/diffusionlive',
                  ),
                  _IconOnlyNavTile(
                    icon: Icons.folder,
                    route: '/contents',
                  ),
                  _IconOnlyNavTile(
                    icon: Icons.settings,
                    route: '/settings',
                  ),
                ] else ...[
                  // Mode desktop/tablet : texte + icônes
                  const SidebarNavTile(
                    icon: Icons.home,
                    label: 'Dashboard',
                    route: '/dashboard',
                  ),
                  const SidebarNavTile(
                    icon: Icons.live_tv,
                    label: 'WebTV',
                    route: '/webtv',
                  ),
                  const SidebarNavTile(
                    icon: Icons.radio,
                    label: 'WebRadio',
                    route: '/webradio',
                  ),
                  const SidebarNavTile(
                    icon: Icons.trending_up,
                    label: 'Analytics',
                    route: '/analytics',
                  ),
                  const SidebarNavTile(
                    icon: Icons.wifi_tethering,
                    label: 'Diffusion Live',
                    route: '/diffusionlive',
                  ),
                  const SidebarNavTile(
                    icon: Icons.folder,
                    label: 'Contenu',
                    route: '/contents',
                  ),
                  const SidebarNavTile(
                    icon: Icons.settings,
                    label: 'Paramètres',
                    route: '/settings',
                  ),
                ],
              ],
            ),
          ),
        ),
        
        // Section Premium (icône sur mobile, texte sur desktop)
        if (isMobile)
          _buildIconOnlyPremiumSection(context)
        else
          _buildFullPremiumSection(context),
      ],
    );
  }

  /// Section Premium avec icône uniquement (mobile)
  Widget _buildIconOnlyPremiumSection(BuildContext context) {
    return Container(
      margin: const EdgeInsets.all(8),
      child: Center(
        child: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: AppTheme.blueColor,
            borderRadius: BorderRadius.circular(12),
          ),
          child: IconButton(
            onPressed: null,
            icon: const Icon(
              Icons.star,
              color: Color(0xFF2F3B52),
              size: 20,
            ),
            tooltip: 'Passez au Premium',
          ),
        ),
      ),
    );
  }

  /// Section Premium avec texte complet (desktop/tablet)
  Widget _buildFullPremiumSection(BuildContext context) {
    final isTablet = ResponsiveUtils.isTablet(context);
    
    return Container(
      margin: EdgeInsets.all(isTablet ? 12 : 16),
      padding: EdgeInsets.all(isTablet ? 12 : 16),
      decoration: BoxDecoration(
        color: AppTheme.blueColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            'Passez au Premium',
            style: TextStyle(
              color: const Color(0xFF2F3B52),
              fontWeight: FontWeight.w700,
              fontSize: isTablet ? 12 : 14,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const Gap(4),
          Text(
            'Débloquez toutes les fonctionnalités et diffusez sans limites.',
            style: const TextStyle(
              color: Color(0xFF2F3B52),
              fontSize: 12,
            ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          const Gap(8),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: null,
              style: ButtonStyle(
                backgroundColor: const WidgetStatePropertyAll(Color(0xFF2F3B52)),
                foregroundColor: const WidgetStatePropertyAll(Colors.white),
                padding: WidgetStatePropertyAll(
                  EdgeInsets.symmetric(vertical: isTablet ? 6 : 8),
                ),
              ),
              child: Text(
                'Mettre à niveau',
                style: TextStyle(
                  fontSize: isTablet ? 10 : 12,
                  fontWeight: FontWeight.w600,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ),
        ],
      ),
    );
  }

}

/// Widget de navigation avec icône uniquement
class _IconOnlyNavTile extends StatelessWidget {
  const _IconOnlyNavTile({
    required this.icon,
    required this.route,
  });

  final IconData icon;
  final String route;

  @override
  Widget build(BuildContext context) {
    final location = GoRouterState.of(context).uri.toString();
    final isSelected = location == route || location.startsWith(route);

    const selectedBg = Color(0xFFB4E3F9);
    const selectedFg = Color(0xFF2F3B52);
    const unselectedFg = Color(0xFF4A5568);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: () => context.go(route),
          child: Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: isSelected ? selectedBg : Colors.transparent,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              icon,
              color: isSelected ? selectedFg : unselectedFg,
              size: 24,
            ),
          ),
        ),
      ),
    );
  }
}

class SidebarNavTile extends StatelessWidget {
  const SidebarNavTile({
    required this.icon,
    required this.label,
    required this.route,
    super.key,
  });

  final IconData icon;
  final String label;
  final String route;

  @override
  Widget build(BuildContext context) {
    final location = GoRouterState.of(context).uri.toString();
    final isSelected = location == route || location.startsWith(route);

    const selectedBg = Color(0xFFB4E3F9);
    const selectedFg = Color(0xFF2F3B52);
    const unselectedFg = Color(0xFF4A5568);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(12),

          onTap: () => context.go(route),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            decoration: BoxDecoration(
              color: isSelected ? selectedBg : Colors.transparent,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              children: [
                Icon(
                  icon,
                  color: isSelected ? selectedFg : unselectedFg,
                  size: 22,
                ),
                const Gap(12),
                Expanded(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontWeight: isSelected
                          ? FontWeight.bold
                          : FontWeight.w500,
                      color: isSelected ? selectedFg : unselectedFg,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
