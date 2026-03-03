import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart' show kIsWeb;

/// Service pour l'API AzuraCast
/// 
/// Gère la communication directe avec l'API AzuraCast pour récupérer
/// les métadonnées en temps réel du flux radio.
class AzuraCastService {
  AzuraCastService._();
  
  static final AzuraCastService instance = AzuraCastService._();
  
  late final Dio _dio;
  
  /// URL de base de l'API AzuraCast (via proxy Nginx HTTPS)
  /// Détecte automatiquement le domaine actuel (rtv.embmission.com ou radio.embmission.com)
  static String get _baseUrl {
    if (kIsWeb) {
      final host = Uri.base.host;
      // Utilise le domaine actuel pour appeler le proxy local
      if (host == 'rtv.embmission.com' || host == 'radio.embmission.com') {
        return 'https://$host/azuracast-api';
      }
      // En développement local, utiliser radio.embmission.com
      if (host == 'localhost' || host == '127.0.0.1') {
        return 'https://radio.embmission.com/azuracast-api';
      }
    }
    // Fallback par défaut
    return 'https://radio.embmission.com/azuracast-api';
  }
  
  /// Initialise le client HTTP Dio pour AzuraCast
  void initialize() {
    _dio = Dio(
      BaseOptions(
        baseUrl: _baseUrl,
        connectTimeout: const Duration(milliseconds: 10000),
        receiveTimeout: const Duration(milliseconds: 10000),
        sendTimeout: const Duration(milliseconds: 10000),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      ),
    );
    
    // Logs en développement
    _dio.interceptors.add(
      LogInterceptor(
        requestBody: false,
        responseBody: false,
        error: true,
        requestHeader: false,
        responseHeader: false,
      ),
    );
  }
  
  /// Récupère les informations de lecture en cours
  /// 
  /// Retourne :
  /// - [NowPlayingData] contenant toutes les métadonnées du flux
  /// - Lance une exception en cas d'erreur
  Future<NowPlayingData> getNowPlaying() async {
    try {
      final response = await _dio.get('/nowplaying/1');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        return NowPlayingData.fromJson(data);
      }
      
      throw Exception('Erreur lors de la récupération des métadonnées');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Gère les erreurs Dio et lance des exceptions appropriées
  void _handleDioError(DioException e) {
    if (e.response != null) {
      throw Exception('Erreur ${e.response?.statusCode}: ${e.response?.statusMessage}');
    }
    
    if (e.type == DioExceptionType.connectionTimeout) {
      throw Exception('Délai de connexion dépassé. Vérifiez votre connexion internet.');
    }
    
    if (e.type == DioExceptionType.receiveTimeout) {
      throw Exception('Délai de réception dépassé. Le serveur met trop de temps à répondre.');
    }
    
    if (e.type == DioExceptionType.connectionError) {
      throw Exception('Impossible de se connecter au serveur AzuraCast.');
    }
    
    throw Exception('Erreur réseau : ${e.message}');
  }
}

/// Modèle de données pour les informations AzuraCast
class NowPlayingData {
  final StationInfo station;
  final ListenerInfo listeners;
  final LiveInfo live;
  final NowPlayingSong nowPlaying;
  final List<SongHistory> songHistory;
  final bool isOnline;

  NowPlayingData({
    required this.station,
    required this.listeners,
    required this.live,
    required this.nowPlaying,
    required this.songHistory,
    required this.isOnline,
  });

  factory NowPlayingData.fromJson(Map<String, dynamic> json) {
    return NowPlayingData(
      station: StationInfo.fromJson(json['station'] as Map<String, dynamic>),
      listeners: ListenerInfo.fromJson(json['listeners'] as Map<String, dynamic>),
      live: LiveInfo.fromJson(json['live'] as Map<String, dynamic>),
      nowPlaying: NowPlayingSong.fromJson(json['now_playing'] as Map<String, dynamic>),
      songHistory: (json['song_history'] as List<dynamic>?)
          ?.map((item) => SongHistory.fromJson(item as Map<String, dynamic>))
          .toList() ?? [],
      isOnline: json['is_online'] as bool? ?? false,
    );
  }
}

/// Informations de la station
class StationInfo {
  final int id;
  final String name;
  final String shortcode;
  final String description;
  final String listenUrl;
  final String publicPlayerUrl;

  StationInfo({
    required this.id,
    required this.name,
    required this.shortcode,
    required this.description,
    required this.listenUrl,
    required this.publicPlayerUrl,
  });

  factory StationInfo.fromJson(Map<String, dynamic> json) {
    return StationInfo(
      id: json['id'] as int? ?? 1,
      name: json['name'] as String? ?? 'Radio EMB Mission',
      shortcode: json['shortcode'] as String? ?? '',
      description: json['description'] as String? ?? '',
      listenUrl: json['listen_url'] as String? ?? '',
      publicPlayerUrl: json['public_player_url'] as String? ?? '',
    );
  }
}

/// Informations des auditeurs
class ListenerInfo {
  final int total;
  final int unique;
  final int current;

  ListenerInfo({
    required this.total,
    required this.unique,
    required this.current,
  });

  factory ListenerInfo.fromJson(Map<String, dynamic> json) {
    return ListenerInfo(
      total: json['total'] as int? ?? 0,
      unique: json['unique'] as int? ?? 0,
      current: json['current'] as int? ?? 0,
    );
  }
}

/// Informations du live
class LiveInfo {
  final bool isLive;
  final String streamerName;
  final String? art;

  LiveInfo({
    required this.isLive,
    required this.streamerName,
    this.art,
  });

  factory LiveInfo.fromJson(Map<String, dynamic> json) {
    return LiveInfo(
      isLive: json['is_live'] as bool? ?? false,
      streamerName: json['streamer_name'] as String? ?? '',
      art: json['art'] as String?,
    );
  }
}

/// Chanson en cours de lecture
class NowPlayingSong {
  final SongInfo song;
  final int elapsed;
  final int remaining;
  final int duration;

  NowPlayingSong({
    required this.song,
    required this.elapsed,
    required this.remaining,
    required this.duration,
  });

  factory NowPlayingSong.fromJson(Map<String, dynamic> json) {
    return NowPlayingSong(
      song: SongInfo.fromJson(json['song'] as Map<String, dynamic>),
      elapsed: json['elapsed'] as int? ?? 0,
      remaining: json['remaining'] as int? ?? 0,
      duration: json['duration'] as int? ?? 0,
    );
  }

  /// Retourne le temps écoulé formaté (MM:SS)
  String get elapsedFormatted {
    final minutes = elapsed ~/ 60;
    final seconds = elapsed % 60;
    return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
  }

  /// Retourne la durée totale formatée (MM:SS)
  String get durationFormatted {
    final minutes = duration ~/ 60;
    final seconds = duration % 60;
    return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
  }

  /// Retourne le pourcentage de progression (0.0 à 1.0)
  double get progress {
    if (duration == 0) return 0.0;
    return elapsed / duration;
  }
}

/// Informations d'une chanson
class SongInfo {
  final String id;
  final String title;
  final String artist;
  final String album;
  final String art;

  SongInfo({
    required this.id,
    required this.title,
    required this.artist,
    required this.album,
    required this.art,
  });

  factory SongInfo.fromJson(Map<String, dynamic> json) {
    return SongInfo(
      id: json['id'] as String? ?? '',
      title: json['title'] as String? ?? 'Titre inconnu',
      artist: json['artist'] as String? ?? 'Artiste inconnu',
      album: json['album'] as String? ?? '',
      art: json['art'] as String? ?? '',
    );
  }
}

/// Historique des chansons
class SongHistory {
  final int shId;
  final int playedAt;
  final int duration;
  final SongInfo song;

  SongHistory({
    required this.shId,
    required this.playedAt,
    required this.duration,
    required this.song,
  });

  factory SongHistory.fromJson(Map<String, dynamic> json) {
    return SongHistory(
      shId: json['sh_id'] as int? ?? 0,
      playedAt: json['played_at'] as int? ?? 0,
      duration: json['duration'] as int? ?? 0,
      song: SongInfo.fromJson(json['song'] as Map<String, dynamic>),
    );
  }

  /// Retourne l'heure de lecture formatée (HH:MM)
  String get timeFormatted {
    final date = DateTime.fromMillisecondsSinceEpoch(playedAt * 1000);
    return '${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
  }

  /// Retourne la première lettre du titre (pour la miniature)
  String get thumbnailText {
    return song.title.isNotEmpty ? song.title[0].toUpperCase() : '?';
  }
}

