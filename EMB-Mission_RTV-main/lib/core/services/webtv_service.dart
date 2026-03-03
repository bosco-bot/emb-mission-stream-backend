import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:emb_mission_dashboard/core/config/api_config.dart';
import 'package:emb_mission_dashboard/core/models/webtv_models.dart';

/// Service de gestion de la WebTV (Ant Media Server)
/// 
/// Gère la communication avec l'API Laravel qui communique avec Ant Media Server
class WebTvService {
  WebTvService._();
  
  static final WebTvService instance = WebTvService._();
  
  late final Dio _dio;
  
  /// Initialise le client HTTP Dio
  void initialize() {
    _dio = Dio(
      BaseOptions(
        connectTimeout: Duration(milliseconds: ApiConfig.connectTimeout),
        receiveTimeout: Duration(milliseconds: ApiConfig.receiveTimeout),
        sendTimeout: Duration(milliseconds: ApiConfig.sendTimeout),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      ),
    );
    
    // Logs en développement
    if (ApiConfig.enableLogs) {
      _dio.interceptors.add(
        LogInterceptor(
          requestBody: true,
          responseBody: true,
          error: true,
          requestHeader: true,
          responseHeader: false,
        ),
      );
    }
  }
  
  /// Récupère le stream WebTV actuel
  /// 
  /// Retourne :
  /// - [WebTvStream] contenant les informations du stream en cours
  /// - Lance une exception en cas d'erreur
  Future<WebTvStream> getCurrentStream() async {
    try {
      final response = await _dio.get(ApiConfig.webtvCurrentStream);
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return WebTvStream.fromJson(data['data'] as Map<String, dynamic>);
        }
      }
      
      throw Exception('Erreur lors de la récupération du stream actuel');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Récupère la liste des streams WebTV
  /// 
  /// Paramètres optionnels :
  /// - [status] : Filtrer par statut (live, offline, scheduled, finished)
  /// - [featured] : Afficher uniquement les streams mis en avant
  /// - [perPage] : Nombre de résultats par page (défaut: 10)
  /// 
  /// Retourne :
  /// - [WebTvStreamsResponse] contenant la liste des streams et la pagination
  /// - Lance une exception en cas d'erreur
  Future<WebTvStreamsResponse> getStreams({
    String? status,
    bool? featured,
    int perPage = 10,
  }) async {
    try {
      final queryParams = <String, dynamic>{
        'per_page': perPage,
      };
      
      if (status != null) {
        queryParams['status'] = status;
      }
      
      if (featured != null) {
        queryParams['featured'] = featured.toString();
      }
      
      final response = await _dio.get(
        ApiConfig.webtvStreams,
        queryParameters: queryParams,
      );
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true) {
          return WebTvStreamsResponse.fromJson(data);
        }
      }
      
      throw Exception('Erreur lors de la récupération des streams');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Récupère le statut d'un stream spécifique
  /// 
  /// Paramètres :
  /// - [streamId] : ID du stream à vérifier
  /// 
  /// Retourne :
  /// - [WebTvStreamStatus] contenant le statut du stream
  /// - Lance une exception en cas d'erreur
  Future<WebTvStreamStatus> getStreamStatus(String streamId) async {
    try {
      final response = await _dio.get(ApiConfig.webtvStreamStatus(streamId));
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return WebTvStreamStatus.fromJson(data['data'] as Map<String, dynamic>);
        }
      }
      
      throw Exception('Erreur lors de la récupération du statut du stream');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Vérifie la connexion au serveur Ant Media
  /// 
  /// Retourne :
  /// - [WebTvConnection] contenant l'état de la connexion
  /// - Lance une exception en cas d'erreur
  Future<WebTvConnection> checkConnection() async {
    try {
      final response = await _dio.get(ApiConfig.webtvCheckConnection);
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return WebTvConnection.fromJson(data['data'] as Map<String, dynamic>);
        }
      }
      
      throw Exception('Erreur lors de la vérification de la connexion');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Récupère le statut de la playlist automatique
  /// 
  /// Retourne :
  /// - [Map<String, dynamic>] contenant le statut de la playlist automatique
  /// - Lance une exception en cas d'erreur
  Future<Map<String, dynamic>> getAutoPlaylistStatus() async {
    try {
      final response = await _dio.get(ApiConfig.webtvAutoPlaylistStatus);
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return data['data'] as Map<String, dynamic>;
        }
      }
      
      throw Exception('Erreur lors de la récupération du statut de la playlist automatique');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Récupère l'URL de lecture actuelle de la playlist automatique
  /// 
  /// Retourne :
  /// - [Map<String, dynamic>] contenant l'URL de lecture actuelle
  /// - Lance une exception en cas d'erreur
  Future<Map<String, dynamic>> getAutoPlaylistCurrentUrl() async {
    try {
      final response = await _dio.get(ApiConfig.webtvAutoPlaylistCurrentUrl);
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return data['data'] as Map<String, dynamic>;
        }
      }
      
      throw Exception('Erreur lors de la récupération de l\'URL actuelle');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Récupère le code d'embedding pour un stream
  /// 
  /// Paramètres :
  /// - [streamId] : ID du stream
  /// - [width] : Largeur en % (défaut: 100)
  /// - [height] : Hauteur en % (défaut: 100)
  /// 
  /// Retourne :
  /// - [WebTvEmbedCode] contenant le code iframe
  /// - Lance une exception en cas d'erreur
  Future<WebTvEmbedCode> getEmbedCode(
    String streamId, {
    int width = 100,
    int height = 100,
  }) async {
    try {
      final response = await _dio.get(
        ApiConfig.webtvEmbedCode(streamId, width: width, height: height),
      );
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return WebTvEmbedCode.fromJson(data['data'] as Map<String, dynamic>);
        }
      }
      
      throw Exception('Erreur lors de la récupération du code d\'embedding');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Récupère la liste des playlists WebTV
  /// 
  /// Paramètres optionnels :
  /// - [type] : Filtrer par type (live, vod, etc.)
  /// - [isActive] : Filtrer par statut actif
  /// 
  /// Retourne :
  /// - [WebTvPlaylistsResponse] contenant la liste des playlists
  /// - Lance une exception en cas d'erreur
  Future<WebTvPlaylistsResponse> getPlaylists({
    String? type,
    bool? isActive,
  }) async {
    try {
      final queryParams = <String, dynamic>{};
      
      if (type != null) {
        queryParams['type'] = type;
      }
      
      if (isActive != null) {
        queryParams['is_active'] = isActive.toString();
      }
      
      final response = await _dio.get(
        ApiConfig.webtvPlaylists,
        queryParameters: queryParams.isEmpty ? null : queryParams,
      );
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true) {
          return WebTvPlaylistsResponse.fromJson(data);
        }
      }
      
      throw Exception('Erreur lors de la récupération des playlists');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Supprime une playlist WebTV
  /// 
  /// Paramètres :
  /// - [playlistId] : ID de la playlist à supprimer
  /// 
  /// Retourne :
  /// - [Map<String, dynamic>] contenant la réponse de suppression
  /// - Lance une exception en cas d'erreur
  Future<Map<String, dynamic>> deletePlaylist(int playlistId) async {
    try {
      final response = await _dio.delete(
        ApiConfig.webtvPlaylist(playlistId),
      );

      if (response.statusCode == 200 || response.statusCode == 204) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true) {
          return data;
        } else {
          throw Exception(data['message'] ?? 'Erreur lors de la suppression');
        }
      }

      throw Exception('Erreur serveur (${response.statusCode})');

    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Récupère les paramètres de connexion OBS
  /// 
  /// Retourne :
  /// - [WebTvObsParams] contenant tous les paramètres de connexion
  /// - Lance une exception en cas d'erreur
  Future<WebTvObsParams> getObsParams() async {
    try {
      final response = await _dio.get(
        ApiConfig.webtvObsParams,
        options: Options(
          headers: {'Content-Type': 'application/json'},
        ),
      );

      if (response.statusCode == 200) {
        final responseData = WebTvObsParamsResponse.fromJson(response.data as Map<String, dynamic>);
        debugPrint('✅ Paramètres OBS récupérés avec succès');
        return responseData.data;
      } else {
        throw Exception('Erreur lors de la récupération des paramètres OBS: ${response.statusCode}');
      }
    } catch (e) {
      debugPrint('💥 Erreur récupération paramètres OBS: $e');
      rethrow;
    }
  }

  /// Récupère l'URL publique du flux M3U8
  /// 
  /// Retourne :
  /// - [String] URL publique du flux pour les spectateurs
  /// - Lance une exception en cas d'erreur
  Future<String> getPublicStreamUrl() async {
    try {
      final response = await _dio.get(
        ApiConfig.webtvPublicStreamUrl,
        options: Options(
          headers: {'Content-Type': 'application/json'},
        ),
      );

      if (response.statusCode == 200) {
        final responseData = response.data as Map<String, dynamic>;
        final data = responseData['data'] as Map<String, dynamic>?;
        final url = data?['url'] as String? ?? '';
        debugPrint('✅ URL publique du flux récupérée: $url');
        return url;
      } else {
        throw Exception('Erreur lors de la récupération de l\'URL publique: ${response.statusCode}');
      }
    } catch (e) {
      debugPrint('💥 Erreur récupération URL publique: $e');
      rethrow;
    }
  }

  /// Modifie l'ordre des items d'une playlist WebTV
  /// 
  /// Paramètres :
  /// - [playlistId] : ID de la playlist
  /// - [items] : Liste des items avec leur nouvel ordre
  /// 
  /// Retourne :
  /// - [Map<String, dynamic>] contenant la réponse de mise à jour
  /// - Lance une exception en cas d'erreur
  Future<Map<String, dynamic>> updatePlaylistItemsOrder({
    required int playlistId,
    required List<Map<String, dynamic>> items,
  }) async {
    try {
      final requestData = {
        'items': items,
      };

      print('📤 Modification ordre playlist: ${ApiConfig.webtvPlaylistItemsOrder(playlistId)}');
      print('📦 Données envoyées: $requestData');

      final response = await _dio.put(
        ApiConfig.webtvPlaylistItemsOrder(playlistId),
        data: requestData,
      );

      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true) {
          return data;
        } else {
          throw Exception(data['message'] ?? 'Erreur lors de la mise à jour de l\'ordre');
        }
      }

      throw Exception('Erreur serveur (${response.statusCode})');

    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Supprime un item d'une playlist WebTV
  /// 
  /// Paramètres :
  /// - [playlistId] : ID de la playlist
  /// - [itemId] : ID de l'item à supprimer
  /// 
  /// Retourne :
  /// - [Map<String, dynamic>] contenant la réponse de suppression
  /// - Lance une exception en cas d'erreur
  Future<Map<String, dynamic>> deletePlaylistItem({
    required int playlistId,
    required int itemId,
  }) async {
    try {
      final response = await _dio.delete(
        ApiConfig.webtvPlaylistItem(playlistId, itemId),
      );

      if (response.statusCode == 200 || response.statusCode == 204) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true) {
          return data;
        } else {
          throw Exception(data['message'] ?? 'Erreur lors de la suppression');
        }
      }

      throw Exception('Erreur serveur (${response.statusCode})');

    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Crée ou met à jour une playlist WebTV
  /// 
  /// Paramètres :
  /// - [name] : Nom de la playlist
  /// - [description] : Description de la playlist
  /// - [type] : Type de playlist (live, scheduled, etc.)
  /// - [quality] : Qualité de la playlist (1080p, 720p, etc.)
  /// - [items] : Liste des éléments de la playlist
  /// 
  /// Retourne :
  /// - [Map<String, dynamic>] contenant la réponse complète avec gestion des doublons
  /// - Lance une exception en cas d'erreur
  Future<Map<String, dynamic>> savePlaylist({
    required String name,
    required String description,
    required String type,
    required String quality,
    required List<Map<String, dynamic>> items,
    bool isLoop = false,
    bool isShuffle = false,
  }) async {
    try {
      final requestData = {
        'name': name,
        'description': description,
        'type': type,
        'quality': quality,
        'items': items,
        'is_loop': isLoop,
        'shuffle_enabled': isShuffle,
      };

      print('📤 Envoi playlist vers: ${ApiConfig.webtvPlaylists}');
      print('📦 Données envoyées: $requestData');

      final response = await _dio.post(
        ApiConfig.webtvPlaylists,
        data: requestData,
      );

      if (response.statusCode == 200 || response.statusCode == 201) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true) {
          return data;
        } else {
          // Afficher les détails de l'erreur si disponibles
          final errorMessage = data['message'] ?? data['error'] ?? 'Erreur inconnue';
          print('❌ Erreur API: $errorMessage');
          print('📋 Données complètes: $data');
          throw Exception('Erreur API: $errorMessage');
        }
      }

      // Gérer les autres codes de statut
      print('❌ Code de statut invalide: ${response.statusCode}');
      print('📋 Réponse: ${response.data}');
      throw Exception('Erreur serveur (${response.statusCode}): ${response.data}');

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
      final errorData = e.response?.data;
      
      if (errorData is Map<String, dynamic>) {
        if (errorData.containsKey('message')) {
          throw Exception(errorData['message'].toString());
        }
      }
      
      throw Exception('Erreur ${e.response?.statusCode}: ${e.response?.statusMessage}');
    }
    
    if (e.type == DioExceptionType.connectionTimeout) {
      throw Exception('Délai de connexion dépassé. Vérifiez votre connexion internet.');
    }
    
    if (e.type == DioExceptionType.receiveTimeout) {
      throw Exception('Délai de réception dépassé. Le serveur met trop de temps à répondre.');
    }
    
    if (e.type == DioExceptionType.connectionError) {
      throw Exception('Impossible de se connecter au serveur. Vérifiez votre connexion internet.');
    }
    
    throw Exception('Erreur réseau : ${e.message}');
  }
}





