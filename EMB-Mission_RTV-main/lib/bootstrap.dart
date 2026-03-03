import 'dart:async';
import 'dart:developer';

import 'package:bloc/bloc.dart';
import 'package:emb_mission_dashboard/core/services/auth_service.dart';
import 'package:emb_mission_dashboard/core/services/azuracast_service.dart';
import 'package:emb_mission_dashboard/core/services/media_service.dart';
import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:emb_mission_dashboard/core/services/webradio_service.dart';
import 'package:emb_mission_dashboard/core/services/webtv_service.dart';
import 'package:flutter/widgets.dart';

class AppBlocObserver extends BlocObserver {
  const AppBlocObserver();

  @override
  void onChange(BlocBase<dynamic> bloc, Change<dynamic> change) {
    super.onChange(bloc, change);
    log('onChange(${bloc.runtimeType}, $change)');
  }

  @override
  void onError(BlocBase<dynamic> bloc, Object error, StackTrace stackTrace) {
    log('onError(${bloc.runtimeType}, $error, $stackTrace)');
    super.onError(bloc, error, stackTrace);
  }
}

Future<void> bootstrap(FutureOr<Widget> Function() builder) async {
  FlutterError.onError = (details) {
    log(details.exceptionAsString(), stackTrace: details.stack);
  };

  Bloc.observer = const AppBlocObserver();

  // Initialisation des services
  AuthService.instance.initialize();
  await UserService.instance.initialize();
  WebRadioService.instance.initialize();
  AzuraCastService.instance.initialize();
  MediaService.instance.initialize();
  WebTvService.instance.initialize();

          // Add cross-flavor configuration here

          runApp(await builder());
}
