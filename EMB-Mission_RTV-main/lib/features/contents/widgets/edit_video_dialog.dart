import 'package:flutter/material.dart';
import 'package:emb_mission_dashboard/core/models/media_models.dart';
import 'package:emb_mission_dashboard/core/services/media_service.dart';

/// Dialogue de modification de fichier vidéo
class EditVideoDialog extends StatefulWidget {
  const EditVideoDialog({
    super.key,
    required this.file,
    required this.onSave,
  });

  final MediaFile file;
  final Function(MediaFile) onSave;

  @override
  State<EditVideoDialog> createState() => _EditVideoDialogState();
}

class _EditVideoDialogState extends State<EditVideoDialog> {
  late TextEditingController _nameController;
  late TextEditingController _titleController;
  late TextEditingController _descriptionController;
  late TextEditingController _resolutionController;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.file.originalName);
    
    // Initialiser les métadonnées
    final metadata = widget.file.metadata ?? {};
    _titleController = TextEditingController(text: metadata['title']?.toString() ?? '');
    _descriptionController = TextEditingController(text: metadata['description']?.toString() ?? '');
    _resolutionController = TextEditingController(text: metadata['resolution']?.toString() ?? '');
  }

  @override
  void dispose() {
    _nameController.dispose();
    _titleController.dispose();
    _descriptionController.dispose();
    _resolutionController.dispose();
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
        if (_descriptionController.text.trim().isNotEmpty) 'description': _descriptionController.text.trim(),
        if (_resolutionController.text.trim().isNotEmpty) 'resolution': _resolutionController.text.trim(),
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
        bytesUploaded: widget.file.bytesUploaded,
        bytesTotal: widget.file.bytesTotal,
        estimatedTimeRemaining: widget.file.estimatedTimeRemaining,
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
          const Icon(Icons.video_library, color: Color(0xFF059669)),
          const SizedBox(width: 8),
          const Text('Modifier la vidéo'),
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
                        const Icon(Icons.video_library, size: 20, color: Color(0xFF6B7280)),
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
                  hintText: 'ex: ma_video.mp4',
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
              
              // Métadonnées vidéo
              const Text(
                'Métadonnées vidéo',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF111827),
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Ces informations apparaîtront dans les lecteurs vidéo',
                style: TextStyle(
                  color: Color(0xFF6B7280),
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 16),
              
              // Titre
              TextField(
                controller: _titleController,
                decoration: InputDecoration(
                  labelText: 'Titre',
                  hintText: 'ex: Mon super clip',
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
              
              // Description
              TextField(
                controller: _descriptionController,
                decoration: InputDecoration(
                  labelText: 'Description',
                  hintText: 'ex: Description du contenu vidéo',
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
                maxLines: 3,
              ),
              const SizedBox(height: 16),
              
              // Résolution
              TextField(
                controller: _resolutionController,
                decoration: InputDecoration(
                  labelText: 'Résolution',
                  hintText: 'ex: 1920x1080',
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
            foregroundColor: const Color(0xFF6B7280),
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
                  width: 20,
                  height: 20,
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
