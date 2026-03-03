import 'package:flutter/material.dart';
import 'dart:convert';
import 'dart:math' as math;

/// Modèles de données pour la médiathèque (Upload et gestion de fichiers)

/// Fichier média (vidéo, audio, image)
class MediaFile {
  final int id;
  final String filename;
  final String originalName;
  final String fileType; // 'video', 'audio', 'image'
  final String fileSizeFormatted;
  final String status; // 'uploading', 'importing', 'processing', 'completed', 'error'
  final String statusText;
  final int progress; // 0-100
  final String? errorMessage;
  final String? estimatedTimeRemaining;
  final int? bytesUploaded;
  final int? bytesTotal;
  final String fileUrl;
  final String? thumbnailUrl;
  final bool hasThumbnail;
  final int? duration;
  final int? width;
  final int? height;
  final int? bitrate;
  final Map<String, dynamic>? metadata;
  final DateTime createdAt;
  final DateTime? updatedAt;
  final int? playlistItemId; // 🔥 ID de l'item dans la playlist (pour suppression)

  MediaFile({
    required this.id,
    required this.filename,
    required this.originalName,
    required this.fileType,
    required this.fileSizeFormatted,
    required this.status,
    required this.statusText,
    required this.progress,
    this.errorMessage,
    this.estimatedTimeRemaining,
    this.bytesUploaded,
    this.bytesTotal,
    required this.fileUrl,
    this.thumbnailUrl,
    required this.hasThumbnail,
    this.duration,
    this.width,
    this.height,
    this.bitrate,
    this.metadata,
    required this.createdAt,
    this.updatedAt,
    this.playlistItemId,
  });

  factory MediaFile.fromJson(Map<String, dynamic> json) {
    final metadata = _parseMetadata(json['metadata']);

    dynamic rawDuration = json['duration'];
    rawDuration ??= json['duration_seconds'];
    if (metadata != null) {
      rawDuration ??= metadata['duration_seconds'];
      rawDuration ??= metadata['duration'];
      rawDuration ??= metadata['duration_formatted'];
      rawDuration ??= metadata['duration_human'];
      rawDuration ??= metadata['human_readable_duration'];
    }

    return MediaFile(
      id: json['id'] as int,
      filename: json['filename'] as String? ?? '',
      originalName: json['original_name'] as String? ?? '',
      fileType: json['file_type'] as String? ?? 'unknown',
      fileSizeFormatted: json['file_size_formatted'] as String? ?? '0 MB',
      status: json['status'] as String? ?? 'unknown',
      statusText: json['status_text'] as String? ?? _getStatusText(json['status'] as String?),
      progress: json['progress'] as int? ?? 0,
      errorMessage: json['error_message'] as String?,
      estimatedTimeRemaining: json['estimated_time_remaining'] as String?,
      bytesUploaded: json['bytes_uploaded'] as int?,
      bytesTotal: json['bytes_total'] as int?,
      fileUrl: json['file_url'] as String? ?? '',
      thumbnailUrl: json['thumbnail_url'] as String?,
      hasThumbnail: json['has_thumbnail'] as bool? ?? false,
      duration: _parseDuration(rawDuration),
      width: json['width'] as int?,
      height: json['height'] as int?,
      bitrate: json['bitrate'] as int?,
      metadata: metadata,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] != null 
          ? DateTime.parse(json['updated_at'] as String) 
          : null,
      playlistItemId: json['playlist_item_id'] as int?, // 🔥 Nouveau champ
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'playlist_item_id': playlistItemId, // 🔥 Nouveau champ
      'filename': filename,
      'original_name': originalName,
      'file_type': fileType,
      'file_size_formatted': fileSizeFormatted,
      'status': status,
      'status_text': statusText,
      'progress': progress,
      'error_message': errorMessage,
      'estimated_time_remaining': estimatedTimeRemaining,
      'bytes_uploaded': bytesUploaded,
      'bytes_total': bytesTotal,
      'file_url': fileUrl,
      'thumbnail_url': thumbnailUrl,
      'has_thumbnail': hasThumbnail,
      'duration': duration,
      'width': width,
      'height': height,
      'bitrate': bitrate,
      'metadata': metadata,
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt?.toIso8601String(),
    };
  }

  // Méthodes utilitaires
  bool get isVideo => fileType == 'video';
  bool get isAudio => fileType == 'audio';
  bool get isImage => fileType == 'image';
  bool get isImporting => status == 'importing';
  bool get hasError => status == 'error';
  bool get isCompleted => status == 'completed';
  bool get isProcessing => ['uploading', 'importing', 'processing'].contains(status);

  double get progressPercentage {
    // Si completed, retourner 100%
    if (isCompleted) return 1.0;
    // Si error, retourner 100% aussi pour montrer la barre rouge
    if (hasError) return 1.0;
    // Sinon, utiliser le progress du backend
    return progress / 100.0;
  }

  /// Retourne l'icône appropriée selon le type de fichier
  IconData get fileIcon {
    switch (fileType) {
      case 'video':
        return Icons.videocam;
      case 'audio':
        return Icons.audiotrack;
      case 'image':
        return Icons.image;
      default:
        return Icons.insert_drive_file;
    }
  }

  /// Retourne la couleur de l'icône selon le statut
  Color get iconColor {
    if (hasError) return const Color(0xFFEF4444);
    if (isCompleted) return const Color(0xFF10B981);
    return const Color(0xFF6B7280);
  }

  /// Retourne la couleur du statut
  Color get statusColor {
    if (hasError) return const Color(0xFFEF4444);
    if (isCompleted) return const Color(0xFF10B981);
    return const Color(0xFF3B82F6); // Bleu pour en cours
  }

  /// Génère le texte de statut si non fourni
  static String _getStatusText(String? status) {
    switch (status) {
      case 'uploading':
        return 'En cours d\'upload';
      case 'importing':
        return 'En cours d\'importation';
      case 'processing':
        return 'En cours de traitement';
      case 'completed':
        return 'Terminé';
      case 'error':
        return 'Erreur';
      default:
        return 'Inconnu';
    }
  }

  /// Parse la durée depuis différents formats (String ou int)
  static int? _parseDuration(dynamic duration) {
    if (duration == null) return null;
    
    if (duration is num) {
      if (duration.isNaN) return null;
      return duration.round();
    }
    
    if (duration is String) {
      // Formats "HH:MM:SS" ou "MM:SS"
      if (duration.contains(':')) {
        final parts = duration.split(':').map((part) => part.trim()).toList();
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
        } catch (e) {
          return null;
        }
      }
      
      // Format numérique simple
      try {
        final parsedInt = int.tryParse(duration);
        if (parsedInt != null) return parsedInt;
        final parsedDouble = double.tryParse(duration);
        if (parsedDouble != null && !parsedDouble.isNaN) {
          return parsedDouble.round();
        }
      } catch (e) {
        return null;
      }
    }
    
    return null;
  }

  /// Retourne le texte formaté de progression
  String get progressText {
    if (hasError && errorMessage != null) {
      return errorMessage!;
    }
    
    if (isCompleted) {
      return '$fileSizeFormatted - Importation réussie';
    }
    
    if (bytesUploaded != null && bytesTotal != null) {
      final uploadedMB = (bytesUploaded! / (1024 * 1024)).toStringAsFixed(1);
      final totalMB = (bytesTotal! / (1024 * 1024)).toStringAsFixed(1);
      final remaining = estimatedTimeRemaining ?? 'Calcul en cours...';
      return '$uploadedMB Mo / $totalMB Mo - $remaining';
    }
    
    return fileSizeFormatted;
  }

  /// Retourne la durée formatée (ex: "10:00" ou "1:30:00")
  String get durationFormatted {
    final totalSeconds = effectiveDurationSeconds;
    if (totalSeconds == null) return 'N/A';
    
    final hours = totalSeconds ~/ 3600;
    final minutes = (totalSeconds % 3600) ~/ 60;
    final seconds = totalSeconds % 60;
    
    if (hours > 0) {
      return '${hours.toString().padLeft(2, '0')}:${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
    } else {
      return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
    }
  }

  int? get effectiveDurationSeconds {
    if (duration != null) return duration;
    final meta = metadata;
    if (meta == null) return null;

    final candidates = [
      meta['duration_seconds'],
      meta['duration'],
      meta['duration_formatted'],
      meta['duration_human'],
      meta['human_readable_duration'],
      meta['human_readable'],
    ];

    for (final candidate in candidates) {
      final parsed = _parseDuration(candidate);
      if (parsed != null) {
        return parsed;
      }
    }
    return null;
  }

  String get effectiveDurationLabel {
    final seconds = effectiveDurationSeconds;
    if (seconds == null) {
      final meta = metadata;
      if (meta != null) {
        final fallback = meta['duration_formatted'] ??
            meta['duration_human'] ??
            meta['human_readable_duration'] ??
            meta['human_readable'];
        if (fallback is String && fallback.isNotEmpty) {
          return fallback;
        }
      }
      return '--:--';
    }

    final hours = seconds ~/ 3600;
    final minutes = (seconds % 3600) ~/ 60;
    final remainingSeconds = seconds % 60;

    if (hours > 0) {
      return '${hours.toString().padLeft(2, '0')}:${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
    } else {
      return '${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
    }
  }
}

/// Session d'upload
class UploadSession {
  final int sessionId;
  final String sessionToken;

  UploadSession({
    required this.sessionId,
    required this.sessionToken,
  });

  factory UploadSession.fromJson(Map<String, dynamic> json) {
    return UploadSession(
      sessionId: json['session_id'] as int,
      sessionToken: json['session_token'] as String,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'session_id': sessionId,
      'session_token': sessionToken,
    };
  }
}

/// Réponse de la liste de fichiers avec pagination
class MediaFilesResponse {
  final bool success;
  final String message;
  final List<MediaFile> data;
  final PaginationInfo? pagination;

  MediaFilesResponse({
    required this.success,
    required this.message,
    required this.data,
    this.pagination,
  });

  factory MediaFilesResponse.fromJson(Map<String, dynamic> json) {
    return MediaFilesResponse(
      success: json['success'] as bool? ?? false,
      message: json['message'] as String? ?? '',
      data: (json['data'] as List<dynamic>? ?? [])
          .map((file) => MediaFile.fromJson(file as Map<String, dynamic>))
          .toList(),
      pagination: json['pagination'] != null
          ? PaginationInfo.fromJson(json['pagination'] as Map<String, dynamic>)
          : null,
    );
  }
}

/// Informations de pagination
class PaginationInfo {
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;
  final int? from;
  final int? to;

  PaginationInfo({
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
    this.from,
    this.to,
  });

  factory PaginationInfo.fromJson(Map<String, dynamic> json) {
    return PaginationInfo(
      currentPage: json['current_page'] as int,
      lastPage: json['last_page'] as int,
      perPage: json['per_page'] as int,
      total: json['total'] as int,
      from: json['from'] as int?,
      to: json['to'] as int?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'current_page': currentPage,
      'last_page': lastPage,
      'per_page': perPage,
      'total': total,
      'from': from,
      'to': to,
    };
  }

  /// Calcule les valeurs from/to si elles ne sont pas fournies par l'API
  int get calculatedFrom {
    if (from != null) return from!;
    if (total == 0) return 0;
    return (currentPage - 1) * perPage + 1;
  }

  int get calculatedTo {
    if (to != null) return to!;
    if (total == 0) return 0;
    return math.min(currentPage * perPage, total);
  }
}


/// Parse les métadonnées depuis JSON (peut être String ou Map)
Map<String, dynamic>? _parseMetadata(dynamic metadata) {
  if (metadata == null) return null;
  if (metadata is Map<String, dynamic>) return metadata;
  if (metadata is String) {
    try {
      return jsonDecode(metadata) as Map<String, dynamic>;
    } catch (e) {
      print('Erreur parsing metadata: $e');
      return null;
    }
  }
  return null;
}
