import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:emb_mission_dashboard/core/widgets/emb_logo.dart';
import 'package:emb_mission_dashboard/core/services/user_service.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;
  bool _isLoading = false;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  /// Appel API pour la connexion
  Future<void> _handleLogin() async {
    // Validation basique
    if (_emailController.text.trim().isEmpty) {
      _showError('Veuillez entrer votre email');
      return;
    }
    
    if (_passwordController.text.isEmpty) {
      _showError('Veuillez entrer votre mot de passe');
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      // Appel API de connexion
      final response = await http.post(
        Uri.parse('https://tv.embmission.com/api/login'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'email': _emailController.text.trim(),
          'password': _passwordController.text,
        }),
      );

      if (!mounted) return;

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        
        if (data['success'] == true && data['data'] != null) {
          final userData = data['data']['user'] as Map<String, dynamic>;
          final token = data['data']['token'] as String;
          
          // 🔥 Vérifier si le compte est actif
          final isActive = userData['is_active'];
          final isActiveBool = isActive == true || isActive == 1;
          
          if (!isActiveBool) {
            _showError('Votre compte n\'est pas encore activé. Veuillez contacter l\'administrateur.');
            return;
          }
          
          // Sauvegarder les données utilisateur
          await UserService.instance.setUserData(userData, token);
          
          // Succès
          _showSuccess('Connexion réussie !');
          
          // Redirection vers le dashboard
          await Future.delayed(const Duration(seconds: 1));
          if (mounted) {
            context.go('/dashboard');
          }
        } else {
          throw Exception(data['message'] ?? 'Erreur lors de la connexion');
        }
      } else {
        final errorData = jsonDecode(response.body);
        throw Exception(errorData['message'] ?? 'Erreur lors de la connexion');
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
          backgroundColor: const Color(0xFFF8FAFC), // Fond gris clair de la maquette
          body: SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: EdgeInsets.symmetric(
                  horizontal: isMobile ? 16 : 24,
                  vertical: isMobile ? 20 : 40,
                ),
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    maxWidth: isMobile ? double.infinity : 800,
                  ),
                  child: _LoginCard(
                    emailController: _emailController,
                    passwordController: _passwordController,
                    obscurePassword: _obscurePassword,
                    isLoading: _isLoading,
                    onPasswordToggle: () {
                      setState(() {
                        _obscurePassword = !_obscurePassword;
                      });
                    },
                    onLogin: _handleLogin,
                    isMobile: isMobile,
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

/// Carte de connexion avec panneau gauche et formulaire
class _LoginCard extends StatelessWidget {
  const _LoginCard({
    required this.emailController,
    required this.passwordController,
    required this.obscurePassword,
    required this.isLoading,
    required this.onPasswordToggle,
    required this.onLogin,
    required this.isMobile,
  });

  final TextEditingController emailController;
  final TextEditingController passwordController;
  final bool obscurePassword;
  final bool isLoading;
  final VoidCallback onPasswordToggle;
  final Future<void> Function() onLogin;
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    if (isMobile) {
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
        child: Column(
          children: [
            // Panneau branding mobile
            _BrandingPanel(isMobile: isMobile),
            
            // Formulaire de connexion
            _LoginForm(
              emailController: emailController,
              passwordController: passwordController,
              obscurePassword: obscurePassword,
              isLoading: isLoading,
              onPasswordToggle: onPasswordToggle,
              onLogin: onLogin,
              isMobile: isMobile,
            ),
          ],
        ),
      );
    }

    return Container(
      height: 600,
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
      child: Row(
        children: [
          // Panneau gauche - Branding
          _BrandingPanel(isMobile: isMobile),
          
          // Panneau droit - Formulaire
          _LoginForm(
            emailController: emailController,
            passwordController: passwordController,
            obscurePassword: obscurePassword,
            isLoading: isLoading,
            onPasswordToggle: onPasswordToggle,
            onLogin: onLogin,
            isMobile: isMobile,
          ),
        ],
      ),
    );
  }
}

/// Panneau de branding avec logo et colombe
class _BrandingPanel extends StatelessWidget {
  const _BrandingPanel({required this.isMobile});
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: isMobile ? double.infinity : 400,
      padding: EdgeInsets.all(isMobile ? 32 : 48),
      decoration: BoxDecoration(
        color: const Color(0xFFE0F2F7), // Bleu clair de la maquette
        borderRadius: isMobile 
          ? const BorderRadius.only(
              topLeft: Radius.circular(16),
              topRight: Radius.circular(16),
            )
          : const BorderRadius.only(
              topLeft: Radius.circular(16),
              bottomLeft: Radius.circular(16),
            ),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Logo rond EMB-MISSION
          EmbLogo(size: isMobile ? 120 : 150),
          const Gap(24),
          
          // Titre principal
          Text(
            'EMB-MISSION-RTV',
            style: TextStyle(
              fontSize: isMobile ? 24 : 28,
              fontWeight: FontWeight.w900,
              color: const Color(0xFF0F172A),
            ),
            textAlign: TextAlign.center,
          ),
          const Gap(16),
          
          // Description
          Text(
            'Votre plateforme tout-en-un pour la diffusion WebTV & WebRadio.',
            style: TextStyle(
              fontSize: isMobile ? 14 : 16,
              color: const Color(0xFF64748B),
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}

/// Formulaire de connexion
class _LoginForm extends StatelessWidget {
  const _LoginForm({
    required this.emailController,
    required this.passwordController,
    required this.obscurePassword,
    required this.isLoading,
    required this.onPasswordToggle,
    required this.onLogin,
    required this.isMobile,
  });

  final TextEditingController emailController;
  final TextEditingController passwordController;
  final bool obscurePassword;
  final bool isLoading;
  final VoidCallback onPasswordToggle;
  final Future<void> Function() onLogin;
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Padding(
        padding: EdgeInsets.all(isMobile ? 32 : 48),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Titre
            Text(
              'Connexion',
              style: TextStyle(
                fontSize: isMobile ? 24 : 28,
                fontWeight: FontWeight.bold,
                color: const Color(0xFF0F172A),
              ),
            ),
            const Gap(8),
            
            // Sous-titre
            Text(
              'Heureux de vous revoir !',
              style: TextStyle(
                fontSize: isMobile ? 14 : 16,
                color: const Color(0xFF64748B),
              ),
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
                hintText: 'votre.email@example.com',
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
            const Gap(24),
            
            // Champ Mot de passe avec lien
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Mot de passe',
                  style: TextStyle(
                    fontWeight: FontWeight.w500,
                    color: Color(0xFF334155),
                  ),
                ),
                TextButton(
                  onPressed: () => context.go('/auth/forget-password'),
                  style: TextButton.styleFrom(
                    foregroundColor: const Color(0xFF64748B),
                    padding: EdgeInsets.zero,
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                  child: const Text('Mot de passe oublié ?'),
                ),
              ],
            ),
            const Gap(8),
            TextField(
              controller: passwordController,
              obscureText: obscurePassword,
              decoration: InputDecoration(
                hintText: '••••••••',
                prefixIcon: const Icon(Icons.lock_outline, color: Color(0xFF6B7280)),
                suffixIcon: IconButton(
                  icon: Icon(
                    obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                    color: const Color(0xFF6B7280),
                  ),
                  onPressed: onPasswordToggle,
                ),
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
            
            // Bouton de connexion
            ElevatedButton(
              onPressed: isLoading ? null : onLogin,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.redColor,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
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
                      'Se connecter',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
            ),
            const Gap(24),
            
            // Lien d'inscription
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Text(
                  'Pas encore de compte ?',
                  style: TextStyle(color: Color(0xFF64748B)),
                ),
                TextButton(
                  onPressed: () => context.go('/auth/register'),
                  style: TextButton.styleFrom(
                    foregroundColor: const Color(0xFF0F172A),
                    padding: const EdgeInsets.only(left: 8),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                  child: const Text(
                    'Créer un compte',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
