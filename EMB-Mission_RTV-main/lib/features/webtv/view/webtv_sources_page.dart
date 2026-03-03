import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/emb_logo.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:emb_mission_dashboard/core/services/webtv_service.dart';
import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:emb_mission_dashboard/core/models/webtv_models.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

/// Page de gestion des sources vidéo WebTV
/// 
/// Interface pour afficher les paramètres de connexion OBS Studio
class WebtvSourcesPage extends StatefulWidget {
  const WebtvSourcesPage({super.key});

  @override
  State<WebtvSourcesPage> createState() => _WebtvSourcesPageState();
}

class _WebtvSourcesPageState extends State<WebtvSourcesPage> {
  WebTvObsParams? _obsParams;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadObsParams();
  }

  @override
  void dispose() {
    super.dispose();
  }

  Future<void> _loadObsParams() async {
    try {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });

      final obsParams = await WebTvService.instance.getObsParams();
      
      if (mounted) {
        setState(() {
          _obsParams = obsParams;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = e.toString();
          _isLoading = false;
        });
      }
    }
  }

  void _copyToClipboard(String text, String label) {
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
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        final isTablet = constraints.maxWidth >= 768 && constraints.maxWidth < 1024;
        
        return Column(
          children: [
            // Header avec titre et actions
            _Header(isMobile: isMobile),
            
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
                              const Gap(16),
                              ElevatedButton(
                                onPressed: _loadObsParams,
                                child: const Text('Réessayer'),
                              ),
                            ],
                          ),
                        )
                      : SingleChildScrollView(
                          padding: EdgeInsets.symmetric(
                            horizontal: isMobile ? 16 : 24,
                            vertical: isMobile ? 16 : 20,
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              // Paramètres de connexion OBS
                              if (_obsParams != null)
                                _ObsConnectionCard(
                                  obsParams: _obsParams!,
                                  isMobile: isMobile,
                                  onCopy: _copyToClipboard,
                                ),
                            ],
                          ),
                        ),
            ),
            
            // Bottom bar
            _BottomBar(isMobile: isMobile),
          ],
        );
      },
    );
  }
}

/// Header avec titre et actions
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
              color: Colors.black.withOpacity(0.1),
              blurRadius: 4,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          children: [
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
                const Spacer(),
              ],
            ),
            const Gap(12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const UserProfileChip(),
                ElevatedButton.icon(
                  onPressed: () => context.go('/webtv/diffusionlive'),
                  icon: const Icon(Icons.live_tv, size: 18),
                  label: const Text('Démarrer Diffusion Live'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.redColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
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
            color: Colors.black.withOpacity(0.1),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          const EmbLogo(size: 40),
          const Gap(12),
          const Text(
            'EMB-MISSION',
            style: TextStyle(
              fontWeight: FontWeight.w900,
              fontSize: 20,
              color: Color(0xFF0F172A),
            ),
          ),
          const Spacer(),
          const UserProfileChip(),
          const Gap(16),
          ElevatedButton.icon(
            onPressed: () => context.go('/webtv/diffusionlive'),
            icon: const Icon(Icons.live_tv),
            label: const Text('Démarrer Diffusion Live'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.redColor,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
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

/// Bottom bar avec actions
class _BottomBar extends StatelessWidget {
  const _BottomBar({required this.isMobile});
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.1),
            blurRadius: 4,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: isMobile
          ? Column(
              children: [
                const Text(
                  'Paramètres de Connexion OBS',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF111827),
                  ),
                ),
                const Gap(12),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: () => context.go('/webtv/diffusionlive'),
                    icon: const Icon(Icons.live_tv),
                    label: const Text('Démarrer Diffusion Live'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.redColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
              ],
            )
          : Row(
              children: [
                const Text(
                  'Paramètres de Connexion OBS',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF111827),
                  ),
                ),
                const Spacer(),
                ElevatedButton.icon(
                  onPressed: () => context.go('/webtv/diffusionlive'),
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

/// Card affichant les paramètres de connexion OBS
class _ObsConnectionCard extends StatelessWidget {
  const _ObsConnectionCard({
    required this.obsParams,
    required this.isMobile,
    required this.onCopy,
  });

  final WebTvObsParams obsParams;
  final bool isMobile;
  final Function(String, String) onCopy;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 4,
      color: Colors.white,
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Titre et icône
            Row(
              children: [
                Icon(
                  Icons.videocam,
                  color: AppTheme.redColor,
                  size: 32,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Paramètres de connexion OBS Studio',
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF0F172A),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Configuration pour la diffusion en direct',
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
            
            // Informations de connexion RTMP
            Text(
              'Paramètres de connexion RTMP',
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
                  label: 'URL du serveur',
                  value: obsParams.serverUrl,
                  icon: Icons.dns,
                  onCopy: () => onCopy(obsParams.serverUrl, 'URL du serveur'),
                ),
                _InfoField(
                  label: 'Clé de diffusion',
                  value: obsParams.streamKey,
                  icon: Icons.key,
                  onCopy: () => onCopy(obsParams.streamKey, 'Clé de diffusion'),
                ),
                _InfoField(
                  label: 'Nom d\'utilisateur',
                  value: obsParams.username,
                  icon: Icons.person,
                  onCopy: () => onCopy(obsParams.username, 'Nom d\'utilisateur'),
                ),
                _InfoField(
                  label: 'Mot de passe',
                  value: obsParams.password,
                  icon: Icons.lock,
                  isPassword: true,
                  onCopy: () => onCopy(obsParams.password, 'Mot de passe'),
                ),
                _InfoField(
                  label: 'Port',
                  value: obsParams.port.toString(),
                  icon: Icons.numbers,
                  onCopy: () => onCopy(obsParams.port.toString(), 'Port'),
                ),
                _InfoField(
                  label: 'Application',
                  value: obsParams.application,
                  icon: Icons.apps,
                  onCopy: () => onCopy(obsParams.application, 'Application'),
                ),
              ],
            ),
            
            const Gap(24),
            const Divider(),
            const Gap(24),
            
            // Informations techniques recommandées
            Text(
              'Paramètres techniques recommandés',
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
                  label: 'Bitrate',
                  value: obsParams.bitrateRecommended,
                  icon: Icons.speed,
                ),
                _InfoField(
                  label: 'Résolution',
                  value: obsParams.resolutionRecommended,
                  icon: Icons.aspect_ratio,
                ),
                _InfoField(
                  label: 'FPS',
                  value: obsParams.fpsRecommended,
                  icon: Icons.video_settings,
                ),
                _InfoField(
                  label: 'Encodeur',
                  value: obsParams.encoderRecommended,
                  icon: Icons.settings,
                ),
                _InfoField(
                  label: 'Codec vidéo',
                  value: obsParams.videoCodec,
                  icon: Icons.videocam,
                ),
                _InfoField(
                  label: 'Codec audio',
                  value: obsParams.audioCodec,
                  icon: Icons.audiotrack,
                ),
              ],
            ),
            
            const Gap(24),
            const Divider(),
            const Gap(24),
            
            // Instructions de configuration
            Text(
              'Instructions de configuration OBS Studio',
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
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: obsParams.instructions.map((instruction) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Text(
                      instruction,
                      style: const TextStyle(
                        fontSize: 14,
                        color: Color(0xFF374151),
                      ),
                    ),
                  );
                }).toList(),
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
              Icon(icon, color: AppTheme.redColor, size: 18),
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