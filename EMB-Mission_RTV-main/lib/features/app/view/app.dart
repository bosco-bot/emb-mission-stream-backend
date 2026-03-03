import 'package:emb_mission_dashboard/core/l10n/l10n.dart';
import 'package:emb_mission_dashboard/routes.dart';
import 'package:flutter/material.dart';

class App extends StatelessWidget {
  const App({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme:
            ColorScheme.fromSeed(
              seedColor: const Color(0xFF2B6CB0), // primary brand blue
            ).copyWith(
              surface: const Color(0xFFF6F8FB),
            ),
        scaffoldBackgroundColor: const Color(0xFFF6F8FB),
        appBarTheme: const AppBarTheme(
          backgroundColor: Colors.white,
          surfaceTintColor: Colors.transparent,
          elevation: 0,
          foregroundColor: Color(0xFF2F3B52),
        ),
        navigationRailTheme: const NavigationRailThemeData(
          backgroundColor: Color(0xFFF2F6FA), // light sidebar bg
          indicatorColor: Color(0xFFBFE6FA), // light blue selection
          indicatorShape: StadiumBorder(),
          selectedIconTheme: IconThemeData(
            color: Colors.white,
            size: 22,
          ),
          unselectedIconTheme: IconThemeData(
            color: Color(0xFF72819A),
            size: 22,
          ),
          selectedLabelTextStyle: TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w700,
          ),
          unselectedLabelTextStyle: TextStyle(
            color: Color(0xFF72819A),
            fontWeight: FontWeight.w600,
          ),
          elevation: 0,
          labelType: NavigationRailLabelType.all,
          groupAlignment: -1,
        ),
      ),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      routerConfig: router,
    );
  }
}
