import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:gap/gap.dart';

/// Page de configuration des flux
/// 
/// Interface pour configurer les flux d'intégration WebTV et WebRadio
/// conforme à la maquette fournie.
class StreamConfigPage extends StatelessWidget {
  const StreamConfigPage({super.key});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        
        return DefaultTabController(
          length: 2,
          child: Padding(
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
                _StreamConfigTabs(),
                Expanded(
                  child: _StreamConfigTabViews(),
                ),
              ],
            ),
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
    return const Row(
      children: [
        Expanded(
          child: _HeaderTitle(),
        ),
        StartLiveButton(),
      ],
    );
  }
}

/// Header mobile : éléments empilés
class _MobileHeader extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _HeaderTitle(),
        Gap(16),
        StartLiveButton(),
      ],
    );
  }
}

/// Titre et sous-titre du header
class _HeaderTitle extends StatelessWidget {
  const _HeaderTitle();
  @override
  Widget build(BuildContext context) {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Configuration des Flux',
          style: TextStyle(
            fontSize: 32,
            fontWeight: FontWeight.w800,
            color: Color(0xFF111827),
          ),
        ),
        Gap(4),
        Text(
          'Configurez vos flux d\'intégration WebTV et WebRadio.',
          style: TextStyle(color: Color(0xFF6B7280)),
        ),
      ],
    );
  }
}

/// Onglets de configuration
class _StreamConfigTabs extends StatelessWidget {
  const _StreamConfigTabs();
  @override
  Widget build(BuildContext context) {
    final isMobile = MediaQuery.of(context).size.width < 768;
    
    return TabBar(
      isScrollable: isMobile, // Scrollable sur mobile seulement
      labelColor: const Color(0xFF111827),
      unselectedLabelColor: const Color(0xFF6B7280),
      indicatorColor: const Color(0xFF111827),
      labelStyle: TextStyle(
        fontSize: isMobile ? 14 : 16,
        fontWeight: FontWeight.w600,
      ),
      tabs: const [
        Tab(
          icon: Icon(Icons.tv),
          text: 'WebTV',
        ),
        Tab(
          icon: Icon(Icons.radio),
          text: 'WebRadio',
        ),
      ],
    );
  }
}

/// Contenu des onglets
class _StreamConfigTabViews extends StatelessWidget {
  const _StreamConfigTabViews();
  @override
  Widget build(BuildContext context) {
    return const TabBarView(
      children: [
        _WebTvConfigTab(),
        _WebRadioConfigTab(),
      ],
    );
  }
}

/// Onglet de configuration WebTV
class _WebTvConfigTab extends StatelessWidget {
  const _WebTvConfigTab();
  @override
  Widget build(BuildContext context) {
    return const SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Gap(24),
          _EmbedSection(),
        ],
      ),
    );
  }
}

/// Onglet de configuration WebRadio
class _WebRadioConfigTab extends StatelessWidget {
  const _WebRadioConfigTab();
  @override
  Widget build(BuildContext context) {
    return const SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Gap(24),
          _RadioEmbedSection(),
        ],
      ),
    );
  }
}

/// Section d'intégration HTML5 pour WebTV
class _EmbedSection extends StatelessWidget {
  const _EmbedSection();
  @override
  Widget build(BuildContext context) {
    return _Card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Flux d\'intégration HTML5',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: Color(0xFF111827),
            ),
          ),
          const Gap(8),
          const Text(
            'Utilisez cette URL pour intégrer votre lecteur WebTV sur n\'importe quel site web.',
            style: TextStyle(
              fontSize: 14,
              color: Color(0xFF6B7280),
            ),
          ),
          const Gap(16),
          _EmbedUrlField(),
        ],
      ),
    );
  }
}

/// Section d'intégration HTML5 pour WebRadio
class _RadioEmbedSection extends StatelessWidget {
  const _RadioEmbedSection();
  @override
  Widget build(BuildContext context) {
    return _Card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Flux d\'intégration HTML5',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: Color(0xFF111827),
            ),
          ),
          const Gap(8),
          const Text(
            'Utilisez cette URL pour intégrer votre lecteur WebRadio sur n\'importe quel site web.',
            style: TextStyle(
              fontSize: 14,
              color: Color(0xFF6B7280),
            ),
          ),
          const Gap(16),
          _RadioEmbedUrlField(),
        ],
      ),
    );
  }
}

/// Champ URL d'embed pour WebTV
class _EmbedUrlField extends StatelessWidget {
  const _EmbedUrlField();
  @override
  Widget build(BuildContext context) {
    const embedUrl = '<iframe src="https://tv.embmission.com/watch" width="800" height="450" frameborder="0" allowfullscreen="true" allow="autoplay; encrypted-media; fullscreen"></iframe>';
    
    return Row(
      children: [
        Expanded(
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              border: Border.all(color: const Color(0xFFE5E7EB)),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              embedUrl,
              style: const TextStyle(
                fontSize: 14,
                color: Color(0xFF111827),
              ),
            ),
          ),
        ),
        const Gap(8),
        _CopyButton(
          onPressed: () {
            Clipboard.setData(const ClipboardData(text: embedUrl));
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('URL copiée dans le presse-papiers'),
                duration: Duration(seconds: 2),
              ),
            );
          },
        ),
      ],
    );
  }
}

/// Champ URL d'embed pour WebRadio
class _RadioEmbedUrlField extends StatelessWidget {
  const _RadioEmbedUrlField();
  @override
  Widget build(BuildContext context) {
    const embedUrl = '<iframe src="https://radio.embmission.com/player" width="100%" height="500" frameborder="0" allow="autoplay" style="max-width: 450px;"></iframe>';
    
    return Row(
      children: [
        Expanded(
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              border: Border.all(color: const Color(0xFFE5E7EB)),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              embedUrl,
              style: const TextStyle(
                fontSize: 14,
                color: Color(0xFF111827),
              ),
            ),
          ),
        ),
        const Gap(8),
        _CopyButton(
          onPressed: () {
            Clipboard.setData(const ClipboardData(text: embedUrl));
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('URL copiée dans le presse-papiers'),
                duration: Duration(seconds: 2),
              ),
            );
          },
        ),
      ],
    );
  }
}

/// Bouton de copie
class _CopyButton extends StatelessWidget {
  const _CopyButton({required this.onPressed});
  final VoidCallback onPressed;
  
  @override
  Widget build(BuildContext context) {
    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: const Color(0xFF3B82F6),
        borderRadius: BorderRadius.circular(8),
      ),
      child: IconButton(
        onPressed: onPressed,
        icon: const Icon(
          Icons.copy,
          color: Colors.white,
          size: 20,
        ),
      ),
    );
  }
}

/// Carte de contenu
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
