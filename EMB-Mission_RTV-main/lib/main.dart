import 'package:emb_mission_dashboard/bootstrap.dart';
import 'package:emb_mission_dashboard/features/app/app.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
// We intentionally ignore `depend_on_referenced_packages` here because
// `flutter_web_plugins` is provided by the Flutter SDK and used only on web
// to configure a hashless URL strategy. Adding it to pubspec.yaml is not
// necessary for non-web targets in this project.
// ignore: depend_on_referenced_packages
import 'package:flutter_web_plugins/url_strategy.dart';

void main() {
  // Remove '#' from Flutter web URLs using the path URL strategy.
  if (kIsWeb) {
    //
    //ignore: prefer_const_constructors
    setUrlStrategy(PathUrlStrategy());
  }
  bootstrap(() => const App());
}
