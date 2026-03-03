/// Modèles de données pour la WebTV (Ant Media Server)
/// 
/// Gère les données des streams vidéo en direct

/// Informations d'un stream WebTV
class WebTvStream {
  final int id;
  final String streamId;
  final String title;
  final String description;
  final String status;
  final bool isLive;
  final DateTime? startTime;
  final DateTime? endTime;
  final String playbackUrl;
  final String webrtcUrl;
  final String thumbnailUrl;
  final String? embedCode;
  final bool isFeatured;
  final DateTime createdAt;
  final DateTime updatedAt;

  WebTvStream({
    required this.id,
    required this.streamId,
    required this.title,
    required this.description,
    required this.status,
    required this.isLive,
    this.startTime,
    this.endTime,
    required this.playbackUrl,
    required this.webrtcUrl,
    required this.thumbnailUrl,
    this.embedCode,
    required this.isFeatured,
    required this.createdAt,
    required this.updatedAt,
  });

  factory WebTvStream.fromJson(Map<String, dynamic> json) {
    return WebTvStream(
      id: json['id'] as int,
      streamId: json['stream_id'] as String,
      title: json['title'] as String? ?? 'Sans titre',
      description: json['description'] as String? ?? '',
      status: json['status'] as String? ?? 'offline',
      isLive: json['is_live'] as bool? ?? false,
      startTime: json['start_time'] != null 
        ? DateTime.parse(json['start_time'] as String)
        : null,
      endTime: json['end_time'] != null 
        ? DateTime.parse(json['end_time'] as String)
        : null,
      playbackUrl: json['playback_url'] as String? ?? '',
      webrtcUrl: json['webrtc_url'] as String? ?? '',
      thumbnailUrl: json['thumbnail_url'] as String? ?? '',
      embedCode: json['embed_code'] as String?,
      isFeatured: json['is_featured'] as bool? ?? false,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'stream_id': streamId,
      'title': title,
      'description': description,
      'status': status,
      'is_live': isLive,
      'start_time': startTime?.toIso8601String(),
      'end_time': endTime?.toIso8601String(),
      'playback_url': playbackUrl,
      'webrtc_url': webrtcUrl,
      'thumbnail_url': thumbnailUrl,
      'embed_code': embedCode,
      'is_featured': isFeatured,
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt.toIso8601String(),
    };
  }
}

/// Statut d'un stream WebTV
class WebTvStreamStatus {
  final String streamId;
  final String status;
  final bool isLive;

  WebTvStreamStatus({
    required this.streamId,
    required this.status,
    required this.isLive,
  });

  factory WebTvStreamStatus.fromJson(Map<String, dynamic> json) {
    return WebTvStreamStatus(
      streamId: json['stream_id'] as String,
      status: json['status'] as String? ?? 'offline',
      isLive: json['is_live'] as bool? ?? false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'stream_id': streamId,
      'status': status,
      'is_live': isLive,
    };
  }
}

/// Connexion au serveur Ant Media
class WebTvConnection {
  final bool connected;
  final String antMediaUrl;

  WebTvConnection({
    required this.connected,
    required this.antMediaUrl,
  });

  factory WebTvConnection.fromJson(Map<String, dynamic> json) {
    return WebTvConnection(
      connected: json['connected'] as bool? ?? false,
      antMediaUrl: json['ant_media_url'] as String? ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'connected': connected,
      'ant_media_url': antMediaUrl,
    };
  }
}

/// Code d'embedding pour un stream
class WebTvEmbedCode {
  final String streamId;
  final String embedCode;
  final int width;
  final int height;

  WebTvEmbedCode({
    required this.streamId,
    required this.embedCode,
    required this.width,
    required this.height,
  });

  factory WebTvEmbedCode.fromJson(Map<String, dynamic> json) {
    return WebTvEmbedCode(
      streamId: json['stream_id'] as String,
      embedCode: json['embed_code'] as String,
      width: json['width'] as int? ?? 100,
      height: json['height'] as int? ?? 100,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'stream_id': streamId,
      'embed_code': embedCode,
      'width': width,
      'height': height,
    };
  }
}

/// Information de pagination
class PaginationInfo {
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;

  PaginationInfo({
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
  });

  factory PaginationInfo.fromJson(Map<String, dynamic> json) {
    return PaginationInfo(
      currentPage: json['current_page'] as int,
      perPage: json['per_page'] as int,
      total: json['total'] as int,
      lastPage: json['last_page'] as int,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'current_page': currentPage,
      'per_page': perPage,
      'total': total,
      'last_page': lastPage,
    };
  }
}

/// Réponse de la liste des streams avec pagination
class WebTvStreamsResponse {
  final List<WebTvStream> streams;
  final PaginationInfo pagination;

  WebTvStreamsResponse({
    required this.streams,
    required this.pagination,
  });

  factory WebTvStreamsResponse.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as List<dynamic>;
    final streams = data.map((item) => WebTvStream.fromJson(item as Map<String, dynamic>)).toList();
    final pagination = PaginationInfo.fromJson(json['pagination'] as Map<String, dynamic>);

    return WebTvStreamsResponse(
      streams: streams,
      pagination: pagination,
    );
  }
}

/// Élément d'une playlist WebTV
class WebTvPlaylistItem {
  final int id;
  final String title;
  final String? artist;
  final int duration;
  final String? quality;
  final String? uniqueId;
  final int order;
  final int? videoFileId; // 🔥 ID du fichier vidéo associé

  WebTvPlaylistItem({
    required this.id,
    required this.title,
    this.artist,
    required this.duration,
    this.quality,
    this.uniqueId,
    required this.order,
    this.videoFileId,
  });

  factory WebTvPlaylistItem.fromJson(Map<String, dynamic> json) {
    final rawDuration = json['duration'];
    final parsedDuration = _parseDuration(rawDuration);

    return WebTvPlaylistItem(
      id: json['id'] as int,
      title: json['title'] as String,
      artist: json['artist'] as String?,
      duration: parsedDuration ?? 0,
      quality: json['quality'] as String?,
      uniqueId: json['unique_id'] as String?,
      order: json['order'] as int? ?? 0,
      videoFileId: json['video_file_id'] as int?, // 🔥 Nouveau champ
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'title': title,
      'artist': artist,
      'duration': duration,
      'quality': quality,
      'unique_id': uniqueId,
      'order': order,
      'video_file_id': videoFileId, // 🔥 Nouveau champ
    };
  }

  static int? _parseDuration(dynamic value) {
    if (value == null) return null;
    if (value is num) {
      if (value.isNaN) return null;
      return value.round();
    }
    if (value is String) {
      if (value.contains(':')) {
        final parts = value.split(':').map((part) => part.trim()).toList();
        try {
          if (parts.length == 3) {
            final hours = int.parse(parts[0]);
            final minutes = int.parse(parts[1]);
            final seconds = int.parse(parts[2]);
            return hours * 3600 + minutes * 60 + seconds;
          }
          if (parts.length == 2) {
            final minutes = int.parse(parts[0]);
            final seconds = int.parse(parts[1]);
            return minutes * 60 + seconds;
          }
        } catch (_) {
          return null;
        }
      }

      final parsedInt = int.tryParse(value);
      if (parsedInt != null) return parsedInt;
      final parsedDouble = double.tryParse(value);
      if (parsedDouble != null && !parsedDouble.isNaN) {
        return parsedDouble.round();
      }
      return null;
    }
    return null;
  }
}

/// Playlist WebTV
class WebTvPlaylist {
  final int id;
  final String name;
  final String? description;
  final String type;
  final String? quality;
  final bool isActive;
  final bool isLoop;
  final bool isShuffle;
  final List<WebTvPlaylistItem> items;
  final DateTime createdAt;
  final DateTime updatedAt;

  WebTvPlaylist({
    required this.id,
    required this.name,
    this.description,
    required this.type,
    this.quality,
    required this.isActive,
    this.isLoop = false,
    this.isShuffle = false,
    required this.items,
    required this.createdAt,
    required this.updatedAt,
  });

  factory WebTvPlaylist.fromJson(Map<String, dynamic> json) {
    final itemsJson = json['items'] as List<dynamic>? ?? [];
    final items = itemsJson.map((item) => WebTvPlaylistItem.fromJson(item as Map<String, dynamic>)).toList();

    return WebTvPlaylist(
      id: json['id'] as int,
      name: json['name'] as String,
      description: json['description'] as String?,
      type: json['type'] as String? ?? 'vod',
      quality: json['quality'] as String?,
      isActive: json['is_active'] as bool? ?? false,
      isLoop: json['is_loop'] as bool? ?? false,
      isShuffle: json['shuffle_enabled'] as bool? ?? false, // Backend utilise 'shuffle_enabled'
      items: items,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'description': description,
      'type': type,
      'quality': quality,
      'is_active': isActive,
      'is_loop': isLoop,
      'is_shuffle': isShuffle,
      'items': items.map((item) => item.toJson()).toList(),
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt.toIso8601String(),
    };
  }
}

/// Réponse de la liste des playlists WebTV
class WebTvPlaylistsResponse {
  final bool success;
  final String message;
  final List<WebTvPlaylist> data;

  WebTvPlaylistsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory WebTvPlaylistsResponse.fromJson(Map<String, dynamic> json) {
    return WebTvPlaylistsResponse(
      success: json['success'] ?? false,
      message: json['message'] ?? '',
      data: (json['data'] as List<dynamic>?)
          ?.map((item) => WebTvPlaylist.fromJson(item))
          .toList() ?? [],
    );
  }
}

/// Modèle pour les paramètres de connexion OBS
class WebTvObsParams {
  final String serverUrl;
  final String streamKey;
  final String username;
  final String password;
  final String serverName;
  final String protocol;
  final int port;
  final String application;
  final bool authenticationRequired;
  final String bitrateRecommended;
  final String resolutionRecommended;
  final String fpsRecommended;
  final String encoderRecommended;
  final String keyframeInterval;
  final String audioBitrate;
  final String videoCodec;
  final String audioCodec;
  final String profile;
  final String preset;
  final String tune;
  final List<String> instructions;

  WebTvObsParams({
    required this.serverUrl,
    required this.streamKey,
    required this.username,
    required this.password,
    required this.serverName,
    required this.protocol,
    required this.port,
    required this.application,
    required this.authenticationRequired,
    required this.bitrateRecommended,
    required this.resolutionRecommended,
    required this.fpsRecommended,
    required this.encoderRecommended,
    required this.keyframeInterval,
    required this.audioBitrate,
    required this.videoCodec,
    required this.audioCodec,
    required this.profile,
    required this.preset,
    required this.tune,
    required this.instructions,
  });

  factory WebTvObsParams.fromJson(Map<String, dynamic> json) {
    return WebTvObsParams(
      serverUrl: json['server_url'] ?? '',
      streamKey: json['stream_key'] ?? '',
      username: json['username'] ?? '',
      password: json['password'] ?? '',
      serverName: json['server_name'] ?? '',
      protocol: json['protocol'] ?? '',
      port: json['port'] ?? 1935,
      application: json['application'] ?? '',
      authenticationRequired: json['authentication_required'] ?? false,
      bitrateRecommended: json['bitrate_recommended'] ?? '',
      resolutionRecommended: json['resolution_recommended'] ?? '',
      fpsRecommended: json['fps_recommended'] ?? '',
      encoderRecommended: json['encoder_recommended'] ?? '',
      keyframeInterval: json['keyframe_interval'] ?? '',
      audioBitrate: json['audio_bitrate'] ?? '',
      videoCodec: json['video_codec'] ?? '',
      audioCodec: json['audio_codec'] ?? '',
      profile: json['profile'] ?? '',
      preset: json['preset'] ?? '',
      tune: json['tune'] ?? '',
      instructions: (json['instructions'] as List<dynamic>?)
          ?.map((item) => item.toString())
          .toList() ?? [],
    );
  }
}

/// Réponse API pour les paramètres OBS
class WebTvObsParamsResponse {
  final bool success;
  final String message;
  final WebTvObsParams data;

  WebTvObsParamsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory WebTvObsParamsResponse.fromJson(Map<String, dynamic> json) {
    return WebTvObsParamsResponse(
      success: json['success'] ?? false,
      message: json['message'] ?? '',
      data: WebTvObsParams.fromJson(json['data'] ?? {}),
    );
  }
}






