import 'package:dio/dio.dart';
import 'package:emb_mission_dashboard/core/config/api_config.dart';
import 'package:emb_mission_dashboard/core/models/webradio_models.dart';

/// Service de gestion de la WebRadio (AzuraCast via Laravel)
/// 
/// Gère la communication avec l'API Laravel qui communique avec AzuraCast
class WebRadioService {
  WebRadioService._();
  
  static final WebRadioService instance = WebRadioService._();
  
  late final Dio _dio;
  
  /// Initialise le client HTTP Dio
  void initialize() {
    _dio = Dio(
      BaseOptions(
        baseUrl: ApiConfig.baseUrl,
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
  
  /// Récupère les informations du flux en cours de lecture
  /// 
  /// Retourne :
  /// - [CurrentStream] contenant les informations de la piste en cours
  /// - Lance une exception en cas d'erreur
  Future<CurrentStream> getCurrentStream() async {
    try {
      final response = await _dio.get('/webradio/current-stream');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return CurrentStream.fromJson(data['data'] as Map<String, dynamic>);
        }
      }
      
      throw Exception('Erreur lors de la récupération du flux en cours');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Récupère l'historique des pistes jouées
  /// 
  /// Retourne :
  /// - Liste de [TrackHistory] contenant les dernières pistes jouées
  /// - Lance une exception en cas d'erreur
  Future<List<TrackHistory>> getTrackHistory() async {
    try {
      final response = await _dio.get('/webradio/track-history');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          final tracksData = data['data'] as List<dynamic>;
          return tracksData
              .map((track) => TrackHistory.fromJson(track as Map<String, dynamic>))
              .toList();
        }
      }
      
      throw Exception('Erreur lors de la récupération de l\'historique');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Récupère les informations de diffusion (port, mount point, URLs)
  /// 
  /// Retourne :
  /// - [BroadcastInfo] contenant les informations de configuration du stream
  /// - Lance une exception en cas d'erreur
  Future<BroadcastInfo> getBroadcastInfo() async {
    try {
      final response = await _dio.get('/radio/broadcast-info');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return BroadcastInfo.fromJson(data['data'] as Map<String, dynamic>);
        }
      }
      
      throw Exception('Erreur lors de la récupération des informations de diffusion');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Récupère les paramètres de streaming dynamiques depuis AzuraCast
  /// 
  /// Retourne :
  /// - [StreamingSettings] contenant les paramètres de configuration
  /// - Lance une exception en cas d'erreur
  Future<StreamingSettings> getStreamingSettings() async {
    try {
      final response = await _dio.get('/radio/streaming-settings');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return StreamingSettings.fromJson(data['data'] as Map<String, dynamic>);
        }
      }
      
      throw Exception('Erreur lors de la récupération des paramètres de streaming');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Contrôle la diffusion (start, stop, restart)
  /// 
  /// Paramètres :
  /// - [action] : Action à effectuer ('start', 'stop', 'restart')
  /// 
  /// Retourne :
  /// - Message de confirmation en cas de succès
  /// - Lance une exception en cas d'erreur
  Future<String> controlBroadcast(String action) async {
    try {
      final response = await _dio.post(
        '/webradio/control',
        data: {
          'action': action,
        },
      );
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true) {
          return data['message'] as String? ?? 'Action effectuée avec succès';
        }
      }
      
      throw Exception('Erreur lors du contrôle de la diffusion');
      
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
  
  // ========================================
  // GESTION DES SOURCES RADIO
  // ========================================
  
  /// Récupère toutes les sources radio
  /// 
  /// Retourne :
  /// - Liste de [RadioSource]
  /// - Lance une exception en cas d'erreur
  Future<List<RadioSource>> getSources() async {
    try {
      final response = await _dio.get('/radio/sources');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          final sourcesData = data['data'] as List<dynamic>;
          return sourcesData
              .map((source) => RadioSource.fromJson(source as Map<String, dynamic>))
              .toList();
        }
      }
      
      throw Exception('Erreur lors de la récupération des sources');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Récupère une source spécifique par ID
  /// 
  /// Paramètres :
  /// - [id] : ID de la source
  /// 
  /// Retourne :
  /// - [RadioSource]
  /// - Lance une exception en cas d'erreur
  Future<RadioSource> getSource(int id) async {
    try {
      final response = await _dio.get('/radio/sources/$id');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return RadioSource.fromJson(data['data'] as Map<String, dynamic>);
        }
      }
      
      throw Exception('Erreur lors de la récupération de la source');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Crée une nouvelle source radio
  /// 
  /// Paramètres :
  /// - [name] : Nom de la source
  /// - [url] : URL du flux
  /// 
  /// Retourne :
  /// - [RadioSource] créée
  /// - Lance une exception en cas d'erreur
  Future<RadioSource> createSource({
    required String name,
    required String url,
  }) async {
    try {
      final response = await _dio.post(
        '/radio/sources',
        data: {
          'name': name,
          'url': url,
        },
      );
      
      if (response.statusCode == 201 || response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return RadioSource.fromJson(data['data'] as Map<String, dynamic>);
        }
      }
      
      throw Exception('Erreur lors de la création de la source');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Met à jour une source radio
  /// 
  /// Paramètres :
  /// - [id] : ID de la source
  /// - [name] : Nouveau nom (optionnel)
  /// - [url] : Nouvelle URL (optionnel)
  /// - [isActive] : Nouveau statut (optionnel)
  /// 
  /// Retourne :
  /// - [RadioSource] mise à jour
  /// - Lance une exception en cas d'erreur
  Future<RadioSource> updateSource({
    required int id,
    String? name,
    String? url,
    bool? isActive,
  }) async {
    try {
      final Map<String, dynamic> data = {};
      
      if (name != null) data['name'] = name;
      if (url != null) data['url'] = url;
      if (isActive != null) data['is_active'] = isActive;
      
      final response = await _dio.put(
        '/radio/sources/$id',
        data: data,
      );
      
      if (response.statusCode == 200) {
        final responseData = response.data as Map<String, dynamic>;
        
        if (responseData['success'] == true && responseData.containsKey('data')) {
          return RadioSource.fromJson(responseData['data'] as Map<String, dynamic>);
        }
      }
      
      throw Exception('Erreur lors de la mise à jour de la source');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Supprime une source radio
  /// 
  /// Paramètres :
  /// - [id] : ID de la source
  /// 
  /// Retourne :
  /// - Message de confirmation
  /// - Lance une exception en cas d'erreur
  Future<String> deleteSource(int id) async {
    try {
      final response = await _dio.delete('/radio/sources/$id');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true) {
          return data['message'] as String? ?? 'Source supprimée avec succès';
        }
      }
      
      throw Exception('Erreur lors de la suppression de la source');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
}

