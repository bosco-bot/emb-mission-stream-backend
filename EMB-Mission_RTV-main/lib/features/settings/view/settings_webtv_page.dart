import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:emb_mission_dashboard/core/services/webtv_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

class SettingsWebTvPage extends StatefulWidget {
  const SettingsWebTvPage({super.key});

  @override
  State<SettingsWebTvPage> createState() => _SettingsWebTvPageState();
}

class _SettingsWebTvPageState extends State<SettingsWebTvPage> {
  String _streamKey = '';
  String _rtmpServerUrl = '';
  String _publicStreamUrl = '';
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadWebTvSettings();
  }

  Future<void> _loadWebTvSettings() async {
    try {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });

      print('🔄 Chargement des paramètres WebTV...');
      
      // Charger seulement les paramètres OBS (l'API public-stream-url a des problèmes)
      final obsParams = await WebTvService.instance.getObsParams();

      print('✅ Paramètres OBS chargés: ${obsParams.streamKey}');

      if (mounted) {
        setState(() {
          _streamKey = obsParams.streamKey;
          _rtmpServerUrl = obsParams.serverUrl;
          _publicStreamUrl = 'https://tv.embmission.com/hls/streams/unified.m3u8'; // URL directe
          _isLoading = false;
        });
        print('✅ Interface mise à jour avec les nouvelles données');
      }
    } catch (e) {
      print('💥 Erreur lors du chargement des paramètres WebTV: $e');
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
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SettingsTopBar(currentTab: _SettingsTab.webtv),
          const Divider(height: 32),
          const Text(
            'Paramètres de flux',
            style: TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w800,
              color: Color(0xFF111827),
            ),
          ),
          const Gap(6),
          const Text(
            '''Utilisez ces informations pour configurer votre logiciel de streaming (OBS, etc.).''',
            style: TextStyle(color: Color(0xFF6B7280)),
          ),
          const Gap(16),
          
          // Contenu dynamique selon l'état de chargement
          if (_isLoading)
            const _Card(
              child: Center(
                child: Padding(
                  padding: EdgeInsets.all(40),
                  child: CircularProgressIndicator(),
                ),
              ),
            )
          else if (_errorMessage != null)
            _Card(
              child: Column(
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
                    onPressed: _loadWebTvSettings,
                    child: const Text('Réessayer'),
                  ),
                ],
              ),
            )
          else
            _Card(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _LabeledField(
                    label: 'Clé de flux',
                    value: _streamKey,
                    onCopy: () => _copyToClipboard(_streamKey, 'Clé de flux'),
                  ),
                  const Gap(12),
                  _LabeledField(
                    label: 'URL du serveur RTMP',
                    value: _rtmpServerUrl,
                    onCopy: () => _copyToClipboard(_rtmpServerUrl, 'URL du serveur RTMP'),
                  ),
                  const Gap(12),
                  _LabeledField(
                    label: 'URL du flux public (M3U8)',
                    value: _publicStreamUrl,
                    onCopy: () => _copyToClipboard(_publicStreamUrl, 'URL du flux public'),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class SettingsWebRadioPage extends StatelessWidget {
  const SettingsWebRadioPage({super.key});

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
              const _SettingsTopBar(currentTab: _SettingsTab.webradio),
              const Divider(height: 32),
              
              // Header avec titre, description et bouton info
              isMobile ? _MobileWebRadioHeader() : _DesktopWebRadioHeader(),
              
              const Gap(16),
              _Card(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _TwoFieldsRow(
                      left: _LabeledField(
                        label: 'Adresse du serveur',
                        value: 'stream.emb-mission-rtv.com',
                      ),
                      right: _LabeledField(
                        label: 'Point de montage',
                        value: '/live',
                      ),
                    ),
                    const Gap(12),
                    _TwoFieldsRow(
                      left: const _LabeledField(label: 'Port', value: '8000'),
                      right: const _LabeledField(
                        label: "Nom d'utilisateur",
                        value: 'source',
                      ),
                    ),
                    const Gap(12),
                    const _PasswordField(
                      label: 'Mot de passe (Clé de diffusion)',
                      value: '************',
                    ),
                    const Gap(12),
                    const _LabeledField(
                      label: 'URL du flux M3U (pour les auditeurs)',
                      value: 'https://stream.emb-mission-rtv.com/public/webradio.m3u',
                    ),
                    const Gap(8),
                    const Text(
                      'Partagez cette URL pour que votre audience puisse écouter votre radio sur des lecteurs comme VLC, iTunes, etc.',
                      style: TextStyle(
                        color: Color(0xFF6B7280),
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

enum _SettingsTab { webtv, webradio }

/// Header desktop pour WebRadio : titre, description et bouton info en ligne
class _DesktopWebRadioHeader extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Paramètres de Diffusion',
                style: TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF111827),
                ),
              ),
              Gap(6),
              Text(
                '''Utilisez ces informations pour configurer votre logiciel de streaming (ex: BUTT, AltaCast).''',
                style: TextStyle(color: Color(0xFF6B7280)),
              ),
            ],
          ),
        ),
        // Bouton Informations de connexion
        ElevatedButton.icon(
          onPressed: () {},
          icon: const Icon(Icons.info_outline, size: 16),
          label: const Text('Informations de connexion'),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppTheme.blueColor,
            foregroundColor: const Color(0xFF2F3B52),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(20),
            ),
          ),
        ),
      ],
    );
  }
}

/// Header mobile pour WebRadio : titre, description et bouton info empilés
class _MobileWebRadioHeader extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Paramètres de Diffusion',
          style: TextStyle(
            fontSize: 28,
            fontWeight: FontWeight.w800,
            color: Color(0xFF111827),
          ),
        ),
        Gap(6),
        Text(
          '''Utilisez ces informations pour configurer votre logiciel de streaming (ex: BUTT, AltaCast).''',
          style: TextStyle(color: Color(0xFF6B7280)),
        ),
        Gap(16),
        // Bouton Informations de connexion
        Align(
          alignment: Alignment.centerLeft,
          child: _InfoButton(),
        ),
      ],
    );
  }
}

/// Bouton Informations de connexion
class _InfoButton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return ElevatedButton.icon(
      onPressed: () {},
      icon: const Icon(Icons.info_outline, size: 16),
      label: const Text('Informations de connexion'),
      style: ElevatedButton.styleFrom(
        backgroundColor: AppTheme.blueColor,
        foregroundColor: const Color(0xFF2F3B52),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
        ),
      ),
    );
  }
}

/// Champ spécial pour le mot de passe avec icône œil et bouton générer
class _PasswordField extends StatefulWidget {
  const _PasswordField({
    required this.label,
    required this.value,
  });
  
  final String label;
  final String value;

  @override
  State<_PasswordField> createState() => _PasswordFieldState();
}

class _PasswordFieldState extends State<_PasswordField> {
  bool _obscureText = true;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(widget.label, style: const TextStyle(color: Color(0xFF6B7280))),
        const Gap(6),
        Row(
          children: [
            Expanded(
              child: TextField(
                readOnly: true,
                obscureText: _obscureText,
                controller: TextEditingController(text: widget.value),
                decoration: InputDecoration(
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                  isDense: true,
                ),
              ),
            ),
            const Gap(8),
            // Icône œil séparée
            ElevatedButton(
              onPressed: () {
                setState(() {
                  _obscureText = !_obscureText;
                });
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFF3F4F6),
                foregroundColor: const Color(0xFF374151),
                padding: const EdgeInsets.all(8),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
                elevation: 0,
                minimumSize: const Size(40, 40),
              ),
              child: Icon(
                _obscureText ? Icons.visibility : Icons.visibility_off,
                size: 20,
              ),
            ),
            const Gap(8),
            // Bouton copier séparé
            ElevatedButton(
              onPressed: () {},
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFF3F4F6),
                foregroundColor: const Color(0xFF374151),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
                elevation: 0,
              ),
              child: const Text('Copier'),
            ),
          ],
        ),
        const Gap(8),
        ElevatedButton.icon(
          onPressed: () {},
          icon: const Icon(Icons.refresh, size: 16),
          label: const Text('Générer une nouvelle clé'),
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF111827),
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
        ),
      ],
    );
  }
}

class _SettingsTopBar extends StatelessWidget {
  const _SettingsTopBar({required this.currentTab});
  final _SettingsTab currentTab;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Expanded(
          child: Text(
            'Paramètres',
            style: TextStyle(
              fontSize: 32,
              fontWeight: FontWeight.w800,
              color: Color(0xFF111827),
            ),
          ),
        ),
        const StartLiveButton(),
        const Gap(10),
        const UserProfileChip(),
        const Gap(16),
        // Tabs
        _TabButton(
          label: 'WebTV',
          selected: currentTab == _SettingsTab.webtv,
          onTap: () => context.go('/settings/webtv'),
        ),
        const Gap(8),
        _TabButton(
          label: 'WebRadio',
          selected: currentTab == _SettingsTab.webradio,
          onTap: () => context.go('/settings/webradio'),
        ),
      ],
    );
  }
}

class _TabButton extends StatelessWidget {
  const _TabButton({required this.label, this.selected = false, this.onTap});
  final String label;
  final bool selected;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(10),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFF111827) : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
          border: selected ? null : Border.all(color: const Color(0xFFE5E7EB)),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontWeight: FontWeight.w600,
            color: selected ? Colors.white : const Color(0xFF111827),
          ),
        ),
      ),
    );
  }
}

class _Card extends StatelessWidget {
  const _Card({required this.child});
  final Widget child;
  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
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
      child: child,
    );
  }
}

class _LabeledField extends StatelessWidget {
  const _LabeledField({
    required this.label,
    required this.value,
    this.obfuscated = false,
    this.onCopy,
  });
  final String label;
  final String value;
  final bool obfuscated;
  final VoidCallback? onCopy;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(color: Color(0xFF6B7280))),
        const Gap(6),
        Row(
          children: [
            Expanded(
              child: TextField(
                readOnly: true,
                obscureText: obfuscated,
                controller: TextEditingController(text: value),
                decoration: InputDecoration(
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                  isDense: true,
                ),
              ),
            ),
            const Gap(8),
            ElevatedButton(
              onPressed: onCopy,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFF3F4F6),
                foregroundColor: const Color(0xFF374151),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
                elevation: 0,
              ),
              child: const Text('Copier'),
            ),
          ],
        ),
      ],
    );
  }
}

class _TwoFieldsRow extends StatelessWidget {
  const _TwoFieldsRow({required this.left, required this.right});
  final Widget left;
  final Widget right;
  @override
  Widget build(BuildContext context) {
    final isMobile = MediaQuery.of(context).size.width < 768;
    
    if (isMobile) {
      // Sur mobile : champs empilés verticalement
      return Column(
        children: [
          left,
          const Gap(12),
          right,
        ],
      );
    }
    
    // Sur desktop : champs côte à côte
    return Row(
      children: [
        Expanded(child: left),
        const SizedBox(width: 12),
        Expanded(child: right),
      ],
    );
  }
}
