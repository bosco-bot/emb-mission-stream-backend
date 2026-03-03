import 'package:emb_mission_dashboard/core/config/api_config.dart';
import 'package:emb_mission_dashboard/core/services/auth_service.dart';
import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/emb_logo.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';

/// Page de création de compte
/// 
/// Interface de création de compte avec carte centrée sur fond bleu,
/// conforme à la maquette fournie.
class RegisterPage extends StatefulWidget {
  const RegisterPage({super.key});

  @override
  State<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;
  bool _acceptTerms = false;
  bool _isLoading = false;

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  /// Appel API pour l'inscription
  Future<void> _handleRegister() async {
    // Validation basique
    if (_nameController.text.trim().isEmpty) {
      _showError('Veuillez entrer votre nom complet');
      return;
    }
    
    if (_emailController.text.trim().isEmpty) {
      _showError('Veuillez entrer votre email');
      return;
    }
    
    if (_passwordController.text.isEmpty) {
      _showError('Veuillez entrer un mot de passe');
      return;
    }
    
    if (_passwordController.text != _confirmPasswordController.text) {
      _showError('Les mots de passe ne correspondent pas');
      return;
    }
    
    if (!_acceptTerms) {
      _showError('Vous devez accepter les conditions générales');
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      // Appel API d'inscription
      final response = await AuthService.instance.register(
        name: _nameController.text.trim(),
        email: _emailController.text.trim(),
        password: _passwordController.text,
        passwordConfirmation: _confirmPasswordController.text,
        acceptTerms: _acceptTerms,
      );

      if (!mounted) return;

      // Sauvegarder les données utilisateur
      if (response.containsKey('data')) {
        final data = response['data'] as Map<String, dynamic>;
        final user = data['user'] as Map<String, dynamic>;
        final token = data['token'] as String;
        
        await UserService.instance.setUserData(user, token);
      }

      // Succès
      _showSuccess('Inscription réussie ! Connectez-vous maintenant.');
      
      // Redirection vers la page de connexion
      await Future.delayed(const Duration(seconds: 1));
      if (mounted) {
        context.go('/auth');
      }
      
    } catch (e) {
      if (!mounted) return;
      
      // Afficher l'erreur
      _showError(e.toString().replaceFirst('Exception: ', ''));
      
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  /// Afficher un message d'erreur
  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.red,
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 4),
      ),
    );
  }

  /// Afficher un message de succès
  void _showSuccess(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.green,
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 2),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 768;
        
        return Scaffold(
          backgroundColor: const Color(0xFFCDE8F4), // Fond bleu clair de la maquette
          body: Center(
            child: SingleChildScrollView(
              padding: EdgeInsets.symmetric(
                horizontal: isMobile ? 16 : 24,
                vertical: 24,
              ),
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxWidth: isMobile ? double.infinity : 480,
                ),
              child: _RegisterCard(
                isMobile: isMobile,
                nameController: _nameController,
                emailController: _emailController,
                passwordController: _passwordController,
                confirmPasswordController: _confirmPasswordController,
                obscurePassword: _obscurePassword,
                obscureConfirmPassword: _obscureConfirmPassword,
                acceptTerms: _acceptTerms,
                isLoading: _isLoading,
                onPasswordToggle: () {
                  setState(() {
                    _obscurePassword = !_obscurePassword;
                  });
                },
                onConfirmPasswordToggle: () {
                  setState(() {
                    _obscureConfirmPassword = !_obscureConfirmPassword;
                  });
                },
                onTermsChanged: (value) {
                  setState(() {
                    _acceptTerms = value ?? false;
                  });
                },
                onRegister: _handleRegister,
                onLogin: () {
                  context.go('/auth');
                },
              ),
              ),
            ),
          ),
        );
      },
    );
  }
}

/// Carte de création de compte conforme à la maquette
class _RegisterCard extends StatelessWidget {
  const _RegisterCard({
    required this.isMobile,
    required this.nameController,
    required this.emailController,
    required this.passwordController,
    required this.confirmPasswordController,
    required this.obscurePassword,
    required this.obscureConfirmPassword,
    required this.acceptTerms,
    required this.isLoading,
    required this.onPasswordToggle,
    required this.onConfirmPasswordToggle,
    required this.onTermsChanged,
    required this.onRegister,
    required this.onLogin,
  });

  final bool isMobile;
  final TextEditingController nameController;
  final TextEditingController emailController;
  final TextEditingController passwordController;
  final TextEditingController confirmPasswordController;
  final bool obscurePassword;
  final bool obscureConfirmPassword;
  final bool acceptTerms;
  final bool isLoading;
  final VoidCallback onPasswordToggle;
  final VoidCallback onConfirmPasswordToggle;
  final ValueChanged<bool?> onTermsChanged;
  final VoidCallback onRegister;
  final VoidCallback onLogin;

  @override
  Widget build(BuildContext context) {
    return Container(
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
      padding: EdgeInsets.all(isMobile ? 24 : 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          // Logo et nom de l'application
          _AppHeader(),
          const Gap(24),
          
          // Titre et sous-titre
          const Text(
            'Créer votre compte',
            style: TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.bold,
              color: Color(0xFF111827),
            ),
          ),
          const Gap(8),
          const Text(
            'Rejoignez la plateforme de diffusion de demain.',
            style: TextStyle(
              fontSize: 16,
              color: Color(0xFF6B7280),
            ),
          ),
          const Gap(32),

          // Champs du formulaire
          _FormField(
            label: 'Nom complet',
            controller: nameController,
            hintText: 'John Doe',
            prefixIcon: Icons.person_outline,
          ),
          const Gap(20),
          
          _FormField(
            label: 'Email',
            controller: emailController,
            hintText: 'john.doe@email.com',
            prefixIcon: Icons.email_outlined,
          ),
          const Gap(20),
          
          _PasswordField(
            label: 'Mot de passe',
            controller: passwordController,
            obscureText: obscurePassword,
            onToggle: onPasswordToggle,
          ),
          const Gap(20),
          
          _PasswordField(
            label: 'Confirmation du mot de passe',
            controller: confirmPasswordController,
            obscureText: obscureConfirmPassword,
            onToggle: onConfirmPasswordToggle,
          ),
          const Gap(24),

          // Checkbox Conditions Générales
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Checkbox(
                value: acceptTerms,
                onChanged: onTermsChanged,
                activeColor: AppTheme.redColor,
              ),
              Expanded(
                child: RichText(
                  text: TextSpan(
                    style: const TextStyle(
                      fontSize: 14,
                      color: Color(0xFF6B7280),
                    ),
                    children: [
                      const TextSpan(text: 'J\'accepte les '),
                      TextSpan(
                        text: 'Conditions Générales d\'Utilisation',
                        style: TextStyle(
                          color: AppTheme.redColor,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const Gap(24),

          // Bouton d'inscription
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: (acceptTerms && !isLoading) ? onRegister : null,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.redColor,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
                elevation: 0,
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
                      'S\'inscrire',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
            ),
          ),
          const Gap(24),

          // Lien de connexion
          Center(
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text(
                  'Déjà inscrit ?',
                  style: TextStyle(color: Color(0xFF64748B)),
                ),
                TextButton(
                  onPressed: onLogin,
                  style: TextButton.styleFrom(
                    foregroundColor: const Color(0xFF0F172A),
                    padding: const EdgeInsets.only(left: 8),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                  child: const Text(
                    'Se connecter',
                    style: TextStyle(fontWeight: FontWeight.bold),
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

/// En-tête avec logo et nom de l'application
class _AppHeader extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        // Logo rond EMB-MISSION
        const EmbLogo(size: 48),
        const Gap(12),
        
        // Nom de l'application
        const Text(
          'EMB-MISSION-RTV',
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.bold,
            color: Color(0xFF111827),
          ),
        ),
      ],
    );
  }
}

/// Champ de formulaire générique
class _FormField extends StatelessWidget {
  const _FormField({
    required this.label,
    required this.controller,
    required this.hintText,
    required this.prefixIcon,
  });

  final String label;
  final TextEditingController controller;
  final String hintText;
  final IconData prefixIcon;

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
            color: Color(0xFF6B7280),
          ),
        ),
        const Gap(6),
        TextField(
          controller: controller,
          decoration: InputDecoration(
            hintText: hintText,
            prefixIcon: Icon(prefixIcon, color: const Color(0xFF6B7280)),
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
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 12,
            ),
          ),
        ),
      ],
    );
  }
}

/// Champ de mot de passe avec icône de visibilité
class _PasswordField extends StatelessWidget {
  const _PasswordField({
    required this.label,
    required this.controller,
    required this.obscureText,
    required this.onToggle,
  });

  final String label;
  final TextEditingController controller;
  final bool obscureText;
  final VoidCallback onToggle;

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
            color: Color(0xFF6B7280),
          ),
        ),
        const Gap(6),
        TextField(
          controller: controller,
          obscureText: obscureText,
          decoration: InputDecoration(
            hintText: '••••••••',
            prefixIcon: const Icon(Icons.lock_outline, color: Color(0xFF6B7280)),
            suffixIcon: IconButton(
              icon: Icon(
                obscureText ? Icons.visibility_off : Icons.visibility,
                color: const Color(0xFF6B7280),
              ),
              onPressed: onToggle,
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
              borderSide: BorderSide(color: AppTheme.blueColor),
            ),
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 12,
            ),
          ),
        ),
      ],
    );
  }
}