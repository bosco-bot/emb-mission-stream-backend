import 'package:flutter/material.dart';

/// Utilitaires pour la gestion responsive de l'application
/// 
/// Définit les breakpoints et méthodes pour adapter l'interface
/// selon la taille de l'écran.
class ResponsiveUtils {
  ResponsiveUtils._();

  // ========================================
  // BREAKPOINTS STANDARDS
  // ========================================
  
  /// Mobile : < 768px
  static const double mobileBreakpoint = 768;
  
  /// Tablet : 768px - 1024px
  static const double tabletBreakpoint = 1024;
  
  /// Desktop : 1024px - 1440px
  static const double desktopBreakpoint = 1440;
  
  /// Large Desktop : > 1440px
  static const double largeDesktopBreakpoint = 1440;

  // ========================================
  // MÉTHODES DE DÉTECTION
  // ========================================
  
  /// Vérifie si l'écran est mobile
  static bool isMobile(BuildContext context) {
    return MediaQuery.sizeOf(context).width < mobileBreakpoint;
  }
  
  /// Vérifie si l'écran est tablette
  static bool isTablet(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    return width >= mobileBreakpoint && width < tabletBreakpoint;
  }
  
  /// Vérifie si l'écran est desktop
  static bool isDesktop(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    return width >= tabletBreakpoint && width < largeDesktopBreakpoint;
  }
  
  /// Vérifie si l'écran est large desktop
  static bool isLargeDesktop(BuildContext context) {
    return MediaQuery.sizeOf(context).width >= largeDesktopBreakpoint;
  }

  // ========================================
  // DIMENSIONS RESPONSIVE
  // ========================================
  
  /// Largeur de la sidebar selon le breakpoint
  static double getSidebarWidth(BuildContext context) {
    if (isMobile(context)) {
      return 80; // Sidebar étroite sur mobile (icônes uniquement)
    } else if (isTablet(context)) {
      return 200; // Sidebar normale sur tablette (texte + icônes)
    } else if (isDesktop(context)) {
      return 240; // Sidebar normale sur desktop (texte + icônes)
    } else {
      return 280; // Sidebar normale sur grand écran (texte + icônes)
    }
  }
  
  /// Padding horizontal selon le breakpoint
  static EdgeInsets getHorizontalPadding(BuildContext context) {
    if (isMobile(context)) {
      return const EdgeInsets.symmetric(horizontal: 16);
    } else if (isTablet(context)) {
      return const EdgeInsets.symmetric(horizontal: 20);
    } else {
      return const EdgeInsets.symmetric(horizontal: 24);
    }
  }
  
  /// Padding vertical selon le breakpoint
  static EdgeInsets getVerticalPadding(BuildContext context) {
    if (isMobile(context)) {
      return const EdgeInsets.symmetric(vertical: 12);
    } else {
      return const EdgeInsets.symmetric(vertical: 16);
    }
  }
  
  /// Espacement entre les éléments selon le breakpoint
  static double getSpacing(BuildContext context) {
    if (isMobile(context)) {
      return 12;
    } else if (isTablet(context)) {
      return 16;
    } else {
      return 20;
    }
  }

  // ========================================
  // LAYOUTS RESPONSIVE
  // ========================================
  
  /// Nombre de colonnes pour les grilles selon le breakpoint
  static int getGridColumns(BuildContext context) {
    if (isMobile(context)) {
      return 1;
    } else if (isTablet(context)) {
      return 2;
    } else if (isDesktop(context)) {
      return 3;
    } else {
      return 4;
    }
  }
  
  /// Nombre de colonnes pour les stats selon le breakpoint
  static int getStatsColumns(BuildContext context) {
    if (isMobile(context)) {
      return 2;
    } else if (isTablet(context)) {
      return 2;
    } else {
      return 4;
    }
  }
  
  /// Largeur maximale du contenu principal
  static double getMaxContentWidth(BuildContext context) {
    if (isMobile(context)) {
      return double.infinity;
    } else if (isTablet(context)) {
      return 1200;
    } else {
      return 1400;
    }
  }

  // ========================================
  // TYPOGRAPHIE RESPONSIVE
  // ========================================
  
  /// Taille de police pour les titres selon le breakpoint
  static double getTitleFontSize(BuildContext context) {
    if (isMobile(context)) {
      return 20;
    } else if (isTablet(context)) {
      return 24;
    } else {
      return 28;
    }
  }
  
  /// Taille de police pour les sous-titres selon le breakpoint
  static double getSubtitleFontSize(BuildContext context) {
    if (isMobile(context)) {
      return 14;
    } else if (isTablet(context)) {
      return 16;
    } else {
      return 18;
    }
  }
  
  /// Taille de police pour le corps de texte selon le breakpoint
  static double getBodyFontSize(BuildContext context) {
    if (isMobile(context)) {
      return 12;
    } else if (isTablet(context)) {
      return 14;
    } else {
      return 16;
    }
  }

  // ========================================
  // UTILITAIRES
  // ========================================
  
  /// Retourne le type d'écran actuel (pour debug)
  static String getScreenType(BuildContext context) {
    if (isMobile(context)) return 'Mobile';
    if (isTablet(context)) return 'Tablet';
    if (isDesktop(context)) return 'Desktop';
    return 'Large Desktop';
  }
  
  /// Vérifie si on doit afficher la sidebar
  static bool shouldShowSidebar(BuildContext context) {
    return !isMobile(context);
  }
  
  /// Vérifie si on doit utiliser un drawer sur mobile
  static bool shouldUseDrawer(BuildContext context) {
    return isMobile(context);
  }
}
