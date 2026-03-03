import 'package:emb_mission_dashboard/core/models/media_models.dart';
import 'package:emb_mission_dashboard/core/services/media_service.dart';
import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/confirmation_dialog.dart';
import 'package:emb_mission_dashboard/core/widgets/emb_logo.dart';
import 'package:emb_mission_dashboard/core/widgets/success_dialog.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:emb_mission_dashboard/features/contents/widgets/edit_video_dialog.dart';
import 'package:audioplayers/audioplayers.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'dart:html' as html;
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

class ContentsPage extends StatefulWidget {
  const ContentsPage({super.key});

  @override
  State<ContentsPage> createState() => _ContentsPageState();
}

class _ContentsPageState extends State<ContentsPage> with SingleTickerProviderStateMixin {
  final Set<int> _selectedItems = {};
  bool _isSelectionMode = false;
  late TabController _tabController;
  int _currentTabIndex = 0; // 0 = Vidéos, 1 = Audios
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';
  
  // État pour les fichiers audio dynamiques
  List<MediaFile> _audioFiles = [];
  bool _isLoadingAudio = true;
  String? _errorMessageAudio;
  PaginationInfo? _audioPagination;
  int _currentAudioPage = 1;
  
  // État pour les fichiers vidéo dynamiques
  List<MediaFile> _videoFiles = [];
  bool _isLoadingVideo = true;
  String? _errorMessageVideo;
  PaginationInfo? _videoPagination;
  int _currentVideoPage = 1;
  
  // État pour le lecteur audio
  final Map<int, AudioPlayer> _audioPlayers = {};
  final Map<int, bool> _playingStates = {};
  int? _currentlyPlayingId;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _tabController.addListener(() {
      final newIndex = _tabController.index;
      // Recharger les données si l'onglet a changé
      if (newIndex != _currentTabIndex) {
        if (newIndex == 0) {
          // Onglet Vidéos
          _loadVideoFiles();
        } else if (newIndex == 1) {
          // Onglet Audios
          _loadAudioFiles();
        }
      }
      setState(() {
        _currentTabIndex = newIndex;
      });
    });
    // Charger les fichiers audio et vidéo au démarrage
    _loadAudioFiles();
    _loadVideoFiles();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _searchController.dispose();
    
    // Nettoyer tous les lecteurs audio
    for (final player in _audioPlayers.values) {
      player.dispose();
    }
    _audioPlayers.clear();
    _playingStates.clear();
    
    super.dispose();
  }

  void _toggleSelectionMode() {
    setState(() {
      _isSelectionMode = !_isSelectionMode;
      if (!_isSelectionMode) {
        _selectedItems.clear();
      }
    });
  }

  void _onSearchChanged(String query) {
    setState(() {
      _searchQuery = query.toLowerCase();
    });
  }

  /// Charge les fichiers vidéo depuis l'API
  Future<void> _loadVideoFiles({int? page}) async {
    try {
      setState(() {
        _isLoadingVideo = true;
        _errorMessageVideo = null;
      });

      final targetPage = page ?? _currentVideoPage;
      print('🎬 Chargement des fichiers vidéo - Page $targetPage');

      final result = await MediaService.instance.getMediaFiles(
        page: targetPage,
        perPage: 20,
        fileType: 'video', // Filtrer seulement les vidéos
      );

      setState(() {
        _videoFiles = result.data;
        _videoPagination = result.pagination;
        _currentVideoPage = targetPage;
        _isLoadingVideo = false;
      });

      print('✅ ${result.data.length} fichiers vidéo chargés');
    } catch (e) {
      print('💥 Erreur chargement vidéos: $e');
      setState(() {
        _isLoadingVideo = false;
        _errorMessageVideo = e.toString();
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur chargement vidéos: $e'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 3),
          ),
        );
      }
    }
  }

  /// Charge les fichiers audio depuis l'API
  Future<void> _loadAudioFiles({int? page}) async {
    setState(() {
      _isLoadingAudio = true;
      _errorMessageAudio = null;
      if (page != null) {
        _currentAudioPage = page;
      }
    });

    try {
      final response = await MediaService.instance.getMediaFiles(
        fileType: 'audio',
        page: _currentAudioPage,
        perPage: 10,
      );

      if (mounted) {
        setState(() {
          _audioFiles = response.data;
          _audioPagination = response.pagination;
          _isLoadingAudio = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessageAudio = e.toString();
          _isLoadingAudio = false;
        });
      }
    }
  }

  /// Passe à la page suivante (audio)
  void _nextAudioPage() {
    if (_audioPagination != null && _currentAudioPage < _audioPagination!.lastPage) {
      _loadAudioFiles(page: _currentAudioPage + 1);
    }
  }

  /// Passe à la page précédente (audio)
  void _previousAudioPage() {
    if (_currentAudioPage > 1) {
      _loadAudioFiles(page: _currentAudioPage - 1);
    }
  }

  /// Page suivante pour les fichiers vidéo
  void _nextVideoPage() {
    if (_videoPagination != null && _currentVideoPage < _videoPagination!.lastPage) {
      _loadVideoFiles(page: _currentVideoPage + 1);
    }
  }

  /// Page précédente pour les fichiers vidéo
  void _previousVideoPage() {
    if (_currentVideoPage > 1) {
      _loadVideoFiles(page: _currentVideoPage - 1);
    }
  }

  /// Supprime un fichier vidéo
  Future<void> _deleteVideoFile(MediaFile file) async {
    // Afficher une boîte de dialogue de confirmation
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirmer la suppression'),
        content: Text('Êtes-vous sûr de vouloir supprimer "${file.originalName}" ?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      try {
        setState(() {
          _isLoadingVideo = true;
        });

        final result = await MediaService.instance.deleteFile(file.id);
        
        // La méthode deleteFile retourne directement le message de succès
        // Retirer le fichier de la liste locale
        setState(() {
          _videoFiles.removeWhere((f) => f.id == file.id);
          _isLoadingVideo = false;
        });

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('${file.originalName} supprimé avec succès'),
              backgroundColor: Colors.green,
            ),
          );
        }
      } catch (e) {
        setState(() {
          _isLoadingVideo = false;
        });

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Erreur lors de la suppression: $e'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    }
  }

  /// Joue un fichier vidéo (ouvre dans un nouvel onglet)
  Future<void> _playVideoFile(MediaFile file) async {
    try {
      if (file.fileUrl.isEmpty) {
        throw Exception('URL du fichier manquante');
      }

      if (!file.isVideo) {
        throw Exception('Ce fichier n\'est pas une vidéo');
      }

      if (!file.isCompleted) {
        throw Exception('Le fichier n\'est pas encore prêt (${file.statusText})');
      }

      // Ouvrir la vidéo dans un nouvel onglet
      print('🎬 Ouverture vidéo: ${file.fileUrl}');
      
      // Utiliser dart:html pour ouvrir dans un nouvel onglet (web uniquement)
      if (kIsWeb) {
        html.window.open(file.fileUrl, '_blank');
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Ouverture de "${file.originalName}" dans un nouvel onglet'),
              backgroundColor: Colors.blue,
              duration: const Duration(seconds: 2),
            ),
          );
        }
      } else {
        // Pour mobile/desktop, afficher un message avec l'URL
        if (mounted) {
          showDialog(
            context: context,
            builder: (context) => AlertDialog(
              title: Text('Lecture vidéo: ${file.originalName}'),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('URL de la vidéo :'),
                  const SizedBox(height: 8),
                  SelectableText(
                    file.fileUrl,
                    style: const TextStyle(
                      fontFamily: 'monospace',
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text('Copiez cette URL dans votre navigateur pour lire la vidéo.'),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Fermer'),
                ),
                ElevatedButton(
                  onPressed: () {
                    Navigator.of(context).pop();
                    // Copier l'URL dans le presse-papier
                    Clipboard.setData(ClipboardData(text: file.fileUrl));
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('URL copiée dans le presse-papier'),
                        backgroundColor: Colors.green,
                      ),
                    );
                  },
                  child: const Text('Copier l\'URL'),
                ),
              ],
            ),
          );
        }
      }
    } catch (e) {
      print('💥 Erreur lecture vidéo: $e');
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur lecture vidéo: $e'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 3),
          ),
        );
      }
    }
  }

  /// Formate la durée en secondes en format HH:MM:SS
  String _formatDuration(int seconds) {
    final hours = seconds ~/ 3600;
    final minutes = (seconds % 3600) ~/ 60;
    final remainingSeconds = seconds % 60;
    
    if (hours > 0) {
      return '${hours.toString().padLeft(2, '0')}:${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
    } else {
      return '${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
    }
  }


  /// Supprime un fichier audio
  Future<void> _deleteAudioFile(MediaFile file) async {
    // Afficher une boîte de dialogue de confirmation
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirmer la suppression'),
        content: Text(
          'Êtes-vous sûr de vouloir supprimer le fichier "${file.originalName}" ?\n\nCette action est irréversible.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: TextButton.styleFrom(
              foregroundColor: Colors.red,
            ),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      try {
        // Afficher un indicateur de chargement
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Row(
                children: [
                  const SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                  const Gap(12),
                  Text('Suppression de "${file.originalName}"...'),
                ],
              ),
              duration: const Duration(seconds: 2),
            ),
          );
        }

        // Appeler l'API de suppression
        await MediaService.instance.deleteFile(file.id);

        // Rafraîchir la liste des fichiers
        await _loadAudioFiles();

        // Afficher un message de succès
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Fichier "${file.originalName}" supprimé avec succès'),
              backgroundColor: Colors.green,
              duration: const Duration(seconds: 3),
            ),
          );
        }
      } catch (e) {
        // Afficher un message d'erreur
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Erreur lors de la suppression : $e'),
              backgroundColor: Colors.red,
              duration: const Duration(seconds: 5),
            ),
          );
        }
      }
    }
  }

  /// Joue ou met en pause un fichier audio (version directe sans validation)
  Future<void> _playAudioFileDirect(MediaFile file) async {
    try {
      // Arrêter le fichier actuellement en cours s'il y en a un
      if (_currentlyPlayingId != null && _currentlyPlayingId != file.id) {
        await _stopAudioFile(_currentlyPlayingId!);
      }

      // Obtenir ou créer le lecteur pour ce fichier
      final player = _audioPlayers[file.id] ?? AudioPlayer();
      _audioPlayers[file.id] = player;

      if (_playingStates[file.id] == true) {
        // Mettre en pause
        await player.pause();
        setState(() {
          _playingStates[file.id] = false;
          _currentlyPlayingId = null;
        });
      } else {
        // Test de connectivité de l'URL avant de jouer
        print('🎵 Tentative de lecture: ${file.fileUrl}');
        
        // Jouer avec gestion d'erreur spécifique
        await player.play(UrlSource(file.fileUrl));
        setState(() {
          _playingStates[file.id] = true;
          _currentlyPlayingId = file.id;
        });

        // Écouter la fin de la lecture
        player.onPlayerComplete.listen((_) {
          if (mounted) {
            setState(() {
              _playingStates[file.id] = false;
              _currentlyPlayingId = null;
            });
          }
        });

        // Écouter les erreurs de lecture
        player.onPlayerStateChanged.listen((state) {
          if (state == PlayerState.stopped && _playingStates[file.id] == true) {
            // Le lecteur s'est arrêté de manière inattendue
            if (mounted) {
              setState(() {
                _playingStates[file.id] = false;
                _currentlyPlayingId = null;
              });
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('La lecture s\'est arrêtée de manière inattendue'),
                  backgroundColor: Colors.orange,
                  duration: Duration(seconds: 2),
                ),
              );
            }
          }
        });
      }
    } catch (e) {
      print('💥 Erreur de lecture audio directe: $e');
      rethrow; // Propager l'erreur pour le fallback
    }
  }

  /// Joue ou met en pause un fichier audio
  Future<void> _playAudioFile(MediaFile file) async {
    try {
      // Validation de l'URL du fichier
      if (file.fileUrl.isEmpty) {
        throw Exception('URL du fichier manquante');
      }

      // Vérifier que l'URL est valide
      if (!_isValidAudioUrl(file.fileUrl)) {
        throw Exception('URL du fichier audio invalide');
      }

      // Vérifier que le fichier est bien un fichier audio
      if (!file.isAudio) {
        throw Exception('Ce fichier n\'est pas un fichier audio');
      }

      // Vérifier que le format est supporté
      if (!_isAudioFormatSupported(file.originalName)) {
        throw Exception('Format audio non supporté: ${file.originalName.split('.').last}');
      }

      // Vérifier que le fichier est disponible (status completed)
      if (!file.isCompleted) {
        throw Exception('Le fichier n\'est pas encore prêt (${file.statusText})');
      }

      // Vérifier les métadonnées du fichier pour détecter des problèmes potentiels
      if (file.metadata != null) {
        final metadata = file.metadata!;
        
        // Vérifier si le fichier a des métadonnées manquantes (signe de problème)
        if (metadata['duration'] == null && metadata['bitrate'] == null) {
          print('⚠️ Fichier avec métadonnées manquantes: ${file.originalName}');
        }
        
        // Vérifier le bitrate (trop bas peut causer des problèmes)
        final bitrate = metadata['bitrate'];
        if (bitrate != null && (bitrate as num) < 64) {
          print('⚠️ Bitrate très bas détecté: ${bitrate}kbps pour ${file.originalName}');
        }
      }

      // Arrêter le fichier actuellement en cours s'il y en a un
      if (_currentlyPlayingId != null && _currentlyPlayingId != file.id) {
        await _stopAudioFile(_currentlyPlayingId!);
      }

      // Obtenir ou créer le lecteur pour ce fichier
      final player = _audioPlayers[file.id] ?? AudioPlayer();
      _audioPlayers[file.id] = player;

      if (_playingStates[file.id] == true) {
        // Mettre en pause
        await player.pause();
        setState(() {
          _playingStates[file.id] = false;
          _currentlyPlayingId = null;
        });
      } else {
        // Test de connectivité de l'URL avant de jouer
        print('🎵 Tentative de lecture: ${file.fileUrl}');
        
        // Jouer avec gestion d'erreur spécifique
        await player.play(UrlSource(file.fileUrl));
        setState(() {
          _playingStates[file.id] = true;
          _currentlyPlayingId = file.id;
        });

        // Écouter la fin de la lecture
        player.onPlayerComplete.listen((_) {
          if (mounted) {
            setState(() {
              _playingStates[file.id] = false;
              _currentlyPlayingId = null;
            });
          }
        });

        // Écouter les erreurs de lecture
        player.onPlayerStateChanged.listen((state) {
          if (state == PlayerState.stopped && _playingStates[file.id] == true) {
            // Le lecteur s'est arrêté de manière inattendue
            if (mounted) {
              setState(() {
                _playingStates[file.id] = false;
                _currentlyPlayingId = null;
              });
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('La lecture s\'est arrêtée de manière inattendue'),
                  backgroundColor: Colors.orange,
                  duration: Duration(seconds: 2),
                ),
              );
            }
          }
        });
      }
    } catch (e) {
      print('💥 Erreur de lecture audio: $e');
      
      if (mounted) {
        // Messages d'erreur plus spécifiques
        String errorMessage;
        if (e.toString().contains('NotSupportedError')) {
          errorMessage = 'Format audio non supporté ou URL invalide';
        } else if (e.toString().contains('NetworkError')) {
          errorMessage = 'Erreur de réseau - Impossible d\'accéder au fichier';
        } else if (e.toString().contains('URL du fichier manquante')) {
          errorMessage = 'URL du fichier manquante';
        } else if (e.toString().contains('URL du fichier audio invalide')) {
          errorMessage = 'URL du fichier audio invalide';
        } else if (e.toString().contains('Format audio non supporté')) {
          errorMessage = 'Format audio non supporté par le navigateur';
        } else if (e.toString().contains('n\'est pas un fichier audio')) {
          errorMessage = 'Ce fichier n\'est pas un fichier audio';
        } else if (e.toString().contains('pas encore prêt')) {
          errorMessage = 'Le fichier n\'est pas encore prêt pour la lecture';
        } else {
          errorMessage = 'Erreur lors de la lecture : $e';
        }

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(errorMessage),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 4),
            action: SnackBarAction(
              label: 'Détails',
              textColor: Colors.white,
              onPressed: () {
                showDialog(
                  context: context,
                  builder: (context) => AlertDialog(
                    title: const Text('Détails de l\'erreur'),
                    content: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Fichier: ${file.originalName}'),
                        Text('URL: ${file.fileUrl}'),
                        Text('Type: ${file.fileType}'),
                        Text('Statut: ${file.statusText}'),
                        const SizedBox(height: 8),
                        Text('Erreur: $e'),
                      ],
                    ),
                    actions: [
                      TextButton(
                        onPressed: () => Navigator.of(context).pop(),
                        child: const Text('Fermer'),
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

  /// Arrête la lecture d'un fichier audio
  Future<void> _stopAudioFile(int fileId) async {
    final player = _audioPlayers[fileId];
    if (player != null) {
      await player.stop();
      setState(() {
        _playingStates[fileId] = false;
        if (_currentlyPlayingId == fileId) {
          _currentlyPlayingId = null;
        }
      });
    }
  }

  /// Vérifie si le format audio est supporté
  bool _isAudioFormatSupported(String fileName) {
    final supportedFormats = [
      '.mp3', '.wav', '.aac', '.m4a', '.ogg', '.flac', '.wma'
    ];
    
    final extension = fileName.toLowerCase().split('.').last;
    return supportedFormats.contains('.$extension');
  }

  /// Valide l'URL du fichier audio
  bool _isValidAudioUrl(String url) {
    if (url.isEmpty) return false;
    
    // Vérifier que l'URL commence par http/https
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      return false;
    }
    
    // Vérifier que l'URL contient une extension audio
    final audioExtensions = ['.mp3', '.wav', '.aac', '.m4a', '.ogg', '.flac', '.wma'];
    return audioExtensions.any((ext) => url.toLowerCase().contains(ext));
  }

  /// Teste la compatibilité du fichier audio avec le navigateur
  Future<bool> _testAudioCompatibility(String url) async {
    try {
      // Créer un lecteur temporaire pour tester
      final testPlayer = AudioPlayer();
      
      // Essayer de charger le fichier sans le jouer
      await testPlayer.setSource(UrlSource(url));
      
      // Si on arrive ici, le fichier est compatible
      await testPlayer.dispose();
      return true;
    } catch (e) {
      print('🔍 Test de compatibilité échoué pour $url: $e');
      return false;
    }
  }

  /// Diagnostique complet du fichier audio
  Future<void> _diagnoseAudioFile(MediaFile file) async {
    print('🔬 === DIAGNOSTIC AUDIO ===');
    print('📁 Fichier: ${file.originalName}');
    print('🔗 URL: ${file.fileUrl}');
    print('📊 Taille: ${file.fileSizeFormatted}');
    print('🏷️ Type: ${file.fileType}');
    print('📈 Statut: ${file.statusText}');
    
    if (file.metadata != null) {
      print('📋 Métadonnées:');
      file.metadata!.forEach((key, value) {
        print('   $key: $value');
      });
    } else {
      print('⚠️ Aucune métadonnée disponible');
    }
    
    // Test de connectivité HTTP avec différents User-Agent
    try {
      print('🌐 Test de connectivité HTTP...');
      
      // Test 1: Avec User-Agent Flutter (comme AudioPlayer)
      final response1 = await MediaService.instance.dio.head(
        file.fileUrl,
        options: Options(
          headers: {
            'User-Agent': 'Flutter AudioPlayer',
            'Accept': 'audio/*',
            'Range': 'bytes=0-1023',
          },
        ),
      );
      print('✅ Serveur accessible (Flutter UA): ${response1.statusCode}');
      
      // Test 2: Avec User-Agent navigateur
      final response2 = await MediaService.instance.dio.head(
        file.fileUrl,
        options: Options(
          headers: {
            'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
            'Accept': 'audio/*',
          },
        ),
      );
      print('✅ Serveur accessible (Browser UA): ${response2.statusCode}');
      
      // Comparer les en-têtes
      final headers1 = response1.headers;
      final headers2 = response2.headers;
      
      print('📋 En-têtes HTTP (Flutter UA):');
      headers1.forEach((key, values) {
        print('   $key: ${values.join(', ')}');
      });
      
      print('📋 En-têtes HTTP (Browser UA):');
      headers2.forEach((key, values) {
        print('   $key: ${values.join(', ')}');
      });
      
      // Vérifier les différences critiques
      final contentType1 = headers1['content-type']?.first;
      final contentType2 = headers2['content-type']?.first;
      
      if (contentType1 != contentType2) {
        print('⚠️ Content-Type différent selon User-Agent:');
        print('   Flutter: $contentType1');
        print('   Browser: $contentType2');
      }
      
      // Vérifier Accept-Ranges
      final acceptRanges1 = headers1['accept-ranges']?.first;
      final acceptRanges2 = headers2['accept-ranges']?.first;
      
      if (acceptRanges1 != acceptRanges2) {
        print('⚠️ Accept-Ranges différent selon User-Agent:');
        print('   Flutter: $acceptRanges1');
        print('   Browser: $acceptRanges2');
      }
      
    } catch (e) {
      print('❌ Erreur de connectivité: $e');
    }
    
    // Test de compatibilité audio
    print('🎵 Test de compatibilité audio...');
    final isCompatible = await _testAudioCompatibility(file.fileUrl);
    print('${isCompatible ? '✅' : '❌'} Compatibilité: ${isCompatible ? 'OK' : 'ÉCHEC'}');
    
    // Test direct avec AudioPlayer pour voir l'erreur exacte
    if (!isCompatible) {
      print('🔍 Test détaillé avec AudioPlayer...');
      try {
        final testPlayer = AudioPlayer();
        await testPlayer.setSource(UrlSource(file.fileUrl));
        print('✅ AudioPlayer peut charger le fichier');
        await testPlayer.dispose();
      } catch (e) {
        print('❌ Erreur AudioPlayer détaillée: $e');
        
        // Analyser le type d'erreur
        if (e.toString().contains('CORS')) {
          print('🚫 Problème CORS détecté');
        }
        if (e.toString().contains('NetworkError')) {
          print('🌐 Problème réseau détecté');
        }
        if (e.toString().contains('Format error')) {
          print('🎵 Problème de format détecté');
        }
        if (e.toString().contains('NotSupportedError')) {
          print('❌ Format non supporté détecté');
        }
      }
    }
    
    print('🔬 === FIN DIAGNOSTIC ===');
  }

  /// Tente de jouer le fichier avec différentes méthodes
  Future<void> _playAudioWithFallback(MediaFile file) async {
    try {
      // Validation préalable
      if (file.fileUrl.isEmpty) {
        throw Exception('URL du fichier manquante');
      }

      if (!_isValidAudioUrl(file.fileUrl)) {
        throw Exception('URL du fichier audio invalide');
      }

      if (!file.isAudio) {
        throw Exception('Ce fichier n\'est pas un fichier audio');
      }

      if (!_isAudioFormatSupported(file.originalName)) {
        throw Exception('Format audio non supporté: ${file.originalName.split('.').last}');
      }

      if (!file.isCompleted) {
        throw Exception('Le fichier n\'est pas encore prêt (${file.statusText})');
      }

      // Diagnostic complet du fichier
      await _diagnoseAudioFile(file);
      
      // Test de compatibilité avant tentative de lecture
      print('🔍 Test de compatibilité pour ${file.originalName}...');
      final isCompatible = await _testAudioCompatibility(file.fileUrl);
      
      if (!isCompatible) {
        print('⚠️ Fichier non compatible détecté, utilisation de la méthode alternative');
        await _playAudioWithAlternativeMethod(file);
        return;
      }

      // Méthode 1: Lecture directe
      print('🎵 Tentative de lecture directe pour ${file.originalName}');
      await _playAudioFileDirect(file);
      
    } catch (e) {
      print('💥 Erreur dans la méthode principale: $e');
      
      if (e.toString().contains('Format error') || 
          e.toString().contains('MEDIA_ELEMENT_ERROR') ||
          e.toString().contains('NotSupportedError') ||
          e.toString().contains('WebAudioError')) {
        
        print('🔄 Activation du fallback pour ${file.originalName}');
        
        try {
          // Méthode 2: Essayer avec un lecteur différent
          await _playAudioWithAlternativeMethod(file);
        } catch (fallbackError) {
          print('💥 Fallback échoué: $fallbackError');
          // Afficher l'erreur finale à l'utilisateur
          _showFinalError(file, fallbackError);
        }
      } else {
        // Erreur non liée au format, afficher directement
        _showFinalError(file, e);
      }
    }
  }

  /// Méthode alternative de lecture audio
  Future<void> _playAudioWithAlternativeMethod(MediaFile file) async {
    try {
      // Arrêter le fichier actuellement en cours s'il y en a un
      if (_currentlyPlayingId != null && _currentlyPlayingId != file.id) {
        await _stopAudioFile(_currentlyPlayingId!);
      }

      // Créer un nouveau lecteur avec des paramètres différents
      final player = AudioPlayer();
      _audioPlayers[file.id] = player;

      // Configurer le lecteur avec des paramètres de compatibilité
      await player.setPlayerMode(PlayerMode.mediaPlayer);
      
      // Essayer de jouer avec un délai
      await Future.delayed(const Duration(milliseconds: 100));
      await player.play(UrlSource(file.fileUrl));
      
      setState(() {
        _playingStates[file.id] = true;
        _currentlyPlayingId = file.id;
      });

      // Écouter la fin de la lecture
      player.onPlayerComplete.listen((_) {
        if (mounted) {
          setState(() {
            _playingStates[file.id] = false;
            _currentlyPlayingId = null;
          });
        }
      });

      // Écouter les erreurs de lecture
      player.onPlayerStateChanged.listen((state) {
        if (state == PlayerState.stopped && _playingStates[file.id] == true) {
          if (mounted) {
            setState(() {
              _playingStates[file.id] = false;
              _currentlyPlayingId = null;
            });
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('La lecture s\'est arrêtée de manière inattendue'),
                backgroundColor: Colors.orange,
                duration: Duration(seconds: 2),
              ),
            );
          }
        }
      });

    } catch (e) {
      print('💥 Méthode alternative échouée: $e');
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Impossible de lire ce fichier audio. Format non compatible avec le navigateur.'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 4),
            action: SnackBarAction(
              label: 'Détails',
              textColor: Colors.white,
              onPressed: () {
                showDialog(
                  context: context,
                  builder: (context) => AlertDialog(
                    title: const Text('Fichier non compatible'),
                    content: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Fichier: ${file.originalName}'),
                        Text('URL: ${file.fileUrl}'),
                        Text('Type: ${file.fileType}'),
                        Text('Statut: ${file.statusText}'),
                        const SizedBox(height: 8),
                        const Text('Ce fichier audio n\'est pas compatible avec votre navigateur.'),
                        const Text('Essayez de télécharger le fichier pour le lire localement.'),
                      ],
                    ),
                    actions: [
                      TextButton(
                        onPressed: () => Navigator.of(context).pop(),
                        child: const Text('Fermer'),
                      ),
                      TextButton(
                        onPressed: () {
                          Navigator.of(context).pop();
                          _copyFileUrl(file.fileUrl);
                        },
                        child: const Text('Copier l\'URL'),
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

  /// Affiche l'erreur finale à l'utilisateur
  void _showFinalError(MediaFile file, dynamic error) {
    if (!mounted) return;
    
    String errorMessage;
    if (error.toString().contains('Format error') || 
        error.toString().contains('MEDIA_ELEMENT_ERROR') ||
        error.toString().contains('NotSupportedError') ||
        error.toString().contains('WebAudioError')) {
      errorMessage = 'Format audio non compatible avec le navigateur';
    } else if (error.toString().contains('NetworkError')) {
      errorMessage = 'Erreur de réseau - Impossible d\'accéder au fichier';
    } else if (error.toString().contains('URL du fichier manquante')) {
      errorMessage = 'URL du fichier manquante';
    } else if (error.toString().contains('URL du fichier audio invalide')) {
      errorMessage = 'URL du fichier audio invalide';
    } else if (error.toString().contains('Format audio non supporté')) {
      errorMessage = 'Format audio non supporté par le navigateur';
    } else if (error.toString().contains('n\'est pas un fichier audio')) {
      errorMessage = 'Ce fichier n\'est pas un fichier audio';
    } else if (error.toString().contains('pas encore prêt')) {
      errorMessage = 'Le fichier n\'est pas encore prêt pour la lecture';
    } else {
      errorMessage = 'Erreur lors de la lecture : $error';
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(errorMessage),
        backgroundColor: Colors.red,
        duration: const Duration(seconds: 4),
        action: SnackBarAction(
          label: 'Détails',
          textColor: Colors.white,
          onPressed: () {
            showDialog(
              context: context,
              builder: (context) => AlertDialog(
                title: const Text('Détails de l\'erreur'),
                content: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Fichier: ${file.originalName}'),
                    Text('URL: ${file.fileUrl}'),
                    Text('Type: ${file.fileType}'),
                    Text('Statut: ${file.statusText}'),
                    const SizedBox(height: 8),
                    Text('Erreur: $error'),
                    const SizedBox(height: 8),
                    const Text('Solutions possibles:'),
                    const Text('• Télécharger le fichier pour le lire localement'),
                    const Text('• Utiliser un lecteur audio externe'),
                    const Text('• Convertir le fichier en format compatible'),
                    const Text('• Vérifier la connexion réseau'),
                  ],
                ),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.of(context).pop(),
                    child: const Text('Fermer'),
                  ),
                  TextButton(
                    onPressed: () {
                      Navigator.of(context).pop();
                      _downloadFile(file);
                    },
                    child: const Text('Télécharger'),
                  ),
                  TextButton(
                    onPressed: () {
                      Navigator.of(context).pop();
                      _copyFileUrl(file.fileUrl);
                    },
                    child: const Text('Copier l\'URL'),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  /// Télécharge le fichier audio
  void _downloadFile(MediaFile file) {
    print('📥 Téléchargement de ${file.originalName}');
    
    // Afficher un message avec l'URL de téléchargement
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Téléchargement de ${file.originalName}...'),
        duration: const Duration(seconds: 3),
        action: SnackBarAction(
          label: 'Ouvrir',
          onPressed: () {
            // Ouvrir l'URL de téléchargement dans le navigateur
            // Note: Nécessite le package url_launcher
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('URL de téléchargement: ${file.fileUrl}'),
                duration: const Duration(seconds: 5),
                action: SnackBarAction(
                  label: 'Copier',
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('URL copiée dans le presse-papier'),
                        duration: Duration(seconds: 2),
                      ),
                    );
                  },
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  /// Modifie un fichier audio
  Future<void> _editAudioFile(MediaFile file) async {
    await showDialog(
      context: context,
      builder: (context) => _EditFileDialog(
        file: file,
        onSave: (updatedFile) async {
          try {
            // Appeler l'API de modification
            final modifiedFile = await MediaService.instance.updateFile(
              fileId: file.id,
              originalName: updatedFile.originalName,
              fileType: file.fileType,
              metadata: updatedFile.metadata,
            );
            
            // Mettre à jour la liste des fichiers
            setState(() {
              final index = _audioFiles.indexWhere((f) => f.id == file.id);
              if (index != -1) {
                _audioFiles[index] = modifiedFile;
              }
            });
            
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('Fichier "${modifiedFile.originalName}" modifié avec succès'),
                  backgroundColor: Colors.green,
                  duration: const Duration(seconds: 3),
                ),
              );
            }
          } catch (e) {
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('Erreur lors de la modification : $e'),
                  backgroundColor: Colors.red,
                  duration: const Duration(seconds: 4),
                ),
              );
            }
          }
        },
      ),
    );
  }

  /// Modifie un fichier vidéo
  Future<void> _editVideoFile(MediaFile file) async {
    await showDialog(
      context: context,
      builder: (context) => EditVideoDialog(
        file: file,
        onSave: (updatedFile) async {
          try {
            // Appeler l'API de modification
            final modifiedFile = await MediaService.instance.updateFile(
              fileId: file.id,
              originalName: updatedFile.originalName,
              fileType: file.fileType,
              metadata: updatedFile.metadata,
            );
            
            // Mettre à jour la liste des fichiers
            setState(() {
              final index = _videoFiles.indexWhere((f) => f.id == file.id);
              if (index != -1) {
                _videoFiles[index] = modifiedFile;
              }
            });
            
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('Fichier "${modifiedFile.originalName}" modifié avec succès'),
                  backgroundColor: Colors.green,
                  duration: const Duration(seconds: 3),
                ),
              );
            }
          } catch (e) {
            print('💥 Erreur modification vidéo: $e');
            
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('Erreur lors de la modification: $e'),
                  backgroundColor: Colors.red,
                  duration: const Duration(seconds: 3),
                ),
              );
            }
          }
        },
      ),
    );
  }

  /// Copie l'URL du fichier dans le presse-papier
  void _copyFileUrl(String url) {
    print('📋 Copie de l\'URL: $url');
    
    // Afficher l'URL dans un snackbar avec option de copie
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('URL du fichier: $url'),
        duration: const Duration(seconds: 8),
        action: SnackBarAction(
          label: 'Copier',
          onPressed: () {
            // Copier l'URL dans le presse-papier
            // Note: Nécessite le package flutter/services
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('URL copiée dans le presse-papier'),
                duration: Duration(seconds: 2),
              ),
            );
          },
        ),
      ),
    );
  }

  List<_Item> _getFilteredItems() {
    if (_searchQuery.isEmpty) {
      return _demoItems;
    }
    return _demoItems.where((item) {
      return item.title.toLowerCase().contains(_searchQuery) ||
             item.type.toLowerCase().contains(_searchQuery);
    }).toList();
  }

  /// Filtre les fichiers audio côté frontend
  List<MediaFile> _getFilteredAudioFiles() {
    if (_searchQuery.isEmpty) {
      return _audioFiles;
    }
    return _audioFiles.where((file) {
      return file.originalName.toLowerCase().contains(_searchQuery) ||
             file.fileType.toLowerCase().contains(_searchQuery);
    }).toList();
  }

  /// Filtre les fichiers vidéo côté frontend
  List<MediaFile> _getFilteredVideoFiles() {
    if (_searchQuery.isEmpty) {
      return _videoFiles;
    }
    return _videoFiles.where((file) {
      return file.originalName.toLowerCase().contains(_searchQuery) ||
             file.fileType.toLowerCase().contains(_searchQuery);
    }).toList();
  }

  void _toggleItemSelection(int index) {
    setState(() {
      if (_selectedItems.contains(index)) {
        _selectedItems.remove(index);
      } else {
        _selectedItems.add(index);
      }
      if (_selectedItems.isEmpty) {
        _isSelectionMode = false;
      }
    });
  }

  void _selectAll() {
    setState(() {
      final currentItems = _currentTabIndex == 0 ? _getFilteredVideoFiles() : _getFilteredAudioFiles();
      if (_selectedItems.length == currentItems.length) {
        _selectedItems.clear();
        _isSelectionMode = false;
      } else {
        _selectedItems.addAll(List.generate(currentItems.length, (i) => i));
        _isSelectionMode = true;
      }
    });
  }

  /// Affiche la boîte de dialogue de confirmation de suppression
  Future<void> _showDeleteConfirmation() async {
    final selectedCount = _selectedItems.length;
    
    final result = await ConfirmationDialog.show(
      context,
      title: 'Voulez-vous vraiment supprimer ?',
      description: selectedCount == 1
          ? 'Cette action est irréversible. L\'élément sélectionné sera définitivement perdu.'
          : 'Cette action est irréversible. Les $selectedCount éléments sélectionnés seront définitivement perdus.',
      confirmText: 'Oui',
      cancelText: 'Non',
    );

    if (result == true) {
      try {
        // Déterminer les fichiers à supprimer selon l'onglet actuel
        final currentFiles = _currentTabIndex == 0 
            ? _getFilteredVideoFiles() 
            : _getFilteredAudioFiles();
        
        // Récupérer les IDs des fichiers sélectionnés
        final selectedFileIds = <int>[];
        for (final index in _selectedItems) {
          if (index < currentFiles.length) {
            selectedFileIds.add(currentFiles[index].id);
          }
        }

        if (selectedFileIds.isEmpty) {
          throw Exception('Aucun fichier valide sélectionné');
        }

        // Appeler l'API de suppression pour chaque fichier
        int successCount = 0;
        int errorCount = 0;
        
        for (final fileId in selectedFileIds) {
          try {
            await MediaService.instance.deleteFile(fileId);
            successCount++;
          } catch (e) {
            print('💥 Erreur suppression fichier $fileId: $e');
            errorCount++;
          }
        }
        
        // Mettre à jour les listes locales
        setState(() {
          if (_currentTabIndex == 0) {
            // Supprimer les vidéos sélectionnées
            _videoFiles.removeWhere((file) => selectedFileIds.contains(file.id));
          } else {
            // Supprimer les audios sélectionnés
            _audioFiles.removeWhere((file) => selectedFileIds.contains(file.id));
          }
          
          // Réinitialiser la sélection
          _selectedItems.clear();
          _isSelectionMode = false;
        });
        
        // Afficher le message de succès
        if (mounted) {
          String message;
          if (errorCount == 0) {
            message = successCount == 1
                ? 'Élément supprimé avec succès'
                : '$successCount éléments supprimés avec succès';
          } else if (successCount == 0) {
            message = 'Erreur lors de la suppression de tous les éléments';
          } else {
            message = '$successCount éléments supprimés, $errorCount erreurs';
          }
          
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(message),
              backgroundColor: errorCount == 0 ? Colors.green : Colors.orange,
              duration: const Duration(seconds: 3),
            ),
          );
        }
        
      } catch (e) {
        print('💥 Erreur suppression multiple: $e');
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Erreur lors de la suppression: $e'),
              backgroundColor: Colors.red,
              duration: const Duration(seconds: 3),
            ),
          );
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        final isTablet = constraints.maxWidth >= 768 && constraints.maxWidth < 1024;
        
        return Padding(
          padding: EdgeInsets.symmetric(
            horizontal: isMobile ? 16 : 24,
            vertical: 16,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (!_isSelectionMode) ...[
                _TopBar(
                  isMobile: isMobile,
                  searchController: _searchController,
                  onSearchChanged: _onSearchChanged,
                ),
                const Divider(height: 32),
                const Text(
                  'Médiathèque',
                  style: TextStyle(
                    fontSize: 32,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF111827),
                  ),
                ),
                const Gap(6),
                const Text(
                  "Gérez l'ensemble de vos contenus vidéos et audios.",
                  style: TextStyle(color: Color(0xFF6B7280)),
                ),
                const Gap(20),
                _buildTabsAndActions(isMobile, isTablet),
                const Gap(20),
                _MediaTabs(tabController: _tabController),
              ] else ...[
                _SelectionHeader(
                  selectedCount: _selectedItems.length,
                  onClose: _toggleSelectionMode,
                  onSelectAll: _selectAll,
                  onDelete: _showDeleteConfirmation,
                ),
              ],
              const Gap(14),
              if (!_isSelectionMode)
                Row(
                  children: [
                    const Spacer(),
                    TextButton.icon(
                      onPressed: _toggleSelectionMode,
                      icon: const Icon(Icons.checklist, size: 16),
                      label: const Text('Sélectionner'),
                      style: TextButton.styleFrom(
                        foregroundColor: const Color(0xFF6B7280),
                      ),
                    ),
                  ],
                ),
              const Gap(8),
              Expanded(
                child: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        height: 600, // Hauteur fixe pour le contenu
                        child: TabBarView(
                          controller: _tabController,
                          children: [
                            // Onglet Vidéos (dynamique)
                            _VideoContentsCard(
                              files: _getFilteredVideoFiles(),
                              selectedItems: _selectedItems,
                              isSelectionMode: _isSelectionMode,
                              onItemToggle: _toggleItemSelection,
                              onDelete: _deleteVideoFile,
                              onPlay: _playVideoFile,
                              onEdit: _editVideoFile,
                              isMobile: isMobile,
                              isLoading: _isLoadingVideo,
                              errorMessage: _errorMessageVideo,
                              pagination: _videoPagination,
                              onNextPage: _nextVideoPage,
                              onPreviousPage: _previousVideoPage,
                              onRefresh: _loadVideoFiles,
                            ),
                            // Onglet Audios (dynamique)
                            _AudioContentsCard(
                              files: _getFilteredAudioFiles(),
                              selectedItems: _selectedItems,
                              isSelectionMode: _isSelectionMode,
                              onItemToggle: _toggleItemSelection,
                              onDelete: _deleteAudioFile,
                              onPlay: _playAudioWithFallback,
                              onEdit: _editAudioFile,
                              playingStates: _playingStates,
                              isMobile: isMobile,
                              isLoading: _isLoadingAudio,
                              errorMessage: _errorMessageAudio,
                              pagination: _audioPagination,
                              onNextPage: _nextAudioPage,
                              onPreviousPage: _previousAudioPage,
                              onRefresh: _loadAudioFiles,
                            ),
                          ],
                        ),
                      ),
                      const Gap(20),
                      _buildFooter(isMobile),
                    ],
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildTabsAndActions(bool isMobile, bool isTablet) {
    if (isMobile) {
      return Column(
        children: [
          Row(
            children: [
              MouseRegion(
                cursor: SystemMouseCursors.click,
                child: GestureDetector(
                  onTap: () {
                    _tabController.animateTo(0);
                  },
                  child: _TabPill(
                    icon: Icons.ondemand_video,
                    label: 'Vidéos',
                    selected: _currentTabIndex == 0,
                  ),
                ),
              ),
              const Gap(12),
              MouseRegion(
                cursor: SystemMouseCursors.click,
                child: GestureDetector(
                  onTap: () {
                    _tabController.animateTo(1);
                  },
                  child: _TabPill(
                    icon: Icons.audiotrack,
                    label: 'Audios',
                    selected: _currentTabIndex == 1,
                  ),
                ),
              ),
            ],
          ),
          const Gap(12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              ElevatedButton.icon(
                onPressed: () => context.go('/contents/playlist-builder'),
                icon: const Icon(Icons.playlist_add, size: 18),
                label: const Text('Playlists TV'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.blueColor,
                  foregroundColor: const Color(0xFF2F3B52),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                ),
              ),
              ElevatedButton.icon(
                onPressed: () => context.go('/contents/radio-playlist-builder'),
                icon: const Icon(Icons.radio, size: 18),
                label: const Text('Playlists Radio'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF8B5CF6),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                ),
              ),
              OutlinedButton.icon(
                onPressed: () => context.go('/contents/add'),
                icon: const Icon(Icons.add),
                label: const Text('Ajouter'),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                ),
              ),
            ],
          ),
        ],
      );
    }

    return Row(
      children: [
        MouseRegion(
          cursor: SystemMouseCursors.click,
          child: GestureDetector(
            onTap: () {
              _tabController.animateTo(0);
            },
            child: _TabPill(
              icon: Icons.ondemand_video,
              label: 'Vidéos',
              selected: _currentTabIndex == 0,
            ),
          ),
        ),
        const Gap(12),
        MouseRegion(
          cursor: SystemMouseCursors.click,
          child: GestureDetector(
            onTap: () {
              _tabController.animateTo(1);
            },
            child: _TabPill(
              icon: Icons.audiotrack,
              label: 'Audios',
              selected: _currentTabIndex == 1,
            ),
          ),
        ),
        const Spacer(),
        ElevatedButton.icon(
          onPressed: () => context.go('/contents/playlist-builder'),
          icon: const Icon(Icons.playlist_add, size: 18),
          label: const Text('Playlists TV'),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppTheme.blueColor,
            foregroundColor: const Color(0xFF2F3B52),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          ),
        ),
        const Gap(8),
        ElevatedButton.icon(
          onPressed: () => context.go('/contents/radio-playlist-builder'),
          icon: const Icon(Icons.radio, size: 18),
          label: const Text('Playlists Radio'),
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF8B5CF6),
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          ),
        ),
        const Gap(12),
        OutlinedButton.icon(
          onPressed: () => context.go('/contents/add'),
          icon: const Icon(Icons.add),
          label: const Text('Ajouter un contenu'),
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          ),
        ),
        // Commenté temporairement
        // const Gap(8),
        // OutlinedButton.icon(
        //   onPressed: () => context.go('/contents/metadata-edit'),
        //   icon: const Icon(Icons.edit),
        //   label: const Text('Éditer métadonnées'),
        //   style: OutlinedButton.styleFrom(
        //     padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        //   ),
        // ),
      ],
    );
  }

  Widget _buildFooter(bool isMobile) {
    if (isMobile) {
      return Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Médiathèque',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF111827),
                ),
              ),
            ],
          ),
          const Gap(12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () {},
              icon: const Icon(Icons.live_tv),
              label: const Text('Démarrer Diffusion Live'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.redColor,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
            ),
          ),
          const Gap(8),
          const UserProfileChip(),
        ],
      );
    }

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16),
      child: Row(
        children: [
          const Text(
            'Médiathèque',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: Color(0xFF111827),
            ),
          ),
          const Spacer(),
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

// ————————————————— Widgets —————————————————

class _TopBar extends StatelessWidget {
  const _TopBar({
    required this.isMobile,
    required this.searchController,
    required this.onSearchChanged,
  });
  final bool isMobile;
  final TextEditingController searchController;
  final Function(String) onSearchChanged;

  @override
  Widget build(BuildContext context) {
    if (isMobile) {
      return Column(
        children: [
          // Logo et titre
          Row(
            children: [
              const EmbLogo(size: 32),
              const Gap(8),
              const Text(
                'EMB-MISSION',
                style: TextStyle(
                  fontWeight: FontWeight.w900,
                  fontSize: 16,
                  color: Color(0xFF0F172A),
                ),
              ),
            ],
          ),
          const Gap(12),
          // Search
          Container(
            height: 40,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            decoration: BoxDecoration(
              color: const Color(0xFFF3F4F6),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(
              children: [
                const Icon(Icons.search, color: Color(0xFF9CA3AF)),
                const Gap(8),
                Expanded(
                  child: TextField(
                    controller: searchController,
                    onChanged: onSearchChanged,
                    decoration: const InputDecoration(
                      hintText: 'Rechercher un contenu…',
                      hintStyle: TextStyle(color: Color(0xFF9CA3AF)),
                      border: InputBorder.none,
                      contentPadding: EdgeInsets.zero,
                    ),
                    style: const TextStyle(color: Color(0xFF111827)),
                  ),
                ),
              ],
            ),
          ),
          const Gap(12),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: [
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
              const UserProfileChip(),
            ],
          ),
        ],
      );
    }

    return Row(
      children: [
        // Logo et titre
        const EmbLogo(size: 32),
        const Gap(8),
        const Text(
          'EMB-MISSION',
          style: TextStyle(
            fontWeight: FontWeight.w900,
            fontSize: 16,
            color: Color(0xFF0F172A),
          ),
        ),
        const Gap(16),
        // Search
        Expanded(
          child: Container(
            height: 40,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            decoration: BoxDecoration(
              color: const Color(0xFFF3F4F6),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(
              children: [
                const Icon(Icons.search, color: Color(0xFF9CA3AF)),
                const Gap(8),
                Expanded(
                  child: TextField(
                    controller: searchController,
                    onChanged: onSearchChanged,
                    decoration: const InputDecoration(
                      hintText: 'Rechercher un contenu…',
                      hintStyle: TextStyle(color: Color(0xFF9CA3AF)),
                      border: InputBorder.none,
                      contentPadding: EdgeInsets.zero,
                    ),
                    style: const TextStyle(color: Color(0xFF111827)),
                  ),
                ),
              ],
            ),
          ),
        ),
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
        const Gap(16),
        const UserProfileChip(),
      ],
    );
  }
}

class _SelectionHeader extends StatelessWidget {
  const _SelectionHeader({
    required this.selectedCount,
    required this.onClose,
    required this.onSelectAll,
    required this.onDelete,
  });

  final int selectedCount;
  final VoidCallback onClose;
  final VoidCallback onSelectAll;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFE0F2F7),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        children: [
          Text(
            '$selectedCount Éléments sélectionnés',
            style: const TextStyle(
              fontWeight: FontWeight.w600,
              color: Color(0xFF111827),
            ),
          ),
          const Spacer(),
          ElevatedButton.icon(
            onPressed: onDelete,
            icon: const Icon(Icons.delete_outline, size: 16),
            label: const Text('Supprimer'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.redColor,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            ),
          ),
          const Gap(8),
          IconButton(
            onPressed: onClose,
            icon: const Icon(Icons.close),
            tooltip: 'Fermer la sélection',
          ),
        ],
      ),
    );
  }
}

class _TabPill extends StatelessWidget {
  const _TabPill({
    required this.icon,
    required this.label,
    this.selected = false,
  });
  final IconData icon;
  final String label;
  final bool selected;
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: selected ? const Color(0xFF111827) : Colors.transparent,
        borderRadius: BorderRadius.circular(10),
        border: selected ? null : Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        children: [
          Icon(
            icon,
            size: 18,
            color: selected ? Colors.white : const Color(0xFF111827),
          ),
          const Gap(8),
          Text(
            label,
            style: TextStyle(
              fontWeight: FontWeight.w600,
              color: selected ? Colors.white : const Color(0xFF111827),
            ),
          ),
        ],
      ),
    );
  }
}

class _ContentsCard extends StatelessWidget {
  const _ContentsCard({
    required this.items,
    required this.selectedItems,
    required this.isSelectionMode,
    required this.onItemToggle,
    required this.isMobile,
    required this.onDelete,
  });

  final List<_Item> items;
  final Set<int> selectedItems;
  final bool isSelectionMode;
  final Function(int) onItemToggle;
  final bool isMobile;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        children: [
          // Header row
          if (!isMobile)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: const BoxDecoration(
                color: Color(0xFFF9FAFB),
                borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
              ),
              child: Row(
                children: [
                  if (isSelectionMode)
                    const SizedBox(width: 40, child: _HeaderLabel('')),
                  const Expanded(flex: 6, child: _HeaderLabel('Nom')),
                  const Expanded(flex: 2, child: _HeaderLabel('Type')),
                  const Expanded(flex: 2, child: _HeaderLabel('Durée')),
                  const Expanded(flex: 2, child: _HeaderLabel('Taille')),
                  const Expanded(flex: 2, child: _HeaderLabel('Date')),
                  const SizedBox(width: 120, child: _HeaderLabel('Actions')),
                ],
              ),
            ),

          for (int i = 0; i < items.length; i++)
            _ContentRow(
              item: items[i],
              index: i,
              isSelected: selectedItems.contains(i),
              isSelectionMode: isSelectionMode,
              onToggle: onItemToggle,
              isMobile: isMobile,
              onDelete: onDelete,
            ),

          // Footer
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: _CardFooter(),
          ),
        ],
      ),
    );
  }
}

class _HeaderLabel extends StatelessWidget {
  const _HeaderLabel(this.text);
  final String text;
  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(
        color: Color(0xFF6B7280),
        fontWeight: FontWeight.w600,
      ),
    );
  }
}

class _ContentRow extends StatelessWidget {
  const _ContentRow({
    required this.item,
    required this.index,
    required this.isSelected,
    required this.isSelectionMode,
    required this.onToggle,
    required this.isMobile,
    required this.onDelete,
  });

  final _Item item;
  final int index;
  final bool isSelected;
  final bool isSelectionMode;
  final Function(int) onToggle;
  final bool isMobile;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    if (isMobile) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: const BoxDecoration(
          border: Border(
            top: BorderSide(color: Color(0xFFF3F4F6)),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                if (isSelectionMode) ...[
                  Checkbox(
                    value: isSelected,
                    onChanged: (_) => onToggle(index),
                  ),
                  const Gap(8),
                ],
                Expanded(
                  child: Row(
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: Image.network(
                          item.thumbnail,
                          width: 60,
                          height: 40,
                          fit: BoxFit.cover,
                        ),
                      ),
                      const Gap(12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.title,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF111827),
                                fontSize: 14,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                            Text(
                              item.subtitle,
                              style: const TextStyle(
                                color: Color(0xFF9CA3AF),
                                fontSize: 12,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                if (!isSelectionMode)
                  Row(
                    children: [
                      IconButton(
                        onPressed: () {},
                        icon: const Icon(Icons.play_arrow_rounded, size: 20),
                        color: const Color(0xFF6B7280),
                      ),
                      IconButton(
                        onPressed: onDelete,
                        icon: const Icon(Icons.delete_outline, size: 18),
                        color: const Color(0xFFEF4444),
                      ),
                    ],
                  ),
              ],
            ),
            const Gap(8),
            Row(
              children: [
                _buildMetadata(Icons.access_time, item.duration),
                const Gap(16),
                _buildMetadata(Icons.storage, item.size),
                const Gap(16),
                _buildMetadata(Icons.calendar_today, item.date),
              ],
            ),
          ],
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: const BoxDecoration(
        border: Border(
          top: BorderSide(color: Color(0xFFF3F4F6)),
        ),
      ),
      child: Row(
        children: [
          // Checkbox
          if (isSelectionMode) ...[
            Checkbox(
              value: isSelected,
              onChanged: (_) => onToggle(index),
            ),
            const Gap(8),
          ],
          // Title cell
          Expanded(
            flex: 6,
            child: Row(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.network(
                    item.thumbnail,
                    width: 96,
                    height: 56,
                    fit: BoxFit.cover,
                  ),
                ),
                const Gap(12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.title,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF111827),
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                      Text(
                        item.subtitle,
                        style: const TextStyle(color: Color(0xFF9CA3AF)),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          // Type
          Expanded(
            flex: 2,
            child: Text(
              item.type,
              style: const TextStyle(color: Color(0xFF111827)),
            ),
          ),
          // Duration
          Expanded(
            flex: 2,
            child: Row(
              children: [
                const Icon(Icons.access_time, size: 16, color: Color(0xFF6B7280)),
                const Gap(4),
                Text(
                  item.duration,
                  style: const TextStyle(color: Color(0xFF111827)),
                ),
              ],
            ),
          ),
          // Size
          Expanded(
            flex: 2,
            child: Row(
              children: [
                const Icon(Icons.storage, size: 16, color: Color(0xFF6B7280)),
                const Gap(4),
                Text(
                  item.size,
                  style: const TextStyle(color: Color(0xFF111827)),
                ),
              ],
            ),
          ),
          // Date
          Expanded(
            flex: 2,
            child: Row(
              children: [
                const Icon(Icons.calendar_today, size: 16, color: Color(0xFF6B7280)),
                const Gap(4),
                Text(
                  item.date,
                  style: const TextStyle(color: Color(0xFF111827)),
                ),
              ],
            ),
          ),
          // Actions
          const SizedBox(
            width: 120,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                Icon(Icons.edit_outlined, size: 18, color: Color(0xFF6B7280)),
                Gap(10),
                Icon(
                  Icons.play_arrow_rounded,
                  size: 20,
                  color: Color(0xFF6B7280),
                ),
                Gap(10),
                Icon(Icons.delete_outline, size: 18, color: Color(0xFFEF4444)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMetadata(IconData icon, String text) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: const Color(0xFF6B7280)),
        const Gap(4),
        Text(
          text,
          style: const TextStyle(
            color: Color(0xFF111827),
            fontSize: 12,
          ),
        ),
      ],
    );
  }
}

class _CardFooter extends StatelessWidget {
  const _CardFooter();
  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Expanded(
          child: Text(
            'Affiche 1-3 sur 15',
            style: TextStyle(color: Color(0xFF6B7280)),
          ),
        ),
        OutlinedButton(
          onPressed: () {},
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          ),
          child: const Text('Précédent'),
        ),
        const Gap(10),
        OutlinedButton(
          onPressed: () {},
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          ),
          child: const Text('Suivant'),
        ),
      ],
    );
  }
}

// ————————————————— Audio Contents Card (Dynamique) —————————————————

class _VideoContentsCard extends StatelessWidget {
  const _VideoContentsCard({
    required this.files,
    required this.selectedItems,
    required this.isSelectionMode,
    required this.onItemToggle,
    required this.onDelete,
    required this.onPlay,
    required this.onEdit,
    required this.isMobile,
    required this.isLoading,
    required this.errorMessage,
    required this.pagination,
    required this.onNextPage,
    required this.onPreviousPage,
    required this.onRefresh,
  });

  final List<MediaFile> files;
  final Set<int> selectedItems;
  final bool isSelectionMode;
  final Function(int) onItemToggle;
  final Function(MediaFile) onDelete;
  final Function(MediaFile) onPlay;
  final Function(MediaFile) onEdit;
  final bool isMobile;
  final bool isLoading;
  final String? errorMessage;
  final PaginationInfo? pagination;
  final VoidCallback onNextPage;
  final VoidCallback onPreviousPage;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        children: [
          // Header row
          if (!isMobile)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: const BoxDecoration(
                color: Color(0xFFF9FAFB),
                borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
              ),
              child: Row(
                children: [
                  if (isSelectionMode)
                    const SizedBox(width: 40, child: _HeaderLabel('')),
                  const Expanded(flex: 6, child: _HeaderLabel('Nom')),
                  const Expanded(flex: 2, child: _HeaderLabel('Type')),
                  const Expanded(flex: 2, child: _HeaderLabel('Durée')),
                  const Expanded(flex: 2, child: _HeaderLabel('Taille')),
                  const Expanded(flex: 2, child: _HeaderLabel('Date')),
                  const SizedBox(width: 120, child: _HeaderLabel('Actions')),
                ],
              ),
            ),

          // États de chargement/erreur
          if (isLoading)
            const Expanded(
              child: Center(
                child: CircularProgressIndicator(),
              ),
            )
          else if (errorMessage != null)
            Expanded(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(
                      Icons.error_outline,
                      size: 48,
                      color: Color(0xFFEF4444),
                    ),
                    const Gap(16),
                    Text(
                      'Erreur de chargement',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF111827),
                      ),
                    ),
                    const Gap(8),
                    Text(
                      errorMessage!,
                      style: const TextStyle(
                        color: Color(0xFF6B7280),
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const Gap(16),
                    ElevatedButton.icon(
                      onPressed: onRefresh,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Réessayer'),
                    ),
                  ],
                ),
              ),
            )
          else if (files.isEmpty)
            Expanded(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(
                      Icons.video_library_outlined,
                      size: 48,
                      color: Color(0xFF9CA3AF),
                    ),
                    const Gap(16),
                    const Text(
                      'Aucune vidéo trouvée',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF111827),
                      ),
                    ),
                    const Gap(8),
                    const Text(
                      'Ajoutez des fichiers vidéo pour les voir apparaître ici',
                      style: TextStyle(
                        color: Color(0xFF6B7280),
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            )
          else
            Expanded(
              child: Column(
                children: [
                  // Liste des fichiers
                  Expanded(
                    child: ListView.builder(
                      itemCount: files.length,
                      itemBuilder: (context, index) {
                        final file = files[index];
                        final isSelected = selectedItems.contains(index);
                        
                        return _VideoContentRow(
                          file: file,
                          index: index,
                          isSelected: isSelected,
                          isSelectionMode: isSelectionMode,
                          onToggle: onItemToggle,
                          onDelete: onDelete,
                          onPlay: onPlay,
                          onEdit: onEdit,
                          isMobile: isMobile,
                        );
                      },
                    ),
                  ),
                ],
              ),
            ),

          // Footer avec pagination
          if (!isLoading && errorMessage == null && pagination != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              child: _VideoCardFooter(
                pagination: pagination!,
                onNextPage: onNextPage,
                onPreviousPage: onPreviousPage,
              ),
            ),
        ],
      ),
    );
  }
}

class _AudioContentsCard extends StatelessWidget {
  const _AudioContentsCard({
    required this.files,
    required this.selectedItems,
    required this.isSelectionMode,
    required this.onItemToggle,
    required this.onDelete,
    required this.onPlay,
    required this.onEdit,
    required this.playingStates,
    required this.isMobile,
    required this.isLoading,
    required this.errorMessage,
    required this.pagination,
    required this.onNextPage,
    required this.onPreviousPage,
    required this.onRefresh,
  });

  final List<MediaFile> files;
  final Set<int> selectedItems;
  final bool isSelectionMode;
  final Function(int) onItemToggle;
  final Function(MediaFile) onDelete;
  final Function(MediaFile) onPlay;
  final Function(MediaFile) onEdit;
  final Map<int, bool> playingStates;
  final bool isMobile;
  final bool isLoading;
  final String? errorMessage;
  final PaginationInfo? pagination;
  final VoidCallback onNextPage;
  final VoidCallback onPreviousPage;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        children: [
          // Header row
          if (!isMobile)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: const BoxDecoration(
                color: Color(0xFFF9FAFB),
                borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
              ),
              child: Row(
                children: [
                  if (isSelectionMode)
                    const SizedBox(width: 40, child: _HeaderLabel('')),
                  const Expanded(flex: 6, child: _HeaderLabel('Nom')),
                  const Expanded(flex: 2, child: _HeaderLabel('Type')),
                  const Expanded(flex: 2, child: _HeaderLabel('Durée')),
                  const Expanded(flex: 2, child: _HeaderLabel('Taille')),
                  const Expanded(flex: 2, child: _HeaderLabel('Date')),
                  const SizedBox(width: 120, child: _HeaderLabel('Actions')),
                ],
              ),
            ),

          // États de chargement/erreur
          if (isLoading)
            const Expanded(
              child: Center(
                child: CircularProgressIndicator(),
              ),
            )
          else if (errorMessage != null)
            Expanded(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(
                      Icons.error_outline,
                      size: 48,
                      color: Color(0xFFEF4444),
                    ),
                    const Gap(16),
                    Text(
                      'Erreur de chargement',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF111827),
                      ),
                    ),
                    const Gap(8),
                    Text(
                      errorMessage!,
                      style: const TextStyle(color: Color(0xFF6B7280)),
                      textAlign: TextAlign.center,
                    ),
                    const Gap(16),
                    ElevatedButton.icon(
                      onPressed: onRefresh,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Réessayer'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.blueColor,
                      ),
                    ),
                  ],
                ),
              ),
            )
          else if (files.isEmpty)
            const Expanded(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.audiotrack,
                      size: 48,
                      color: Color(0xFF6B7280),
                    ),
                    Gap(16),
                    Text(
                      'Aucun fichier audio',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF111827),
                      ),
                    ),
                    Gap(8),
                    Text(
                      'Ajoutez vos premiers fichiers audio',
                      style: TextStyle(color: Color(0xFF6B7280)),
                    ),
                  ],
                ),
              ),
            )
          else
            Expanded(
              child: ListView.builder(
                itemCount: files.length,
                itemBuilder: (context, i) {
                  return _AudioContentRow(
                    file: files[i],
                    index: i,
                    isSelected: selectedItems.contains(i),
                    isSelectionMode: isSelectionMode,
                    onToggle: onItemToggle,
                    onDelete: onDelete,
                    onPlay: onPlay,
                    onEdit: onEdit,
                    isPlaying: playingStates[files[i].id] ?? false,
                    isMobile: isMobile,
                  );
                },
              ),
            ),

          // Footer avec pagination
          if (!isLoading && errorMessage == null && pagination != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              child: _AudioCardFooter(
                pagination: pagination!,
                onNextPage: onNextPage,
                onPreviousPage: onPreviousPage,
              ),
            ),
        ],
      ),
    );
  }
}

/// Widget pour afficher une ligne de fichier audio
class _AudioContentRow extends StatelessWidget {
  const _AudioContentRow({
    required this.file,
    required this.index,
    required this.isSelected,
    required this.isSelectionMode,
    required this.onToggle,
    required this.onDelete,
    required this.onPlay,
    required this.onEdit,
    required this.isPlaying,
    required this.isMobile,
  });

  final MediaFile file;
  final int index;
  final bool isSelected;
  final bool isSelectionMode;
  final Function(int) onToggle;
  final Function(MediaFile) onDelete;
  final Function(MediaFile) onPlay;
  final Function(MediaFile) onEdit;
  final bool isPlaying;
  final bool isMobile;

  String _formatDate(DateTime date) {
    return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }

  /// Vérifie si le fichier a des problèmes potentiels de compatibilité
  bool _hasCompatibilityIssues() {
    if (file.metadata == null) return false;
    
    final metadata = file.metadata!;
    
    // Vérifier les métadonnées manquantes
    if (metadata['duration'] == null && metadata['bitrate'] == null) {
      return true;
    }
    
    // Vérifier le bitrate trop bas
    final bitrate = metadata['bitrate'];
    if (bitrate != null && (bitrate as num) < 64) {
      return true;
    }
    
    return false;
  }


  @override
  Widget build(BuildContext context) {
    if (isMobile) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: const BoxDecoration(
          border: Border(
            top: BorderSide(color: Color(0xFFF3F4F6)),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                if (isSelectionMode) ...[
                  Checkbox(
                    value: isSelected,
                    onChanged: (_) => onToggle(index),
                  ),
                  const Gap(8),
                ],
                Expanded(
                  child: Row(
                    children: [
                      // Icône générique pour audio
                      Container(
                        width: 60,
                        height: 40,
                        decoration: BoxDecoration(
                          color: AppTheme.blueColor.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Icon(
                          Icons.audiotrack,
                          color: AppTheme.blueColor,
                          size: 24,
                        ),
                      ),
                      const Gap(12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              file.originalName,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF111827),
                                fontSize: 14,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                            Text(
                              file.fileType,
                              style: const TextStyle(
                                color: Color(0xFF9CA3AF),
                                fontSize: 12,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                if (!isSelectionMode)
                  Row(
                    children: [
                      IconButton(
                        onPressed: () {},
                        icon: const Icon(Icons.play_arrow_rounded, size: 20),
                        color: const Color(0xFF6B7280),
                        tooltip: 'Lire',
                      ),
                      IconButton(
                        onPressed: () {},
                        icon: const Icon(Icons.delete_outline, size: 18),
                        color: const Color(0xFFEF4444),
                        tooltip: 'Supprimer',
                      ),
                    ],
                  ),
              ],
            ),
            const Gap(8),
            Row(
              children: [
                _buildMetadata(Icons.access_time, file.durationFormatted),
                const Gap(16),
                _buildMetadata(Icons.storage, file.fileSizeFormatted),
                const Gap(16),
                _buildMetadata(Icons.calendar_today, _formatDate(file.createdAt)),
              ],
            ),
          ],
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: const BoxDecoration(
        border: Border(
          top: BorderSide(color: Color(0xFFF3F4F6)),
        ),
      ),
      child: Row(
        children: [
          // Checkbox
          if (isSelectionMode) ...[
            Checkbox(
              value: isSelected,
              onChanged: (_) => onToggle(index),
            ),
            const Gap(8),
          ],
          // Title cell avec icône générique
          Expanded(
            flex: 4,
            child: Row(
              children: [
                // Icône générique pour audio
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: AppTheme.blueColor.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(
                    Icons.audiotrack,
                    color: AppTheme.blueColor,
                    size: 24,
                  ),
                ),
                const Gap(8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              file.originalName,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF111827),
                                fontSize: 14,
                              ),
                              overflow: TextOverflow.ellipsis,
                              maxLines: 1,
                            ),
                          ),
                          if (_hasCompatibilityIssues()) ...[
                            const Gap(4),
                            const Icon(
                              Icons.warning_amber_rounded,
                              size: 16,
                              color: Colors.orange,
                            ),
                          ],
                        ],
                      ),
                      Text(
                        file.fileType,
                        style: const TextStyle(
                          color: Color(0xFF9CA3AF),
                          fontSize: 12,
                        ),
                        overflow: TextOverflow.ellipsis,
                        maxLines: 1,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          // Type
          Expanded(
            flex: 1,
            child: Text(
              file.fileType,
              style: const TextStyle(
                color: Color(0xFF111827),
                fontSize: 14,
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ),
          // Duration
          Expanded(
            flex: 1,
            child: Row(
              children: [
                const Icon(Icons.access_time, size: 14, color: Color(0xFF6B7280)),
                const Gap(4),
                Expanded(
                  child: Text(
                    file.durationFormatted,
                    style: const TextStyle(
                      color: Color(0xFF111827),
                      fontSize: 14,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ),
          // Size
          Expanded(
            flex: 1,
            child: Row(
              children: [
                const Icon(Icons.storage, size: 14, color: Color(0xFF6B7280)),
                const Gap(4),
                Expanded(
                  child: Text(
                    file.fileSizeFormatted,
                    style: const TextStyle(
                      color: Color(0xFF111827),
                      fontSize: 14,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ),
          // Date
          Expanded(
            flex: 1,
            child: Row(
              children: [
                const Icon(Icons.calendar_today, size: 14, color: Color(0xFF6B7280)),
                const Gap(4),
                Expanded(
                  child: Text(
                    _formatDate(file.createdAt),
                    style: const TextStyle(
                      color: Color(0xFF111827),
                      fontSize: 14,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ),
          // Actions (non fonctionnelles pour l'instant)
          SizedBox(
            width: 100,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                IconButton(
                  onPressed: () => onEdit(file),
                  icon: const Icon(Icons.edit_outlined, size: 16),
                  color: const Color(0xFF6B7280),
                  tooltip: 'Éditer',
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(
                    minWidth: 32,
                    minHeight: 32,
                  ),
                ),
                IconButton(
                  onPressed: () => onPlay(file),
                  icon: Icon(
                    isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded,
                    size: 16,
                  ),
                  color: isPlaying ? const Color(0xFF059669) : const Color(0xFF6B7280),
                  tooltip: isPlaying ? 'Pause' : 'Lire',
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(
                    minWidth: 32,
                    minHeight: 32,
                  ),
                ),
                IconButton(
                  onPressed: () => onDelete(file),
                  icon: const Icon(Icons.delete_outline, size: 16),
                  color: const Color(0xFFEF4444),
                  tooltip: 'Supprimer',
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(
                    minWidth: 32,
                    minHeight: 32,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMetadata(IconData icon, String text) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: const Color(0xFF6B7280)),
        const Gap(4),
        Text(
          text,
          style: const TextStyle(
            color: Color(0xFF111827),
            fontSize: 12,
          ),
        ),
      ],
    );
  }
}

/// Footer pour la pagination des audios
class _AudioCardFooter extends StatelessWidget {
  const _AudioCardFooter({
    required this.pagination,
    required this.onNextPage,
    required this.onPreviousPage,
  });
  
  final PaginationInfo pagination;
  final VoidCallback onNextPage;
  final VoidCallback onPreviousPage;

  @override
  Widget build(BuildContext context) {
    final canGoPrevious = pagination.currentPage > 1;
    final canGoNext = pagination.currentPage < pagination.lastPage;
    
    return Row(
      children: [
        Expanded(
          child: Text(
            'Affiche ${pagination.calculatedFrom}-${pagination.calculatedTo} sur ${pagination.total}',
            style: const TextStyle(color: Color(0xFF6B7280)),
          ),
        ),
        OutlinedButton(
          onPressed: canGoPrevious ? onPreviousPage : null,
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          ),
          child: const Text('Précédent'),
        ),
        const Gap(10),
        OutlinedButton(
          onPressed: canGoNext ? onNextPage : null,
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          ),
          child: const Text('Suivant'),
        ),
      ],
    );
  }
}

/// Widget pour afficher une ligne de fichier vidéo
class _VideoContentRow extends StatelessWidget {
  const _VideoContentRow({
    required this.file,
    required this.index,
    required this.isSelected,
    required this.isSelectionMode,
    required this.onToggle,
    required this.onDelete,
    required this.onPlay,
    required this.onEdit,
    required this.isMobile,
  });

  final MediaFile file;
  final int index;
  final bool isSelected;
  final bool isSelectionMode;
  final Function(int) onToggle;
  final Function(MediaFile) onDelete;
  final Function(MediaFile) onPlay;
  final Function(MediaFile) onEdit;
  final bool isMobile;

  String _formatDate(DateTime date) {
    return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }

  /// Formate la durée en secondes en format HH:MM:SS
  String _formatDuration(int seconds) {
    final hours = seconds ~/ 3600;
    final minutes = (seconds % 3600) ~/ 60;
    final remainingSeconds = seconds % 60;
    
    if (hours > 0) {
      return '${hours.toString().padLeft(2, '0')}:${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
    } else {
      return '${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
    }
  }

  @override
  Widget build(BuildContext context) {
    if (isMobile) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: const BoxDecoration(
          border: Border(
            top: BorderSide(color: Color(0xFFF3F4F6)),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                if (isSelectionMode) ...[
                  Checkbox(
                    value: isSelected,
                    onChanged: (_) => onToggle(index),
                  ),
                  const Gap(8),
                ],
                Expanded(
                  child: Row(
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: file.thumbnailUrl != null
                            ? Image.network(
                                file.thumbnailUrl!,
                                width: 60,
                                height: 40,
                                fit: BoxFit.cover,
                                errorBuilder: (context, error, stackTrace) {
                                  return Container(
                                    width: 60,
                                    height: 40,
                                    color: const Color(0xFFF3F4F6),
                                    child: const Icon(
                                      Icons.video_library,
                                      color: Color(0xFF9CA3AF),
                                    ),
                                  );
                                },
                              )
                            : Container(
                                width: 60,
                                height: 40,
                                color: const Color(0xFFF3F4F6),
                                child: const Icon(
                                  Icons.video_library,
                                  color: Color(0xFF9CA3AF),
                                ),
                              ),
                      ),
                      const Gap(12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              file.originalName,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF111827),
                                fontSize: 14,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                            Text(
                              file.fileType,
                              style: const TextStyle(
                                color: Color(0xFF9CA3AF),
                                fontSize: 12,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                if (!isSelectionMode)
                  PopupMenuButton<String>(
                    onSelected: (value) {
                      switch (value) {
                        case 'play':
                          onPlay(file);
                          break;
                        case 'edit':
                          onEdit(file);
                          break;
                        case 'delete':
                          onDelete(file);
                          break;
                      }
                    },
                    itemBuilder: (context) => [
                      const PopupMenuItem(
                        value: 'play',
                        child: Row(
                          children: [
                            Icon(Icons.play_arrow, size: 20),
                            Gap(8),
                            Text('Lire'),
                          ],
                        ),
                      ),
                      const PopupMenuItem(
                        value: 'edit',
                        child: Row(
                          children: [
                            Icon(Icons.edit, size: 20),
                            Gap(8),
                            Text('Modifier'),
                          ],
                        ),
                      ),
                      const PopupMenuItem(
                        value: 'delete',
                        child: Row(
                          children: [
                            Icon(Icons.delete, size: 20, color: Colors.red),
                            Gap(8),
                            Text('Supprimer', style: TextStyle(color: Colors.red)),
                          ],
                        ),
                      ),
                    ],
                  ),
              ],
            ),
            const Gap(8),
            Row(
              children: [
                Text(
                  'Durée: ${file.duration != null ? _formatDuration(file.duration!) : 'N/A'}',
                  style: const TextStyle(color: Color(0xFF6B7280), fontSize: 12),
                ),
                const Gap(16),
                Text(
                  'Taille: ${file.fileSizeFormatted}',
                  style: const TextStyle(color: Color(0xFF6B7280), fontSize: 12),
                ),
                const Gap(16),
                Text(
                  'Date: ${_formatDate(file.createdAt)}',
                  style: const TextStyle(color: Color(0xFF6B7280), fontSize: 12),
                ),
              ],
            ),
          ],
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: const BoxDecoration(
        border: Border(
          top: BorderSide(color: Color(0xFFF3F4F6)),
        ),
      ),
      child: Row(
        children: [
          // Checkbox
          if (isSelectionMode) ...[
            Checkbox(
              value: isSelected,
              onChanged: (_) => onToggle(index),
            ),
            const Gap(8),
          ],
          // Title cell
          Expanded(
            flex: 6,
            child: Row(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: file.thumbnailUrl != null
                      ? Image.network(
                          file.thumbnailUrl!,
                          width: 96,
                          height: 56,
                          fit: BoxFit.cover,
                          errorBuilder: (context, error, stackTrace) {
                            return Container(
                              width: 96,
                              height: 56,
                              color: const Color(0xFFF3F4F6),
                              child: const Icon(
                                Icons.video_library,
                                color: Color(0xFF9CA3AF),
                              ),
                            );
                          },
                        )
                      : Container(
                          width: 96,
                          height: 56,
                          color: const Color(0xFFF3F4F6),
                          child: const Icon(
                            Icons.video_library,
                            color: Color(0xFF9CA3AF),
                          ),
                        ),
                ),
                const Gap(12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        file.originalName,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF111827),
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                      Text(
                        file.fileType,
                        style: const TextStyle(color: Color(0xFF9CA3AF)),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          // Type
          Expanded(
            flex: 2,
            child: Text(
              file.fileType,
              style: const TextStyle(color: Color(0xFF111827)),
            ),
          ),
          // Duration
          Expanded(
            flex: 2,
            child: Text(
              file.duration != null ? _formatDuration(file.duration!) : 'N/A',
              style: const TextStyle(color: Color(0xFF111827)),
            ),
          ),
          // Size
          Expanded(
            flex: 2,
            child: Text(
              file.fileSizeFormatted,
              style: const TextStyle(color: Color(0xFF111827)),
            ),
          ),
          // Date
          Expanded(
            flex: 2,
            child: Text(
              _formatDate(file.createdAt),
              style: const TextStyle(color: Color(0xFF111827)),
            ),
          ),
          // Actions
          SizedBox(
            width: 120,
            child: Row(
              children: [
                IconButton(
                  onPressed: () => onPlay(file),
                  icon: const Icon(Icons.play_arrow, size: 20),
                  tooltip: 'Lire',
                ),
                IconButton(
                  onPressed: () => onEdit(file),
                  icon: const Icon(Icons.edit, size: 20),
                  tooltip: 'Modifier',
                ),
                IconButton(
                  onPressed: () => onDelete(file),
                  icon: const Icon(Icons.delete_outline, size: 20),
                  color: const Color(0xFFEF4444),
                  tooltip: 'Supprimer',
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Footer avec pagination pour les vidéos
class _VideoCardFooter extends StatelessWidget {
  const _VideoCardFooter({
    required this.pagination,
    required this.onNextPage,
    required this.onPreviousPage,
  });
  
  final PaginationInfo pagination;
  final VoidCallback onNextPage;
  final VoidCallback onPreviousPage;

  @override
  Widget build(BuildContext context) {
    final canGoPrevious = pagination.currentPage > 1;
    final canGoNext = pagination.currentPage < pagination.lastPage;
    
    return Row(
      children: [
        Expanded(
          child: Text(
            'Affiche ${pagination.calculatedFrom}-${pagination.calculatedTo} sur ${pagination.total}',
            style: const TextStyle(color: Color(0xFF6B7280)),
          ),
        ),
        OutlinedButton(
          onPressed: canGoPrevious ? onPreviousPage : null,
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          ),
          child: const Text('Précédent'),
        ),
        const Gap(10),
        OutlinedButton(
          onPressed: canGoNext ? onNextPage : null,
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          ),
          child: const Text('Suivant'),
        ),
      ],
    );
  }
}

// ————————————————— Demo data —————————————————

class _Item {
  const _Item({
    required this.thumbnail,
    required this.title,
    required this.subtitle,
    required this.type,
    required this.duration,
    required this.size,
    required this.date,
  });
  final String thumbnail;
  final String title;
  final String subtitle;
  final String type;
  final String duration;
  final String size;
  final String date;
}

const _demoItems = <_Item>[
  _Item(
    thumbnail:
        'https://images.unsplash.com/photo-1590602847861-f357a9332bbc?w=300&h=200&fit=crop&auto=format&q=60',
    title: 'Introduction au Podcast',
    subtitle: 'audio/mpeg',
    type: 'audio/mpeg',
    duration: '00:05:32',
    size: '12.5 MB',
    date: '2025-09-01',
  ),
  _Item(
    thumbnail:
        'https://images.unsplash.com/photo-1516245834210-c4c142787335?w=300&h=200&fit=crop&auto=format&q=60',
    title: 'Concert Live - Session 1',
    subtitle: 'video/mp4',
    type: 'video/mp4',
    duration: '01:22:10',
    size: '1.5 GB',
    date: '2025-08-28',
  ),
  _Item(
    thumbnail:
        'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?w=300&h=200&fit=crop&auto=format&q=60',
    title: 'Prédication du Dimanche',
    subtitle: 'video/mp4',
    type: 'video/mp4',
    duration: '00:45:15',
    size: '750 MB',
    date: '2025-08-25',
  ),
  _Item(
    thumbnail:
        'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=300&h=200&fit=crop&auto=format&q=60',
    title: 'Jingle EMB-RTV',
    subtitle: 'video/mp4',
    type: 'video/mp4',
    duration: '00:00:15',
    size: '5.2 MB',
    date: '2025-08-15',
  ),
  _Item(
    thumbnail:
        'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=300&h=200&fit=crop&auto=format&q=60',
    title: 'Interview invité spécial',
    subtitle: 'video/mp4',
    type: 'video/mp4',
    duration: '00:18:40',
    size: '320 MB',
    date: '2025-08-12',
  ),
];

/// Onglets pour Vidéos et Audios
class _MediaTabs extends StatelessWidget {
  const _MediaTabs({required this.tabController});
  final TabController tabController;

  @override
  Widget build(BuildContext context) {
    return TabBar(
      controller: tabController,
      labelColor: const Color(0xFF111827),
      unselectedLabelColor: const Color(0xFF6B7280),
      indicatorColor: const Color(0xFF111827),
      labelStyle: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w600,
      ),
      tabs: const [
        Tab(
          icon: Icon(Icons.videocam),
          text: 'Vidéos',
        ),
        Tab(
          icon: Icon(Icons.audiotrack),
          text: 'Audios',
        ),
      ],
    );
  }
}

/// Dialogue de modification de fichier
class _EditFileDialog extends StatefulWidget {
  const _EditFileDialog({
    required this.file,
    required this.onSave,
  });

  final MediaFile file;
  final Function(MediaFile) onSave;

  @override
  State<_EditFileDialog> createState() => _EditFileDialogState();
}

class _EditFileDialogState extends State<_EditFileDialog> {
  late TextEditingController _nameController;
  late TextEditingController _titleController;
  late TextEditingController _artistController;
  late TextEditingController _albumController;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.file.originalName);
    
    // Initialiser les métadonnées
    final metadata = widget.file.metadata ?? {};
    _titleController = TextEditingController(text: metadata['title']?.toString() ?? '');
    _artistController = TextEditingController(text: metadata['artist']?.toString() ?? '');
    _albumController = TextEditingController(text: metadata['album']?.toString() ?? '');
  }

  @override
  void dispose() {
    _nameController.dispose();
    _titleController.dispose();
    _artistController.dispose();
    _albumController.dispose();
    super.dispose();
  }

  void _save() async {
    if (_nameController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Le nom du fichier est requis'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    setState(() => _isLoading = true);

    try {
      // Créer les métadonnées mises à jour
      final updatedMetadata = <String, dynamic>{
        if (_titleController.text.trim().isNotEmpty) 'title': _titleController.text.trim(),
        if (_artistController.text.trim().isNotEmpty) 'artist': _artistController.text.trim(),
        if (_albumController.text.trim().isNotEmpty) 'album': _albumController.text.trim(),
      };

      // Créer le fichier modifié
      final updatedFile = MediaFile(
        id: widget.file.id,
        filename: widget.file.filename,
        originalName: _nameController.text.trim(),
        fileType: widget.file.fileType,
        fileSizeFormatted: widget.file.fileSizeFormatted,
        status: widget.file.status,
        statusText: widget.file.statusText,
        progress: widget.file.progress,
        errorMessage: widget.file.errorMessage,
        estimatedTimeRemaining: widget.file.estimatedTimeRemaining,
        bytesUploaded: widget.file.bytesUploaded,
        bytesTotal: widget.file.bytesTotal,
        fileUrl: widget.file.fileUrl,
        thumbnailUrl: widget.file.thumbnailUrl,
        hasThumbnail: widget.file.hasThumbnail,
        duration: widget.file.duration,
        width: widget.file.width,
        height: widget.file.height,
        bitrate: widget.file.bitrate,
        metadata: updatedMetadata.isNotEmpty ? updatedMetadata : null,
        createdAt: widget.file.createdAt,
        updatedAt: DateTime.now(),
      );

      // Appeler la fonction de sauvegarde
      await widget.onSave(updatedFile);
      
      if (mounted) {
        Navigator.of(context).pop();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur lors de la sauvegarde : $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final screenWidth = MediaQuery.of(context).size.width;
    final isMobile = screenWidth < 600;
    
    return AlertDialog(
      title: Row(
        children: [
          const Icon(Icons.edit_outlined, color: Color(0xFF059669)),
          const SizedBox(width: 8),
          const Text('Modifier le fichier'),
        ],
      ),
      contentPadding: const EdgeInsets.all(24),
      content: SizedBox(
        width: isMobile ? screenWidth * 0.9 : 500,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Informations du fichier
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFFF9FAFB),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFE5E7EB)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.audiotrack, size: 20, color: Color(0xFF6B7280)),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            widget.file.originalName,
                            style: const TextStyle(
                              fontWeight: FontWeight.w600,
                              fontSize: 16,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        const Icon(Icons.storage, size: 16, color: Color(0xFF6B7280)),
                        const SizedBox(width: 4),
                        Text(
                          widget.file.fileSizeFormatted ?? 'Taille inconnue',
                          style: const TextStyle(
                            color: Color(0xFF6B7280),
                            fontSize: 14,
                          ),
                        ),
                        const SizedBox(width: 16),
                        const Icon(Icons.schedule, size: 16, color: Color(0xFF6B7280)),
                        const SizedBox(width: 4),
                        Text(
                          widget.file.duration?.toString() ?? 'Durée inconnue',
                          style: const TextStyle(
                            color: Color(0xFF6B7280),
                            fontSize: 14,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              
              // Nom du fichier
              const Text(
                'Nom du fichier',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF111827),
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _nameController,
                decoration: InputDecoration(
                  labelText: 'Nom du fichier',
                  hintText: 'ex: ma_chanson.mp3',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                    borderSide: const BorderSide(color: Color(0xFF059669), width: 2),
                  ),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                ),
                style: const TextStyle(fontSize: 16),
              ),
              const SizedBox(height: 24),
              
              // Métadonnées
              const Text(
                'Métadonnées audio',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF111827),
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Ces informations apparaîtront dans les lecteurs audio',
                style: TextStyle(
                  fontSize: 14,
                  color: Color(0xFF6B7280),
                ),
              ),
              const SizedBox(height: 16),
              
              TextField(
                controller: _titleController,
                decoration: InputDecoration(
                  labelText: 'Titre',
                  hintText: 'Titre de la chanson',
                  prefixIcon: const Icon(Icons.music_note, color: Color(0xFF6B7280)),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                    borderSide: const BorderSide(color: Color(0xFF059669), width: 2),
                  ),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                ),
                style: const TextStyle(fontSize: 16),
              ),
              const SizedBox(height: 16),
              
              TextField(
                controller: _artistController,
                decoration: InputDecoration(
                  labelText: 'Artiste',
                  hintText: 'Nom de l\'artiste',
                  prefixIcon: const Icon(Icons.person, color: Color(0xFF6B7280)),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                    borderSide: const BorderSide(color: Color(0xFF059669), width: 2),
                  ),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                ),
                style: const TextStyle(fontSize: 16),
              ),
              const SizedBox(height: 16),
              
              TextField(
                controller: _albumController,
                decoration: InputDecoration(
                  labelText: 'Album',
                  hintText: 'Nom de l\'album',
                  prefixIcon: const Icon(Icons.album, color: Color(0xFF6B7280)),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                    borderSide: const BorderSide(color: Color(0xFF059669), width: 2),
                  ),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                ),
                style: const TextStyle(fontSize: 16),
              ),
            ],
          ),
        ),
      ),
      actionsPadding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
      actions: [
        TextButton(
          onPressed: _isLoading ? null : () => Navigator.of(context).pop(),
          style: TextButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          ),
          child: const Text('Annuler'),
        ),
        ElevatedButton(
          onPressed: _isLoading ? null : _save,
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF059669),
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
          child: _isLoading
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                  ),
                )
              : const Text('Sauvegarder'),
        ),
      ],
    );
  }
}
