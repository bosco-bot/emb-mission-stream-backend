import 'package:emb_mission_dashboard/core/models/webradio_models.dart';
import 'package:emb_mission_dashboard/core/services/webradio_service.dart';
import 'package:emb_mission_dashboard/core/services/azuracast_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/confirmation_dialog.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

/// Page de gestion des sources WebRadio
/// 
/// Interface pour ajouter et gérer les flux de la WebRadio
class WebradioSourcesPage extends StatefulWidget {
  const WebradioSourcesPage({super.key});

  @override
  State<WebradioSourcesPage> createState() => _WebradioSourcesPageState();
}

class _WebradioSourcesPageState extends State<WebradioSourcesPage> {
  StreamingSettings? _settings;
  NowPlayingData? _nowPlaying;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadActiveSource();
    _startPeriodicRefresh();
  }

  Future<void> _loadActiveSource({bool showLoader = true}) async {
    if (showLoader) {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });
    }

    try {
      // Charger les paramètres de streaming et l'état actuel
      final settings = await WebRadioService.instance.getStreamingSettings();
      final nowPlaying = await AzuraCastService.instance.getNowPlaying();
      
      if (mounted) {
        setState(() {
          _settings = settings;
          _nowPlaying = nowPlaying;
          if (showLoader) {
          _isLoading = false;
          }
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          if (showLoader) {
          _errorMessage = e.toString();
          _isLoading = false;
          }
          // En cas d'erreur lors du refresh silencieux, on garde les anciennes données
        });
      }
    }
  }

  void _startPeriodicRefresh() {
    // Rafraîchir toutes les 10 secondes pour éviter le rate limit
    Future.delayed(const Duration(seconds: 10), () {
        if (mounted) {
        _loadActiveSource(showLoader: false); // Refresh silencieux sans loader
        _startPeriodicRefresh();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        
        return Padding(
          padding: EdgeInsets.symmetric(
            horizontal: isMobile ? 16 : 24,
            vertical: 16,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Top bar avec avatar et bouton "Démarrer un Live"
              _TopBar(
                isMobile: isMobile,
                isLive: _nowPlaying?.live.isLive ?? false,
              ),
              const Divider(height: 32),
              
              // Contenu principal
              Expanded(
                child: _isLoading
                    ? const Center(child: CircularProgressIndicator())
                    : _errorMessage != null
                        ? Center(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                const Icon(Icons.error_outline, size: 48, color: Colors.red),
                                const Gap(16),
                                Text(
                                  'Erreur: $_errorMessage',
                                  style: const TextStyle(color: Colors.red),
                                  textAlign: TextAlign.center,
                                ),
                              ],
                            ),
                          )
                        : SingleChildScrollView(
                            child: _ActiveSourceCard(
                              settings: _settings!,
                              nowPlaying: _nowPlaying!,
                              isMobile: isMobile,
                            ),
                          ),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Card affichant la source active avec toutes ses informations
class _ActiveSourceCard extends StatelessWidget {
  const _ActiveSourceCard({
    required this.settings,
    required this.nowPlaying,
    required this.isMobile,
  });

  final StreamingSettings settings;
  final NowPlayingData nowPlaying;
  final bool isMobile;

  void _copyToClipboard(BuildContext context, String text, String label) {
    Clipboard.setData(ClipboardData(text: text));
          ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('$label copié dans le presse-papiers'),
              backgroundColor: Colors.green,
        duration: const Duration(seconds: 2),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isLive = nowPlaying.live.isLive;
    
    return Card(
      elevation: 4,
      color: Colors.white,
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Titre et type de source
            Row(
              children: [
                Icon(
                  isLive ? Icons.mic : Icons.queue_music,
                  color: AppTheme.blueColor,
                  size: 32,
                ),
                const SizedBox(width: 12),
                Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
                      Text(
                        isLive ? 'DJ Live - ${nowPlaying.live.streamerName}' : 'Playlist Automatique (AutoDJ)',
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF0F172A),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        isLive ? 'Diffusion en direct depuis Mixxx' : 'Lecture automatique de la playlist',
                        style: const TextStyle(
                          fontSize: 14,
                          color: Color(0xFF6B7280),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const Gap(24),
            const Divider(),
            const Gap(24),
            
            // Informations de connexion DJ
            Text(
              'Paramètres de connexion DJ (Mixxx)',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: const Color(0xFF111827),
              ),
            ),
            const Gap(16),
            
            // Grille responsive des paramètres
            Wrap(
              spacing: 16,
              runSpacing: 16,
              children: [
                _InfoField(
                  label: 'Serveur',
                  value: settings.serverAddress,
                  icon: Icons.dns,
                  onCopy: () => _copyToClipboard(context, settings.serverAddress, 'Serveur'),
                ),
                _InfoField(
                  label: 'Port',
                  value: settings.port.toString(),
                  icon: Icons.numbers,
                  onCopy: () => _copyToClipboard(context, settings.port.toString(), 'Port'),
                ),
                _InfoField(
                  label: 'Mount Point',
                  value: settings.mountPoint,
                  icon: Icons.folder,
                  onCopy: () => _copyToClipboard(context, settings.mountPoint, 'Mount Point'),
                ),
                _InfoField(
                  label: 'Nom d\'utilisateur',
                  value: settings.username,
                  icon: Icons.person,
                  onCopy: () => _copyToClipboard(context, settings.username, 'Nom d\'utilisateur'),
                ),
                _InfoField(
                  label: 'Mot de passe',
                  value: settings.password,
                  icon: Icons.lock,
                  isPassword: true,
                  onCopy: () => _copyToClipboard(context, settings.password, 'Mot de passe'),
                ),
                _InfoField(
                  label: 'Type',
                  value: '${settings.frontend} (${settings.backend})',
                  icon: Icons.settings_input_antenna,
                ),
              ],
            ),
            
            const Gap(24),
            const Divider(),
            const Gap(24),
            
            // Informations du flux
            Text(
              'Informations du flux audio',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: const Color(0xFF111827),
              ),
            ),
            const Gap(16),
            
            Wrap(
              spacing: 16,
              runSpacing: 16,
              children: [
                _InfoField(
                  label: 'Nom de la station',
                  value: settings.stationName,
                  icon: Icons.radio,
                ),
                _InfoField(
                  label: 'Bitrate',
                  value: '${settings.bitrate} kbps',
                  icon: Icons.speed,
                ),
                _InfoField(
                  label: 'Format',
                  value: settings.format.toUpperCase(),
                  icon: Icons.audio_file,
                ),
                _InfoField(
                  label: 'URL d\'écoute',
                  value: settings.m3uUrl,
                  icon: Icons.link,
                  onCopy: () => _copyToClipboard(context, settings.m3uUrl, 'URL d\'écoute'),
                ),
              ],
            ),
            
            const Gap(24),
            const Divider(),
            const Gap(24),
            
            // Morceau en cours de lecture
            Text(
              'En cours de lecture',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: const Color(0xFF111827),
              ),
            ),
            const Gap(16),
            
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFFF9FAFB),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Row(
                children: [
                  Icon(Icons.music_note, color: AppTheme.blueColor, size: 32),
                  const SizedBox(width: 16),
              Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                        Text(
                          nowPlaying.nowPlaying.song.title,
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF0F172A),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          nowPlaying.nowPlaying.song.artist,
                          style: const TextStyle(
                            fontSize: 14,
                            color: Color(0xFF6B7280),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 16),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        '${nowPlaying.listeners.total} auditeur${nowPlaying.listeners.total > 1 ? 's' : ''}',
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF0F172A),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        nowPlaying.nowPlaying.elapsedFormatted,
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF6B7280),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Widget pour afficher un champ d'information avec bouton copier optionnel
class _InfoField extends StatelessWidget {
  const _InfoField({
    required this.label,
    required this.value,
    required this.icon,
    this.isPassword = false,
    this.onCopy,
  });

  final String label;
  final String value;
  final IconData icon;
  final bool isPassword;
  final VoidCallback? onCopy;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 350,
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
              Icon(icon, color: AppTheme.blueColor, size: 18),
              const SizedBox(width: 8),
              Text(
                label,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF6B7280),
                ),
              ),
              if (onCopy != null) ...[
                const Spacer(),
                IconButton(
                  onPressed: onCopy,
                  icon: const Icon(Icons.copy, size: 16),
                  tooltip: 'Copier',
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(),
                ),
              ],
            ],
          ),
          const SizedBox(height: 8),
          Text(
            isPassword ? '••••••••••••••' : value,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: Color(0xFF0F172A),
                ),
              ),
            ],
          ),
    );
  }
}

/// Barre supérieure avec titre et actions
class _TopBar extends StatelessWidget {
  const _TopBar({required this.isMobile, required this.isLive});
  final bool isMobile;
  final bool isLive;

  @override
  Widget build(BuildContext context) {
    if (isMobile) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
        children: [
          const Text(
                'Source Active',
            style: TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.w800,
              color: Color(0xFF111827),
            ),
              ),
              const SizedBox(width: 12),
              // Badge LIVE / OFFLINE
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  color: isLive 
                    ? const Color(0xFFEF4444) 
                    : const Color(0xFF6B7280),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      isLive ? 'LIVE' : 'OFFLINE',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 12,
                        letterSpacing: 0.5,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const Gap(4),
          Text(
            isLive 
              ? 'Diffusion en direct depuis votre source externe'
              : 'Lecture automatique de la playlist (AutoDJ)',
            style: const TextStyle(
              fontSize: 16,
              color: Color(0xFF6B7280),
            ),
          ),
          const Gap(16),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              const UserProfileChip(),
            ],
          ),
          const Gap(12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () => context.go('/diffusionlive'),
              icon: const Icon(Icons.live_tv),
              label: const Text('Démarrer un Live'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.redColor,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
            ),
          ),
        ],
      );
    }

    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
            children: [
              const Text(
                    'Source Active',
                style: TextStyle(
                  fontSize: 32,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF111827),
                ),
                  ),
                  const SizedBox(width: 16),
                  // Badge LIVE / OFFLINE
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: isLive 
                        ? const Color(0xFFEF4444) 
                        : const Color(0xFF6B7280),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          decoration: const BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          isLive ? 'LIVE' : 'OFFLINE',
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                            fontSize: 12,
                            letterSpacing: 0.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const Gap(4),
              Text(
                isLive 
                  ? 'Diffusion en direct depuis votre source externe'
                  : 'Lecture automatique de la playlist (AutoDJ)',
                style: const TextStyle(
                  fontSize: 16,
                  color: Color(0xFF6B7280),
                ),
              ),
            ],
          ),
        ),
        const UserProfileChip(),
        const Gap(16),
        ElevatedButton.icon(
          onPressed: () => context.go('/diffusionlive'),
          icon: const Icon(Icons.live_tv),
          label: const Text('Démarrer un Live'),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppTheme.redColor,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          ),
        ),
      ],
    );
  }
}

/// Carte pour ajouter une nouvelle source
class _AddSourceCard extends StatefulWidget {
  const _AddSourceCard({
    required this.isMobile,
    required this.onSourceAdded,
  });
  
  final bool isMobile;
  final VoidCallback onSourceAdded;

  @override
  State<_AddSourceCard> createState() => _AddSourceCardState();
}

class _AddSourceCardState extends State<_AddSourceCard> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _urlController = TextEditingController();
  bool _isSubmitting = false;

  @override
  void dispose() {
    _nameController.dispose();
    _urlController.dispose();
    super.dispose();
  }

  Future<void> _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSubmitting = true);

    try {
      await WebRadioService.instance.createSource(
        name: _nameController.text.trim(),
        url: _urlController.text.trim(),
      );

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Source ajoutée avec succès'),
            backgroundColor: Colors.green,
          ),
        );

        // Reset form
        _nameController.clear();
        _urlController.clear();
        _formKey.currentState!.reset();

        // Refresh parent
        widget.onSourceAdded();
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
    } finally {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
    }
  }

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
      ),
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Ajouter une nouvelle source',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF111827),
                ),
              ),
              const Gap(20),
              
              // Champ Nom de la source
              _FormField(
                label: 'Nom de la source',
                hint: 'Ex: Playlist Louange',
                icon: Icons.label_outline,
                controller: _nameController,
                validator: (value) {
                  if (value == null || value.trim().isEmpty) {
                    return 'Le nom est obligatoire';
                  }
                  if (value.trim().length > 255) {
                    return 'Le nom ne doit pas dépasser 255 caractères';
                  }
                  return null;
                },
              ),
              const Gap(16),
              
              // Champ URL du flux
              _FormField(
                label: 'URL du flux / fichier',
                hint: 'https://...',
                icon: Icons.link,
                controller: _urlController,
                validator: (value) {
                  if (value == null || value.trim().isEmpty) {
                    return 'L\'URL est obligatoire';
                  }
                  if (value.trim().length > 500) {
                    return 'L\'URL ne doit pas dépasser 500 caractères';
                  }
                  // Validation URL basique
                  if (!Uri.tryParse(value.trim())!.hasScheme) {
                    return 'URL invalide (doit commencer par http:// ou https://)';
                  }
                  return null;
                },
              ),
              const Gap(24),
              
              // Bouton Ajouter
              SizedBox(
                width: widget.isMobile ? double.infinity : null,
                child: ElevatedButton.icon(
                  onPressed: _isSubmitting ? null : _handleSubmit,
                  icon: _isSubmitting
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.add, size: 18),
                  label: Text(_isSubmitting ? 'Ajout en cours...' : 'Ajouter la source'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.blueColor,
                    foregroundColor: const Color(0xFF2F3B52),
                    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Carte des sources actives
class _ActiveSourcesCard extends StatelessWidget {
  const _ActiveSourcesCard({
    required this.isMobile,
    required this.sources,
    required this.isLoading,
    required this.errorMessage,
    required this.onEdit,
    required this.onDelete,
    required this.onToggleStatus,
    required this.onRefresh,
  });
  
  final bool isMobile;
  final List<RadioSource> sources;
  final bool isLoading;
  final String? errorMessage;
  final Function(RadioSource) onEdit;
  final Function(int) onDelete;
  final Function(int, bool) onToggleStatus;
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
      ),
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Text(
                  'Sources Actives',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF111827),
                  ),
                ),
                const Spacer(),
                if (!isLoading)
                  Text(
                    '${sources.length} source${sources.length > 1 ? 's' : ''}',
                    style: const TextStyle(
                      fontSize: 14,
                      color: Color(0xFF6B7280),
                    ),
                  ),
                if (errorMessage != null)
                  IconButton(
                    onPressed: onRefresh,
                    icon: const Icon(Icons.refresh, size: 20),
                    tooltip: 'Réessayer',
                    style: IconButton.styleFrom(
                      backgroundColor: AppTheme.blueColor,
                      foregroundColor: const Color(0xFF2F3B52),
                      padding: const EdgeInsets.all(8),
                    ),
                  ),
              ],
            ),
            const Gap(20),
            
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
            
            // Liste vide
            if (sources.isEmpty && !isLoading && errorMessage == null)
              Center(
                child: Padding(
                  padding: const EdgeInsets.all(40),
                  child: Column(
                    children: [
                      Icon(
                        Icons.radio,
                        size: 64,
                        color: Colors.grey[300],
                      ),
                      const Gap(16),
                      const Text(
                        'Aucune source configurée',
                        style: TextStyle(
                          fontSize: 16,
                          color: Color(0xFF6B7280),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            
            // Tableau des sources
            if (sources.isNotEmpty && !isLoading)
              ...[
                if (isMobile)
                  ...sources.map((source) => _MobileSourceItem(
                        source: source,
                        onEdit: onEdit,
                        onDelete: onDelete,
                        onToggleStatus: onToggleStatus,
                      ))
                else
                  _DesktopSourcesTable(
                    sources: sources,
                    onEdit: onEdit,
                    onDelete: onDelete,
                    onToggleStatus: onToggleStatus,
                  ),
              ],
          ],
        ),
      ),
    );
  }
}

/// Champ de formulaire
class _FormField extends StatelessWidget {
  const _FormField({
    required this.label,
    required this.hint,
    required this.icon,
    this.controller,
    this.validator,
  });

  final String label;
  final String hint;
  final IconData icon;
  final TextEditingController? controller;
  final String? Function(String?)? validator;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: Color(0xFF111827),
          ),
        ),
        const Gap(8),
        TextFormField(
          controller: controller,
          validator: validator,
          decoration: InputDecoration(
            hintText: hint,
            prefixIcon: Icon(icon, color: const Color(0xFF6B7280)),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: BorderSide(color: AppTheme.blueColor),
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          ),
        ),
      ],
    );
  }
}

/// Tableau desktop des sources
class _DesktopSourcesTable extends StatelessWidget {
  const _DesktopSourcesTable({
    required this.sources,
    required this.onEdit,
    required this.onDelete,
    required this.onToggleStatus,
  });
  
  final List<RadioSource> sources;
  final Function(RadioSource) onEdit;
  final Function(int) onDelete;
  final Function(int, bool) onToggleStatus;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // En-têtes
        Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: const BoxDecoration(
            border: Border(
              bottom: BorderSide(color: Color(0xFFF3F4F6)),
            ),
          ),
          child: const Row(
            children: [
              Expanded(
                flex: 2,
                child: Text(
                  'Nom de la source',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF6B7280),
                  ),
                ),
              ),
              Expanded(
                flex: 3,
                child: Text(
                  'URL du flux',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF6B7280),
                  ),
                ),
              ),
              SizedBox(
                width: 100,
                child: Text(
                  'Actions',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF6B7280),
                  ),
                ),
              ),
            ],
          ),
        ),
        
        // Lignes de données
        ...sources.map((source) => _DesktopSourceRow(
              source: source,
              onEdit: onEdit,
              onDelete: onDelete,
              onToggleStatus: onToggleStatus,
            )),
      ],
    );
  }
}

/// Ligne desktop d'une source
class _DesktopSourceRow extends StatelessWidget {
  const _DesktopSourceRow({
    required this.source,
    required this.onEdit,
    required this.onDelete,
    required this.onToggleStatus,
  });
  
  final RadioSource source;
  final Function(RadioSource) onEdit;
  final Function(int) onDelete;
  final Function(int, bool) onToggleStatus;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16),
      decoration: const BoxDecoration(
        border: Border(
          bottom: BorderSide(color: Color(0xFFF3F4F6)),
        ),
      ),
      child: Row(
        children: [
          Expanded(
            flex: 2,
            child: Text(
              source.name,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w500,
                color: Color(0xFF111827),
              ),
            ),
          ),
          Expanded(
            flex: 3,
            child: Text(
              source.url,
              style: const TextStyle(
                fontSize: 14,
                color: Color(0xFF6B7280),
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ),
          SizedBox(
            width: 140,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                IconButton(
                  onPressed: () => onEdit(source),
                  icon: const Icon(Icons.edit_outlined, size: 18),
                  color: const Color(0xFF6B7280),
                  tooltip: 'Modifier',
                ),
                IconButton(
                  onPressed: () => onToggleStatus(source.id, source.isActive),
                  icon: Icon(
                    source.isActive ? Icons.toggle_on : Icons.toggle_off,
                    size: 24,
                  ),
                  color: source.isActive ? const Color(0xFF10B981) : const Color(0xFF6B7280),
                  tooltip: source.isActive ? 'Désactiver' : 'Activer',
                ),
                IconButton(
                  onPressed: () => onDelete(source.id),
                  icon: const Icon(Icons.delete_outline, size: 18),
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

/// Élément mobile d'une source
class _MobileSourceItem extends StatelessWidget {
  const _MobileSourceItem({
    required this.source,
    required this.onEdit,
    required this.onDelete,
    required this.onToggleStatus,
  });
  
  final RadioSource source;
  final Function(RadioSource) onEdit;
  final Function(int) onDelete;
  final Function(int, bool) onToggleStatus;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
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
              Expanded(
                child: Text(
                  source.name,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF111827),
                  ),
                ),
              ),
              Row(
                children: [
                  IconButton(
                    onPressed: () => onEdit(source),
                    icon: const Icon(Icons.edit_outlined, size: 18),
                    color: const Color(0xFF6B7280),
                    tooltip: 'Modifier',
                  ),
                  IconButton(
                    onPressed: () => onToggleStatus(source.id, source.isActive),
                    icon: Icon(
                      source.isActive ? Icons.toggle_on : Icons.toggle_off,
                      size: 24,
                    ),
                    color: source.isActive ? const Color(0xFF10B981) : const Color(0xFF6B7280),
                    tooltip: source.isActive ? 'Désactiver' : 'Activer',
                  ),
                  IconButton(
                    onPressed: () => onDelete(source.id),
                    icon: const Icon(Icons.delete_outline, size: 18),
                    color: const Color(0xFFEF4444),
                    tooltip: 'Supprimer',
                  ),
                ],
              ),
            ],
          ),
          const Gap(8),
          Text(
            source.url,
            style: const TextStyle(
              fontSize: 14,
              color: Color(0xFF6B7280),
            ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

/// Dialog de modification d'une source
class _EditSourceDialog extends StatefulWidget {
  const _EditSourceDialog({required this.source});
  final RadioSource source;

  @override
  State<_EditSourceDialog> createState() => _EditSourceDialogState();
}

class _EditSourceDialogState extends State<_EditSourceDialog> {
  late final TextEditingController _nameController;
  late final TextEditingController _urlController;
  final _formKey = GlobalKey<FormState>();

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.source.name);
    _urlController = TextEditingController(text: widget.source.url);
  }

  @override
  void dispose() {
    _nameController.dispose();
    _urlController.dispose();
    super.dispose();
  }

  void _handleSave() {
    if (_formKey.currentState!.validate()) {
      Navigator.of(context).pop({
        'name': _nameController.text.trim(),
        'url': _urlController.text.trim(),
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      child: Container(
        constraints: const BoxConstraints(maxWidth: 500),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.1),
              blurRadius: 20,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Titre
                const Text(
                  'Modifier la source',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF111827),
                  ),
                ),
                const Gap(24),
                
                // Champ Nom
                _FormField(
                  label: 'Nom de la source',
                  hint: 'Ex: Playlist Louange',
                  icon: Icons.label_outline,
                  controller: _nameController,
                  validator: (value) {
                    if (value == null || value.trim().isEmpty) {
                      return 'Le nom est obligatoire';
                    }
                    if (value.trim().length > 255) {
                      return 'Le nom ne doit pas dépasser 255 caractères';
                    }
                    return null;
                  },
                ),
                const Gap(16),
                
                // Champ URL
                _FormField(
                  label: 'URL du flux',
                  hint: 'https://...',
                  icon: Icons.link,
                  controller: _urlController,
                  validator: (value) {
                    if (value == null || value.trim().isEmpty) {
                      return 'L\'URL est obligatoire';
                    }
                    if (value.trim().length > 500) {
                      return 'L\'URL ne doit pas dépasser 500 caractères';
                    }
                    if (!Uri.tryParse(value.trim())!.hasScheme) {
                      return 'URL invalide';
                    }
                    return null;
                  },
                ),
                const Gap(32),
                
                // Boutons
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Navigator.of(context).pop(),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          side: const BorderSide(color: Color(0xFF6B7280)),
                        ),
                        child: const Text('Annuler'),
                      ),
                    ),
                    const Gap(12),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: _handleSave,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppTheme.blueColor,
                          foregroundColor: const Color(0xFF2F3B52),
                          padding: const EdgeInsets.symmetric(vertical: 12),
                        ),
                        child: const Text('Enregistrer'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
