import 'dart:async';
import 'dart:js_util' as js_util;
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:emb_mission_dashboard/core/models/media_models.dart';
import 'package:emb_mission_dashboard/core/services/media_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/confirmation_dialog.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter_dropzone/flutter_dropzone.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

/// AddContentPage
///
/// Page d'ajout de contenu média avec upload fonctionnel
class AddContentPage extends StatefulWidget {
  const AddContentPage({super.key});

  @override
  State<AddContentPage> createState() => _AddContentPageState();
}

class _AddContentPageState extends State<AddContentPage> {
  List<MediaFile> _files = [];
  bool _isLoading = true;
  bool _isUploading = false;
  String? _errorMessage;
  UploadSession? _uploadSession;
  bool _hasProcessingFiles = false;
  Timer? _refreshTimer;
  
  // Map pour stocker la progression d'upload de chaque fichier
  // Clé: nom du fichier (ou ID temporaire), Valeur: pourcentage (0.0 à 1.0)
  final Map<String, double> _uploadProgress = {};
  // Map pour stocker la taille totale de chaque fichier en cours d'upload
  final Map<String, int> _fileSizes = {};
  late DropzoneViewController? _dropzoneController;

  @override
  void initState() {
    super.initState();
    _initializeAndLoad();
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  Future<void> _initializeAndLoad() async {
    await _createUploadSession();
    await _loadFiles();
    _startPeriodicRefresh();
  }

  /// Crée une session d'upload
  Future<void> _createUploadSession() async {
    try {
      final session = await MediaService.instance.createUploadSession();
      setState(() {
        _uploadSession = session;
      });
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur de session: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  /// Charge tous les fichiers (en cours, erreurs, terminés)
  Future<void> _loadFiles({bool silent = false}) async {
    if (!silent) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final results = await Future.wait([
        MediaService.instance.getImportingFiles(),
        MediaService.instance.getErrorFiles(),
        MediaService.instance.getCompletedFiles(),
      ]);

      if (mounted) {
        final newFiles = [...results[0], ...results[1], ...results[2]];
        final hasProcessing = newFiles.any((f) => f.isProcessing);
        
        // Nettoyer les progressions pour les fichiers terminés (completed)
        final completedFileNames = newFiles
            .where((f) => f.status == 'completed')
            .map((f) => f.originalName)
            .toSet();
        
        setState(() {
          _files = newFiles;
          _hasProcessingFiles = hasProcessing;
          _isLoading = false;
          
          // Nettoyer les progressions des fichiers terminés
          _uploadProgress.removeWhere((key, _) => completedFileNames.contains(key));
          _fileSizes.removeWhere((key, _) => completedFileNames.contains(key));
        });

        // Arrêter le refresh si plus de fichiers en cours
        if (!hasProcessing) {
          _stopPeriodicRefresh();
        }
      }
    } catch (e) {
      if (mounted && !silent) {
        setState(() {
          _errorMessage = e.toString();
          _isLoading = false;
        });
      }
    }
  }

  /// Démarre le refresh périodique intelligent
  void _startPeriodicRefresh() {
    _refreshTimer?.cancel();
    
    if (_hasProcessingFiles) {
      _refreshTimer = Timer.periodic(const Duration(seconds: 3), (timer) {
        if (mounted && _hasProcessingFiles) {
          _loadFiles(silent: true); // Refresh silencieux
        } else {
          _stopPeriodicRefresh();
        }
      });
    }
  }

  /// Arrête le refresh périodique
  void _stopPeriodicRefresh() {
    _refreshTimer?.cancel();
    _refreshTimer = null;
  }

  /// Sélectionne et upload des fichiers
  Future<void> _pickAndUploadFiles() async {
    if (_uploadSession == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Session d\'upload non initialisée'),
            backgroundColor: Colors.red,
          ),
        );
      }
      return;
    }

    try {
      print('🎯 Début sélection fichiers...');
      
      FilePickerResult? result = await FilePicker.platform.pickFiles(
        allowMultiple: true,
        type: FileType.custom,
        allowedExtensions: ['mp4', 'mov', 'avi', 'mkv', 'mp3', 'wav', 'aac', 'm4a', 'jpg', 'jpeg', 'png', 'gif', 'webp'],
        withData: kIsWeb, // Important pour le web
        withReadStream: !kIsWeb, // Pour desktop/mobile
      );

      print('📁 Résultat sélection: ${result?.files.length ?? 0} fichier(s)');

      if (result != null && result.files.isNotEmpty) {
        print('📤 Début upload de ${result.files.length} fichier(s)');
        await _uploadFiles(result.files);
      } else {
        print('❌ Aucun fichier sélectionné');
      }
    } catch (e, stackTrace) {
      print('💥 Erreur sélection fichiers: $e');
      print('📋 Stack trace: $stackTrace');
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur de sélection: $e'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 5),
          ),
        );
      }
    }
  }

  /// Upload les fichiers sélectionnés
  Future<void> _uploadFiles(List<PlatformFile> platformFiles) async {
    if (_uploadSession == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Session d\'upload non initialisée'),
            backgroundColor: Colors.red,
          ),
        );
      }
      return;
    }

    setState(() => _isUploading = true);

    try {
      // Filtrer les fichiers valides
      final validFiles = platformFiles.where((f) => f.bytes != null || f.path != null).toList();
      
      if (validFiles.isEmpty) {
        throw Exception('Aucun fichier valide à uploader');
      }

      List<MediaFile> uploadedFiles = [];

      // Upload un par un (plus fiable)
      for (var file in validFiles) {
        try {
          MediaFile uploadedFile;
          
          if (kIsWeb && file.bytes != null) {
            // Upload web avec bytes
            uploadedFile = await _uploadWebFile(file);
          } else if (!kIsWeb && file.path != null) {
            // Upload desktop/mobile avec path
            uploadedFile = await _uploadNativeFile(file);
          } else {
            continue; // Skip invalid files
          }
          
          uploadedFiles.add(uploadedFile);
          
          // Mapper la progression au nom original du fichier de l'API
          final originalName = uploadedFile.originalName;
          final fileKey = file.name;
          if (_uploadProgress.containsKey(fileKey) && _fileSizes.containsKey(fileKey)) {
            setState(() {
              // Transférer la progression au nom original
              _uploadProgress[originalName] = _uploadProgress[fileKey] ?? 1.0;
              _fileSizes[originalName] = _fileSizes[fileKey]!;
              // Garder l'ancienne clé aussi en attendant le refresh
              // Elle sera nettoyée après le refresh
            });
          }
          
          // Feedback pour chaque fichier
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('${file.name} envoyé avec succès'),
                backgroundColor: Colors.green,
                duration: const Duration(seconds: 2),
              ),
            );
          }
          
        } catch (e) {
          // Erreur sur un fichier spécifique
          if (mounted) {
            String errorMessage = e.toString();
            
            // Message spécifique pour les erreurs CORS
            if (errorMessage.contains('connection error') || 
                errorMessage.contains('XMLHttpRequest onError')) {
              errorMessage = 'Erreur CORS: Le serveur bloque les uploads depuis localhost. '
                  'Contactez l\'administrateur pour configurer CORS ou déployez l\'application.';
            }
            
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Erreur ${file.name}: $errorMessage'),
                backgroundColor: Colors.orange,
                duration: const Duration(seconds: 8),
                action: SnackBarAction(
                  label: 'Aide',
                  textColor: Colors.white,
                  onPressed: () {
                    showDialog(
                      context: context,
                      builder: (context) => AlertDialog(
                        title: const Text('Erreur CORS'),
                        content: const Text(
                          'Problème : Le serveur bloque les uploads depuis localhost.\n\n'
                          'Solutions :\n'
                          '• Déployer l\'application sur le même domaine\n'
                          '• Configurer CORS sur le serveur Laravel\n'
                          '• Utiliser un proxy de développement\n\n'
                          'Contactez l\'administrateur système.',
                        ),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.of(context).pop(),
                            child: const Text('OK'),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
            );
          }
        }
      }

      // Feedback global
      if (mounted) {
        if (uploadedFiles.isNotEmpty) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('${uploadedFiles.length}/${validFiles.length} fichier(s) envoyé(s)'),
              backgroundColor: uploadedFiles.length == validFiles.length ? Colors.green : Colors.orange,
              duration: const Duration(seconds: 3),
            ),
          );
        }
        
        // Refresh immédiat
        await _loadFiles();
        _startPeriodicRefresh(); // Redémarrer le refresh si nécessaire
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur générale d\'upload: $e'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 5),
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _isUploading = false);
      }
    }
  }

  /// Upload d'un fichier sur le web
  Future<MediaFile> _uploadWebFile(PlatformFile file) async {
    print('🌐 Upload web: ${file.name} (${file.size} bytes)');
    
    if (file.bytes == null) {
      print('❌ Fichier web sans données bytes');
      throw Exception('Fichier web sans données');
    }

    // Stocker la taille du fichier pour l'affichage
    final fileKey = file.name;
    setState(() {
      _fileSizes[fileKey] = file.size;
      _uploadProgress[fileKey] = 0.0;
    });

    // Détecter le MIME type
    final mimeType = file.extension?.toLowerCase() == 'mp4' 
        ? 'video/mp4'
        : file.extension?.toLowerCase() == 'mp3'
        ? 'audio/mpeg'
        : 'application/octet-stream';

    // Utiliser l'upload par chunks pour fichiers > 50MB (plus fiable pour éviter les timeouts)
    if (file.size > 50 * 1024 * 1024) {
      print('📦 Fichier volumineux (${(file.size / 1024 / 1024).toStringAsFixed(1)} MB), utilisation de l\'upload par chunks');
      try {
        final mediaFile = await MediaService.instance.uploadFileByChunks(
          fileBytes: file.bytes!,
          fileName: file.name,
          sessionToken: _uploadSession!.sessionToken,
          onProgress: (progress) {
            if (mounted) {
              setState(() {
                _uploadProgress[fileKey] = progress;
              });
            }
          },
        );

        if (mounted) {
          setState(() {
            _uploadProgress[fileKey] = 1.0;
          });
        }

        return mediaFile;
      } catch (e) {
        print('⚠️ Upload par chunks échoué: $e');
        // Fallback sur upload normal
        print('📤 Retour à l\'upload normal...');
      }
    }

    // Upload normal pour petits fichiers
    print('📦 Création FormData...');
    final formData = FormData.fromMap({
      'session_token': _uploadSession!.sessionToken,
      'file': MultipartFile.fromBytes(
        file.bytes!,
        filename: file.name,
      ),
    });

    print('📤 Envoi vers /media/upload...');
    final response = await _dio.post(
      '/media/upload',
      data: formData,
      onSendProgress: (sent, total) {
        if (mounted && total > 0) {
          final progress = sent / total;
          setState(() {
            _uploadProgress[fileKey] = progress;
          });
          print('📊 Progression: ${(progress * 100).toStringAsFixed(1)}% ($sent / $total bytes)');
        }
      },
    );
    
    // Marquer comme terminé mais garder la progression jusqu'au refresh
    if (mounted) {
      setState(() {
        _uploadProgress[fileKey] = 1.0; // 100%
      });
    }
    
    print('📥 Réponse reçue: ${response.statusCode}');
    print('📄 Données: ${response.data}');
    
    if (response.statusCode == 200 || response.statusCode == 201) {
      final data = response.data as Map<String, dynamic>;
      
      if (data['success'] == true && data.containsKey('data')) {
        final mediaFileData = data['data']['media_file'] as Map<String, dynamic>;
        print('✅ Fichier uploadé avec succès: ${mediaFileData['filename']}');
        return MediaFile.fromJson(mediaFileData);
      } else {
        print('❌ Réponse API: success=false ou data manquant');
        print('📋 Réponse complète: $data');
      }
    } else {
      print('❌ Code de statut invalide: ${response.statusCode}');
    }
    
    throw Exception('Réponse API invalide: ${response.statusCode}');
  }

  /// Upload d'un fichier natif (desktop/mobile)
  Future<MediaFile> _uploadNativeFile(PlatformFile file) async {
    if (file.path == null) {
      throw Exception('Fichier natif sans chemin');
    }

    // Stocker la taille du fichier pour l'affichage
    final fileKey = file.name;
    setState(() {
      _fileSizes[fileKey] = file.size;
      _uploadProgress[fileKey] = 0.0;
    });

    try {
      final mediaFile = await MediaService.instance.uploadFile(
      filePath: file.path!,
      fileName: file.name,
      sessionToken: _uploadSession!.sessionToken,
        onProgress: (progress) {
          if (mounted) {
            setState(() {
              _uploadProgress[fileKey] = progress;
            });
          }
        },
      );

      // Marquer comme terminé mais garder la progression jusqu'au refresh
      if (mounted) {
        setState(() {
          _uploadProgress[fileKey] = 1.0; // 100%
        });
      }

      return mediaFile;
    } catch (e) {
      // Nettoyer en cas d'erreur
      if (mounted) {
        setState(() {
          _uploadProgress.remove(fileKey);
          _fileSizes.remove(fileKey);
        });
      }
      rethrow;
    }
  }

  /// Upload d'un gros fichier JavaScript par chunks sans charger en mémoire Dart
  /// Utilise file.slice() pour découper le fichier JS et XMLHttpRequest pour uploader
  Future<void> _uploadLargeFileByChunksJs(dynamic jsFile) async {
    print('🚀 Upload gros fichier par chunks JS: ${js_util.getProperty(jsFile as Object, 'name')}');
    
    try {
      final name = js_util.getProperty(jsFile as Object, 'name') as String;
      final size = js_util.getProperty(jsFile as Object, 'size') as int;
      const chunkSize = 25 * 1024 * 1024; // 25 MB par chunk
      
      print('📁 Fichier: $name (${(size / 1024 / 1024).toStringAsFixed(1)} MB)');
      print('📊 Chunks: ${(size / chunkSize).ceil()} morceaux de ${(chunkSize / 1024 / 1024).toStringAsFixed(1)} MB');
      
      // Stocker pour affichage progression
      setState(() {
        _fileSizes[name] = size;
        _uploadProgress[name] = 0.0;
      });
      
      final uploadId = '${name}_${DateTime.now().millisecondsSinceEpoch}';
      final totalChunks = (size / chunkSize).ceil();
      
      // Upload chaque chunk avec slice()
      for (int chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
        final start = chunkIndex * chunkSize;
        final end = (start + chunkSize < size) ? start + chunkSize : size;
        
        print('📤 Chunk ${chunkIndex + 1}/$totalChunks: ${start}-${end}');
        
        // Découper le fichier avec slice()
        final chunk = js_util.callMethod(jsFile as Object, 'slice', [start, end]);
        
        // Uploader ce chunk avec XMLHttpRequest
        await _uploadChunkWithXHR(chunk, name, uploadId, chunkIndex, totalChunks);
        
        // Mettre à jour progression
        if (mounted) {
          setState(() {
            _uploadProgress[name] = (chunkIndex + 1) / totalChunks;
          });
        }
      }
      
      // Finaliser l'upload
      print('✅ Tous les chunks uploadés, finalisation...');
      final mediaFile = await _finalizeChunkUpload(uploadId, name);
      
      if (mounted) {
        setState(() {
          _uploadProgress[name] = 1.0;
        });
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Fichier uploadé: $name'),
            backgroundColor: Colors.green,
          ),
        );
        
        await _loadFiles();
        _startPeriodicRefresh();
      }
      
      print('✅ Upload terminé avec succès');
      return;
    } catch (e) {
      print('❌ Erreur upload par chunks: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur upload: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
      rethrow;
    }
  }
  
  /// Upload un chunk individuel via XMLHttpRequest
  Future<void> _uploadChunkWithXHR(dynamic chunk, String fileName, String uploadId, int chunkIndex, int totalChunks) async {
    final completer = Completer<void>();
    final baseUrl = _dio.options.baseUrl;
    final url = '$baseUrl/media/upload-chunk';
    
    // Créer FormData JS
    final formDataConstructor = js_util.getProperty(js_util.globalThis as Object, 'FormData');
    final jsFormData = js_util.callConstructor(formDataConstructor as Object, []);
    js_util.callMethod(jsFormData as Object, 'append', ['session_token', _uploadSession!.sessionToken]);
    js_util.callMethod(jsFormData as Object, 'append', ['file', chunk]);
    js_util.callMethod(jsFormData as Object, 'append', ['upload_id', uploadId]);
    js_util.callMethod(jsFormData as Object, 'append', ['chunk_index', chunkIndex.toString()]);
    js_util.callMethod(jsFormData as Object, 'append', ['total_chunks', totalChunks.toString()]);
    
    // Créer XMLHttpRequest
    final xhrConstructor = js_util.getProperty(js_util.globalThis as Object, 'XMLHttpRequest');
    final xhr = js_util.callConstructor(xhrConstructor as Object, []);
    
    js_util.callMethod(xhr as Object, 'open', ['POST', url, true]);
    js_util.callMethod(xhr as Object, 'setRequestHeader', ['Accept', 'application/json']);
    
    // Callback succès
    js_util.setProperty(xhr as Object, 'onload', js_util.allowInterop((dynamic event) {
      final status = js_util.getProperty(xhr as Object, 'status') as int;
      if (status >= 200 && status < 300) {
        completer.complete();
      } else {
        completer.completeError('HTTP $status');
      }
    }));
    
    // Callback erreur
    js_util.setProperty(xhr as Object, 'onerror', js_util.allowInterop((dynamic error) {
      completer.completeError('Erreur réseau: $error');
    }));
    
    // Envoyer
    js_util.callMethod(xhr as Object, 'send', [jsFormData]);
    
    await completer.future;
  }
  
  /// Finalise l'upload par chunks
  Future<MediaFile> _finalizeChunkUpload(String uploadId, String fileName) async {
    final response = await _dio.post(
      '/media/finalize-chunk-upload',
      data: {
        'session_token': _uploadSession!.sessionToken,
        'upload_id': uploadId,
        'filename': fileName,
      },
    );
    
    if (response.statusCode == 200 || response.statusCode == 201) {
      final data = response.data as Map<String, dynamic>;
      if (data['success'] == true && data.containsKey('data')) {
        final mediaFileData = data['data']['media_file'] as Map<String, dynamic>;
        return MediaFile.fromJson(mediaFileData);
      }
    }
    
    throw Exception('Erreur finalisation');
  }

  // Getter pour Dio (pour le web)
  Dio get _dio => MediaService.instance.dio;

  /// Annule l'importation d'un fichier
  Future<void> _cancelImport(int fileId) async {
    try {
      await MediaService.instance.cancelImport(fileId);
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Importation annulée'),
            backgroundColor: Colors.green,
          ),
        );
        _loadFiles();
        _startPeriodicRefresh();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  /// Relance l'importation d'un fichier en erreur
  Future<void> _retryImport(int fileId) async {
    try {
      await MediaService.instance.retryImport(fileId);
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Réimportation lancée'),
            backgroundColor: Colors.green,
          ),
        );
        _loadFiles();
        _startPeriodicRefresh();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  /// Annule tous les fichiers en cours et supprime ceux en erreur avant de quitter
  Future<void> _cancelAllAndExit() async {
    try {
      // Récupérer tous les fichiers en cours et en erreur
      final importingFiles = _files.where((f) => f.status == 'importing').toList();
      final errorFiles = _files.where((f) => f.status == 'error').toList();
      
      if (importingFiles.isEmpty && errorFiles.isEmpty) {
        // Aucun fichier à nettoyer, quitter directement
        if (mounted) {
          context.go('/contents');
        }
        return;
      }
      
      // Afficher un indicateur de chargement
      if (mounted) {
        setState(() => _isLoading = true);
      }
      
      int cancelledCount = 0;
      int deletedCount = 0;
      
      // Annuler tous les fichiers en cours
      for (final file in importingFiles) {
        try {
          await MediaService.instance.cancelImport(file.id);
          cancelledCount++;
          print('✅ Importation annulée: ${file.originalName}');
        } catch (e) {
          print('⚠️ Erreur annulation ${file.originalName}: $e');
        }
      }
      
      // Supprimer tous les fichiers en erreur
      for (final file in errorFiles) {
        try {
          await MediaService.instance.deleteFile(file.id);
          deletedCount++;
          print('✅ Fichier erreur supprimé: ${file.originalName}');
        } catch (e) {
          print('⚠️ Erreur suppression ${file.originalName}: $e');
        }
      }
      
      if (mounted) {
        // Afficher un message de confirmation
        if (cancelledCount > 0 || deletedCount > 0) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                '${cancelledCount} importation(s) annulée(s), '
                '${deletedCount} fichier(s) en erreur supprimé(s)'
              ),
              backgroundColor: Colors.green,
              duration: const Duration(seconds: 3),
            ),
          );
        }
        
        // Retourner à /contents
        context.go('/contents');
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur lors de l\'annulation: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        
        return Column(
          children: [
            // Header avec logo et profil
            _Header(isMobile: isMobile),
            
            // Contenu principal
            Expanded(
              child: SingleChildScrollView(
                padding: EdgeInsets.symmetric(
                  horizontal: isMobile ? 16 : 24,
                  vertical: isMobile ? 16 : 20,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Titre et sous-titre
                    const Text(
                      'Médiathèque',
                      style: TextStyle(
                        fontSize: 32,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF111827),
                      ),
                    ),
                    const Gap(4),
                    const Text(
                      'Ajoutez de nouveaux fichiers à votre collection',
                      style: TextStyle(
                        fontSize: 16,
                        color: Color(0xFF6B7280),
                      ),
                    ),
                    const Gap(32),
                    
                    // Zone de dépôt
                    _UploadZone(
                      isMobile: isMobile,
                      isUploading: _isUploading,
                      onPickFiles: _pickAndUploadFiles,
                      onDropFiles: (files) async {
                        print('📤 onDropFiles appelé avec ${files.length} fichiers');
                        if (_uploadSession != null) {
                          print('✅ Session d\'upload disponible, lancement de l\'upload...');
                          await _uploadFiles(files);
                        } else {
                          print('❌ Pas de session d\'upload disponible');
                        }
                      },
                      onDropzoneCreated: (controller) {
                        _dropzoneController = controller;
                      },
                      onLargeFileDrop: (ev) => _uploadLargeFileByChunksJs(ev),
                    ),
                    const Gap(24),
                    
                    // Liste des fichiers (incluant ceux en cours d'upload)
                    _FilesList(
                      isMobile: isMobile,
                      files: _files,
                      isLoading: _isLoading,
                      errorMessage: _errorMessage,
                      uploadProgress: _uploadProgress,
                      fileSizes: _fileSizes,
                      onCancel: _cancelImport,
                      onRetry: _retryImport,
                      onRefresh: _loadFiles,
                      onClearUploadProgress: (fileName) {
                        setState(() {
                          _uploadProgress.remove(fileName);
                          _fileSizes.remove(fileName);
                        });
                      },
                    ),
                    const Gap(32),
                    
                    // Boutons d'action
                    _ActionButtons(
                      isMobile: isMobile,
                      isUploading: _isUploading,
                      onImport: _pickAndUploadFiles,
                      onCancel: _cancelAllAndExit,
                    ),
                  ],
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

/// Header avec logo et profil utilisateur
class _Header extends StatelessWidget {
  const _Header({required this.isMobile});
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    if (isMobile) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.1),
              blurRadius: 4,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: AppTheme.blueColor,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Text(
                    'EMB-MISSION',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                    ),
                  ),
                ),
                const Spacer(),
              ],
            ),
            const Gap(12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const UserProfileChip(),
                ElevatedButton.icon(
                  onPressed: () {},
                  icon: const Icon(Icons.live_tv),
                  label: const Text('Démarrer Diffusion Live'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.redColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  ),
                ),
              ],
            ),
          ],
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.1),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: AppTheme.blueColor,
              borderRadius: BorderRadius.circular(6),
            ),
            child: const Text(
              'EMB-MISSION',
              style: TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w700,
                fontSize: 12,
              ),
            ),
          ),
          const Spacer(),
          const UserProfileChip(),
          const Gap(16),
          ElevatedButton.icon(
            onPressed: () {},
            icon: const Icon(Icons.live_tv),
            label: const Text('Démarrer Diffusion Live'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.redColor,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            ),
          ),
        ],
      ),
    );
  }
}

/// Zone de dépôt drag & drop
class _UploadZone extends StatefulWidget {
  const _UploadZone({
    required this.isMobile,
    required this.isUploading,
    required this.onPickFiles,
    required this.onDropFiles,
    required this.onDropzoneCreated,
    this.onLargeFileDrop,
  });
  
  final bool isMobile;
  final bool isUploading;
  final VoidCallback onPickFiles;
  final Function(List<PlatformFile>) onDropFiles;
  final Function(DropzoneViewController) onDropzoneCreated;
  final Function(dynamic)? onLargeFileDrop; // Pour fichiers > 500MB

  @override
  State<_UploadZone> createState() => _UploadZoneState();
}

class _UploadZoneState extends State<_UploadZone> {
  bool _isDragOver = false;
  DropzoneViewController? _dropzoneController;

  /// Convertit un File JavaScript en PlatformFile en le chargeant en mémoire
  /// ⚠️ LIMITE: Ne peut pas charger des fichiers > 2GB (limite ArrayBuffer JavaScript)
  Future<PlatformFile> _convertJsFileToPlatformFile(dynamic ev) async {
    // Récupérer les propriétés du fichier
    final name = js_util.getProperty(ev as Object, 'name') as String;
    final size = js_util.getProperty(ev as Object, 'size') as int;
    
    print('📁 Fichier: $name ($size bytes)');
    
    // Avertissement pour gros fichiers
    if (size > 500 * 1024 * 1024) {
      print('⚠️ Fichier volumineux détecté (${(size / 1024 / 1024).toStringAsFixed(1)} MB)');
      print('⚠️ Chargement en mémoire - peut échouer si > 2GB');
    }
    
    // Créer un FileReader pour lire le fichier
    final fileReaderConstructor = js_util.getProperty(js_util.globalThis as Object, 'FileReader');
    final fileReader = js_util.callConstructor(fileReaderConstructor as Object, []);
    
    // Créer un completer pour attendre la lecture
    final completer = Completer<Uint8List>();
    
    // Définir le callback onload
    js_util.setProperty(fileReader as Object, 'onload', js_util.allowInterop((dynamic event) {
      final result = js_util.getProperty(js_util.getProperty(event as Object, 'target') as Object, 'result');
      final bytes = Uint8List.view(result as ByteBuffer);
      completer.complete(bytes);
    }));
    
    // Définir le callback onerror
    js_util.setProperty(fileReader as Object, 'onerror', js_util.allowInterop((dynamic error) {
      completer.completeError(Exception('FileReader error: fichier trop volumineux (limite ArrayBuffer ~2GB)'));
    }));
    
    // Lancer la lecture
    js_util.callMethod(fileReader as Object, 'readAsArrayBuffer', [ev as Object]);
    
    // Attendre la fin de la lecture
    final bytes = await completer.future;
    
    print('✅ Fichier lu: $name (${bytes.length} bytes)');
    
    return PlatformFile(
      name: name,
      size: bytes.length,
      bytes: bytes,
      path: null,
    );
  }

  @override
  Widget build(BuildContext context) {
    if (!kIsWeb) {
      // Version non-web (simple container cliquable)
      return GestureDetector(
        onTap: widget.isUploading ? null : widget.onPickFiles,
        child: _UploadZoneContent(
          isMobile: widget.isMobile,
          isUploading: widget.isUploading,
          onPickFiles: widget.onPickFiles,
          isDragOver: false,
        ),
      );
    }

    // Version web avec drag & drop
    return Stack(
      children: [
        // Contenu visuel en arrière-plan
        _UploadZoneContent(
          isMobile: widget.isMobile,
          isUploading: widget.isUploading,
          onPickFiles: widget.onPickFiles,
          isDragOver: _isDragOver,
        ),
        // DropzoneView au premier plan pour capturer les événements de drag & drop
        // IgnorePointer pour laisser passer les clics vers le bouton
        Positioned.fill(
          child: IgnorePointer(
            ignoring: true, // Ignore les clics mais pas le drag & drop
            child: DropzoneView(
            onCreated: (controller) {
              _dropzoneController = controller;
              widget.onDropzoneCreated(controller);
            },
            onDrop: (dynamic ev) async {
              print('🎯 DROP DETECTÉ ! Type: ${ev.runtimeType}');
              setState(() => _isDragOver = false);
              
              // Récupérer la taille du fichier
              final size = js_util.getProperty(ev as Object, 'size') as int;
              
              // Si gros fichier et callback disponible, utiliser upload par chunks JavaScript
              if (size > 500 * 1024 * 1024 && widget.onLargeFileDrop != null) {
                print('📦 Gros fichier détecté (${(size / 1024 / 1024).toStringAsFixed(1)} MB), utilisation upload par chunks');
                await widget.onLargeFileDrop!(ev);
              } else {
                // Upload normal via FileReader (charge en mémoire)
                await widget.onDropFiles([await _convertJsFileToPlatformFile(ev)]);
              }
            },
            onHover: () {
              // Pas de log pour éviter de polluer la console
              setState(() => _isDragOver = true);
            },
            onLeave: () {
              // Pas de log pour éviter de polluer la console
              setState(() => _isDragOver = false);
            },
          ),
          ),
        ),
      ],
    );
  }
}

/// Contenu de la zone d'upload
class _UploadZoneContent extends StatelessWidget {
  const _UploadZoneContent({
    required this.isMobile,
    required this.isUploading,
    required this.onPickFiles,
    this.isDragOver = false,
  });
  
  final bool isMobile;
  final bool isUploading;
  final VoidCallback onPickFiles;
  final bool isDragOver;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(48),
      decoration: BoxDecoration(
        color: isDragOver ? AppTheme.blueColor.withValues(alpha: 0.05) : Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isDragOver ? AppTheme.blueColor : const Color(0xFFE5E7EB),
          style: BorderStyle.solid,
          width: isDragOver ? 3 : 2,
        ),
        boxShadow: [
          BoxShadow(
            color: isDragOver 
                ? AppTheme.blueColor.withValues(alpha: 0.2)
                : Colors.black.withValues(alpha: 0.06),
            blurRadius: isDragOver ? 20 : 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        children: [
          // Icône cloud
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: isDragOver 
                  ? AppTheme.blueColor.withValues(alpha: 0.2)
                  : AppTheme.blueColor.withValues(alpha: 0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(
              isUploading ? Icons.cloud_sync : Icons.cloud_upload,
              size: isMobile ? 48 : 64,
              color: isDragOver ? AppTheme.blueColor : AppTheme.blueColor,
            ),
          ),
          const Gap(24),
          
          // Texte principal
          Text(
            isUploading 
                ? 'Upload en cours...' 
                : isDragOver 
                    ? 'Relâchez pour uploader' 
                    : 'Glissez et déposez vos fichiers ici',
            style: TextStyle(
              fontSize: isMobile ? 18 : 20,
              fontWeight: FontWeight.w600,
              color: isDragOver ? AppTheme.blueColor : const Color(0xFF111827),
            ),
            textAlign: TextAlign.center,
          ),
          const Gap(8),
          
          // Types de fichiers acceptés
          Text(
            'Fichiers vidéo, audio ou images',
            style: TextStyle(
              fontSize: isMobile ? 14 : 16,
              color: const Color(0xFF6B7280),
            ),
            textAlign: TextAlign.center,
          ),
          const Gap(24),
          
          // Séparateur
          Row(
            children: [
              const Expanded(child: Divider()),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Text(
                  'OU',
                  style: TextStyle(
                    fontSize: isMobile ? 12 : 14,
                    color: const Color(0xFF6B7280),
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              const Expanded(child: Divider()),
            ],
          ),
          const Gap(24),
          
          // Bouton Parcourir
          ElevatedButton.icon(
            onPressed: isUploading ? null : onPickFiles,
            icon: isUploading
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.folder_open, size: 18),
            label: Text(isUploading ? 'Upload en cours...' : 'Parcourir les fichiers'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.blueColor,
              foregroundColor: const Color(0xFF2F3B52),
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Liste des fichiers avec statuts
class _FilesList extends StatelessWidget {
  const _FilesList({
    required this.isMobile,
    required this.files,
    required this.isLoading,
    required this.errorMessage,
    required this.uploadProgress,
    required this.fileSizes,
    required this.onCancel,
    required this.onRetry,
    required this.onRefresh,
    required this.onClearUploadProgress,
  });
  
  final bool isMobile;
  final List<MediaFile> files;
  final bool isLoading;
  final String? errorMessage;
  final Map<String, double> uploadProgress;
  final Map<String, int> fileSizes;
  final Function(int) onCancel;
  final Function(int) onRetry;
  final VoidCallback onRefresh;
  final Function(String) onClearUploadProgress;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
      children: [
        Text(
          'Fichiers en cours d\'importation',
          style: TextStyle(
            fontSize: isMobile ? 18 : 20,
            fontWeight: FontWeight.w700,
            color: const Color(0xFF111827),
          ),
            ),
            // Indicateur d'activité en temps réel
            if (files.any((f) => f.isProcessing))
              Container(
                margin: const EdgeInsets.only(left: 8),
                width: 8,
                height: 8,
                decoration: const BoxDecoration(
                  color: Color(0xFF10B981),
                  shape: BoxShape.circle,
                ),
              ),
            const Spacer(),
            if (errorMessage != null)
              IconButton(
                onPressed: onRefresh,
                icon: const Icon(Icons.refresh, size: 20),
                tooltip: 'Réessayer',
              ),
          ],
        ),
        const Gap(16),
        
        // État de chargement
        if (isLoading)
          const Center(
            child: Padding(
              padding: EdgeInsets.all(40),
              child: CircularProgressIndicator(),
            ),
          ),
        
        // État d'erreur
        if (errorMessage != null && !isLoading)
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: const Color(0xFFFEF2F2),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFFECACA)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    Icon(Icons.error_outline, color: Color(0xFFDC2626), size: 20),
                    Gap(8),
                    Text(
                      'Erreur de chargement',
                      style: TextStyle(
                        color: Color(0xFFDC2626),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
                const Gap(8),
                Text(
                  errorMessage!,
                  style: const TextStyle(
                    color: Color(0xFF991B1B),
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ),
        
        // Liste des fichiers en cours d'upload (pas encore dans l'API)
        if (uploadProgress.isNotEmpty)
          ...uploadProgress.entries.map((entry) {
            // Vérifier si ce fichier est déjà dans la liste API
            final isInApiList = files.any((f) => 
              f.originalName == entry.key || f.filename == entry.key
            );
            
            // Afficher seulement si pas encore dans l'API
            if (isInApiList) return const SizedBox.shrink();
            
            final fileName = entry.key;
            final progress = entry.value;
            final fileSize = fileSizes[fileName];
            
            return Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _FileItem(
                file: null, // Fichier temporaire
                fileName: fileName,
                isMobile: isMobile,
                uploadProgress: progress,
                fileSize: fileSize,
                onCancel: () {
                  // Annuler l'upload en cours
                  onClearUploadProgress(fileName);
                },
                onRetry: null,
              ),
            );
          }),
        
        // Liste vide
        if (files.isEmpty && uploadProgress.isEmpty && !isLoading && errorMessage == null)
          Center(
            child: Padding(
              padding: const EdgeInsets.all(40),
              child: Column(
                children: [
                  Icon(
                    Icons.inbox,
                    size: 64,
                    color: Colors.grey[300],
                  ),
                  const Gap(16),
                  const Text(
                    'Aucun fichier en cours d\'importation',
                    style: TextStyle(
                      fontSize: 16,
                      color: Color(0xFF6B7280),
                    ),
                  ),
                ],
              ),
            ),
          ),
        
        // Liste des fichiers
        if (files.isNotEmpty && !isLoading)
          ...files.map((file) {
            // Chercher la progression par originalName d'abord, puis par filename comme fallback
            final progress = uploadProgress[file.originalName] ?? 
                            uploadProgress[file.filename];
            final size = fileSizes[file.originalName] ?? 
                        fileSizes[file.filename];
            
            return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _FileItem(
                  file: file,
          isMobile: isMobile,
                uploadProgress: progress,
                fileSize: size,
                  onCancel: file.isProcessing ? () => onCancel(file.id) : null,
                  onRetry: file.hasError ? () => onRetry(file.id) : null,
        ),
            );
          }),
      ],
    );
  }
}

/// Élément de fichier
class _FileItem extends StatelessWidget {
  const _FileItem({
    this.file,
    this.fileName,
    required this.isMobile,
    this.uploadProgress,
    this.fileSize,
    this.onCancel,
    this.onRetry,
  }) : assert(file != null || fileName != null, 'file ou fileName doit être fourni');

  final MediaFile? file;
  final String? fileName; // Pour les fichiers en cours d'upload non encore dans l'API
  final bool isMobile;
  final double? uploadProgress;
  final int? fileSize;
  final VoidCallback? onCancel;
  final VoidCallback? onRetry;
  
  // Helper pour obtenir le nom du fichier
  String get _displayName => file?.originalName ?? fileName ?? 'Fichier inconnu';
  
  // Helper pour obtenir l'icône du fichier
  IconData get _fileIcon {
    if (file != null) return file!.fileIcon;
    final name = fileName ?? '';
    if (name.toLowerCase().endsWith('.mp4') || name.toLowerCase().endsWith('.avi') || name.toLowerCase().endsWith('.mov')) {
      return Icons.videocam;
    } else if (name.toLowerCase().endsWith('.mp3') || name.toLowerCase().endsWith('.wav')) {
      return Icons.audiotrack;
    }
    return Icons.insert_drive_file;
  }
  
  // Helper pour obtenir la couleur de l'icône
  Color get _iconColor {
    if (file != null) return file!.iconColor;
    return AppTheme.blueColor;
  }

  /// Formate la taille en MB ou GB
  String _formatFileSize(int bytes) {
    if (bytes < 1024 * 1024) {
      return '${(bytes / 1024).toStringAsFixed(1)} KB';
    } else if (bytes < 1024 * 1024 * 1024) {
      return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
    } else {
      return '${(bytes / (1024 * 1024 * 1024)).toStringAsFixed(2)} GB';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          // Icône du fichier
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: _iconColor.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(6),
            ),
            child: Icon(_fileIcon, color: _iconColor, size: 20),
          ),
          const Gap(12),
          
          // Informations du fichier
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _displayName,
                  style: TextStyle(
                    fontSize: isMobile ? 14 : 16,
                    fontWeight: FontWeight.w600,
                    color: const Color(0xFF111827),
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
                const Gap(4),
                // Afficher la progression d'upload si disponible, sinon le texte de progression normal
                if (uploadProgress != null && fileSize != null)
                Text(
                    '⬆️ Uploading... ${(uploadProgress! * 100).toStringAsFixed(0)}% '
                    '(${_formatFileSize((uploadProgress! * fileSize!).round())} / ${_formatFileSize(fileSize!)})',
                  style: TextStyle(
                    fontSize: isMobile ? 12 : 14,
                      color: const Color(0xFF2196F3), // Bleu pur/vif
                      fontWeight: FontWeight.w500,
                    ),
                  )
                else if (file != null)
                  Text(
                    file!.progressText,
                    style: TextStyle(
                      fontSize: isMobile ? 12 : 14,
                      color: file!.hasError ? AppTheme.redColor : const Color(0xFF6B7280),
                    ),
                  )
                else
                  const Text(
                    'En attente...',
                    style: TextStyle(
                      fontSize: 14,
                      color: Color(0xFF6B7280),
                  ),
                ),
                const Gap(8),
                
                // Barre de progression avec animation fluide
                // Priorité à la progression d'upload si disponible
                TweenAnimationBuilder<double>(
                  tween: Tween<double>(
                    begin: 0,
                    end: uploadProgress ?? (file?.progressPercentage ?? 0.0),
                  ),
                  duration: const Duration(milliseconds: 300),
                  curve: Curves.easeOut,
                  builder: (context, animatedValue, child) {
                    // Déterminer la couleur : vert si terminé, bleu si en cours d'upload
                    // PRIORITÉ 1: Si le fichier est terminé, toujours vert (ignorer uploadProgress)
                    if (file?.isCompleted == true) {
                      return LinearProgressIndicator(
                        value: animatedValue,
                        backgroundColor: const Color(0xFFE5E7EB),
                        valueColor: AlwaysStoppedAnimation<Color>(file!.statusColor), // Vert
                        minHeight: 4,
                      );
                    }
                    
                    // PRIORITÉ 2: Si upload en cours (progress < 1.0), bleu
                    if (uploadProgress != null && uploadProgress! < 1.0) {
                      return LinearProgressIndicator(
                        value: animatedValue,
                        backgroundColor: const Color(0xFFE5E7EB),
                        valueColor: AlwaysStoppedAnimation<Color>(const Color(0xFF2196F3)), // Bleu
                        minHeight: 4,
                      );
                    }
                    
                    // PRIORITÉ 3: Sinon, utiliser la couleur du statut du fichier
                    final color = file?.statusColor ?? const Color(0xFF2196F3);
                    return LinearProgressIndicator(
                      value: animatedValue,
                      backgroundColor: const Color(0xFFE5E7EB),
                      valueColor: AlwaysStoppedAnimation<Color>(color),
                      minHeight: 4,
                    );
                  },
                ),
              ],
            ),
          ),
          const Gap(12),
          
          // Statut et actions
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              if (file != null)
              Text(
                  file!.statusText,
                style: TextStyle(
                  fontSize: isMobile ? 12 : 14,
                  fontWeight: FontWeight.w600,
                    color: file!.statusColor,
                  ),
                )
              else if (uploadProgress != null)
                Text(
                  'Upload...',
                  style: TextStyle(
                    fontSize: isMobile ? 12 : 14,
                    fontWeight: FontWeight.w600,
                    color: const Color(0xFF2196F3), // Bleu pur/vif
                ),
              ),
              const Gap(8),
              if (onCancel != null)
                IconButton(
                  onPressed: onCancel,
                  icon: const Icon(Icons.close, size: 16),
                  color: const Color(0xFF6B7280),
                  tooltip: 'Annuler',
                )
              else if (onRetry != null)
                IconButton(
                  onPressed: onRetry,
                  icon: const Icon(Icons.refresh, size: 16),
                  color: AppTheme.redColor,
                  tooltip: 'Réessayer',
                )
              else if (file != null)
                Icon(
                  Icons.check_circle,
                  size: 16,
                  color: file!.statusColor,
                ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Boutons d'action
class _ActionButtons extends StatelessWidget {
  const _ActionButtons({
    required this.isMobile,
    required this.isUploading,
    required this.onImport,
    required this.onCancel,
  });
  
  final bool isMobile;
  final bool isUploading;
  final VoidCallback onImport;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    if (isMobile) {
      return Column(
        children: [
          SizedBox(
            width: double.infinity,
            child: OutlinedButton(
              onPressed: onCancel,
              style: OutlinedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 12),
                side: const BorderSide(color: Color(0xFF6B7280)),
              ),
              child: const Text('Annuler'),
            ),
          ),
          const Gap(12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: isUploading ? null : onImport,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.redColor,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
              child: Text(isUploading ? 'Upload en cours...' : 'Importer les fichiers'),
            ),
          ),
        ],
      );
    }

    return Row(
      children: [
        OutlinedButton(
          onPressed: onCancel,
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
            side: const BorderSide(color: Color(0xFF6B7280)),
          ),
          child: const Text('Annuler'),
        ),
        const Spacer(),
        ElevatedButton(
          onPressed: isUploading ? null : onImport,
          style: ElevatedButton.styleFrom(
            backgroundColor: AppTheme.redColor,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          ),
          child: Text(isUploading ? 'Upload en cours...' : 'Importer les fichiers'),
        ),
      ],
    );
  }
}
