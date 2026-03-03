import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:emb_mission_dashboard/core/services/media_service.dart';
import 'package:emb_mission_dashboard/core/models/media_models.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

/// Constructeur de Playlists Radio
/// 
/// Page permettant de créer et organiser des playlists audio par glisser-déposer.
/// Interface en deux colonnes : médiathèque audio à gauche, playlist en construction à droite.
class RadioPlaylistBuilderPage extends StatelessWidget {
  const RadioPlaylistBuilderPage({super.key});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        final isTablet = constraints.maxWidth >= 768 && constraints.maxWidth < 1024;
        
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
                'Constructeur de Playlists Radio',
                style: TextStyle(
                  fontSize: 32,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF111827),
                ),
              ),
              const Gap(6),
              const Text(
                'Créez et organisez vos playlists par glisser-déposer.',
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
    return const SingleChildScrollView(
      child: Column(
        children: [
          AudioLibrarySection(),
          Gap(20),
          RadioPlaylistBuilderSection(),
        ],
      ),
    );
  }
}

/// Layout tablette : colonnes côte à côte mais plus étroites
class _TabletLayout extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(flex: 1, child: AudioLibrarySection()),
        Gap(16),
        Expanded(flex: 1, child: RadioPlaylistBuilderSection()),
      ],
    );
  }
}

/// Layout desktop : colonnes côte à côte avec proportions optimales
class _DesktopLayout extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(flex: 2, child: AudioLibrarySection()),
        Gap(24),
        Expanded(flex: 3, child: RadioPlaylistBuilderSection()),
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

/// Section médiathèque audio (colonne gauche)
class AudioLibrarySection extends StatefulWidget {
  const AudioLibrarySection({super.key});

  @override
  State<AudioLibrarySection> createState() => _AudioLibrarySectionState();
}

class _AudioLibrarySectionState extends State<AudioLibrarySection> {
  List<MediaFile> _audioFiles = [];
  List<MediaFile> _filteredAudioFiles = [];
  bool _isLoading = true;
  String? _errorMessage;
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadAudioFiles();
    _searchController.addListener(_filterAudioFiles);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  /// Charge les fichiers audio depuis l'API
  Future<void> _loadAudioFiles() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final response = await MediaService.instance.getMediaFiles(
        page: 1,
        perPage: 100, // Charger jusqu'à 100 fichiers audio
        fileType: 'audio',
      );

      setState(() {
        _audioFiles = response.data;
        _filteredAudioFiles = response.data;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _errorMessage = 'Erreur lors du chargement des fichiers audio';
        _isLoading = false;
      });
      print('❌ Erreur de chargement des audios: $e');
    }
  }

  /// Filtre les fichiers audio selon la recherche
  void _filterAudioFiles() {
    final query = _searchController.text.toLowerCase();
    
    setState(() {
      if (query.isEmpty) {
        _filteredAudioFiles = _audioFiles;
      } else {
        _filteredAudioFiles = _audioFiles.where((file) {
          final name = file.originalName.toLowerCase();
          final title = file.metadata?['title']?.toString().toLowerCase() ?? '';
          final artist = file.metadata?['artist']?.toString().toLowerCase() ?? '';
          
          return name.contains(query) || 
                 title.contains(query) || 
                 artist.contains(query);
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
                  '${_filteredAudioFiles.length} audio${_filteredAudioFiles.length > 1 ? 's' : ''}',
                  style: const TextStyle(
                    fontSize: 14,
                    color: Color(0xFF6B7280),
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const Gap(8),
                IconButton(
                  onPressed: _loadAudioFiles,
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
                        hintText: 'Rechercher un audio...',
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
                      onPressed: () {
                        _searchController.clear();
                      },
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
          
          // Liste des audios
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
              'Chargement des fichiers audio...',
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
              onPressed: _loadAudioFiles,
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

    if (_filteredAudioFiles.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              _searchController.text.isEmpty 
                ? Icons.music_off 
                : Icons.search_off,
              size: 48,
              color: const Color(0xFF9CA3AF),
            ),
            const Gap(16),
            Text(
              _searchController.text.isEmpty
                ? 'Aucun fichier audio disponible'
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
      itemCount: _filteredAudioFiles.length,
              itemBuilder: (context, index) {
        final audioFile = _filteredAudioFiles[index];
        return AudioCard(audioFile: audioFile);
      },
    );
  }
}

/// Section constructeur de playlist radio (colonne droite)
class AudioCard extends StatelessWidget {
  const AudioCard({required this.audioFile, super.key});
  
  final MediaFile audioFile;

  /// Formate la durée en secondes vers MM:SS
  String _formatDuration(dynamic duration) {
    if (duration == null) return '--:--';
    
    int seconds;
    if (duration is int) {
      seconds = duration;
    } else if (duration is String) {
      // Si c'est déjà formaté comme "03:00", le retourner tel quel
      if (duration.contains(':')) return duration;
      seconds = int.tryParse(duration) ?? 0;
    } else {
      return '--:--';
    }
    
    final minutes = seconds ~/ 60;
    final remainingSeconds = seconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
  }

  /// Détermine l'icône et la couleur selon les métadonnées ou le nom
  Map<String, dynamic> _getIconAndColor() {
    final metadata = audioFile.metadata;
    final fileName = audioFile.originalName.toLowerCase();
    
    // Prédication
    if (fileName.contains('prédication') || 
        fileName.contains('predication') ||
        fileName.contains('sermon')) {
      return {
        'icon': Icons.mic,
        'color': const Color(0xFFEF4444),
      };
    }
    
    // Lecture biblique
    if (fileName.contains('lecture') || 
        fileName.contains('bible') ||
        fileName.contains('biblique')) {
      return {
        'icon': Icons.book,
        'color': const Color(0xFF10B981),
      };
    }
    
    // Jingle / Publicité
    if (fileName.contains('jingle') || 
        fileName.contains('pub') ||
        fileName.contains('spot')) {
      return {
        'icon': Icons.radio,
        'color': const Color(0xFF8B5CF6),
      };
    }
    
    // Par défaut : musique
    return {
      'icon': Icons.music_note,
      'color': const Color(0xFF3B82F6),
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
    final title = _removeExtension(audioFile.originalName);
    final artist = audioFile.metadata?['artist']?.toString() ?? 'Artiste inconnu';
    
    return Draggable<MediaFile>(
      data: audioFile,
      feedback: Material(
        elevation: 8,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          width: 300,
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: const Color(0xFF059669),
            borderRadius: BorderRadius.circular(8),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.2),
                blurRadius: 8,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.drag_indicator,
                  color: Colors.white,
                  size: 20,
                ),
              ),
              const Gap(12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        color: Colors.white,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const Gap(2),
                    Text(
                      artist,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.8),
                        fontSize: 12,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
      childWhenDragging: Opacity(
        opacity: 0.5,
        child: Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: const Color(0xFFF9FAFB),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: const Color(0xFFE5E7EB), style: BorderStyle.solid),
          ),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: (iconData['color'] as Color).withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(
                  iconData['icon'] as IconData,
                  color: iconData['color'] as Color,
                  size: 20,
                ),
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
                        color: Color(0xFF111827),
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const Gap(2),
                    Text(
                      artist,
                      style: const TextStyle(
                        color: Color(0xFF6B7280),
                        fontSize: 12,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              Text(
                _formatDuration(audioFile.duration),
                style: const TextStyle(
                  color: Color(0xFF6B7280),
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFF9FAFB),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: const Color(0xFFE5E7EB)),
        ),
        child: Row(
          children: [
            // Icône du type d'audio
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: (iconData['color'] as Color).withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(
                iconData['icon'] as IconData,
                color: iconData['color'] as Color,
                size: 20,
              ),
            ),
            const Gap(12),
            
            // Informations
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF111827),
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const Gap(2),
                  Text(
                    artist,
                    style: const TextStyle(
                      color: Color(0xFF6B7280),
                      fontSize: 12,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            
            // Durée
            Text(
              _formatDuration(audioFile.duration),
              style: const TextStyle(
                color: Color(0xFF6B7280),
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Carte d'élément de playlist
class _PlaylistItemCard extends StatelessWidget {
  const _PlaylistItemCard({
    required super.key,
    required this.item,
    required this.index,
    required this.onRemove,
  });

  final MediaFile item;
  final int index;
  final VoidCallback onRemove;

  String _removeExtension(String filename) {
    final lastDot = filename.lastIndexOf('.');
    if (lastDot > 0) {
      return filename.substring(0, lastDot);
    }
    return filename;
  }

  String _formatDuration(dynamic duration) {
    if (duration == null) return '--:--';
    
    int seconds;
    if (duration is int) {
      seconds = duration;
    } else if (duration is String) {
      if (duration.contains(':')) return duration;
      seconds = int.tryParse(duration) ?? 0;
    } else {
      return '--:--';
    }
    
    final minutes = seconds ~/ 60;
    final remainingSeconds = seconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final title = _removeExtension(item.originalName);
    final artist = item.metadata?['artist']?.toString() ?? 'Artiste inconnu';

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          // Numéro + poignée de réordonnancement
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: const Color(0xFF059669).withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(6),
            ),
            child: Center(
              child: Text(
                '${index + 1}',
                style: const TextStyle(
                  color: Color(0xFF059669),
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                ),
              ),
            ),
          ),
          const Gap(12),
          const Icon(
            Icons.drag_indicator,
            color: Color(0xFF9CA3AF),
            size: 20,
          ),
          const Gap(12),
          
          // Informations
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF111827),
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const Gap(2),
                Text(
                  artist,
                  style: const TextStyle(
                    color: Color(0xFF6B7280),
                    fontSize: 12,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          
          // Durée
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            child: Text(
              _formatDuration(item.duration),
              style: const TextStyle(
                color: Color(0xFF6B7280),
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          const Gap(8),
          
          // Bouton supprimer
          Container(
            margin: const EdgeInsets.only(right: 4),
            child: IconButton(
              onPressed: onRemove,
              icon: const Icon(Icons.close, size: 18),
              color: const Color(0xFFEF4444),
              padding: const EdgeInsets.all(8),
              constraints: const BoxConstraints(
                minWidth: 32,
                minHeight: 32,
              ),
              tooltip: 'Retirer',
            ),
          ),
        ],
      ),
    );
  }
}

/// Section principale de construction de playlist
class RadioPlaylistBuilderSection extends StatefulWidget {
  const RadioPlaylistBuilderSection({super.key});

  @override
  State<RadioPlaylistBuilderSection> createState() => _RadioPlaylistBuilderSectionState();
}

class _RadioPlaylistBuilderSectionState extends State<RadioPlaylistBuilderSection> {
  List<MediaFile> _playlistItems = [];
  List<int> _playlistItemIds = []; // IDs des items dans la playlist
  int? _currentPlaylistId; // ID de la playlist actuelle
  bool _loopEnabled = false;
  bool _shuffleEnabled = false;

  @override
  void initState() {
    super.initState();
    _loadLastPlaylist();
  }

  /// Charge la dernière playlist depuis l'API
  Future<void> _loadLastPlaylist() async {
    try {
      final playlists = await MediaService.instance.getPlaylists(type: 'morning', perPage: 1);
      
      if (playlists.isNotEmpty && mounted) {
        final lastPlaylist = playlists.first;
        final items = (lastPlaylist['items'] as List<dynamic>?) ?? [];
        
        // Convertir les items en MediaFile
        final mediaFiles = <MediaFile>[];
        for (final item in items) {
          final itemMap = item as Map<String, dynamic>;
          if (itemMap['media_file'] != null) {
            final mediaFile = MediaFile.fromJson(itemMap['media_file'] as Map<String, dynamic>);
            mediaFiles.add(mediaFile);
          }
        }
        
        // Trier par ordre
        mediaFiles.sort((a, b) {
          final aItem = items.firstWhere((item) => (item as Map<String, dynamic>)['media_file']['id'] == a.id) as Map<String, dynamic>;
          final bItem = items.firstWhere((item) => (item as Map<String, dynamic>)['media_file']['id'] == b.id) as Map<String, dynamic>;
          final aOrder = aItem['order'] as int? ?? 0;
          final bOrder = bItem['order'] as int? ?? 0;
          return aOrder.compareTo(bOrder);
        });
        
        // Réorganiser les IDs dans le même ordre que les MediaFile triés
        final sortedItemIds = <int>[];
        for (final mediaFile in mediaFiles) {
          final item = items.firstWhere((item) => (item as Map<String, dynamic>)['media_file']['id'] == mediaFile.id) as Map<String, dynamic>;
          sortedItemIds.add(item['id'] as int);
        }
        
        setState(() {
          _playlistItems = mediaFiles;
          _currentPlaylistId = lastPlaylist['id'] as int?;
          _playlistItemIds = sortedItemIds; // IDs dans le bon ordre
          _loopEnabled = (lastPlaylist['is_loop'] as bool?) ?? false;
          _shuffleEnabled = (lastPlaylist['is_shuffle'] as bool?) ?? false;
        });
      }
    } catch (e) {
      print('Erreur lors du chargement de la playlist: $e');
      
      // Afficher un message d'erreur à l'utilisateur
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.warning_amber_rounded, color: Colors.white),
                const Gap(12),
                const Expanded(
                  child: Text(
                    'Impossible de charger la playlist. Erreur serveur (500).',
                    style: TextStyle(fontSize: 14),
                  ),
                ),
              ],
            ),
            backgroundColor: const Color(0xFFF59E0B),
            duration: const Duration(seconds: 5),
            action: SnackBarAction(
              label: 'Réessayer',
              textColor: Colors.white,
              onPressed: _loadLastPlaylist,
            ),
          ),
        );
      }
    }
  }


  /// Calcule la durée totale de la playlist en secondes
  int _calculateTotalDuration() {
    int total = 0;
    for (var item in _playlistItems) {
      if (item.duration is int) {
        total += item.duration as int;
      } else if (item.duration is String) {
        final parts = (item.duration as String).split(':');
        if (parts.length == 2) {
          total += int.parse(parts[0]) * 60 + int.parse(parts[1]);
        }
      }
    }
    return total;
  }

  /// Formate la durée totale en HH:MM:SS
  String _formatTotalDuration(int seconds) {
    final hours = seconds ~/ 3600;
    final minutes = (seconds % 3600) ~/ 60;
    final secs = seconds % 60;
    return '${hours.toString().padLeft(2, '0')}:${minutes.toString().padLeft(2, '0')}:${secs.toString().padLeft(2, '0')}';
  }

  /// Ajoute un fichier à la playlist
  void _addToPlaylist(MediaFile file) {
    // Vérifier si le fichier existe déjà dans la playlist
    final exists = _playlistItems.any((item) => item.id == file.id);
    
    if (exists) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${file.originalName} est déjà dans la playlist'),
          backgroundColor: const Color(0xFFF59E0B),
          duration: const Duration(seconds: 2),
        ),
      );
      return;
    }
    
    setState(() {
      _playlistItems.add(file);
      // Pour les nouveaux items ajoutés localement, on n'a pas encore d'ID
      // L'ID sera généré lors de la sauvegarde
      _playlistItemIds.add(-1); // ID temporaire
    });
    
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('${file.originalName} ajouté à la playlist'),
        backgroundColor: const Color(0xFF059669),
        duration: const Duration(seconds: 2),
      ),
    );
  }

  /// Retire un fichier de la playlist
  Future<void> _removeFromPlaylist(int index) async {
    if (index >= _playlistItems.length) return;
    
    final item = _playlistItems[index];
    
    try {
      // Afficher un indicateur de chargement
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Row(
              children: [
                SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                ),
                Gap(12),
                Text('Suppression de l\'élément...'),
              ],
            ),
            duration: Duration(seconds: 10),
          ),
        );
      }

      // Supprimer localement d'abord pour une meilleure UX
      setState(() {
        _playlistItems.removeAt(index);
      });

      // Appeler l'API pour supprimer de la base de données
      if (_currentPlaylistId != null && index < _playlistItemIds.length) {
        final itemId = _playlistItemIds[index];
        
        // Vérifier que l'ID n'est pas temporaire (-1) et que la playlist existe
        if (itemId != -1 && _currentPlaylistId! > 0) {
          try {
            await MediaService.instance.deletePlaylistItem(
              _currentPlaylistId!, 
              itemId,
              autoSync: true, // Synchronisation automatique avec AzuraCast
            );
            print('✅ Item supprimé du serveur: ID $itemId');
          } catch (e) {
            print('⚠️ Erreur suppression serveur (item déjà supprimé?): $e');
            // Ne pas bloquer l'UI si l'item n'existe plus côté serveur
            // L'item a déjà été supprimé localement, c'est l'essentiel
          }
        } else {
          print('ℹ️ Item local supprimé (ID temporaire: $itemId)');
        }
        
        // Supprimer aussi l'ID de la liste
        _playlistItemIds.removeAt(index);
      }

      // Fermer le snackbar de chargement
      if (mounted) {
        ScaffoldMessenger.of(context).hideCurrentSnackBar();
      }

      // Afficher le succès
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.check_circle, color: Colors.white),
                const Gap(12),
                Text('${item.originalName} retiré de la playlist'),
              ],
            ),
            backgroundColor: const Color(0xFF059669),
            duration: const Duration(seconds: 2),
          ),
        );
      }
    } catch (e) {
      // En cas d'erreur, remettre l'élément dans la liste
      setState(() {
        _playlistItems.insert(index, item);
      });

      // Fermer le snackbar de chargement
      if (mounted) {
        ScaffoldMessenger.of(context).hideCurrentSnackBar();
      }

      // Afficher l'erreur seulement si ce n'est pas un problème de synchronisation
      if (mounted) {
        String errorMessage = 'Erreur lors de la suppression';
        
        // Si l'erreur indique que l'item n'existe plus, c'est probablement un problème de sync
        if (e.toString().contains('No query results for model') || 
            e.toString().contains('Playlistitem')) {
          errorMessage = 'Item supprimé localement (déjà supprimé du serveur)';
        } else {
          errorMessage = 'Erreur lors de la suppression: $e';
        }
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(errorMessage),
            backgroundColor: const Color(0xFFEF4444),
            duration: const Duration(seconds: 3),
          ),
        );
      }
    }
  }

  /// Réorganise les éléments de la playlist
  void _reorderPlaylist(int oldIndex, int newIndex) {
    // Validation des index avec gestion des cas limites
    if (oldIndex < 0 || oldIndex >= _playlistItems.length) {
      print('⚠️ oldIndex invalide: $oldIndex (length: ${_playlistItems.length})');
      return;
    }
    
    // Ajuster newIndex selon les règles de ReorderableListView
    if (newIndex > oldIndex) {
      newIndex -= 1;
    }
    
    // Clamper newIndex dans les limites valides
    newIndex = newIndex.clamp(0, _playlistItems.length - 1);
    
    // Vérifier que le réordonnancement est nécessaire
    if (oldIndex == newIndex) {
      print('ℹ️ Pas de changement nécessaire: $oldIndex → $newIndex');
      return;
    }
    
    print('🔄 Réordonnancement: $oldIndex → $newIndex (length: ${_playlistItems.length})');
    
    setState(() {
      // Effectuer le réordonnancement
      final item = _playlistItems.removeAt(oldIndex);
      final itemId = _playlistItemIds.removeAt(oldIndex);
      _playlistItems.insert(newIndex, item);
      _playlistItemIds.insert(newIndex, itemId);
      
      print('✅ Réordonnancement effectué: ${item.originalName}');
    });

    // Tester l'API de réordonnancement si une playlist existe
    if (_currentPlaylistId != null) {
      _testReorderAPI();
    }
  }

  /// Teste l'API de réordonnancement des items de playlist
  Future<void> _testReorderAPI() async {
    if (_currentPlaylistId == null || _playlistItemIds.isEmpty) {
      print('❌ Pas de playlist ou d\'items pour tester l\'API');
      return;
    }

    try {
      print('🧪 Test de l\'API PUT /playlists/{playlistId}/items/order');
      print('   • Playlist ID: $_currentPlaylistId');
      print('   • Nombre d\'items: ${_playlistItemIds.length}');

             // Préparer les données pour l'API
             final itemOrders = <Map<String, int>>[];
             for (int i = 0; i < _playlistItemIds.length; i++) {
               final itemId = _playlistItemIds[i];
               if (itemId != -1) { // Ignorer les IDs temporaires
                 itemOrders.add({
                   'id': itemId, // ✅ Correction: 'id' au lieu de 'item_id'
                   'order': i + 1, // Ordre basé sur la position actuelle
                 });
               }
             }

      print('   • Données envoyées: $itemOrders');

      // Appeler l'API
      final result = await MediaService.instance.updatePlaylistOrder(
        playlistId: _currentPlaylistId!,
        itemOrders: itemOrders,
        autoSync: true,
      );

      print('✅ API appelée avec succès !');
      print('   • Réponse: $result');

      // Afficher un message de succès à l'utilisateur
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.check_circle, color: Colors.white),
                const Gap(12),
                Expanded(
                  child: Text(
                    'Ordre de la playlist mis à jour sur le serveur',
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
            backgroundColor: const Color(0xFF059669),
            duration: const Duration(seconds: 3),
          ),
        );
      }

    } catch (e) {
      print('❌ Erreur lors du test de l\'API: $e');
      
      // Afficher l'erreur à l'utilisateur
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.error_outline, color: Colors.white),
                const Gap(12),
                Expanded(
                  child: Text('Erreur API réordonnancement: $e'),
                ),
              ],
            ),
            backgroundColor: const Color(0xFFEF4444),
            duration: const Duration(seconds: 5),
          ),
        );
      }
    }
  }

  /// Vide la playlist
  void _clearPlaylist() {
    setState(() {
      _playlistItems.clear();
      _playlistItemIds.clear();
    });
  }

  /// Affiche le dialogue de confirmation pour supprimer la playlist actuelle
  Future<void> _showDeletePlaylistDialog() async {
    // Vérifier s'il y a une playlist à supprimer
    if (_currentPlaylistId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Aucune playlist à supprimer'),
          backgroundColor: Color(0xFFF59E0B),
          duration: Duration(seconds: 2),
        ),
      );
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.warning_amber_rounded, color: Color(0xFFEF4444), size: 24),
            SizedBox(width: 12),
            Text('Supprimer la playlist', style: TextStyle(fontSize: 18)),
          ],
        ),
        content: const Text(
          'Êtes-vous sûr de vouloir supprimer cette playlist ? Cette action est irréversible.',
          style: TextStyle(fontSize: 15),
        ),
        contentPadding: const EdgeInsets.fromLTRB(24, 16, 24, 20),
        actionsPadding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Annuler', style: TextStyle(fontSize: 15)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFEF4444),
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
            ),
            child: const Text('Supprimer', style: TextStyle(fontSize: 15)),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await _deleteCurrentPlaylist();
    }
  }

  /// Supprime la playlist actuelle
  Future<void> _deleteCurrentPlaylist() async {
    if (_currentPlaylistId == null) return;

    try {
      // Afficher un indicateur de chargement
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Row(
              children: [
                SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                ),
                Gap(12),
                Text('Suppression de la playlist...'),
              ],
            ),
            duration: Duration(seconds: 30),
          ),
        );
      }

      // Appeler l'API de suppression de la playlist
      final success = await MediaService.instance.deletePlaylist(_currentPlaylistId!);

      // Fermer le snackbar de chargement
      if (mounted) {
        ScaffoldMessenger.of(context).hideCurrentSnackBar();
      }

      if (success) {
        // Vider la playlist locale
        setState(() {
          _playlistItems.clear();
          _playlistItemIds.clear();
          _currentPlaylistId = null;
        });

        // Afficher le succès
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Row(
                children: [
                  Icon(Icons.check_circle, color: Colors.white),
                  Gap(12),
                  Expanded(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Playlist supprimée avec succès',
                          style: TextStyle(fontWeight: FontWeight.w600),
                        ),
                        Text(
                          'La playlist a également été supprimée sur AzuraCast',
                          style: TextStyle(fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              backgroundColor: Color(0xFF059669),
              duration: Duration(seconds: 5),
            ),
          );
        }
      } else {
        throw Exception('La suppression a échoué');
      }
    } catch (e) {
      // Fermer le snackbar de chargement
      if (mounted) {
        ScaffoldMessenger.of(context).hideCurrentSnackBar();
      }

      // Afficher l'erreur
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.error_outline, color: Colors.white),
                const Gap(12),
                Expanded(
                  child: Text('Erreur lors de la suppression: $e'),
                ),
              ],
            ),
            backgroundColor: const Color(0xFFEF4444),
            duration: const Duration(seconds: 5),
            action: SnackBarAction(
              label: 'Réessayer',
              textColor: Colors.white,
              onPressed: _deleteCurrentPlaylist,
            ),
          ),
        );
      }
    }
  }

  /// Sauvegarde la playlist avec la nouvelle API unifiée
  Future<void> _savePlaylist() async {
    if (_playlistItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('La playlist est vide'),
          backgroundColor: Color(0xFFEF4444),
          duration: Duration(seconds: 2),
        ),
      );
      return;
    }

    try {
      // Afficher un indicateur de chargement
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Row(
              children: [
                SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                ),
                Gap(12),
                Text('Sauvegarde de la playlist...'),
              ],
            ),
            duration: Duration(seconds: 60),
          ),
        );
      }

      // Extraire les IDs des fichiers média
      final itemIds = _playlistItems.map((file) => file.id).toList();

      // Appel unique à l'API avec tous les paramètres
      final result = await MediaService.instance.createPlaylist(
        name: 'Playlist Matinale',
        description: 'Playlist créée depuis le constructeur',
        type: 'morning',
        isLoop: _loopEnabled,
        isShuffle: _shuffleEnabled,
        items: itemIds,
        autoSync: true,
      );

      // Vérifier le succès
      if (result['success'] != true) {
        throw Exception(result['message'] ?? 'Erreur lors de la sauvegarde');
      }

      // Extraire les informations de la réponse
      final action = result['action'] as String; // "created" ou "updated"
      final playlistData = result['data'] as Map<String, dynamic>;
      final playlistId = playlistData['id'] as int;
      final playlistName = playlistData['name'] as String;
      final itemsAdded = result['items_added'] as int? ?? 0;
      final itemsSkipped = result['items_skipped'] as int? ?? 0;
      final totalItems = playlistData['total_items'] as int? ?? 0;

      print('✅ Playlist ${action == "created" ? "créée" : "mise à jour"} avec ID: $playlistId');
      print('   • Items ajoutés: $itemsAdded');
      print('   • Items ignorés: $itemsSkipped');
      print('   • Total items: $totalItems');

      // Mettre à jour l'état local
      setState(() {
        _currentPlaylistId = playlistId;
        // Les IDs des items seront récupérés lors du prochain chargement
        _playlistItemIds = List.generate(_playlistItems.length, (index) => -1);
      });

      // Fermer le snackbar de chargement
      if (mounted) {
        ScaffoldMessenger.of(context).hideCurrentSnackBar();
      }

      // Afficher le message de succès selon l'action
      if (mounted) {
        String mainMessage;
        List<String> details = [];

        if (action == "created") {
          mainMessage = '✅ Playlist "$playlistName" créée avec succès';
          if (itemsAdded > 0) {
            details.add('$itemsAdded fichier${itemsAdded > 1 ? 's' : ''} ajouté${itemsAdded > 1 ? 's' : ''}');
          }
        } else {
          mainMessage = '✅ Playlist "$playlistName" mise à jour';
          if (itemsAdded > 0) {
            details.add('$itemsAdded nouveau${itemsAdded > 1 ? 'x' : ''} fichier${itemsAdded > 1 ? 's' : ''} ajouté${itemsAdded > 1 ? 's' : ''}');
          }
          if (itemsSkipped > 0) {
            details.add('$itemsSkipped fichier${itemsSkipped > 1 ? 's' : ''} déjà présent${itemsSkipped > 1 ? 's' : ''}');
          }
        }

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.check_circle, color: Colors.white),
                    const Gap(12),
                    Expanded(
                      child: Text(
                        mainMessage,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ),
                  ],
                ),
                if (details.isNotEmpty) ...[
                  const Gap(4),
                  Padding(
                    padding: const EdgeInsets.only(left: 36),
                    child: Text(
                      details.join(' • '),
                      style: const TextStyle(fontSize: 12),
                    ),
                  ),
                ],
              ],
            ),
            backgroundColor: const Color(0xFF059669),
            duration: const Duration(seconds: 5),
          ),
        );
      }
    } catch (e) {
      // Fermer le snackbar de chargement
      if (mounted) {
        ScaffoldMessenger.of(context).hideCurrentSnackBar();
      }

      // Afficher l'erreur avec gestion spécifique
      if (mounted) {
        String errorMessage = 'Erreur lors de la sauvegarde';
        
        if (e.toString().contains('422')) {
          errorMessage = 'Données invalides. Veuillez vérifier la playlist.';
        } else if (e.toString().contains('409') || e.toString().contains('DUPLICATE')) {
          errorMessage = 'Une playlist avec ce nom existe déjà.';
        } else if (e.toString().contains('500')) {
          errorMessage = 'Erreur serveur. Veuillez réessayer.';
        } else {
          errorMessage = 'Erreur: $e';
        }
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.error_outline, color: Colors.white),
                const Gap(12),
                Expanded(child: Text(errorMessage)),
              ],
            ),
            backgroundColor: const Color(0xFFEF4444),
            duration: const Duration(seconds: 5),
            action: SnackBarAction(
              label: 'Réessayer',
              textColor: Colors.white,
              onPressed: _savePlaylist,
            ),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final totalDuration = _calculateTotalDuration();
    
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
          // En-tête avec actions
          Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              color: Color(0xFFF9FAFB),
              borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
            ),
            child: Row(
              children: [
                const Text(
                  'Playlist Matinale',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF111827),
                  ),
                ),
                const Spacer(),
                if (_playlistItems.isNotEmpty) const Gap(8),
                OutlinedButton(
                  onPressed: _currentPlaylistId != null ? _showDeletePlaylistDialog : null,
                  child: const Text('Annuler'),
                ),
                const Gap(8),
                ElevatedButton.icon(
                  onPressed: _playlistItems.isEmpty ? null : _savePlaylist,
                  icon: const Icon(Icons.save, size: 16),
                  label: const Text('Sauvegarder'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF111827),
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: const Color(0xFFE5E7EB),
                    disabledForegroundColor: const Color(0xFF9CA3AF),
                  ),
                ),
              ],
            ),
          ),
          
          // Résumé de la playlist
          Container(
            padding: const EdgeInsets.all(16),
            child: Text(
              'Total : ${_playlistItems.length} audio${_playlistItems.length > 1 ? 's' : ''} - ${_formatTotalDuration(totalDuration)}',
              style: const TextStyle(
                color: Color(0xFF6B7280),
                fontSize: 14,
              ),
            ),
          ),
          
          // Zone de drop
          Expanded(
            child: DragTarget<MediaFile>(
              onAcceptWithDetails: (details) {
                _addToPlaylist(details.data);
              },
              builder: (context, candidateData, rejectedData) {
                final isHovering = candidateData.isNotEmpty;
                
                return Container(
              margin: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                border: Border.all(
                      color: isHovering ? const Color(0xFF059669) : const Color(0xFFD1D5DB),
                  style: BorderStyle.solid,
                      width: isHovering ? 3 : 2,
                ),
                borderRadius: BorderRadius.circular(12),
                    color: isHovering ? const Color(0xFF059669).withValues(alpha: 0.05) : const Color(0xFFFAFAFA),
              ),
                  child: _playlistItems.isEmpty
                      ? Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                                isHovering ? Icons.add_circle : Icons.keyboard_arrow_down,
                      size: 48,
                                color: isHovering ? const Color(0xFF059669) : const Color(0xFF9CA3AF),
                    ),
                              const Gap(16),
                    Text(
                                isHovering 
                                    ? 'Déposez l\'audio ici' 
                                    : 'Glissez & Déposez vos audios ici',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                                  color: isHovering ? const Color(0xFF059669) : const Color(0xFF6B7280),
                        fontSize: 16,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                              const Gap(8),
                              const Text(
                      'Commencez à construire votre playlist.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Color(0xFF9CA3AF),
                        fontSize: 14,
                      ),
                    ),
                  ],
                ),
                        )
                        : ReorderableListView.builder(
                          padding: const EdgeInsets.all(8),
                          itemCount: _playlistItems.length,
                          onReorder: _reorderPlaylist,
                          itemBuilder: (context, index) {
                            final item = _playlistItems[index];
                            // Utiliser une clé unique basée sur l'index ET l'ID pour éviter les conflits
                            return _PlaylistItemCard(
                              key: ValueKey('playlist_${index}_${item.id}'),
                              item: item,
                              index: index,
                              onRemove: () => _removeFromPlaylist(index),
                            );
                          },
                        ),
                );
              },
            ),
          ),
          
          // Options de lecture
          Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              color: Color(0xFFF9FAFB),
              borderRadius: BorderRadius.vertical(bottom: Radius.circular(12)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Options:',
                  style: TextStyle(
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF111827),
                  ),
                ),
                const Gap(12),
                Row(
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
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Carte audio individuelle (draggable)

