import 'package:emb_mission_dashboard/core/models/media_models.dart';
import 'package:emb_mission_dashboard/core/models/webtv_models.dart';
import 'package:emb_mission_dashboard/core/services/media_service.dart';
import 'package:emb_mission_dashboard/core/services/webtv_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

/// Constructeur de Playlists TV
/// 
/// Page permettant de créer et organiser des playlists vidéo par
/// glisser-déposer. Interface en deux colonnes : bibliothèque vidéo à
/// gauche, playlist en construction à droite.
class PlaylistBuilderPage extends StatelessWidget {
  const PlaylistBuilderPage({super.key});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        final isTablet = constraints.maxWidth >= 768 && 
            constraints.maxWidth < 1024;
        
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Top bar avec titre et actions
              _TopBar(),
              const Divider(height: 32),
              
              // Titre principal
              const Text(
                'Constructeur de Playlists TV',
                style: TextStyle(
                  fontSize: 32,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF111827),
                ),
              ),
              const Gap(6),
              const Text(
                'Organisez vos vidéos par glisser-déposer.',
                style: TextStyle(color: Color(0xFF6B7280)),
              ),
              const Gap(20),
              
              // Contenu principal responsive
              Expanded(
                child: isMobile 
                  ? _MobileLayout()
                  : isTablet 
                    ? _TabletLayout()
                    : _DesktopLayout(),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Layout mobile : colonnes empilées verticalement
class _MobileLayout extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
        return SingleChildScrollView(
      child: Column(
            children: const [
          VideoLibrarySection(),
          Gap(20),
          PlaylistBuilderSection(),
        ],
      ),
    );
  }
}

/// Layout tablette : colonnes côte à côte mais plus étroites
class _TabletLayout extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
        return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
          children: const [
        Expanded(flex: 1, child: VideoLibrarySection()),
        Gap(16),
        Expanded(flex: 1, child: PlaylistBuilderSection()),
      ],
    );
  }
}

/// Layout desktop : colonnes côte à côte avec proportions optimales
class _DesktopLayout extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
        return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
          children: const [
        Expanded(flex: 2, child: VideoLibrarySection()),
        Gap(24),
        Expanded(flex: 3, child: PlaylistBuilderSection()),
      ],
    );
  }
}

/// Barre supérieure avec boutons d'action
class _TopBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        // Bouton retour
        OutlinedButton.icon(
          onPressed: () => context.go('/contents'),
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 16),
          label: const Text('Retour'),
        ),
        const Spacer(),
        
        // Boutons d'action
        const StartLiveButton(),
        const Gap(10),
        const UserProfileChip(),
      ],
    );
  }
}

/// Section bibliothèque vidéo (colonne gauche)
class VideoLibrarySection extends StatefulWidget {
  const VideoLibrarySection({super.key});

  @override
  State<VideoLibrarySection> createState() => _VideoLibrarySectionState();
}

class _VideoLibrarySectionState extends State<VideoLibrarySection> {
  List<MediaFile> _videoFiles = [];
  List<MediaFile> _filteredVideoFiles = [];
  bool _isLoading = true;
  String? _errorMessage;
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadVideoFiles();
    _searchController.addListener(_filterVideoFiles);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  /// Charge les fichiers vidéo depuis l'API
  Future<void> _loadVideoFiles() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final response = await MediaService.instance.getMediaFiles(
        perPage: 100,
        fileType: 'video',
      );

      setState(() {
        _videoFiles = response.data;
        _filteredVideoFiles = response.data;
        _isLoading = false;
      });
    } on Exception catch (e) {
      setState(() {
        _errorMessage = 'Erreur lors du chargement des fichiers vidéo';
        _isLoading = false;
      });
      debugPrint('❌ Erreur de chargement des vidéos: $e');
    }
  }

  /// Filtre les fichiers vidéo selon la recherche
  void _filterVideoFiles() {
    final query = _searchController.text.toLowerCase();
    
    setState(() {
      if (query.isEmpty) {
        _filteredVideoFiles = _videoFiles;
      } else {
        _filteredVideoFiles = _videoFiles.where((file) {
          final name = file.originalName.toLowerCase();
          final title = file.metadata?['title']?.toString().toLowerCase() ?? 
              '';
          final description = file.metadata?['description']?.toString()
              .toLowerCase() ?? '';
          
          return name.contains(query) || 
                 title.contains(query) || 
                 description.contains(query);
        }).toList();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // En-tête
          Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              color: Color(0xFFF9FAFB),
              borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
            ),
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
                Text(
                  '${_filteredVideoFiles.length} vidéo'
                  '${_filteredVideoFiles.length > 1 ? 's' : ''}',
                  style: const TextStyle(
                    fontSize: 14,
                    color: Color(0xFF6B7280),
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const Gap(8),
                IconButton(
                  onPressed: _loadVideoFiles,
                  icon: const Icon(Icons.refresh, size: 20),
                  tooltip: 'Actualiser',
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(
                    minWidth: 32,
                    minHeight: 32,
                  ),
                ),
              ],
            ),
          ),
          
          // Barre de recherche
          Padding(
            padding: const EdgeInsets.all(16),
            child: Container(
              height: 40,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              decoration: BoxDecoration(
                color: const Color(0xFFF3F4F6),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                children: [
                  const Icon(Icons.search, color: Color(0xFF9CA3AF), size: 20),
                  const Gap(8),
                  Expanded(
                    child: TextField(
                      controller: _searchController,
                      decoration: const InputDecoration(
                        hintText: 'Rechercher une vidéo...',
                        hintStyle: TextStyle(color: Color(0xFF9CA3AF)),
                        border: InputBorder.none,
                        isDense: true,
                        contentPadding: EdgeInsets.zero,
                      ),
                      style: const TextStyle(fontSize: 14),
                    ),
                  ),
                  if (_searchController.text.isNotEmpty)
                    IconButton(
                      onPressed: _searchController.clear,
                      icon: const Icon(Icons.clear, size: 16),
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(
                        minWidth: 24,
                        minHeight: 24,
                      ),
                      color: const Color(0xFF9CA3AF),
                  ),
                ],
              ),
            ),
          ),
          
          // Liste des vidéos
          Expanded(
            child: _buildContent(),
          ),
        ],
      ),
    );
  }

  /// Construit le contenu selon l'état
  Widget _buildContent() {
    if (_isLoading) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CircularProgressIndicator(),
            Gap(16),
            Text(
              'Chargement des fichiers vidéo...',
              style: TextStyle(color: Color(0xFF6B7280)),
            ),
          ],
        ),
      );
    }

    if (_errorMessage != null) {
      return Center(
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
              _errorMessage!,
              style: const TextStyle(color: Color(0xFF6B7280)),
              textAlign: TextAlign.center,
            ),
            const Gap(16),
            ElevatedButton.icon(
              onPressed: _loadVideoFiles,
              icon: const Icon(Icons.refresh, size: 16),
              label: const Text('Réessayer'),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF059669),
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
      );
    }

    if (_filteredVideoFiles.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              _searchController.text.isEmpty 
                ? Icons.video_library_outlined 
                : Icons.search_off,
              size: 48,
              color: const Color(0xFF9CA3AF),
            ),
            const Gap(16),
            Text(
              _searchController.text.isEmpty
                ? 'Aucun fichier vidéo disponible'
                : 'Aucun résultat pour "${_searchController.text}"',
              style: const TextStyle(color: Color(0xFF6B7280)),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      );
    }

    return ListView.builder(
              padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: _filteredVideoFiles.length,
              itemBuilder: (context, index) {
        final videoFile = _filteredVideoFiles[index];
        return VideoCard(videoFile: videoFile);
      },
    );
  }
}

/// Carte vidéo draggable
class VideoCard extends StatelessWidget {
  const VideoCard({required this.videoFile, super.key});
  
  final MediaFile videoFile;

  /// Détermine l'icône et la couleur selon les métadonnées ou le nom
  Map<String, dynamic> _getIconAndColor() {
    final fileName = videoFile.originalName.toLowerCase();
    
    // Clip musical
    if (fileName.contains('clip') || 
        fileName.contains('musique') ||
        fileName.contains('music')) {
      return {
        'icon': Icons.music_video,
        'color': const Color(0xFF3B82F6),
      };
    }
    
    // Documentaire
    if (fileName.contains('documentaire') || 
        fileName.contains('docu') ||
        fileName.contains('reportage')) {
      return {
        'icon': Icons.movie,
        'color': const Color(0xFF10B981),
      };
    }
    
    // Prédication vidéo
    if (fileName.contains('prédication') || 
        fileName.contains('predication') ||
        fileName.contains('sermon')) {
      return {
        'icon': Icons.video_call,
        'color': const Color(0xFFEF4444),
      };
    }
    
    // Par défaut : vidéo générique
    return {
      'icon': Icons.play_circle_outline,
      'color': const Color(0xFF8B5CF6),
    };
  }

  /// Enlève l'extension du nom de fichier
  String _removeExtension(String filename) {
    final lastDot = filename.lastIndexOf('.');
    if (lastDot > 0) {
      return filename.substring(0, lastDot);
    }
    return filename;
  }

  @override
  Widget build(BuildContext context) {
    final iconData = _getIconAndColor();
    final title = _removeExtension(videoFile.originalName);
    final description = videoFile.metadata?['description']?.toString() ?? 
        'Description non disponible';
    
    return Draggable<MediaFile>(
      data: videoFile,
      feedback: Material(
        elevation: 8,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          width: 200,
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: const Color(0xFFE5E7EB)),
          ),
          child: Row(
            children: [
              Icon(
                iconData['icon'] as IconData,
                color: iconData['color'] as Color,
                size: 20,
              ),
              const Gap(8),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                  ),
                  overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
          ),
        ),
      ),
      childWhenDragging: Container(
        padding: const EdgeInsets.all(12),
        margin: const EdgeInsets.only(bottom: 8),
        decoration: BoxDecoration(
          color: const Color(0xFFF3F4F6),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: const Color(0xFFE5E7EB)),
        ),
        child: Row(
          children: [
            Icon(
              iconData['icon'] as IconData,
              color: const Color(0xFF9CA3AF),
              size: 20,
            ),
            const Gap(8),
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  fontWeight: FontWeight.w500,
                  fontSize: 14,
                  color: Color(0xFF9CA3AF),
                ),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
      child: Container(
        padding: const EdgeInsets.all(12),
        margin: const EdgeInsets.only(bottom: 8),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: const Color(0xFFE5E7EB)),
        ),
        child: Row(
          children: [
            Icon(
              iconData['icon'] as IconData,
              color: iconData['color'] as Color,
              size: 20,
            ),
            const Gap(12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 14,
                      color: Color(0xFF111827),
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                  const Gap(4),
                  Text(
                    description,
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xFF6B7280),
                    ),
                    overflow: TextOverflow.ellipsis,
                    maxLines: 2,
                  ),
                ],
              ),
            ),
            const Gap(8),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  videoFile.effectiveDurationLabel,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    color: Color(0xFF6B7280),
                  ),
                ),
                const Gap(2),
                Text(
                  videoFile.fileSizeFormatted,
                  style: const TextStyle(
                    fontSize: 11,
                    color: Color(0xFF9CA3AF),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Section constructeur de playlist TV (colonne droite)
class PlaylistBuilderSection extends StatefulWidget {
  const PlaylistBuilderSection({super.key});

  @override
  State<PlaylistBuilderSection> createState() => _PlaylistBuilderSectionState();
}

class _PlaylistBuilderSectionState extends State<PlaylistBuilderSection> {
  List<MediaFile> _playlistItems = [];
  bool _isLoading = true;
  int? _currentPlaylistId; // ID de la playlist active
  bool _loopEnabled = false;
  bool _shuffleEnabled = false;
  
  @override
  void initState() {
    super.initState();
    _loadSavedPlaylist();
  }

  /// Charge la playlist sauvegardée depuis l'API
  Future<void> _loadSavedPlaylist() async {
    setState(() {
      _isLoading = true;
    });

    try {
      // Récupérer TOUTES les playlists (pas seulement les actives)
      final response = await WebTvService.instance.getPlaylists();
      
      print('🔍 Debug playlist: ${response.data.length} playlists trouvées');
      
      if (response.data.isNotEmpty) {
        // Prendre la première playlist (peu importe son statut)
        final activePlaylist = response.data.first;
        
        // Stocker l'ID de la playlist
        _currentPlaylistId = activePlaylist.id;
        
        print('🎬 Playlist "${activePlaylist.name}" : ${activePlaylist.items.length} items');
        
        // Convertir les items de la playlist en MediaFile
        // Récupérer la médiathèque pour enrichir les métadonnées (durée, etc.)
        Map<int, MediaFile> libraryFilesById = {};
        try {
          final libraryResponse = await MediaService.instance.getMediaFiles(
            perPage: 500,
            fileType: 'video',
          );
          libraryFilesById = {
            for (final file in libraryResponse.data) file.id: file,
          };
          print('✅ ${libraryFilesById.length} fichiers vidéo chargés (pour enrichissement playlist)');
        } catch (e) {
          print('⚠️ Impossible de charger la médiathèque pour enrichir les durées: $e');
        }

        String formatSeconds(int seconds) {
          final hours = seconds ~/ 3600;
          final minutes = (seconds % 3600) ~/ 60;
          final remainingSeconds = seconds % 60;
          if (hours > 0) {
            return '${hours.toString().padLeft(2, '0')}:${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
          }
          return '${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
        }

        final playlistFiles = activePlaylist.items.map((item) {
          final libraryMatch = libraryFilesById[item.videoFileId ?? item.id];
          final libraryDurationSeconds = libraryMatch?.effectiveDurationSeconds;
          final playlistDuration = item.duration;
          final effectiveDuration = libraryDurationSeconds ?? (playlistDuration > 0 ? playlistDuration : null);
          final durationLabel = libraryMatch?.effectiveDurationLabel ??
              (effectiveDuration != null ? formatSeconds(effectiveDuration) : '--:--');

          print(
            '   📹 Item: ${item.title} - video_file_id: ${item.videoFileId} - id: ${item.id} - durationPlaylist=${item.duration} - durationLibrary=${libraryDurationSeconds}',
          );
          
          return MediaFile(
            id: libraryMatch?.id ?? item.videoFileId ?? item.id,
            playlistItemId: item.id,
            filename: libraryMatch?.filename ?? item.uniqueId ?? 'video_${item.videoFileId ?? item.id}',
            originalName: libraryMatch?.originalName ?? item.title,
            fileType: 'video',
            fileSizeFormatted: libraryMatch?.fileSizeFormatted ?? '0 MB',
            status: 'completed',
            statusText: 'Terminé',
            progress: 100,
            errorMessage: null,
            estimatedTimeRemaining: null,
            bytesUploaded: libraryMatch?.bytesUploaded ?? 0,
            bytesTotal: libraryMatch?.bytesTotal ?? 0,
            fileUrl: libraryMatch?.fileUrl ?? '',
            thumbnailUrl: libraryMatch?.thumbnailUrl,
            hasThumbnail: libraryMatch?.hasThumbnail ?? false,
            duration: effectiveDuration,
            width: libraryMatch?.width,
            height: libraryMatch?.height,
            bitrate: libraryMatch?.bitrate,
            metadata: {
              'title': libraryMatch?.metadata?['title'] ?? item.title,
              'artist': libraryMatch?.metadata?['artist'] ?? item.artist ?? 'Artiste inconnu',
              'duration': effectiveDuration,
              'duration_seconds': effectiveDuration,
              'duration_formatted': durationLabel,
              'quality': libraryMatch?.metadata?['quality'] ?? item.quality ?? '1080p',
            },
            createdAt: libraryMatch?.createdAt ?? DateTime.now(),
            updatedAt: libraryMatch?.updatedAt ?? DateTime.now(),
          );
        }).toList();

        if (mounted) {
          setState(() {
            _playlistItems = playlistFiles;
            _loopEnabled = activePlaylist.isLoop;
            _shuffleEnabled = activePlaylist.isShuffle;
            _isLoading = false;
          });
          print('✅ ${playlistFiles.length} items chargés dans la playlist');
          print('📋 IDs chargés: ${playlistFiles.map((item) => 'id=${item.id}, playlistItemId=${item.playlistItemId}').join(", ")}');
        }
      } else {
        // Aucune playlist trouvée
        print('⚠️ Aucune playlist trouvée dans la base de données');
        if (mounted) {
          setState(() {
            _playlistItems = [];
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      print('⚠️ Erreur lors du chargement de la playlist: $e');
      if (mounted) {
        setState(() {
          _playlistItems = [];
          _isLoading = false;
        });
      }
    }
  }

  /// Affiche une boîte de dialogue de confirmation avant de supprimer la playlist
  Future<void> _showDeletePlaylistDialog() async {
    if (_currentPlaylistId == null) {
      // Si pas de playlist active, juste vider la liste locale
      setState(() {
        _playlistItems.clear();
      });
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Row(
          children: [
            Icon(
              Icons.warning_amber_rounded,
              color: Color(0xFFEF4444),
              size: 28,
            ),
            Gap(12),
            Text(
              'Supprimer la playlist',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w700,
                color: Color(0xFF111827),
              ),
            ),
          ],
        ),
        content: const Text(
          'Êtes-vous sûr de vouloir supprimer cette playlist ? Cette action est irréversible.',
          style: TextStyle(
            fontSize: 14,
            color: Color(0xFF6B7280),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text(
              'Annuler',
              style: TextStyle(
                color: Color(0xFF6B7280),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFEF4444),
              foregroundColor: Colors.white,
            ),
            child: const Text(
              'Supprimer',
              style: TextStyle(
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await _deletePlaylist();
    }
  }

  /// Supprime la playlist
  Future<void> _deletePlaylist() async {
    if (_currentPlaylistId == null) return;

    try {
      // Appeler l'API pour supprimer la playlist
      await WebTvService.instance.deletePlaylist(_currentPlaylistId!);

      // Vider la liste locale
      setState(() {
        _playlistItems.clear();
        _currentPlaylistId = null;
      });

      // Afficher un message de succès
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Playlist supprimée avec succès'),
          backgroundColor: Colors.green,
          duration: Duration(seconds: 2),
        ),
      );
    } catch (e) {
      print('💥 Erreur suppression playlist: $e');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Erreur lors de la suppression: $e'),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 3),
        ),
      );
    }
  }

  /// Met à jour l'ordre des items dans la playlist
  Future<void> _updateItemsOrder() async {
    if (_currentPlaylistId == null) return;

    try {
      // Préparer les données avec le nouvel ordre
      final items = _playlistItems.asMap().entries.map((entry) {
        // Utiliser playlistItemId si disponible, sinon utiliser id
        final itemId = entry.value.playlistItemId ?? entry.value.id;
        return {
          'id': itemId,
          'order': entry.key + 1, // Commencer à 1
        };
      }).toList();

      // Appeler l'API pour mettre à jour l'ordre
      await WebTvService.instance.updatePlaylistItemsOrder(
        playlistId: _currentPlaylistId!,
        items: items,
      );

      print('✅ Ordre mis à jour avec succès');
    } catch (e) {
      print('💥 Erreur mise à jour ordre: $e');
      // Ne pas afficher d'erreur à l'utilisateur car le reorder local a déjà été fait
    }
  }

  /// Supprime un item de la playlist
  Future<void> _removeItem(MediaFile video) async {
    // Si pas de playlist active, suppression locale uniquement
    if (_currentPlaylistId == null) {
      setState(() {
        _playlistItems.removeWhere((item) => 
          item.playlistItemId == video.playlistItemId || 
          (video.playlistItemId == null && item.id == video.id)
        );
      });
      return;
    }

    // Vérifier que playlistItemId est disponible
    if (video.playlistItemId == null) {
      print('⚠️ Erreur: playlistItemId est null pour "${video.originalName}"');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Erreur: Impossible de supprimer ${video.originalName}'),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 3),
        ),
      );
      return;
    }

    try {
      print('🗑️ Suppression item: playlist_id=$_currentPlaylistId, item_id=${video.playlistItemId}');
      
      // Appeler l'API pour supprimer l'item
      await WebTvService.instance.deletePlaylistItem(
        playlistId: _currentPlaylistId!,
        itemId: video.playlistItemId!,
      );

      // Supprimer de la liste locale
      setState(() {
        _playlistItems.removeWhere((item) => 
          item.playlistItemId == video.playlistItemId || 
          (video.playlistItemId == null && item.id == video.id)
        );
      });

      // Afficher un message de succès
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${video.originalName} retiré de la playlist'),
          backgroundColor: Colors.green,
          duration: const Duration(seconds: 2),
        ),
      );
      
      // Recharger la playlist pour synchroniser les données avec les IDs de la BDD
      print('🔄 Rechargement de la playlist après suppression...');
      await _loadSavedPlaylist();
      
    } catch (e) {
      print('💥 Erreur suppression item: $e');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Erreur lors de la suppression: $e'),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 3),
        ),
      );
    }
  }

  /// Calcule la durée totale de la playlist
  int _getTotalDuration() {
    return _playlistItems.fold(0, (total, item) {
      final duration = item.effectiveDurationSeconds;
      return total + (duration ?? 0);
    });
  }

  /// Formate la durée totale en HH:MM:SS
  String _formatTotalDuration(int seconds) {
    final hours = seconds ~/ 3600;
    final minutes = (seconds % 3600) ~/ 60;
    final remainingSeconds = seconds % 60;
    
    if (hours > 0) {
      return '${hours.toString().padLeft(2, '0')}:'
          '${minutes.toString().padLeft(2, '0')}:'
          '${remainingSeconds.toString().padLeft(2, '0')}';
    } else {
      return '${minutes.toString().padLeft(2, '0')}:'
          '${remainingSeconds.toString().padLeft(2, '0')}';
    }
  }

  /// Sauvegarde la playlist WebTV
  Future<void> _savePlaylist() async {
    if (_playlistItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('La playlist est vide. Ajoutez des vidéos avant de sauvegarder.'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    try {
      // Préparer les données de la playlist (format simplifié pour test)
      final playlistItems = _playlistItems.map((video) {
        final title = video.metadata?['title']?.toString() ?? video.originalName;
        final artist = video.metadata?['artist']?.toString() ?? 'Artiste inconnu';
        
        return {
          'video_file_id': video.id, // 🔥 CRITIQUE : ID du fichier vidéo
          'title': title,
          'artist': artist,
          'duration': video.duration ?? 0,
          'quality': video.metadata?['resolution']?.toString() ?? '1080p',
          'unique_id': 'video_${video.id}', // 🔥 ID unique stable basé sur l'ID du fichier
        };
      }).toList();

      print('🎬 Préparation playlist:');
      print('• Nombre de vidéos: ${_playlistItems.length}');
      print('• Items préparés: $playlistItems');

      // Appeler l'API pour sauvegarder
      final response = await WebTvService.instance.savePlaylist(
        name: 'Playlist TV', // Nom fixe
        description: 'Playlist créée le ${DateTime.now().day}/${DateTime.now().month}/${DateTime.now().year} à ${DateTime.now().hour}:${DateTime.now().minute.toString().padLeft(2, '0')}',
        type: 'live',
        quality: '1080p',
        items: playlistItems,
        isLoop: _loopEnabled,
        isShuffle: _shuffleEnabled,
      );

      // Afficher le résultat
      if (response['success'] == true) {
        final action = response['action'] ?? 'created';
        final totalItems = response['data']?['total_items'] ?? _playlistItems.length;
        final totalDuration = response['data']?['total_duration'] ?? _getTotalDuration();
        
        // Message de succès avec détails
        String message = 'Playlist WebTV ${action == 'created' ? 'créée' : 'mise à jour'} avec succès !\n';
        message += '• ${totalItems} vidéo${totalItems > 1 ? 's' : ''}\n';
        message += '• Durée totale: ${_formatTotalDuration(totalDuration)}\n';
        
        // Afficher le résumé des médias synchronisés si disponible
        if (response.containsKey('media_sync_summary')) {
          final summary = response['media_sync_summary'] as Map<String, dynamic>;
          final created = summary['created'] ?? 0;
          final updated = summary['updated'] ?? 0;
          final deleted = summary['deleted'] ?? 0;
          final errors = summary['errors'] ?? 0;
          
          if (created > 0 || updated > 0 || deleted > 0) {
            message += '• Synchronisation: +$created, ~$updated, -$deleted';
            if (errors > 0) message += ', ❌$errors erreurs';
          }
        }

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(message),
            backgroundColor: Colors.green,
            duration: const Duration(seconds: 5),
          ),
        );

        // Recharger la playlist pour synchroniser les données
        await _loadSavedPlaylist();
        
      } else {
        throw Exception(response['message'] ?? 'Erreur lors de la sauvegarde');
      }

    } catch (e) {
      print('💥 Erreur sauvegarde playlist: $e');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Erreur lors de la sauvegarde: $e'),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 5),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // En-tête
          Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
              children: [
                const Text(
                      'Playlist TV',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF111827),
                  ),
                ),
                const Spacer(),
                OutlinedButton(
                      onPressed: _showDeletePlaylistDialog,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF111827),
                        side: const BorderSide(color: Color(0xFFD1D5DB)),
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      ),
                  child: const Text('Annuler'),
                ),
                    const Gap(12),
                    ElevatedButton.icon(
                      onPressed: _savePlaylist,
                      icon: const Icon(Icons.save, size: 16),
                      label: const Text('Sauvegarder'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF111827),
                    foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          
          // Zone de drop
          Expanded(
            child: _isLoading
                ? const Center(
                    child: CircularProgressIndicator(),
                  )
                : DragTarget<MediaFile>(
              onAcceptWithDetails: (details) {
                // Vérifier si la vidéo n'est pas déjà dans la playlist
                // Comparer par ID du fichier vidéo ET par nom de fichier pour gérer les doublons
                final isAlreadyInPlaylist = _playlistItems.any(
                  (item) => item.id == details.data.id || 
                            item.originalName.toLowerCase() == details.data.originalName.toLowerCase()
                );
                
                if (!isAlreadyInPlaylist) {
                  // Ajouter la vidéo à la playlist
                  setState(() {
                    _playlistItems.add(details.data);
                  });
                  debugPrint('Vidéo ajoutée à la playlist: '
                      '${details.data.originalName}');
                  
                  // Afficher un message de succès
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text('${details.data.originalName} ajoutée à la playlist'),
                      backgroundColor: Colors.green,
                      duration: const Duration(seconds: 2),
                    ),
                  );
                } else {
                  // Afficher un message d'erreur
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text('${details.data.originalName} est déjà dans la playlist'),
                      backgroundColor: Colors.orange,
                      duration: const Duration(seconds: 2),
                    ),
                  );
                  debugPrint('Vidéo déjà présente dans la playlist: '
                      '${details.data.originalName}');
                }
              },
              builder: (context, candidateData, rejectedData) {
                return Container(
              margin: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                    color: candidateData.isNotEmpty 
                        ? const Color(0xFFE0F2F7) 
                        : const Color(0xFFF9FAFB),
                    borderRadius: BorderRadius.circular(8),
                border: Border.all(
                      color: candidateData.isNotEmpty 
                          ? const Color(0xFF059669) 
                          : const Color(0xFFE5E7EB),
                    ),
                  ),
                  child: _playlistItems.isEmpty
                      ? Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                                candidateData.isNotEmpty 
                                    ? Icons.add_circle 
                                    : Icons.open_in_full,
                      size: 48,
                                color: candidateData.isNotEmpty 
                                    ? const Color(0xFF059669) 
                                    : const Color(0xFF9CA3AF),
                    ),
                              const Gap(16),
                    Text(
                                candidateData.isNotEmpty 
                                    ? 'Déposez la vidéo ici' 
                                    : 'Glissez & déposez des vidéos ici',
                      style: TextStyle(
                        fontSize: 16,
                                  fontWeight: FontWeight.w600,
                                  color: candidateData.isNotEmpty 
                                      ? const Color(0xFF059669) 
                                      : const Color(0xFF6B7280),
                      ),
                                textAlign: TextAlign.center,
                    ),
                  ],
                ),
                        )
                      : ReorderableListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: _playlistItems.length,
                          onReorder: (oldIndex, newIndex) async {
                            setState(() {
                              if (newIndex > oldIndex) {
                                newIndex -= 1;
                              }
                              final item = _playlistItems.removeAt(oldIndex);
                              _playlistItems.insert(newIndex, item);
                            });

                            // Sauvegarder le nouvel ordre si une playlist active existe
                            if (_currentPlaylistId != null) {
                              await _updateItemsOrder();
                            }
                          },
                          itemBuilder: (context, index) {
                            final video = _playlistItems[index];
                            // Utiliser playlistItemId comme key pour éviter les conflits lors du réordonnancement
                            // Si playlistItemId est null, utiliser un UUID basé sur l'objet lui-même
                            final uniqueKey = video.playlistItemId ?? Object.hash(video.id, video.originalName);
                            return _PlaylistVideoCard(
                              key: ValueKey(uniqueKey),
                              video: video,
                              index: index + 1,
                              onRemove: () => _removeItem(video),
                            );
                          },
                        ),
                );
              },
            ),
          ),
          
          // Options de lecture
          if (_playlistItems.isNotEmpty)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: const BoxDecoration(
              color: Color(0xFFF9FAFB),
              border: Border(
                top: BorderSide(color: Color(0xFFE5E7EB)),
              ),
            ),
            child: Row(
              children: [
                Switch(
                  value: _loopEnabled,
                  onChanged: (value) {
                    setState(() {
                      _loopEnabled = value;
                    });
                  },
                  activeColor: AppTheme.blueColor,
                ),
                const Gap(8),
                const Text('Boucle'),
                const Spacer(),
                Switch(
                  value: _shuffleEnabled,
                  onChanged: (value) {
                    setState(() {
                      _shuffleEnabled = value;
                    });
                  },
                  activeColor: AppTheme.blueColor,
                ),
                const Gap(8),
                const Text('Lecture aléatoire'),
              ],
            ),
          ),
          
          // Pied de page avec total et durée
          if (_playlistItems.isNotEmpty)
          Container(
            padding: const EdgeInsets.all(16),
              decoration: const BoxDecoration(
                color: Color(0xFFF9FAFB),
                borderRadius: BorderRadius.vertical(bottom: Radius.circular(12)),
                border: Border(
                  top: BorderSide(color: Color(0xFFE5E7EB)),
                ),
              ),
              child: Row(
              children: [
                Text(
                    'Total: ${_playlistItems.length} vidéo${_playlistItems.length > 1 ? 's' : ''}',
                    style: const TextStyle(
                      fontSize: 14,
                    fontWeight: FontWeight.w600,
                      color: Color(0xFF111827),
                  ),
                ),
                  const Spacer(),
                Text(
                    'Durée totale: ${_formatTotalDuration(_getTotalDuration())}',
                    style: const TextStyle(
                      fontSize: 14,
                    fontWeight: FontWeight.w600,
                      color: Color(0xFF059669),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Carte vidéo dans la playlist
class _PlaylistVideoCard extends StatelessWidget {
  const _PlaylistVideoCard({
    super.key,
    required this.video,
    required this.index,
    required this.onRemove,
  });

  final MediaFile video;
  final int index;
  final VoidCallback onRemove;

  /// Enlève l'extension du nom de fichier
  String _removeExtension(String filename) {
    final lastDot = filename.lastIndexOf('.');
    if (lastDot > 0) {
      return filename.substring(0, lastDot);
    }
    return filename;
  }

  @override
  Widget build(BuildContext context) {
    final title = _removeExtension(video.originalName);
    final artist = (video.metadata?['artist']?.toString().trim().isNotEmpty ?? false)
        ? video.metadata!['artist'].toString()
        : 'Artiste inconnu';
    
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF9FAFB),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        children: [
          // Numéro de position
          Container(
            width: 24,
            height: 24,
            decoration: BoxDecoration(
              color: const Color(0xFF059669),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Center(
              child: Text(
                '$index',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ),
          const Gap(12),
          // Informations de la vidéo
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                    color: Color(0xFF111827),
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
                const Gap(4),
                Text(
                  artist,
                  style: const TextStyle(
                    fontSize: 12,
                    color: Color(0xFF6B7280),
                  ),
                ),
              ],
            ),
          ),
          const Gap(12),
          // Durée
          Text(
            video.effectiveDurationLabel,
            style: const TextStyle(
              fontSize: 12,
              color: Color(0xFF6B7280),
            ),
          ),
          const Gap(12),
          // Boutons d'action
          Row(
            children: [
              // Icône de déplacement
              const Icon(
                Icons.drag_indicator,
                size: 16,
                color: Color(0xFF9CA3AF),
              ),
              const Gap(12),
              // Bouton de suppression
          IconButton(
                onPressed: onRemove,
                icon: const Icon(
                  Icons.remove_circle_outline,
                  size: 20,
                  color: Color(0xFFEF4444),
                ),
                tooltip: 'Retirer de la playlist',
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(
                  minWidth: 32,
                  minHeight: 32,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
