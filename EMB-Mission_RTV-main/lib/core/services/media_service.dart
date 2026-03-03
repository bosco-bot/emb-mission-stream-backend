import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:emb_mission_dashboard/core/config/api_config.dart';
import 'package:emb_mission_dashboard/core/models/media_models.dart';

/// Service de gestion de la médiathèque (Upload et fichiers)
/// 
/// Gère la communication avec l'API Laravel pour l'upload et la gestion des fichiers média
class MediaService {
  MediaService._();
  
  static final MediaService instance = MediaService._();
  
  late final Dio _dio;
  
  // Getter pour accéder au client Dio (utile pour les uploads web custom)
  Dio get dio => _dio;
  
  /// Initialise le client HTTP Dio
  void initialize() {
    _dio = Dio(
      BaseOptions(
        baseUrl: 'https://radio.embmission.com/api',
        connectTimeout: const Duration(milliseconds: 120000), // 2 minutes pour upload
        receiveTimeout: const Duration(milliseconds: 120000),
        sendTimeout: const Duration(seconds: 3600), // 1 heure pour fichiers lourds
        headers: {
          'Accept': 'application/json',
          // Ne pas définir Content-Type pour les uploads multipart
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
    
    // Interceptor pour gérer les uploads
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          // Pour les uploads, ne pas définir Content-Type
          if (options.path.contains('/media/upload')) {
            options.headers.remove('Content-Type');
            print('🚀 Upload request: ${options.method} ${options.path}');
            print('📦 Headers: ${options.headers}');
          }
          handler.next(options);
        },
        onError: (error, handler) {
          print('💥 Dio Error: ${error.type}');
          print('📋 Message: ${error.message}');
          print('🔗 URL: ${error.requestOptions.uri}');
          
          if (error.type == DioExceptionType.connectionError) {
            print('🌐 Connection error - possible CORS ou serveur down');
          }
          
          handler.next(error);
        },
      ),
    );
  }
  
  // ========================================
  // RÉCUPÉRATION DES FICHIERS
  // ========================================
  
  /// Récupère les fichiers en cours d'importation
  Future<List<MediaFile>> getImportingFiles() async {
    try {
      final response = await _dio.get('/media/files/importing');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          final filesData = data['data'] as List<dynamic>;
          return filesData
              .map((file) => MediaFile.fromJson(file as Map<String, dynamic>))
              .toList();
        }
      }
      
      throw Exception('Erreur lors de la récupération des fichiers en cours');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Récupère les fichiers avec erreur
  Future<List<MediaFile>> getErrorFiles() async {
    try {
      final response = await _dio.get('/media/files/errors');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          final filesData = data['data'] as List<dynamic>;
          return filesData
              .map((file) => MediaFile.fromJson(file as Map<String, dynamic>))
              .toList();
        }
      }
      
      throw Exception('Erreur lors de la récupération des fichiers en erreur');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Récupère les fichiers terminés
  Future<List<MediaFile>> getCompletedFiles() async {
    try {
      final response = await _dio.get('/media/files/completed');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          final filesData = data['data'] as List<dynamic>;
          return filesData
              .map((file) => MediaFile.fromJson(file as Map<String, dynamic>))
              .toList();
        }
      }
      
      throw Exception('Erreur lors de la récupération des fichiers terminés');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Récupère tous les fichiers avec filtres optionnels
  Future<MediaFilesResponse> getAllFiles({
    String? fileType,
    String? status,
    String? search,
    String sortBy = 'created_at',
    String sortOrder = 'desc',
    int perPage = 15,
    int page = 1,
  }) async {
    try {
      final queryParams = <String, dynamic>{
        'sort_by': sortBy,
        'sort_order': sortOrder,
        'per_page': perPage,
        'page': page,
      };
      
      if (fileType != null) queryParams['file_type'] = fileType;
      if (status != null) queryParams['status'] = status;
      if (search != null && search.isNotEmpty) queryParams['search'] = search;
      
      final response = await _dio.get(
        '/media/files',
        queryParameters: queryParams,
      );
      
      if (response.statusCode == 200) {
        return MediaFilesResponse.fromJson(response.data as Map<String, dynamic>);
      }
      
      throw Exception('Erreur lors de la récupération des fichiers');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Récupère un fichier spécifique
  Future<MediaFile> getFile(int fileId) async {
    try {
      final response = await _dio.get('/media/files/$fileId');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return MediaFile.fromJson(data['data'] as Map<String, dynamic>);
        }
      }
      
      throw Exception('Erreur lors de la récupération du fichier');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  // ========================================
  // ACTIONS SUR LES FICHIERS
  // ========================================
  
  /// Annule l'importation d'un fichier
  Future<String> cancelImport(int fileId) async {
    try {
      final response = await _dio.delete('/media/files/$fileId/cancel');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true) {
          return data['message'] as String? ?? 'Importation annulée avec succès';
        }
      }
      
      throw Exception('Erreur lors de l\'annulation');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Relance l'importation d'un fichier en erreur
  Future<MediaFile> retryImport(int fileId) async {
    try {
      final response = await _dio.post('/media/files/$fileId/retry');
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return MediaFile.fromJson(data['data'] as Map<String, dynamic>);
        }
      }
      
      throw Exception('Erreur lors du relancement');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Récupère les fichiers média par type avec pagination
  Future<MediaFilesResponse> getMediaFiles({
    String? fileType, // 'audio', 'video', 'image'
    int page = 1,
    int perPage = 10,
  }) async {
    try {
      final queryParams = <String, dynamic>{
        'page': page,
        'per_page': perPage,
      };
      
      if (fileType != null) {
        queryParams['file_type'] = fileType;
      }
      
      final response = await _dio.get(
        '/media/files',
        queryParameters: queryParams,
      );
      
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true) {
          return MediaFilesResponse.fromJson(data);
        }
      }
      
      throw Exception('Erreur lors de la récupération des fichiers');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  // ========================================
  // UPLOAD DE FICHIERS
  // ========================================
  
  /// Crée une session d'upload
  Future<UploadSession> createUploadSession({
    bool autoMatchThumbnails = true,
    bool generateMissingThumbnails = true,
  }) async {
    try {
      final response = await _dio.post(
        '/media/upload-session',
        data: {
          'auto_match_thumbnails': autoMatchThumbnails,
          'generate_missing_thumbnails': generateMissingThumbnails,
        },
      );
      
      if (response.statusCode == 201 || response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          return UploadSession.fromJson(data['data'] as Map<String, dynamic>);
        }
      }
      
      throw Exception('Erreur lors de la création de la session');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Upload un fichier unique
  Future<MediaFile> uploadFile({
    required String filePath,
    required String fileName,
    required String sessionToken,
    Function(double)? onProgress,
  }) async {
    try {
      final formData = FormData.fromMap({
        'session_token': sessionToken,
        'file': await MultipartFile.fromFile(
          filePath,
          filename: fileName,
        ),
      });
      
      final response = await _dio.post(
        '/media/upload',
        data: formData,
        onSendProgress: (sent, total) {
          if (onProgress != null && total > 0) {
            onProgress(sent / total);
          }
        },
      );
      
      if (response.statusCode == 201 || response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          final mediaFileData = data['data']['media_file'] as Map<String, dynamic>;
          return MediaFile.fromJson(mediaFileData);
        }
      }
      
      throw Exception('Erreur lors de l\'upload');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Upload multiple de fichiers
  Future<List<MediaFile>> uploadMultipleFiles({
    required List<Map<String, String>> files, // [{path: '', name: ''}]
    required String sessionToken,
    Function(double)? onProgress,
  }) async {
    try {
      final formData = FormData.fromMap({
        'session_token': sessionToken,
      });
      
      for (var file in files) {
        formData.files.add(
          MapEntry(
            'files[]',
            await MultipartFile.fromFile(
              file['path']!,
              filename: file['name']!,
            ),
          ),
        );
      }
      
      final response = await _dio.post(
        '/media/upload-multiple',
        data: formData,
        onSendProgress: (sent, total) {
          if (onProgress != null && total > 0) {
            onProgress(sent / total);
          }
        },
      );
      
      if (response.statusCode == 201 || response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          final filesData = data['data'] as List<dynamic>;
          return filesData
              .map((item) => MediaFile.fromJson(item['media_file'] as Map<String, dynamic>))
              .toList();
        }
      }
      
      throw Exception('Erreur lors de l\'upload multiple');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Modifie un fichier média
  Future<MediaFile> updateFile({
    required int fileId,
    required String originalName,
    required String fileType,
    Map<String, dynamic>? metadata,
  }) async {
    try {
      final data = {
        'original_name': originalName,
        'file_type': fileType,
        if (metadata != null) 'metadata': metadata,
      };
      
      final response = await _dio.put(
        '/media/files/update/$fileId',
        data: data,
      );
      
      if (response.statusCode == 200 || response.statusCode == 201) {
        final responseData = response.data as Map<String, dynamic>;
        
        if (responseData['success'] == true && responseData.containsKey('data')) {
          final mediaFileData = responseData['data']['media_file'] as Map<String, dynamic>;
          return MediaFile.fromJson(mediaFileData);
        }
      }
      
      throw Exception('Erreur lors de la modification du fichier');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Récupère la liste des playlists
  Future<List<Map<String, dynamic>>> getPlaylists({
    int page = 1,
    int perPage = 50,
    String? type,
  }) async {
    try {
      final queryParams = <String, dynamic>{
        'page': page,
        'per_page': perPage,
      };
      
      if (type != null && type.isNotEmpty) {
        queryParams['type'] = type;
      }

      final response = await _dio.get(
        '/playlists',
        queryParameters: queryParams,
      );

      if (response.statusCode == 200) {
        final responseData = response.data as Map<String, dynamic>;
        
        if (responseData['success'] == true && responseData.containsKey('data')) {
          final List<dynamic> playlists = responseData['data'] as List<dynamic>;
          return playlists.cast<Map<String, dynamic>>();
        }
      }

      throw Exception('Échec de la récupération des playlists');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Supprime une playlist
  Future<bool> deletePlaylist(int playlistId) async {
    try {
      final response = await _dio.delete('/playlists/$playlistId');

      if (response.statusCode == 200) {
        final responseData = response.data as Map<String, dynamic>;
        return responseData['success'] == true;
      }

      throw Exception('Échec de la suppression de la playlist');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Supprime toutes les playlists
  Future<Map<String, dynamic>> deleteAllPlaylists() async {
    try {
      final response = await _dio.post('/playlists/delete-all');

      if (response.statusCode == 200) {
        final responseData = response.data as Map<String, dynamic>;
        
        if (responseData['success'] == true && responseData.containsKey('data')) {
          return responseData['data'] as Map<String, dynamic>;
        }
      }

      throw Exception('Échec de la suppression de toutes les playlists');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Supprime un item d'une playlist avec synchronisation automatique
  Future<Map<String, dynamic>> deletePlaylistItem(int playlistId, int itemId, {bool autoSync = true}) async {
    try {
      // Construction de l'URL avec auto_sync en query parameter
      final response = await _dio.delete(
        '/playlists/$playlistId/items/$itemId?auto_sync=$autoSync',
      );

      if (response.statusCode == 200) {
        final responseData = response.data as Map<String, dynamic>;
        
        if (responseData['success'] == true && responseData.containsKey('data')) {
          return responseData['data'] as Map<String, dynamic>;
        }
      }

      throw Exception('Échec de la suppression de l\'élément de la playlist');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Crée ou met à jour une playlist avec ses items en un seul appel
  /// 
  /// Cette méthode utilise la nouvelle API unifiée qui gère la création/mise à jour
  /// de la playlist et l'ajout des items en une seule requête.
  /// 
  /// Retourne :
  /// - `success`: true/false
  /// - `action`: "created" ou "updated"
  /// - `data`: Informations de la playlist
  /// - `items_added`: Nombre d'items ajoutés
  /// - `items_skipped`: Nombre d'items ignorés (doublons)
  /// - `message`: Message de confirmation
  Future<Map<String, dynamic>> createPlaylist({
    required String name,
    String? description,
    String? type,
    required bool isLoop,
    required bool isShuffle,
    List<int>? items,
    bool autoSync = true,
  }) async {
    try {
      final data = {
        'name': name,
        if (description != null && description.isNotEmpty) 'description': description,
        if (type != null && type.isNotEmpty) 'type': type,
        'is_loop': isLoop,
        'is_shuffle': isShuffle,
        if (items != null && items.isNotEmpty) 'items': items,
        'auto_sync': autoSync,
      };

      final response = await _dio.post(
        '/playlists',
        data: data,
      );

      if (response.statusCode == 200 || response.statusCode == 201) {
        final responseData = response.data as Map<String, dynamic>;
        
        if (responseData['success'] == true) {
          // Retourner toute la réponse (pas seulement 'data')
          // car on a besoin de 'action', 'items_added', 'items_skipped'
          return responseData;
        }
      }

      throw Exception('Échec de la création/mise à jour de la playlist');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Ajoute un item à une playlist existante
  Future<Map<String, dynamic>> addPlaylistItem({
    required int playlistId,
    required int mediaFileId,
    required int order,
    bool autoSync = true,
  }) async {
    try {
      final data = {
        'media_file_id': mediaFileId,
        'order': order,
        'auto_sync': autoSync,
      };

      final response = await _dio.post(
        '/playlists/$playlistId/items',
        data: data,
      );

      if (response.statusCode == 200 || response.statusCode == 201) {
        final responseData = response.data as Map<String, dynamic>;
        
        if (responseData['success'] == true && responseData.containsKey('data')) {
          return responseData['data'] as Map<String, dynamic>;
        }
      }

      throw Exception('Échec de l\'ajout de l\'item à la playlist');
      
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
    
    if (e.type == DioExceptionType.sendTimeout) {
      throw Exception('Délai d\'envoi dépassé. Le fichier est peut-être trop volumineux.');
    }
    
    if (e.type == DioExceptionType.connectionError) {
      throw Exception('Impossible de se connecter au serveur. Vérifiez votre connexion internet.');
    }
    
    throw Exception('Erreur réseau : ${e.message}');
  }

  /// Met à jour l'ordre des items d'une playlist
  ///
  /// Cette API permet de réorganiser les items d'une playlist existante
  /// en spécifiant le nouvel ordre pour chaque item.
  ///
  /// Paramètres:
  /// - `playlistId`: ID de la playlist
  /// - `itemOrders`: Liste des objets contenant item_id et order
  /// - `autoSync`: Synchronisation automatique avec AzuraCast
  ///
  /// Retourne:
  /// - `success`: true si la mise à jour réussit
  /// - `data`: Informations sur la playlist mise à jour
  /// - `message`: Message de confirmation
  Future<Map<String, dynamic>> updatePlaylistOrder({
    required int playlistId,
    required List<Map<String, int>> itemOrders,
    bool autoSync = true,
  }) async {
    try {
      final data = {
        'items': itemOrders, // ✅ Correction: 'items' au lieu de 'item_orders'
        'auto_sync': autoSync,
      };

      print('📤 Envoi API PUT /playlists/$playlistId/items/order');
      print('   • Données: $data');

      final response = await _dio.put(
        '/playlists/$playlistId/items/order',
        data: data,
      );

      print('📥 Réponse API: ${response.statusCode}');
      print('   • Données: ${response.data}');

      if (response.statusCode == 200) {
        final responseData = response.data as Map<String, dynamic>;

        if (responseData['success'] == true) {
          return responseData;
        }
      }

      throw Exception('Échec de la mise à jour de l\'ordre de la playlist');

    } on DioException catch (e) {
      print('💥 Erreur Dio: ${e.response?.data}');
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Supprime un fichier média
  ///
  /// Cette API permet de supprimer définitivement un fichier média
  /// (audio, vidéo, image) du serveur.
  ///
  /// Paramètres:
  /// - `fileId`: ID du fichier à supprimer
  ///
  /// Retourne:
  /// - `success`: true si la suppression réussit
  /// - `message`: Message de confirmation
  Future<Map<String, dynamic>> deleteFile(int fileId) async {
    try {
      print('📤 Envoi API DELETE /media/files/$fileId');

      final response = await _dio.delete('/media/files/$fileId');

      print('📥 Réponse API: ${response.statusCode}');
      print('   • Données: ${response.data}');

      if (response.statusCode == 200) {
        final responseData = response.data as Map<String, dynamic>;

        if (responseData['success'] == true) {
          return responseData;
        }
      }

      throw Exception('Échec de la suppression du fichier');

    } on DioException catch (e) {
      print('💥 Erreur Dio: ${e.response?.data}');
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  // ========================================
  // UPLOAD PAR CHUNKS POUR WEB
  // ========================================

  /// Upload d'un fichier web par chunks pour éviter les problèmes mémoire
  /// 
  /// Pour les fichiers > 100MB, divise le fichier en chunks de 25MB
  Future<MediaFile> uploadFileByChunks({
    required Uint8List fileBytes,
    required String fileName,
    required String sessionToken,
    Function(double)? onProgress,
  }) async {
    const chunkSize = 25 * 1024 * 1024; // 25 MB par chunk
    final totalSize = fileBytes.length;
    
    // Pour petits fichiers, utiliser la méthode normale
    // Note: Seuil réduit à 50MB pour plus de fiabilité (évite les timeouts)
    if (totalSize < 50 * 1024 * 1024) {
      return _uploadSmallWebFile(fileBytes, fileName, sessionToken, onProgress);
    }
    
    print('📦 Upload par chunks: $fileName (${(totalSize / 1024 / 1024).toStringAsFixed(2)} MB)');
    
    try {
      // Créer un nom unique pour cette session d'upload
      final uploadId = '${fileName}_${DateTime.now().millisecondsSinceEpoch}';
      
      // Calculer le nombre de chunks
      final totalChunks = (totalSize / chunkSize).ceil();
      print('📊 Total chunks: $totalChunks');
      
      int uploadedBytes = 0;
      
      // Upload chaque chunk
      for (int chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
        final start = chunkIndex * chunkSize;
        final end = (start + chunkSize < totalSize) ? start + chunkSize : totalSize;
        final chunkBytes = fileBytes.sublist(start, end);
        
        print('📤 Chunk ${chunkIndex + 1}/$totalChunks: ${(chunkBytes.length / 1024 / 1024).toStringAsFixed(2)} MB');
        
        final formData = FormData.fromMap({
          'session_token': sessionToken,
          'file': MultipartFile.fromBytes(
            chunkBytes,
            filename: fileName,
          ),
          'upload_id': uploadId,
          'chunk_index': chunkIndex,
          'total_chunks': totalChunks,
        });
        
        final response = await _dio.post(
          '/media/upload-chunk',
          data: formData,
          options: Options(
            validateStatus: (status) => status != null && status < 500,
          ),
        );
        
        if (response.statusCode == 413) {
          // Fichier trop grand, même par chunks
          throw Exception('Fichier trop volumineux. Chaque chunk ne doit pas dépasser 2048MB.');
        }
        
        uploadedBytes += chunkBytes.length;
        
        // Mise à jour de la progression
        if (onProgress != null) {
          onProgress(uploadedBytes / totalSize);
        }
      }
      
      // Marquer comme terminé
      if (onProgress != null) {
        onProgress(1.0);
      }
      
      // Récupérer le fichier final
      print('✅ Tous les chunks uploadés, finalisation...');
      
      // Appeler finalizeChunkUpload pour assembler le fichier
      final finalizeResponse = await _dio.post(
        '/media/finalize-chunk-upload',
        data: {
          'session_token': sessionToken,
          'upload_id': uploadId,
          'filename': fileName,
          'mime_type': _detectMimeType(fileName),
        },
      );
      
      if (finalizeResponse.statusCode == 201 || finalizeResponse.statusCode == 200) {
        final data = finalizeResponse.data as Map<String, dynamic>;
        
        if (data['success'] == true && data.containsKey('data')) {
          final mediaFileData = data['data']['media_file'] as Map<String, dynamic>;
          print('✅ Fichier assemblé avec succès');
          return MediaFile.fromJson(mediaFileData);
        }
      }
      
      throw Exception('Erreur lors de la finalisation de l\'upload par chunks');
      
    } on DioException catch (e) {
      _handleDioError(e);
      rethrow;
    } catch (e) {
      throw Exception('Erreur lors de l\'upload par chunks: $e');
    }
  }

  /// Upload d'un petit fichier web (méthode normale)
  Future<MediaFile> _uploadSmallWebFile(
    Uint8List fileBytes,
    String fileName,
    String sessionToken,
    Function(double)? onProgress,
  ) async {
    final formData = FormData.fromMap({
      'session_token': sessionToken,
      'file': MultipartFile.fromBytes(
        fileBytes,
        filename: fileName,
      ),
    });

    final response = await _dio.post(
      '/media/upload',
      data: formData,
      onSendProgress: (sent, total) {
        if (onProgress != null && total > 0) {
          onProgress(sent / total);
        }
      },
    );

    if (response.statusCode == 201 || response.statusCode == 200) {
      final data = response.data as Map<String, dynamic>;

      if (data['success'] == true && data.containsKey('data')) {
        final mediaFileData = data['data']['media_file'] as Map<String, dynamic>;
        return MediaFile.fromJson(mediaFileData);
      }
    }

    throw Exception('Erreur lors de l\'upload');
  }

  /// Détecter le type MIME à partir de l'extension
  String _detectMimeType(String fileName) {
    final extension = fileName.toLowerCase().split('.').last;
    switch (extension) {
      case 'mp4':
      case 'avi':
      case 'mov':
      case 'mkv':
      case 'webm':
        return 'video/$extension';
      case 'mp3':
      case 'wav':
      case 'ogg':
      case 'aac':
      case 'm4a':
        return 'audio/$extension';
      case 'jpg':
      case 'jpeg':
      case 'png':
      case 'gif':
      case 'webp':
        return 'image/$extension';
      default:
        return 'application/octet-stream';
    }
  }
}

