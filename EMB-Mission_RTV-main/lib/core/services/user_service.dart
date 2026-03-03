import 'dart:convert';
import 'package:emb_mission_dashboard/core/services/auth_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Service de gestion des données utilisateur
class UserService {
  UserService._internal();
  static final UserService instance = UserService._internal();

  static const String _userDataKey = 'user_data';
  static const String _tokenKey = 'auth_token';

  /// Données utilisateur actuelles
  Map<String, dynamic>? _currentUser;
  String? _currentToken;

  /// Initialise le service
  Future<void> initialize() async {
    await _loadUserData();
  }

  /// Charge les données utilisateur depuis le stockage local
  Future<void> _loadUserData() async {
    final prefs = await SharedPreferences.getInstance();
    final userDataString = prefs.getString(_userDataKey);
    _currentToken = prefs.getString(_tokenKey);

    if (userDataString != null && _currentToken != null) {
      try {
        // Charger les données depuis SharedPreferences
        _currentUser = jsonDecode(userDataString) as Map<String, dynamic>;
        
        // Essayer de rafraîchir depuis l'API (mais ne pas déconnecter si ça échoue)
        try {
          await _loadUserFromAPI();
        } catch (e) {
          // Si l'API échoue, garder les données locales
          print('⚠️ Impossible de rafraîchir les données depuis l\'API, utilisation des données locales');
        }
      } catch (e) {
        // En cas d'erreur de décodage, déconnecter l'utilisateur
        print('❌ Erreur lors du décodage des données utilisateur: $e');
        _currentUser = null;
        _currentToken = null;
        await prefs.remove(_userDataKey);
        await prefs.remove(_tokenKey);
      }
    } else {
      // Aucune donnée sauvegardée, utilisateur non connecté
      _currentUser = null;
      _currentToken = null;
    }
  }

  /// Charge les données utilisateur depuis l'API
  Future<void> _loadUserFromAPI() async {
    if (_currentToken == null) return;
    
    try {
      final userData = await AuthService.instance.getCurrentUser();
      _currentUser = userData;
    } catch (e) {
      // En cas d'erreur, garder les données actuelles
      print('Erreur lors du chargement des données utilisateur: $e');
    }
  }

  /// Sauvegarde les données utilisateur
  Future<void> _saveUserData() async {
    final prefs = await SharedPreferences.getInstance();
    
    if (_currentUser != null) {
      await prefs.setString(_userDataKey, jsonEncode(_currentUser));
    }
    
    if (_currentToken != null) {
      await prefs.setString(_tokenKey, _currentToken!);
    }
  }

  /// Vérifie si l'utilisateur est connecté
  bool get isLoggedIn => _currentUser != null && _currentToken != null;

  /// Retourne les données de l'utilisateur actuel
  Map<String, dynamic>? get currentUser => _currentUser;

  /// Retourne le token d'authentification
  String? get currentToken => _currentToken;

  /// Retourne le nom de l'utilisateur
  String get userName => _currentUser?['name'] ?? 'Utilisateur';

  /// Retourne le rôle de l'utilisateur
  String get userRole {
    final role = _currentUser?['role'] ?? 'user';
    // Convertir le rôle en français
    switch (role) {
      case 'admin':
        return 'Administrateur';
      case 'user':
        return 'Utilisateur';
      case 'moderator':
        return 'Modérateur';
      default:
        return 'Utilisateur';
    }
  }

  /// Retourne l'URL de l'avatar
  String get avatarUrl => _currentUser?['avatar'] ?? 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop&auto=format&q=60';

  /// Retourne l'email de l'utilisateur
  String get userEmail => _currentUser?['email'] ?? '';

  /// Met à jour les données utilisateur après connexion/inscription
  Future<void> setUserData(Map<String, dynamic> userData, String token) async {
    _currentUser = userData;
    _currentToken = token;
    await _saveUserData();
  }

  /// Rafraîchit les données utilisateur depuis l'API
  Future<void> refreshUserData() async {
    await _loadUserFromAPI();
  }

  /// Déconnexion de l'utilisateur
  Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_userDataKey);
    await prefs.remove(_tokenKey);
    
    _currentUser = null;
    _currentToken = null;
  }

  /// Met à jour le profil utilisateur
  Future<void> updateProfile(Map<String, dynamic> updatedData) async {
    if (_currentUser != null) {
      _currentUser = {..._currentUser!, ...updatedData};
      await _saveUserData();
    }
  }
}
