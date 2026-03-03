import 'package:emb_mission_dashboard/core/services/auth_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/emb_logo.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

class ForgetPasswordPage extends StatefulWidget {
  const ForgetPasswordPage({super.key});

  @override
  State<ForgetPasswordPage> createState() => _ForgetPasswordPageState();
}

class _ForgetPasswordPageState extends State<ForgetPasswordPage> {
  final _emailController = TextEditingController();
  bool _isLoading = false;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _handleSubmit() async {
    if (_emailController.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Veuillez saisir votre adresse email'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      await AuthService.instance.forgotPassword(
        email: _emailController.text.trim(),
      );

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Lien de réinitialisation envoyé ! Vérifiez votre email.'),
            backgroundColor: Colors.green,
            behavior: SnackBarBehavior.floating,
          ),
        );
        
        // Rediriger vers la page de connexion après 2 secondes
        Future.delayed(const Duration(seconds: 2), () {
          if (mounted) {
            context.go('/auth');
          }
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.toString().replaceFirst('Exception: ', '')),
            backgroundColor: Colors.red,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        
        return Scaffold(
          backgroundColor: const Color(0xFFE0F2F7), // Fond bleu clair de la maquette
          body: SafeArea(
            child: Column(
              children: [
                // Header avec logo
                _AppHeader(isMobile: isMobile),
                
                // Contenu principal centré
                Expanded(
                  child: Center(
                    child: SingleChildScrollView(
                      padding: EdgeInsets.symmetric(
                        horizontal: isMobile ? 16 : 24,
                        vertical: isMobile ? 20 : 40,
                      ),
                      child: ConstrainedBox(
                        constraints: BoxConstraints(
                          maxWidth: isMobile ? double.infinity : 400,
                        ),
                        child: _ForgotPasswordCard(
                          emailController: _emailController,
                          isLoading: _isLoading,
                          onSubmit: _handleSubmit,
                          isMobile: isMobile,
                        ),
                      ),
                    ),
                  ),
                ),
                
                // Footer avec copyright
                _AppFooter(isMobile: isMobile),
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Header avec logo EMB-MISSION-RTV
class _AppHeader extends StatelessWidget {
  const _AppHeader({required this.isMobile});
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.all(isMobile ? 16 : 24),
      child: Row(
        children: [
          EmbLogo(size: isMobile ? 28 : 32),
          const Gap(8),
          Text(
            'EMB-MISSION-RTV',
            style: TextStyle(
              fontWeight: FontWeight.w900,
              fontSize: isMobile ? 16 : 18,
              color: const Color(0xFF0F172A),
            ),
          ),
        ],
      ),
    );
  }
}

/// Carte principale du formulaire
class _ForgotPasswordCard extends StatelessWidget {
  const _ForgotPasswordCard({
    required this.emailController,
    required this.isLoading,
    required this.onSubmit,
    required this.isMobile,
  });

  final TextEditingController emailController;
  final bool isLoading;
  final VoidCallback onSubmit;
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(isMobile ? 24 : 32),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.1),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Titre
          Text(
            'Mot de passe oublié ?',
            style: TextStyle(
              fontSize: isMobile ? 24 : 28,
              fontWeight: FontWeight.bold,
              color: const Color(0xFF0F172A),
            ),
            textAlign: TextAlign.center,
          ),
          const Gap(12),
          
          // Description
          Text(
            'Pas de soucis. Entrez votre email et nous vous enverrons un lien pour le réinitialiser.',
            style: TextStyle(
              fontSize: isMobile ? 14 : 16,
              color: const Color(0xFF64748B),
            ),
            textAlign: TextAlign.center,
          ),
          const Gap(32),
          
          // Champ Email
          const Text(
            'Email',
            style: TextStyle(
              fontWeight: FontWeight.w500,
              color: Color(0xFF334155),
            ),
          ),
          const Gap(8),
          TextField(
            controller: emailController,
            keyboardType: TextInputType.emailAddress,
            decoration: InputDecoration(
              hintText: 'votre.email@exemple.com',
              prefixIcon: const Icon(Icons.email_outlined, color: Color(0xFF6B7280)),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide: BorderSide(color: AppTheme.blueColor),
              ),
              contentPadding: const EdgeInsets.symmetric(vertical: 16, horizontal: 16),
            ),
          ),
          const Gap(32),
          
          // Bouton Envoyer
          ElevatedButton(
            onPressed: isLoading ? null : onSubmit,
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.redColor,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 16),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
              disabledBackgroundColor: AppTheme.redColor.withOpacity(0.6),
            ),
            child: isLoading
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                    ),
                  )
                : const Text(
                    'Envoyer le lien',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                  ),
          ),
          const Gap(24),
          
          // Lien retour
          GestureDetector(
            onTap: () => context.go('/auth'),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(
                  Icons.arrow_back,
                  size: 16,
                  color: Color(0xFF64748B),
                ),
                const Gap(4),
                Text(
                  'Retour à la connexion',
                  style: TextStyle(
                    color: const Color(0xFF64748B),
                    fontSize: isMobile ? 14 : 16,
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

/// Footer avec copyright
class _AppFooter extends StatelessWidget {
  const _AppFooter({required this.isMobile});
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.all(isMobile ? 16 : 24),
      child: Text(
        '© 2025 EMB-MISSION-RTV. Tous droits réservés.',
        style: TextStyle(
          fontSize: isMobile ? 12 : 14,
          color: const Color(0xFF64748B),
        ),
        textAlign: TextAlign.center,
      ),
    );
  }
}
