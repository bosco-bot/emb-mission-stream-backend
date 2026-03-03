import 'package:emb_mission_dashboard/core/services/auth_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/emb_logo.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

class ResetPasswordPage extends StatefulWidget {
  const ResetPasswordPage({super.key});

  @override
  State<ResetPasswordPage> createState() => _ResetPasswordPageState();
}

class _ResetPasswordPageState extends State<ResetPasswordPage> {
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  
  bool _isNewPasswordVisible = false;
  bool _isConfirmPasswordVisible = false;
  bool _isLoading = false;
  
  String? _token;
  String? _email;

  @override
  void initState() {
    super.initState();
    // Récupérer le token et l'email depuis l'URL
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final uri = Uri.parse(GoRouterState.of(context).uri.toString());
      _token = uri.queryParameters['token'];
      _email = uri.queryParameters['email'];
      
      if (_token == null || _email == null) {
        // Si le token ou l'email manque, rediriger vers la page de mot de passe oublié
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Lien invalide. Veuillez demander un nouveau lien de réinitialisation.'),
            backgroundColor: Colors.orange,
          ),
        );
        Future.delayed(const Duration(seconds: 2), () {
          if (mounted) {
            context.go('/auth/forget-password');
          }
        });
      }
    });
  }

  @override
  void dispose() {
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
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
                        child: _ResetPasswordCard(
                          formKey: _formKey,
                          newPasswordController: _newPasswordController,
                          confirmPasswordController: _confirmPasswordController,
                          isNewPasswordVisible: _isNewPasswordVisible,
                          isConfirmPasswordVisible: _isConfirmPasswordVisible,
                          isLoading: _isLoading,
                          onNewPasswordVisibilityToggle: () {
                            setState(() {
                              _isNewPasswordVisible = !_isNewPasswordVisible;
                            });
                          },
                          onConfirmPasswordVisibilityToggle: () {
                            setState(() {
                              _isConfirmPasswordVisible = !_isConfirmPasswordVisible;
                            });
                          },
                          onReset: _handleReset,
                          onBackToLogin: () => context.go('/auth'),
                          isMobile: isMobile,
                        ),
                      ),
                    ),
                  ),
                ),
                
                // Footer
                _AppFooter(),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _handleReset() async {
    if (_formKey.currentState?.validate() ?? false) {
      if (_token == null || _email == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Lien invalide. Veuillez demander un nouveau lien de réinitialisation.'),
            backgroundColor: Colors.orange,
          ),
        );
        return;
      }

      setState(() {
        _isLoading = true;
      });

      try {
        await AuthService.instance.resetPassword(
          token: _token!,
          email: _email!,
          password: _newPasswordController.text,
          passwordConfirmation: _confirmPasswordController.text,
        );

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Mot de passe réinitialisé avec succès'),
              backgroundColor: Colors.green,
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
  }
}

/// Header avec logo et nom de l'application
class _AppHeader extends StatelessWidget {
  const _AppHeader({required this.isMobile});
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.all(isMobile ? 16 : 24),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Logo
          EmbLogo(size: isMobile ? 32 : 40),
          const Gap(8),
          // Nom de l'application
          Text(
            'EMB-MISSION-RTV',
            style: TextStyle(
              fontSize: isMobile ? 16 : 20,
              fontWeight: FontWeight.w900,
              color: const Color(0xFF0F172A),
            ),
          ),
        ],
      ),
    );
  }
}

/// Carte de réinitialisation du mot de passe
class _ResetPasswordCard extends StatelessWidget {
  const _ResetPasswordCard({
    required this.formKey,
    required this.newPasswordController,
    required this.confirmPasswordController,
    required this.isNewPasswordVisible,
    required this.isConfirmPasswordVisible,
    required this.isLoading,
    required this.onNewPasswordVisibilityToggle,
    required this.onConfirmPasswordVisibilityToggle,
    required this.onReset,
    required this.onBackToLogin,
    required this.isMobile,
  });

  final GlobalKey<FormState> formKey;
  final TextEditingController newPasswordController;
  final TextEditingController confirmPasswordController;
  final bool isNewPasswordVisible;
  final bool isConfirmPasswordVisible;
  final bool isLoading;
  final VoidCallback onNewPasswordVisibilityToggle;
  final VoidCallback onConfirmPasswordVisibilityToggle;
  final VoidCallback onReset;
  final VoidCallback onBackToLogin;
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
      child: Form(
        key: formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Titre
            Text(
              'Réinitialiser le mot de passe',
              style: TextStyle(
                fontSize: isMobile ? 24 : 28,
                fontWeight: FontWeight.w800,
                color: const Color(0xFF111827),
              ),
              textAlign: TextAlign.center,
            ),
            const Gap(8),
            
            // Sous-titre
            Text(
              'Choisissez un nouveau mot de passe sécurisé.',
              style: TextStyle(
                fontSize: isMobile ? 14 : 16,
                color: const Color(0xFF6B7280),
              ),
              textAlign: TextAlign.center,
            ),
            const Gap(32),
            
            // Champ nouveau mot de passe
            _PasswordField(
              label: 'Nouveau mot de passe',
              controller: newPasswordController,
              isVisible: isNewPasswordVisible,
              onVisibilityToggle: onNewPasswordVisibilityToggle,
              validator: (value) {
                if (value == null || value.isEmpty) {
                  return 'Veuillez saisir un mot de passe';
                }
                if (value.length < 8) {
                  return 'Le mot de passe doit contenir au moins 8 caractères';
                }
                return null;
              },
            ),
            const Gap(16),
            
            // Champ confirmation
            _PasswordField(
              label: 'Confirmation',
              controller: confirmPasswordController,
              isVisible: isConfirmPasswordVisible,
              onVisibilityToggle: onConfirmPasswordVisibilityToggle,
              validator: (value) {
                if (value == null || value.isEmpty) {
                  return 'Veuillez confirmer votre mot de passe';
                }
                if (value != newPasswordController.text) {
                  return 'Les mots de passe ne correspondent pas';
                }
                return null;
              },
            ),
            const Gap(32),
            
            // Bouton Réinitialiser
            ElevatedButton(
              onPressed: isLoading ? null : onReset,
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
                  : Text(
                      'Réinitialiser',
                      style: TextStyle(
                        fontSize: isMobile ? 16 : 18,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
            ),
            const Gap(16),
            
            // Lien retour à la connexion
            GestureDetector(
              onTap: onBackToLogin,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(
                    Icons.arrow_back,
                    color: Color(0xFF6B7280),
                    size: 16,
                  ),
                  const Gap(4),
                  Text(
                    'Retour à la connexion',
                    style: TextStyle(
                      fontSize: isMobile ? 14 : 16,
                      color: const Color(0xFF6B7280),
                    ),
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

/// Champ de mot de passe
class _PasswordField extends StatelessWidget {
  const _PasswordField({
    required this.label,
    required this.controller,
    required this.isVisible,
    required this.onVisibilityToggle,
    this.validator,
  });

  final String label;
  final TextEditingController controller;
  final bool isVisible;
  final VoidCallback onVisibilityToggle;
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
            fontWeight: FontWeight.w500,
            color: Color(0xFF374151),
          ),
        ),
        const Gap(8),
        TextFormField(
          controller: controller,
          obscureText: !isVisible,
          validator: validator,
          decoration: InputDecoration(
            hintText: '••••••••',
            prefixIcon: const Icon(
              Icons.lock_outline,
              color: Color(0xFF6B7280),
            ),
            suffixIcon: IconButton(
              onPressed: onVisibilityToggle,
              icon: Icon(
                isVisible ? Icons.visibility_off : Icons.visibility,
                color: const Color(0xFF6B7280),
              ),
            ),
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
              borderSide: const BorderSide(color: Color(0xFFDC2626)),
            ),
            errorBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: const BorderSide(color: Colors.red),
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
          ),
        ),
      ],
    );
  }
}

/// Footer avec copyright
class _AppFooter extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Text(
        '© 2025 EMB-MISSION-RTV. Tous droits réservés.',
        style: const TextStyle(
          fontSize: 12,
          color: Color(0xFF6B7280),
        ),
        textAlign: TextAlign.center,
      ),
    );
  }
}
