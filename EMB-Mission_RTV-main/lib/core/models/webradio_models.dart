import 'package:flutter/material.dart';

/// Modèles de données pour WebRadio (AzuraCast)

/// Informations du flux en cours de lecture
class CurrentStream {
  final String title;
  final String artist;
  final String? artworkUrl;
  final String? album;
  final int duration; // en secondes
  final int elapsed; // en secondes
  final bool isLive;
  final int listeners;

  CurrentStream({
    required this.title,
    required this.artist,
    this.artworkUrl,
    this.album,
    required this.duration,
    required this.elapsed,
    required this.isLive,
    required this.listeners,
  });

  factory CurrentStream.fromJson(Map<String, dynamic> json) {
    return CurrentStream(
      title: json['title'] as String? ?? 'Titre inconnu',
      artist: json['artist'] as String? ?? 'Artiste inconnu',
      artworkUrl: json['artwork_url'] as String?,
      album: json['album'] as String?,
      duration: json['duration'] as int? ?? 0,
      elapsed: json['elapsed'] as int? ?? 0,
      isLive: json['is_live'] as bool? ?? false,
      listeners: json['listeners'] as int? ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'title': title,
      'artist': artist,
      'artwork_url': artworkUrl,
      'album': album,
      'duration': duration,
      'elapsed': elapsed,
      'is_live': isLive,
      'listeners': listeners,
    };
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

/// Historique d'une piste
class TrackHistory {
  final String title;
  final String artist;
  final String? artworkUrl;
  final DateTime playedAt;
  final int duration; // en secondes

  TrackHistory({
    required this.title,
    required this.artist,
    this.artworkUrl,
    required this.playedAt,
    required this.duration,
  });

  factory TrackHistory.fromJson(Map<String, dynamic> json) {
    return TrackHistory(
      title: json['title'] as String? ?? 'Titre inconnu',
      artist: json['artist'] as String? ?? 'Artiste inconnu',
      artworkUrl: json['artwork_url'] as String?,
      playedAt: json['played_at'] != null
          ? DateTime.parse(json['played_at'] as String)
          : DateTime.now(),
      duration: json['duration'] as int? ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'title': title,
      'artist': artist,
      'artwork_url': artworkUrl,
      'played_at': playedAt.toIso8601String(),
      'duration': duration,
    };
  }

  /// Retourne l'heure de lecture formatée (HH:MM)
  String get timeFormatted {
    return '${playedAt.hour.toString().padLeft(2, '0')}:${playedAt.minute.toString().padLeft(2, '0')}';
  }

  /// Retourne la première lettre du titre (pour la miniature)
  String get thumbnailText {
    return title.isNotEmpty ? title[0].toUpperCase() : '?';
  }
}

/// Informations de diffusion
class BroadcastInfo {
  final int port;
  final String mountPoint;
  final String m3uUrl;
  final String listenUrl;
  final String publicPlayerUrl;
  final String streamUrl;
  final int bitrate;
  final String format;

  BroadcastInfo({
    required this.port,
    required this.mountPoint,
    required this.m3uUrl,
    required this.listenUrl,
    required this.publicPlayerUrl,
    required this.streamUrl,
    required this.bitrate,
    required this.format,
  });

  factory BroadcastInfo.fromJson(Map<String, dynamic> json) {
    return BroadcastInfo(
      port: json['port'] as int? ?? 8000,
      mountPoint: json['mount_point'] as String? ?? '/stream',
      m3uUrl: json['m3u_url'] as String? ?? '',
      listenUrl: json['listen_url'] as String? ?? '',
      publicPlayerUrl: json['public_player_url'] as String? ?? '',
      streamUrl: json['stream_url'] as String? ?? '',
      bitrate: json['bitrate'] as int? ?? 128,
      format: json['format'] as String? ?? 'mp3',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'port': port,
      'mount_point': mountPoint,
      'm3u_url': m3uUrl,
      'listen_url': listenUrl,
      'public_player_url': publicPlayerUrl,
      'stream_url': streamUrl,
      'bitrate': bitrate,
      'format': format,
    };
  }
  
  /// Retourne le port formaté en String
  String get portFormatted => port.toString();
  
  /// Retourne le bitrate formaté (ex: "192 kbps")
  String get bitrateFormatted => '$bitrate kbps';
}

/// Source radio pour la diffusion
class RadioSource {
  final int id;
  final String name;
  final String url;
  final bool isActive;
  final DateTime createdAt;
  final DateTime updatedAt;

  RadioSource({
    required this.id,
    required this.name,
    required this.url,
    required this.isActive,
    required this.createdAt,
    required this.updatedAt,
  });

  factory RadioSource.fromJson(Map<String, dynamic> json) {
    return RadioSource(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      url: json['url'] as String? ?? '',
      isActive: json['is_active'] as bool? ?? false,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'url': url,
      'is_active': isActive,
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt.toIso8601String(),
    };
  }

  /// Retourne le statut formaté
  String get statusText => isActive ? 'Active' : 'Inactive';
  
  /// Retourne la couleur du statut
  Color get statusColor => isActive ? const Color(0xFF10B981) : const Color(0xFF6B7280);
}

/// Paramètres de streaming pour la configuration
class StreamingSettings {
  final String stationName;
  final String mountPoint;
  final int bitrate;
  final String format;
  final String frontend;
  final String backend;
  final String serverAddress;
  final int port;
  final String username;
  final String password;
  final String m3uUrl;

  StreamingSettings({
    required this.stationName,
    required this.mountPoint,
    required this.bitrate,
    required this.format,
    required this.frontend,
    required this.backend,
    required this.serverAddress,
    required this.port,
    required this.username,
    required this.password,
    required this.m3uUrl,
  });

  factory StreamingSettings.fromJson(Map<String, dynamic> json) {
    // Utiliser les paramètres DJ si disponibles, sinon utiliser les paramètres généraux
    final djSettings = json['dj_settings'] as Map<String, dynamic>?;
    
    return StreamingSettings(
      stationName: json['station_name'] as String? ?? 'Radio EMB Mission',
      mountPoint: djSettings?['mount_point'] as String? ?? json['mount_point'] as String? ?? '/radio.mp3',
      bitrate: json['bitrate'] as int? ?? 192,
      format: json['format'] as String? ?? 'mp3',
      frontend: json['frontend'] as String? ?? 'icecast',
      backend: json['backend'] as String? ?? 'liquidsoap',
      serverAddress: djSettings?['server_address'] as String? ?? json['server_address'] as String? ?? 'radio.embmission.com',
      port: djSettings?['port'] as int? ?? json['port'] as int? ?? 8000,
      username: djSettings?['username'] as String? ?? json['username'] as String? ?? 'source',
      password: djSettings?['password'] as String? ?? json['password'] as String? ?? 'hackme',
      m3uUrl: json['m3u_url'] as String? ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'station_name': stationName,
      'mount_point': mountPoint,
      'bitrate': bitrate,
      'format': format,
      'frontend': frontend,
      'backend': backend,
      'server_address': serverAddress,
      'port': port,
      'username': username,
      'password': password,
      'm3u_url': m3uUrl,
    };
  }

  /// Retourne le bitrate formaté (ex: "192 kbps")
  String get bitrateFormatted => '$bitrate kbps';
  
  /// Retourne le port formaté en String
  String get portFormatted => port.toString();
}

