import 'dart:async';

import 'package:emb_mission_dashboard/core/models/webradio_models.dart';
import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:emb_mission_dashboard/core/services/webradio_service.dart';
import 'package:emb_mission_dashboard/core/services/webtv_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

class SettingsPage extends StatefulWidget {
  const SettingsPage({super.key});

  @override
  State<SettingsPage> createState() => _SettingsPageState();
}

class _SettingsPageState extends State<SettingsPage> with SingleTickerProviderStateMixin {
  String _streamKey = '';
  String _rtmpServerUrl = '';
  String _publicStreamUrl = '';
  bool _isLoadingWebTv = true;
  String? _errorMessageWebTv;
  bool? _isWebTvLive;
  Timer? _webTvStatusTimer;
  late TabController _tabController;
  bool _tabControllerInitialized = false;

  @override
  void initState() {
    super.initState();
    _loadWebTvSettings();
    // Initialiser avec l'index par défaut, sera mis à jour dans didChangeDependencies
    _tabController = TabController(length: 4, vsync: this, initialIndex: 0);
    _scheduleWebTvStatusRefresh();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Lire le paramètre tab depuis l'URL pour déterminer l'onglet initial (une seule fois)
    if (!_tabControllerInitialized) {
      final uri = GoRouterState.of(context).uri;
      final tabParam = uri.queryParameters['tab'];
      int initialIndex = 0; // Par défaut, WebTV
      
      if (tabParam == 'profile' || tabParam == 'profil') {
        initialIndex = 2; // Onglet Profil
      } else if (tabParam == 'webtv') {
        initialIndex = 0; // Onglet WebTV
      } else if (tabParam == 'webradio' || tabParam == 'radio') {
        initialIndex = 1; // Onglet WebRadio
      } else if (tabParam == 'billing' || tabParam == 'facturation') {
        initialIndex = 3; // Onglet Facturation
      }
      
      if (initialIndex != _tabController.index) {
        _tabController.animateTo(initialIndex);
      }
      _tabControllerInitialized = true;
    }
  }

  @override
  void dispose() {
    _webTvStatusTimer?.cancel();
    _tabController.dispose();
    super.dispose();
  }

  void _scheduleWebTvStatusRefresh() {
    _webTvStatusTimer?.cancel();
    _webTvStatusTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      _refreshWebTvStatus();
    });
  }

  Future<void> _refreshWebTvStatus() async {
    try {
      final status = await WebTvService.instance.getAutoPlaylistStatus();
      final isLive = status['is_live'] as bool? ?? false;
      if (!mounted) return;
      setState(() {
        _isWebTvLive = isLive;
      });
    } catch (_) {
      try {
        final currentStream = await WebTvService.instance.getCurrentStream();
        if (!mounted) return;
        setState(() {
          _isWebTvLive = currentStream.isLive;
        });
      } catch (_) {
        if (!mounted) return;
        setState(() {
          _isWebTvLive = false;
        });
      }
    }
  }

  Future<void> _loadWebTvSettings() async {
    try {
      setState(() {
        _isLoadingWebTv = true;
        _errorMessageWebTv = null;
        _isWebTvLive = null;
      });

      print('🔄 Chargement des paramètres WebTV...');
      
      // Charger seulement les paramètres OBS (l'API public-stream-url a des problèmes)
      final obsParams = await WebTvService.instance.getObsParams();

      print('✅ Paramètres OBS chargés: ${obsParams.streamKey}');

      bool? liveStatus;
      try {
        final status = await WebTvService.instance.getAutoPlaylistStatus();
        liveStatus = status['is_live'] as bool? ?? false;
      } catch (_) {
        try {
          final currentStream = await WebTvService.instance.getCurrentStream();
          liveStatus = currentStream.isLive;
        } catch (_) {
          liveStatus = false;
        }
      }

      if (mounted) {
        setState(() {
          _streamKey = obsParams.streamKey;
          _rtmpServerUrl = obsParams.serverUrl;
          _publicStreamUrl = 'https://tv.embmission.com/hls/streams/unified.m3u8'; // URL directe
          _isWebTvLive = liveStatus ?? false;
          _isLoadingWebTv = false;
        });
        print('✅ Interface mise à jour avec les nouvelles données');
      }
    } catch (e) {
      print('💥 Erreur lors du chargement des paramètres WebTV: $e');
      if (mounted) {
        setState(() {
          _errorMessageWebTv = e.toString();
          _isLoadingWebTv = false;
          _isWebTvLive = false;
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
        
        return Padding(
          padding: EdgeInsets.symmetric(
            horizontal: isMobile ? 16 : 24, 
            vertical: 16,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Top header responsive
              isMobile ? _MobileHeader() : _DesktopHeader(),
              const Divider(height: 32),
              _SettingsTabs(controller: _tabController),
              Expanded(
                child: _SettingsTabViews(
                  controller: _tabController,
                  streamKey: _streamKey,
                  rtmpServerUrl: _rtmpServerUrl,
                  publicStreamUrl: _publicStreamUrl,
                  isLoadingWebTv: _isLoadingWebTv,
                  errorMessageWebTv: _errorMessageWebTv,
                  onRetryWebTv: _loadWebTvSettings,
                  onCopyWebTv: _copyToClipboard,
                  isWebTvLive: _isWebTvLive,
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Header desktop : éléments en ligne
class _DesktopHeader extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Expanded(
          child: _HeaderTitle(),
        ),
        ElevatedButton.icon(
          onPressed: () => context.go('/settings/stream-config'),
          icon: const Icon(Icons.settings_input_component),
          label: const Text('Configuration des Flux'),
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF3B82F6),
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          ),
        ),
        const Gap(10),
        const StartLiveButton(),
        const Gap(10),
        const UserProfileChip(),
      ],
    );
  }
}

/// Header mobile : éléments empilés
class _MobileHeader extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _HeaderTitle(),
        const Gap(16),
        ElevatedButton.icon(
          onPressed: () => context.go('/settings/stream-config'),
          icon: const Icon(Icons.settings_input_component),
          label: const Text('Configuration des Flux'),
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF3B82F6),
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          ),
        ),
        const Gap(16),
        Row(
          children: [
            const StartLiveButton(),
            const Spacer(),
            const UserProfileChip(),
          ],
        ),
      ],
    );
  }
}

class _HeaderTitle extends StatelessWidget {
  const _HeaderTitle();
  @override
  Widget build(BuildContext context) {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Paramètres',
          style: TextStyle(
            fontSize: 32,
            fontWeight: FontWeight.w800,
            color: Color(0xFF111827),
          ),
        ),
        Gap(6),
        Text(
          'Gérez vos clés de flux et configurations.',
          style: TextStyle(color: Color(0xFF6B7280)),
        ),
      ],
    );
  }
}


class _SettingsTabs extends StatelessWidget {
  const _SettingsTabs({required this.controller});
  
  final TabController controller;
  
  @override
  Widget build(BuildContext context) {
    final isMobile = MediaQuery.of(context).size.width < 768;
    
    return TabBar(
      controller: controller,
      isScrollable: isMobile, // Scrollable sur mobile seulement
      labelColor: const Color(0xFF111827),
      unselectedLabelColor: const Color(0xFF6B7280),
      indicatorColor: const Color(0xFF111827),
      labelStyle: TextStyle(
        fontSize: isMobile ? 14 : 16,
        fontWeight: FontWeight.w600,
      ),
      tabs: const [
        Tab(text: 'WebTV'),
        Tab(text: 'WebRadio'),
        Tab(text: 'Profil'),
        Tab(text: 'Facturation'),
      ],
    );
  }
}

class _SettingsTabViews extends StatelessWidget {
  const _SettingsTabViews({
    required this.controller,
    required this.streamKey,
    required this.rtmpServerUrl,
    required this.publicStreamUrl,
    required this.isLoadingWebTv,
    required this.errorMessageWebTv,
    required this.onRetryWebTv,
    required this.onCopyWebTv,
    required this.isWebTvLive,
  });

  final TabController controller;
  final String streamKey;
  final String rtmpServerUrl;
  final String publicStreamUrl;
  final bool isLoadingWebTv;
  final String? errorMessageWebTv;
  final VoidCallback onRetryWebTv;
  final Function(String, String) onCopyWebTv;
  final bool? isWebTvLive;

  @override
  Widget build(BuildContext context) {
    return TabBarView(
      controller: controller,
      children: [
        _WebTvTab(
          streamKey: streamKey,
          rtmpServerUrl: rtmpServerUrl,
          publicStreamUrl: publicStreamUrl,
          isLoading: isLoadingWebTv,
          errorMessage: errorMessageWebTv,
          onRetry: onRetryWebTv,
          onCopy: onCopyWebTv,
          isLive: isWebTvLive,
        ),
        const _WebRadioTab(),
        const _ProfileTab(),
        const _BillingTab(),
      ],
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
    this.obscure = false,
    this.onCopy,
  });
  final String label;
  final String value;
  final bool obscure;
  final VoidCallback? onCopy;
  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(color: Color(0xFF6B7280))),
        const Gap(6),
        LayoutBuilder(
          builder: (context, constraints) {
            final isMobile = constraints.maxWidth < 768;
            
            if (isMobile) {
              // Mode mobile : éléments empilés
              return Column(
                children: [
                  TextField(
                    readOnly: true,
                    obscureText: obscure,
                    controller: TextEditingController(text: value),
                    decoration: InputDecoration(
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                      isDense: true,
                    ),
                  ),
                  const Gap(8),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
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
                  ),
                ],
              );
            }
            
            // Mode desktop : éléments en ligne
            return Row(
          children: [
            Expanded(
              child: TextField(
                readOnly: true,
                obscureText: obscure,
                controller: TextEditingController(text: value),
                decoration: InputDecoration(
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                  isDense: true,
                ),
              ),
            ),
            const Gap(8),
            ElevatedButton(
              onPressed: () async {
                await Clipboard.setData(ClipboardData(text: value));
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Row(
                        children: [
                          const Icon(Icons.check_circle, color: Colors.white, size: 20),
                          const Gap(8),
                          Expanded(
                            child: Text('$label copié dans le presse-papiers'),
                          ),
                        ],
                      ),
                      backgroundColor: const Color(0xFF059669),
                      duration: const Duration(seconds: 2),
                    ),
                  );
                }
              },
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
            );
          },
        ),
      ],
    );
  }
}

/// Champ spécial pour la clé de flux avec icône œil et bouton générer
class _StreamKeyField extends StatefulWidget {
  const _StreamKeyField({
    required this.label,
    required this.value,
    this.onCopy,
  });
  
  final String label;
  final String value;
  final VoidCallback? onCopy;

  @override
  State<_StreamKeyField> createState() => _StreamKeyFieldState();
}

class _StreamKeyFieldState extends State<_StreamKeyField> {
  bool _obscureText = true;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(widget.label, style: const TextStyle(color: Color(0xFF6B7280))),
        const Gap(6),
        LayoutBuilder(
          builder: (context, constraints) {
            final isMobile = constraints.maxWidth < 768;
            
            if (isMobile) {
              // Mode mobile : éléments empilés
              return Column(
                children: [
                  TextField(
                    readOnly: true,
                    obscureText: _obscureText,
                    controller: TextEditingController(text: widget.value),
                    decoration: InputDecoration(
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                      isDense: true,
                    ),
                  ),
                  const Gap(8),
                  Row(
                    children: [
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
                      ElevatedButton(
                        onPressed: widget.onCopy,
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
            
            // Mode desktop : éléments en ligne
            return Row(
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
              onPressed: () async {
                await Clipboard.setData(ClipboardData(text: widget.value));
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Row(
                        children: [
                          const Icon(Icons.check_circle, color: Colors.white, size: 20),
                          const Gap(8),
                          Expanded(
                            child: Text('${widget.label} copié dans le presse-papiers'),
                          ),
                        ],
                      ),
                      backgroundColor: const Color(0xFF059669),
                      duration: const Duration(seconds: 2),
                    ),
                  );
                }
              },
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
            );
          },
        ),
        const Gap(8),
        LayoutBuilder(
          builder: (context, constraints) {
            final isMobile = constraints.maxWidth < 768;
            
            return SizedBox(
              width: isMobile ? double.infinity : null,
              child: ElevatedButton.icon(
          onPressed: () {},
          icon: const Icon(Icons.refresh, size: 16),
                label: Text(isMobile ? 'Générer clé' : 'Générer une nouvelle clé'),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppTheme.blueColor,
            foregroundColor: const Color(0xFF2F3B52),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
              ),
            );
          },
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

class _WebTvTab extends StatelessWidget {
  const _WebTvTab({
    required this.streamKey,
    required this.rtmpServerUrl,
    required this.publicStreamUrl,
    required this.isLoading,
    required this.errorMessage,
    required this.onRetry,
    required this.onCopy,
    required this.isLive,
  });

  final String streamKey;
  final String rtmpServerUrl;
  final String publicStreamUrl;
  final bool isLoading;
  final String? errorMessage;
  final VoidCallback onRetry;
  final Function(String, String) onCopy;
  final bool? isLive;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Gap(16),
          _Card(
            child: _WebTvForm(
              streamKey: streamKey,
              rtmpServerUrl: rtmpServerUrl,
              publicStreamUrl: publicStreamUrl,
              isLoading: isLoading,
              errorMessage: errorMessage,
              onRetry: onRetry,
              onCopy: onCopy,
              isLive: isLive,
            ),
          ),
        ],
      ),
    );
  }
}

class _WebTvForm extends StatelessWidget {
  const _WebTvForm({
    required this.streamKey,
    required this.rtmpServerUrl,
    required this.publicStreamUrl,
    required this.isLoading,
    required this.errorMessage,
    required this.onRetry,
    required this.onCopy,
    required this.isLive,
  });

  final String streamKey;
  final String rtmpServerUrl;
  final String publicStreamUrl;
  final bool isLoading;
  final String? errorMessage;
  final VoidCallback onRetry;
  final Function(String, String) onCopy;
  final bool? isLive;

  @override
  Widget build(BuildContext context) {
    final isMobile = MediaQuery.of(context).size.width < 768;
    
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        isMobile 
          ? _MobileWebTvHeader(isLive: isLive)
          : _DesktopWebTvHeader(isLive: isLive),
        const Gap(16),
        
        // Contenu dynamique selon l'état de chargement
        if (isLoading)
          const Center(
            child: Padding(
              padding: EdgeInsets.all(40),
              child: CircularProgressIndicator(),
            ),
          )
        else if (errorMessage != null)
          Column(
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const Gap(16),
              Text(
                'Erreur: $errorMessage',
                style: const TextStyle(color: Colors.red),
                textAlign: TextAlign.center,
              ),
              const Gap(16),
              ElevatedButton(
                onPressed: onRetry,
                child: const Text('Réessayer'),
              ),
            ],
          )
        else ...[
          _StreamKeyField(
            label: 'Clé de flux',
            value: streamKey,
            onCopy: () => onCopy(streamKey, 'Clé de flux'),
          ),
          const Gap(12),
          _LabeledField(
            label: 'URL du serveur RTMP',
            value: rtmpServerUrl,
            onCopy: () => onCopy(rtmpServerUrl, 'URL du serveur RTMP'),
          ),
          const Gap(12),
          _LabeledField(
            label: 'URL du flux public (M3U8)',
            value: publicStreamUrl,
            onCopy: () => onCopy(publicStreamUrl, 'URL du flux public'),
          ),
        ],
        const Gap(8),
        const Text(
          'Utilisez ce lien pour intégrer votre flux sur un site web ou une application compatible.',
          style: TextStyle(
            color: Color(0xFF6B7280),
            fontSize: 12,
          ),
        ),
      ],
    );
  }
}

/// Header desktop pour WebTV : titre et badge en ligne
class _DesktopWebTvHeader extends StatelessWidget {
  const _DesktopWebTvHeader({required this.isLive});

  final bool? isLive;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Paramètres de flux',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                ),
              ),
              Gap(6),
              Text(
                '''Utilisez ces informations pour configurer votre logiciel de streaming (OBS, etc.).''',
                style: TextStyle(color: Color(0xFF6B7280)),
              ),
            ],
          ),
        ),
        _StatusBadge(isLive: isLive),
      ],
    );
  }
}

/// Header mobile pour WebTV : titre et badge empilés
class _MobileWebTvHeader extends StatelessWidget {
  const _MobileWebTvHeader({required this.isLive});

  final bool? isLive;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Paramètres de flux',
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.w700,
          ),
        ),
        const Gap(6),
        const Text(
          '''Utilisez ces informations pour configurer votre logiciel de streaming (OBS, etc.).''',
          style: TextStyle(color: Color(0xFF6B7280)),
        ),
        const Gap(12),
        Align(
          alignment: Alignment.centerLeft,
          child: _StatusBadge(isLive: isLive),
        ),
      ],
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.isLive});

  final bool? isLive;

  @override
  Widget build(BuildContext context) {
    final live = isLive ?? false;
    final color = live ? AppTheme.redColor : const Color(0xFF6B7280);
    final label = live ? 'LIVE' : 'HORS LIGNE';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w700,
          fontSize: 12,
        ),
      ),
    );
  }
}

class _WebRadioTab extends StatelessWidget {
  const _WebRadioTab();
  @override
  Widget build(BuildContext context) {
    return const SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Gap(16),
          _Card(
            child: _WebRadioForm(),
          ),
        ],
      ),
    );
  }
}

class _WebRadioForm extends StatefulWidget {
  const _WebRadioForm();
  
  @override
  State<_WebRadioForm> createState() => _WebRadioFormState();
}

class _WebRadioFormState extends State<_WebRadioForm> {
  StreamingSettings? _settings;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadStreamingSettings();
  }

  Future<void> _loadStreamingSettings() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final settings = await WebRadioService.instance.getStreamingSettings();
      
      if (mounted) {
        setState(() {
          _settings = settings;
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

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(32),
          child: CircularProgressIndicator(),
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
              'Erreur de chargement',
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w600,
              ),
            ),
            const Gap(8),
            Text(
              _errorMessage!,
              style: const TextStyle(color: Color(0xFF6B7280)),
              textAlign: TextAlign.center,
            ),
            const Gap(16),
            ElevatedButton.icon(
              onPressed: _loadStreamingSettings,
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

    if (_settings == null) {
      return const Center(
        child: Text('Aucune donnée disponible'),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Header avec titre, description et bouton info
        Row(
          children: [
            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Paramètres de Diffusion',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w700,
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
        ),
        const Gap(16),
        
        // Champs de configuration (données dynamiques)
        _TwoFieldsRow(
          left: _LabeledField(
            label: 'Adresse du serveur',
            value: _settings!.serverAddress,
          ),
          right: _LabeledField(
            label: 'Point de montage',
            value: _settings!.mountPoint,
          ),
        ),
        const Gap(12),
        _TwoFieldsRow(
          left: _LabeledField(
            label: 'Port',
            value: _settings!.portFormatted,
          ),
          right: _LabeledField(
            label: "Nom d'utilisateur",
            value: _settings!.username,
          ),
        ),
        const Gap(12),
        _WebRadioPasswordField(
          label: 'Mot de passe (Clé de diffusion)',
          value: _settings!.password,
        ),
        const Gap(12),
        _LabeledField(
          label: 'URL du flux M3U (pour les auditeurs)',
          value: _settings!.m3uUrl,
        ),
        const Gap(8),
        const Text(
          'Partagez cette URL pour que votre audience puisse écouter votre radio sur des lecteurs comme VLC, iTunes, etc.',
          style: TextStyle(
            color: Color(0xFF6B7280),
            fontSize: 12,
          ),
        ),
        const Gap(16),
        // Informations supplémentaires dynamiques
        Wrap(
          spacing: 16,
          runSpacing: 8,
          children: [
            _InfoChip(
              icon: Icons.radio,
              label: 'Station',
              value: _settings!.stationName,
            ),
            _InfoChip(
              icon: Icons.speed,
              label: 'Bitrate',
              value: _settings!.bitrateFormatted,
            ),
            _InfoChip(
              icon: Icons.audiotrack,
              label: 'Format',
              value: _settings!.format.toUpperCase(),
            ),
            _InfoChip(
              icon: Icons.dns,
              label: 'Frontend',
              value: _settings!.frontend,
            ),
            _InfoChip(
              icon: Icons.settings_input_component,
              label: 'Backend',
              value: _settings!.backend,
            ),
          ],
        ),
      ],
    );
  }
}

/// Chip d'information pour afficher les métadonnées
class _InfoChip extends StatelessWidget {
  const _InfoChip({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFF3F4F6),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: const Color(0xFF6B7280)),
          const Gap(6),
          Text(
            '$label: ',
            style: const TextStyle(
              fontSize: 12,
              color: Color(0xFF6B7280),
              fontWeight: FontWeight.w500,
            ),
          ),
          Text(
            value,
            style: const TextStyle(
              fontSize: 12,
              color: Color(0xFF111827),
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

/// Champ spécial pour le mot de passe WebRadio avec icône œil et bouton générer
class _WebRadioPasswordField extends StatefulWidget {
  const _WebRadioPasswordField({
    required this.label,
    required this.value,
  });
  
  final String label;
  final String value;

  @override
  State<_WebRadioPasswordField> createState() => _WebRadioPasswordFieldState();
}

class _WebRadioPasswordFieldState extends State<_WebRadioPasswordField> {
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
              onPressed: () async {
                await Clipboard.setData(ClipboardData(text: widget.value));
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Row(
                        children: [
                          const Icon(Icons.check_circle, color: Colors.white, size: 20),
                          const Gap(8),
                          Expanded(
                            child: Text('${widget.label} copié dans le presse-papiers'),
                          ),
                        ],
                      ),
                      backgroundColor: const Color(0xFF059669),
                      duration: const Duration(seconds: 2),
                    ),
                  );
                }
              },
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

class _ProfileTab extends StatefulWidget {
  const _ProfileTab();

  @override
  State<_ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<_ProfileTab> {
  bool _isLoading = false;
  Map<String, dynamic>? _userData;

  @override
  void initState() {
    super.initState();
    _loadUserData();
  }

  Future<void> _loadUserData() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final userService = UserService.instance;
      await userService.refreshUserData();
      
      if (mounted) {
        setState(() {
          _userData = userService.currentUser;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final isMobile = MediaQuery.of(context).size.width < 768;

    if (_isLoading) {
      return const Center(
        child: CircularProgressIndicator(),
      );
    }

    if (_userData == null) {
      return const Center(
        child: Text('Impossible de charger les données du profil'),
      );
    }

    return SingleChildScrollView(
      padding: EdgeInsets.all(isMobile ? 16 : 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // En-tête
          Text(
            'Mon Profil',
            style: TextStyle(
              fontSize: isMobile ? 24 : 28,
              fontWeight: FontWeight.bold,
              color: const Color(0xFF111827),
            ),
          ),
          const Gap(8),
          Text(
            'Gérez vos informations personnelles',
            style: TextStyle(
              fontSize: isMobile ? 14 : 16,
              color: const Color(0xFF6B7280),
            ),
          ),
          const Gap(32),

          // Carte Informations personnelles
          _ProfileInfoCard(
            userData: _userData!,
            isMobile: isMobile,
          ),
          const Gap(24),

          // Carte Statistiques du compte
          _AccountStatsCard(
            userData: _userData!,
            isMobile: isMobile,
          ),
        ],
      ),
    );
  }
}

class _ProfileInfoCard extends StatelessWidget {
  const _ProfileInfoCard({
    required this.userData,
    required this.isMobile,
  });

  final Map<String, dynamic> userData;
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(isMobile ? 16 : 24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.person_outline, color: Color(0xFF6B7280)),
              const Gap(8),
              Text(
                'Informations personnelles',
                style: TextStyle(
                  fontSize: isMobile ? 16 : 18,
                  fontWeight: FontWeight.w600,
                  color: const Color(0xFF111827),
                ),
              ),
            ],
          ),
          const Gap(24),
          
          // Avatar
          Center(
            child: Column(
              children: [
                CircleAvatar(
                  radius: isMobile ? 40 : 50,
                  backgroundImage: NetworkImage(
                    userData['avatar'] ?? 'https://ui-avatars.com/api/?name=${Uri.encodeComponent(userData['name'] ?? 'User')}&background=0F172A&color=fff&size=128',
                  ),
                ),
                const Gap(12),
                Text(
                  userData['name'] ?? 'Utilisateur',
                  style: TextStyle(
                    fontSize: isMobile ? 20 : 24,
                    fontWeight: FontWeight.bold,
                    color: const Color(0xFF111827),
                  ),
                ),
                const Gap(4),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppTheme.blueColor.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    _getRoleLabel(userData['role']),
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: AppTheme.blueColor,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const Gap(32),

          // Informations détaillées
          _InfoRow(
            icon: Icons.email_outlined,
            label: 'Email',
            value: userData['email'] ?? 'Non renseigné',
            isMobile: isMobile,
          ),
          const Gap(16),
          _InfoRow(
            icon: Icons.badge_outlined,
            label: 'Nom complet',
            value: userData['name'] ?? 'Non renseigné',
            isMobile: isMobile,
          ),
          const Gap(16),
          _InfoRow(
            icon: Icons.info_outline,
            label: 'Statut du compte',
            value: userData['is_active'] == true || userData['is_active'] == 1
                ? 'Actif'
                : 'Inactif',
            valueColor: userData['is_active'] == true || userData['is_active'] == 1
                ? Colors.green
                : Colors.orange,
            isMobile: isMobile,
          ),
        ],
      ),
    );
  }

  String _getRoleLabel(dynamic role) {
    switch (role) {
      case 'admin':
        return 'Administrateur';
      case 'moderator':
        return 'Modérateur';
      case 'user':
        return 'Utilisateur';
      default:
        return 'Utilisateur';
    }
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
    this.valueColor,
    required this.isMobile,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color? valueColor;
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 20, color: const Color(0xFF6B7280)),
        const Gap(12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  color: const Color(0xFF6B7280),
                ),
              ),
              const Gap(4),
              Text(
                value,
                style: TextStyle(
                  fontSize: isMobile ? 14 : 16,
                  fontWeight: FontWeight.w500,
                  color: valueColor ?? const Color(0xFF111827),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _AccountStatsCard extends StatelessWidget {
  const _AccountStatsCard({
    required this.userData,
    required this.isMobile,
  });

  final Map<String, dynamic> userData;
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    final createdAt = userData['created_at'];
    final updatedAt = userData['updated_at'];

    return Container(
      padding: EdgeInsets.all(isMobile ? 16 : 24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.info_outline, color: Color(0xFF6B7280)),
              const Gap(8),
              Text(
                'Informations du compte',
                style: TextStyle(
                  fontSize: isMobile ? 16 : 18,
                  fontWeight: FontWeight.w600,
                  color: const Color(0xFF111827),
                ),
              ),
            ],
          ),
          const Gap(24),
          
          if (createdAt != null)
            _InfoRow(
              icon: Icons.calendar_today_outlined,
              label: 'Compte créé le',
              value: _formatDate(createdAt),
              isMobile: isMobile,
            ),
          if (createdAt != null && updatedAt != null) const Gap(16),
          if (updatedAt != null)
            _InfoRow(
              icon: Icons.update_outlined,
              label: 'Dernière mise à jour',
              value: _formatDate(updatedAt),
              isMobile: isMobile,
            ),
        ],
      ),
    );
  }

  String _formatDate(dynamic date) {
    if (date == null) return 'Non renseigné';
    
    try {
      final dateTime = DateTime.parse(date.toString());
      return '${dateTime.day}/${dateTime.month}/${dateTime.year}';
    } catch (e) {
      return date.toString();
    }
  }
}

class _BillingTab extends StatelessWidget {
  const _BillingTab();
  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Text('Facturation — à implémenter'),
    );
  }
}
