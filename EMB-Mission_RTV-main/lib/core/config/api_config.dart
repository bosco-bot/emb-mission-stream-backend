import 'package:flutter/foundation.dart' show kIsWeb;

/// Configuration centralisée des endpoints API
/// 
/// Détecte automatiquement le domaine actuel (tv.embmission.com ou radio.embmission.com)
/// et adapte l'URL de base de l'API en conséquence.
class ApiConfig {
  ApiConfig._();
  
  /// URL de base de l'API (toujours radio.embmission.com)
  /// 
  /// - Sur rtv.embmission.com → https://radio.embmission.com/api (proxy)
  /// - Sur radio.embmission.com → https://radio.embmission.com/api
  /// - En développement local → https://radio.embmission.com/api
  static String get baseUrl {
    // TOUJOURS utiliser radio.embmission.com pour les APIs Laravel
    return 'https://radio.embmission.com/api';
  }
  
  /// Domaine actuel détecté (pour debug/logs)
  static String get currentDomain {
    if (kIsWeb) {
      return Uri.base.host;
    }
    return 'mobile/desktop';
  }
  
  // ========================================
  // ENDPOINTS AUTHENTIFICATION
  // ========================================
  
  /// POST /api/register
  /// 
  /// Inscription d'un nouveau utilisateur
  static String get register => 'https://tv.embmission.com/api/register';
  
  /// POST /api/auth/login
  /// 
  /// Connexion d'un utilisateur existant
  static String get login => '$baseUrl/auth/login';
  
  /// POST /api/auth/logout
  /// 
  /// Déconnexion de l'utilisateur
  static String get logout => '$baseUrl/auth/logout';
  
  /// POST /api/forgot-password
  /// 
  /// Demande de réinitialisation du mot de passe
  static String get forgotPassword => 'https://tv.embmission.com/api/forgot-password';
  
  /// POST /api/reset-password
  /// 
  /// Réinitialisation du mot de passe
  static String get resetPassword => 'https://tv.embmission.com/api/reset-password';
  
  /// GET /api/auth/me
  /// 
  /// Récupération des informations de l'utilisateur connecté
  static String get me => '$baseUrl/auth/me';
  
  // ========================================
  // ENDPOINTS DASHBOARD
  // ========================================
  
  /// GET /api/dashboard/stats
  /// 
  /// Statistiques du tableau de bord
  static String get dashboardStats => '$baseUrl/dashboard/stats';
  
  // ========================================
  // ENDPOINTS WEBTV
  // ========================================
  
  /// GET /api/webtv/current-stream
  /// 
  /// Récupère le stream WebTV actuel
  static String get webtvCurrentStream => 'https://tv.embmission.com/api/webtv/current-stream';
  
  /// GET /api/webtv/streams
  /// 
  /// Liste des streams WebTV disponibles
  static String get webtvStreams => 'https://tv.embmission.com/api/webtv/streams';
  
  /// GET /api/webtv/stream-status
  /// 
  /// Statut d'un stream spécifique
  static String webtvStreamStatus(String streamId) => 'https://tv.embmission.com/api/webtv/stream-status?stream_id=$streamId';
  
  /// GET /api/webtv/check-connection
  /// 
  /// Vérification de la connexion Ant Media
  static String get webtvCheckConnection => 'https://tv.embmission.com/api/webtv/check-connection';
  
  /// GET /api/webtv/embed-code
  /// 
  /// Code d'embedding pour un stream
  static String webtvEmbedCode(String streamId, {int width = 100, int height = 100}) => 
    'https://tv.embmission.com/api/webtv/embed-code?stream_id=$streamId&width=$width&height=$height';
  
  /// GET /api/webtv/schedule
  /// 
  /// Programme TV
  static String get webtvSchedule => '$baseUrl/webtv/schedule';
  
  /// POST /api/webtv-playlists
  /// 
  /// Création/mise à jour d'une playlist WebTV
  static String get webtvPlaylists => 'https://tv.embmission.com/api/webtv-playlists';
  
  /// DELETE /api/webtv-playlists/{playlistId}/items/{itemId}
  /// 
  /// Suppression d'un item d'une playlist WebTV
  static String webtvPlaylistItem(int playlistId, int itemId) => 
    'https://tv.embmission.com/api/webtv-playlists/$playlistId/items/$itemId';
  
  /// PUT /api/webtv-playlists/{playlistId}/items/order
  /// 
  /// Modification de l'ordre des items d'une playlist WebTV
  static String webtvPlaylistItemsOrder(int playlistId) => 
    'https://tv.embmission.com/api/webtv-playlists/$playlistId/items/order';
  
  /// DELETE /api/webtv-playlists/{playlistId}
  /// 
  /// Suppression d'une playlist WebTV
  static String webtvPlaylist(int playlistId) => 
    'https://tv.embmission.com/api/webtv-playlists/$playlistId';
  
  /// GET /api/webtv-auto-playlist/status
  /// 
  /// Vérification de la connexion et du statut de la playlist automatique
  static String get webtvAutoPlaylistStatus => 'https://tv.embmission.com/api/webtv-auto-playlist/status';
  
  /// GET /api/webtv-auto-playlist/current-url
  /// 
  /// Obtenir l'URL de lecture actuelle de la playlist automatique
  static String get webtvAutoPlaylistCurrentUrl => 'https://tv.embmission.com/api/webtv-auto-playlist/current-url';
  
  /// GET /api/webtv-auto-playlist/obs-params
  /// 
  /// Récupère les paramètres de connexion OBS pour la diffusion
  static String get webtvObsParams => 'https://tv.embmission.com/api/webtv-auto-playlist/obs-params';
  
  /// GET /api/webtv-auto-playlist/public-stream-url
  /// 
  /// Récupère l'URL publique du flux M3U8 pour les spectateurs
  static String get webtvPublicStreamUrl => 'https://tv.embmission.com/api/webtv-auto-playlist/public-stream-url';
  
  // ========================================
  // ENDPOINTS WEBRADIO (AzuraCast via Laravel)
  // ========================================
  
  /// GET /api/webradio/current-stream
  /// 
  /// Récupère les informations du flux en cours de lecture (via AzuraCast)
  static String get webradioCurrentStream => '$baseUrl/webradio/current-stream';
  
  /// GET /api/webradio/track-history
  /// 
  /// Récupère l'historique des pistes jouées (via AzuraCast)
  static String get webradioTrackHistory => '$baseUrl/webradio/track-history';
  
  /// GET /api/webradio/broadcast-info
  /// 
  /// Récupère les informations de diffusion (port, mount point, URLs) (via AzuraCast)
  static String get webradioBroadcastInfo => '$baseUrl/webradio/broadcast-info';
  
  /// POST /api/webradio/control
  /// 
  /// Contrôle la diffusion (start, stop, restart) (via AzuraCast)
  static String get webradioControl => '$baseUrl/webradio/control';
  
  /// GET /api/webradio/streams
  /// 
  /// Liste des streams WebRadio disponibles
  static String get webradioStreams => '$baseUrl/webradio/streams';
  
  /// GET /api/webradio/playlist
  /// 
  /// Playlist actuelle de la WebRadio
  static String get webradioPlaylist => '$baseUrl/webradio/playlist';
  
  // ========================================
  // ENDPOINTS CONTENTS
  // ========================================
  
  /// GET /api/contents
  /// 
  /// Liste des contenus
  static String get contents => '$baseUrl/contents';
  
  /// POST /api/contents
  /// 
  /// Création d'un nouveau contenu
  static String get createContent => '$baseUrl/contents';
  
  /// PUT /api/contents/{id}
  /// 
  /// Mise à jour d'un contenu
  static String updateContent(int id) => '$baseUrl/contents/$id';
  
  /// DELETE /api/contents/{id}
  /// 
  /// Suppression d'un contenu
  static String deleteContent(int id) => '$baseUrl/contents/$id';
  
  // ========================================
  // CONFIGURATION
  // ========================================
  
  /// Timeout des requêtes HTTP (en millisecondes)
  static const int connectTimeout = 30000; // 30 secondes
  static const int receiveTimeout = 30000; // 30 secondes
  static const int sendTimeout = 30000; // 30 secondes
  
  /// Afficher les logs de debug
  static const bool enableLogs = true;
}

