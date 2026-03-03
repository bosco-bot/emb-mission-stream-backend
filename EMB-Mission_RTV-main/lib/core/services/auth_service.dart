import 'package:dio/dio.dart';
import 'package:emb_mission_dashboard/core/config/api_config.dart';

/// Service d'authentification
/// 
/// Gère les appels API pour l'inscription, la connexion et la déconnexion
class AuthService {
  AuthService._();
  
  static final AuthService instance = AuthService._();
  
  late final Dio _dio;
  
  /// Initialise le client HTTP Dio
  void initialize() {
    _dio = Dio(
      BaseOptions(
        baseUrl: ApiConfig.baseUrl,
        connectTimeout: Duration(milliseconds: ApiConfig.connectTimeout),
        receiveTimeout: Duration(milliseconds: ApiConfig.receiveTimeout),
        sendTimeout: Duration(milliseconds: ApiConfig.sendTimeout),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      ),
    );
    
    // Logs en développement
    if (ApiConfig.enableLogs) {
      _dio.interceptors.add(
        LogInterceptor(
          requestBody: true,
          responseBody: true,
          error: true,
          requestHeader: true,
          responseHeader: false,
        ),
      );
    }
  }
  
  /// Inscription d'un nouveau utilisateur
  /// 
  /// Paramètres :
  /// - [name] : Nom complet de l'utilisateur
  /// - [email] : Email de l'utilisateur
  /// - [password] : Mot de passe
  /// - [passwordConfirmation] : Confirmation du mot de passe
  /// - [acceptTerms] : Acceptation des conditions générales
  /// 
  /// Retourne :
  /// - Map contenant les données de l'utilisateur et le token en cas de succès
  /// - Lance une exception en cas d'erreur
  Future<Map<String, dynamic>> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    required bool acceptTerms,
  }) async {
    print('🚀 DEBUG: Début de l\'inscription pour $email');
    print('🌐 DEBUG: URL API: ${ApiConfig.baseUrl}');
    print('🌐 DEBUG: Endpoint complet: ${ApiConfig.register}');
    
    try {
      final response = await _dio.post(
        ApiConfig.register,  // ✅ Utilise l'URL complète depuis ApiConfig
        data: {
          'name': name,
          'email': email,
          'password': password,
          'password_confirmation': passwordConfirmation,
          'accept_terms': acceptTerms,
        },
      );
      
      // Succès (201 Created ou 200 OK)
      if (response.statusCode == 201 || response.statusCode == 200) {
        return response.data as Map<String, dynamic>;
      }
      
      // Erreur inattendue
      throw Exception('Erreur lors de l\'inscription');
      
    } on DioException catch (e) {
      print('❌ DEBUG: DioException capturée');
      print('❌ DEBUG: Type: ${e.type}');
      print('❌ DEBUG: Message: ${e.message}');
      print('❌ DEBUG: Response: ${e.response?.data}');
      print('❌ DEBUG: Status Code: ${e.response?.statusCode}');
      
      // Erreur réseau ou serveur
      if (e.response != null) {
        // Le serveur a répondu avec une erreur
        final errorData = e.response?.data;
        
        if (errorData is Map<String, dynamic>) {
          // Erreur de validation (422)
          if (e.response?.statusCode == 422 && errorData.containsKey('errors')) {
            final errors = errorData['errors'] as Map<String, dynamic>;
            
            // Récupérer le premier message d'erreur et le traduire
            final firstError = errors.values.first;
            if (firstError is List && firstError.isNotEmpty) {
              final errorMessage = firstError.first.toString();
              final translatedMessage = _translateErrorMessage(errorMessage);
              throw Exception(translatedMessage);
            }
          }
          
          // Message d'erreur générique du serveur
          if (errorData.containsKey('message')) {
            final serverMessage = errorData['message'].toString();
            final translatedMessage = _translateErrorMessage(serverMessage);
            throw Exception(translatedMessage);
          }
        }
        
        throw Exception('Erreur ${e.response?.statusCode}: ${e.response?.statusMessage}');
      }
      
      // Pas de réponse (problème réseau)
      if (e.type == DioExceptionType.connectionTimeout) {
        throw Exception('Délai de connexion dépassé. Vérifiez votre connexion internet.');
      }
      
      if (e.type == DioExceptionType.receiveTimeout) {
        throw Exception('Délai de réception dépassé. Le serveur met trop de temps à répondre.');
      }
      
      if (e.type == DioExceptionType.connectionError) {
        throw Exception('Impossible de se connecter au serveur. Vérifiez votre connexion internet.');
      }
      
      throw Exception('Erreur réseau : ${e.message}');
      
    } catch (e) {
      // Autre type d'erreur
      throw Exception('Erreur inattendue : $e');
    }
  }
  
  /// Vérifie si on est en mode développement
  bool _isDevelopmentMode() {
    // Mode développement désactivé - utilise toujours l'API de production
    return false;
  }
  
  /// Traduit les messages d'erreur de l'API en français
  String _translateErrorMessage(String errorMessage) {
    print('🔍 DEBUG: Message d\'erreur original: "$errorMessage"');
    
    // Messages d'erreur courants de Laravel en anglais
    final translations = {
      // Erreurs de mot de passe
      'The password field must contain at least one uppercase letter.': 
        'Le mot de passe doit contenir au moins une lettre majuscule.',
      'The password field must contain at least one lowercase letter.': 
        'Le mot de passe doit contenir au moins une lettre minuscule.',
      'The password field must contain at least one uppercase and one lowercase letter.': 
        'Le mot de passe doit contenir au moins une lettre majuscule et une lettre minuscule.',
      'The password field must contain at least one number.': 
        'Le mot de passe doit contenir au moins un chiffre.',
      'The password field must contain at least one special character.': 
        'Le mot de passe doit contenir au moins un caractère spécial.',
      'The password field must contain at least one symbol.': 
        'Le mot de passe doit contenir au moins un symbole.',
      'The password field must be at least 8 characters.': 
        'Le mot de passe doit contenir au moins 8 caractères.',
      'The password confirmation does not match.': 
        'La confirmation du mot de passe ne correspond pas.',
      
      // Erreurs d'email
      'The email has already been taken.': 
        'Cette adresse email est déjà utilisée.',
      'The email field is required.': 
        'L\'adresse email est obligatoire.',
      'The email must be a valid email address.': 
        'L\'adresse email doit être valide.',
      
      // Erreurs de nom
      'The name field is required.': 
        'Le nom est obligatoire.',
      'The name field must be at least 2 characters.': 
        'Le nom doit contenir au moins 2 caractères.',
      
      // Erreurs générales
      'The accept terms field is required.': 
        'Vous devez accepter les conditions générales.',
      'The accept terms field must be accepted.': 
        'Vous devez accepter les conditions générales.',
      
      // Erreurs de validation génériques
      'Validation failed.': 
        'Échec de la validation.',
      'The given data was invalid.': 
        'Les données fournies sont invalides.',
      
      // Erreurs de route
      'The route api/auth/register could not be found.': 
        'La route d\'inscription n\'existe pas sur le serveur.',
    };
    
    // Chercher une traduction exacte
    if (translations.containsKey(errorMessage)) {
      print('✅ DEBUG: Traduction exacte trouvée: "${translations[errorMessage]}"');
      return translations[errorMessage]!;
    }
    
    // Chercher une traduction partielle (pour les messages dynamiques)
    for (final entry in translations.entries) {
      if (errorMessage.toLowerCase().contains(entry.key.toLowerCase())) {
        print('✅ DEBUG: Traduction partielle trouvée: "${entry.value}"');
        return entry.value;
      }
    }
    
    // Si aucune traduction trouvée, retourner le message original
    print('❌ DEBUG: Aucune traduction trouvée pour: "$errorMessage"');
    return errorMessage;
  }
  
  /// Simule une inscription réussie en mode développement
  Future<Map<String, dynamic>> _simulateRegistration(String name, String email) async {
    // Simulation d'un délai réseau
    await Future.delayed(const Duration(seconds: 1));
    
    // Retourne une réponse simulée
    return {
      'success': true,
      'message': 'Inscription réussie (mode développement)',
      'user': {
        'id': 1,
        'name': name,
        'email': email,
        'email_verified_at': DateTime.now().toIso8601String(),
        'created_at': DateTime.now().toIso8601String(),
        'updated_at': DateTime.now().toIso8601String(),
      },
      'token': 'dev_token_${DateTime.now().millisecondsSinceEpoch}',
      'token_type': 'Bearer',
    };
  }
  
  /// Récupère les informations de l'utilisateur connecté
  Future<Map<String, dynamic>> getCurrentUser() async {
    try {
      final response = await _dio.get('/auth/me');
      
      if (response.statusCode == 200) {
        return response.data as Map<String, dynamic>;
      }
      
      throw Exception('Erreur lors de la récupération des données utilisateur');
      
    } on DioException catch (e) {
      if (e.response != null) {
        final errorData = e.response?.data;
        if (errorData is Map<String, dynamic> && errorData.containsKey('message')) {
          final serverMessage = errorData['message'].toString();
          final translatedMessage = _translateErrorMessage(serverMessage);
          throw Exception(translatedMessage);
        }
        throw Exception('Erreur ${e.response?.statusCode}: ${e.response?.statusMessage}');
      }
      
      if (e.type == DioExceptionType.connectionTimeout) {
        throw Exception('Délai de connexion dépassé. Vérifiez votre connexion internet.');
      }
      
      if (e.type == DioExceptionType.receiveTimeout) {
        throw Exception('Délai de réception dépassé. Le serveur met trop de temps à répondre.');
      }
      
      if (e.type == DioExceptionType.connectionError) {
        throw Exception('Impossible de se connecter au serveur. Vérifiez votre connexion internet.');
      }
      
      throw Exception('Erreur réseau : ${e.message}');
      
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Connexion d'un utilisateur (à implémenter plus tard)
  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
  }) async {
    // TODO: Implémenter plus tard
    throw UnimplementedError('Login à implémenter');
  }
  
  /// Déconnexion de l'utilisateur (à implémenter plus tard)
  Future<void> logout() async {
    // TODO: Implémenter plus tard
    throw UnimplementedError('Logout à implémenter');
  }

  /// Demande de réinitialisation du mot de passe
  /// 
  /// Paramètres :
  /// - [email] : Email de l'utilisateur
  /// 
  /// Retourne :
  /// - Map contenant le message de succès
  /// - Lance une exception en cas d'erreur
  Future<Map<String, dynamic>> forgotPassword({
    required String email,
  }) async {
    try {
      final response = await _dio.post(
        ApiConfig.forgotPassword,
        data: {
          'email': email,
        },
      );
      
      if (response.statusCode == 200 || response.statusCode == 201) {
        return response.data as Map<String, dynamic>;
      }
      
      throw Exception('Erreur lors de la demande de réinitialisation');
      
    } on DioException catch (e) {
      if (e.response != null) {
        final errorData = e.response?.data;
        
        if (errorData is Map<String, dynamic>) {
          if (errorData.containsKey('message')) {
            final serverMessage = errorData['message'].toString();
            final translatedMessage = _translateErrorMessage(serverMessage);
            throw Exception(translatedMessage);
          }
        }
        
        throw Exception('Erreur ${e.response?.statusCode}: ${e.response?.statusMessage}');
      }
      
      if (e.type == DioExceptionType.connectionTimeout) {
        throw Exception('Délai de connexion dépassé. Vérifiez votre connexion internet.');
      }
      
      if (e.type == DioExceptionType.receiveTimeout) {
        throw Exception('Délai de réception dépassé. Le serveur met trop de temps à répondre.');
      }
      
      if (e.type == DioExceptionType.connectionError) {
        throw Exception('Impossible de se connecter au serveur. Vérifiez votre connexion internet.');
      }
      
      throw Exception('Erreur réseau : ${e.message}');
      
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }

  /// Réinitialisation du mot de passe
  /// 
  /// Paramètres :
  /// - [token] : Token de réinitialisation
  /// - [email] : Email de l'utilisateur
  /// - [password] : Nouveau mot de passe
  /// - [passwordConfirmation] : Confirmation du mot de passe
  /// 
  /// Retourne :
  /// - Map contenant le message de succès
  /// - Lance une exception en cas d'erreur
  Future<Map<String, dynamic>> resetPassword({
    required String token,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    try {
      final response = await _dio.post(
        ApiConfig.resetPassword,
        data: {
          'token': token,
          'email': email,
          'password': password,
          'password_confirmation': passwordConfirmation,
        },
      );
      
      if (response.statusCode == 200 || response.statusCode == 201) {
        return response.data as Map<String, dynamic>;
      }
      
      throw Exception('Erreur lors de la réinitialisation du mot de passe');
      
    } on DioException catch (e) {
      if (e.response != null) {
        final errorData = e.response?.data;
        
        if (errorData is Map<String, dynamic>) {
          if (errorData.containsKey('message')) {
            final serverMessage = errorData['message'].toString();
            final translatedMessage = _translateErrorMessage(serverMessage);
            throw Exception(translatedMessage);
          }
        }
        
        throw Exception('Erreur ${e.response?.statusCode}: ${e.response?.statusMessage}');
      }
      
      if (e.type == DioExceptionType.connectionTimeout) {
        throw Exception('Délai de connexion dépassé. Vérifiez votre connexion internet.');
      }
      
      if (e.type == DioExceptionType.receiveTimeout) {
        throw Exception('Délai de réception dépassé. Le serveur met trop de temps à répondre.');
      }
      
      if (e.type == DioExceptionType.connectionError) {
        throw Exception('Impossible de se connecter au serveur. Vérifiez votre connexion internet.');
      }
      
      throw Exception('Erreur réseau : ${e.message}');
      
    } catch (e) {
      throw Exception('Erreur inattendue : $e');
    }
  }
}

