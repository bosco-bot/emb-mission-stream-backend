# 📡 GUIDE D'INTÉGRATION WEBSOCKET (Laravel Reverb) - Flutter Web

## 🎯 Vue d'ensemble

Laravel Reverb est configuré et opérationnel. Ce document fournit toutes les informations nécessaires pour intégrer les WebSockets dans une page Flutter Web.

---

## 🔌 INFORMATIONS DE CONNEXION

### Configuration WebSocket

| Paramètre | Valeur | Description |
|-----------|--------|-------------|
| **Broadcaster** | `reverb` | Type de serveur WebSocket |
| **Host** | `tv.embmission.com` | Domaine du serveur (production) |
| **Port WS** | `8444` | Port WebSocket non sécurisé |
| **Port WSS** | `8444` | Port WebSocket sécurisé (TLS) |
| **Scheme** | `https` | Protocole (toujours HTTPS en production) |
| **Force TLS** | `true` | Forcer l'utilisation de WSS |
| **App Key** | `mn7s2vqxddwgxiyui68x` | Clé d'application (confirmée) |
| **App ID** | `501782` | ID d'application (confirmé) |
| **Auth Endpoint** | `https://tv.embmission.com/broadcasting/auth` | Endpoint d'authentification |

### Variables d'environnement (confirmées depuis le serveur)

Les valeurs réelles confirmées dans le fichier `.env` :
- `REVERB_APP_KEY` : `mn7s2vqxddwgxiyui68x` ✅
- `REVERB_APP_ID` : `501782` ✅
- `REVERB_HOST` : `localhost` (serveur interne, exposé via Nginx sur `tv.embmission.com`)
- `REVERB_PORT` : `6001` (serveur interne, exposé via Nginx sur port `8444`)
- `REVERB_SCHEME` : `http` (serveur interne, exposé via Nginx en `https`)

**Note importante :** Le serveur Reverb écoute sur `localhost:6001` en interne, mais est exposé publiquement via Nginx reverse proxy sur `tv.embmission.com:8444` avec SSL/TLS.

### Architecture serveur (Information technique - Flutter n'a pas besoin de ces détails)

```
Client Flutter Web
    ↓ (WSS)
tv.embmission.com:8444 (Nginx avec SSL)
    ↓ (reverse proxy interne - transparent pour Flutter)
localhost:6001 (Laravel Reverb)
```

**Important pour Flutter :** Flutter se connecte uniquement à `tv.embmission.com:8444`. Le reverse proxy Nginx et la redirection vers `http://127.0.0.1:6001/app/` sont gérés automatiquement par le serveur et sont **transparents pour le client Flutter**.

**Ce que Flutter doit connaître :**
- ✅ Host : `tv.embmission.com`
- ✅ Port : `8444`
- ✅ Protocol : `WSS` (WebSocket Secure)
- ✅ App Key : `mn7s2vqxddwgxiyui68x`

**Ce que Flutter n'a PAS besoin de connaître :**
- ❌ Le port interne du serveur Reverb (`6001`)
- ❌ L'URL interne (`127.0.0.1:6001/app/`)
- ❌ La configuration Nginx
- ❌ Les détails du reverse proxy

---

## 📋 RÉFÉRENCE : IMPLÉMENTATION RÉELLE

### Code source de référence

L'implémentation JavaScript réelle se trouve dans `/var/www/emb-mission/resources/views/watch.blade.php`.

**Points clés de l'implémentation :**

1. **Initialisation Echo** (lignes 94-113) :
   - Utilise `config("broadcasting.connections.reverb.key")` pour la clé
   - Détection automatique de l'environnement (local vs production)
   - Force TLS si host = `tv.embmission.com`

2. **Fonction `initWebSocket()`** (lignes 1094-1179) :
   - S'abonne au channel `webtv-stream-status`
   - Écoute l'événement `.stream.status.changed` (avec le point)
   - Gère les erreurs, déconnexions et reconnexions
   - Active un fallback polling en cas d'échec

3. **Fonction `handleStreamData()`** (lignes 165-221) :
   - Traite les données de la même manière pour WebSocket et polling
   - Gère les modes : `live`, `vod`, `paused`
   - Évite les reconnexions inutiles (vérifie si le stream est identique)

4. **Polling de sécurité** (lignes 258-263) :
   - Même avec WebSocket actif, un polling toutes les 30 secondes est maintenu
   - Sert de filet de sécurité en cas de problème WebSocket

---

## 📡 CHANNEL ET ÉVÉNEMENT

### Channel Public

**Nom du channel :** `webtv-stream-status`

**Type :** Channel public (pas d'authentification requise)

**Accès :** Tous les clients peuvent s'abonner sans authentification

### Événement

**Nom de l'événement :** `stream.status.changed`

**Format complet :** `.stream.status.changed` (avec le point au début)

**Fréquence :** Émis uniquement lors d'un changement de statut (Live ↔ VoD)

---

## 📦 STRUCTURE DES DONNÉES

### ⚠️ IMPORTANT : Même structure que le polling

**Les données reçues via WebSocket ont exactement la même structure que celles reçues via l'API polling** (`/api/webtv-auto-playlist/current-url`).

Si votre code Flutter gère déjà le VoD/Live avec le polling via `WebTvService.instance.getAutoPlaylistCurrentUrl()`, **vous pouvez réutiliser exactement la même logique de traitement** pour les données WebSocket.

### Structure des données (référence rapide)

Les données WebSocket contiennent les mêmes champs que l'API polling :

```dart
{
  "mode": "live" | "vod" | "paused",
  "stream_id": String?,
  "stream_name": String?,
  "url": String?,
  "stream_url": String?,
  "sync_timestamp": int,
  "item_title": String?,
  "item_id": int?,
  "current_time": double,
  "duration": double?,
  "is_finished": bool
}
```

### Intégration simplifiée

**Au lieu de créer une nouvelle fonction de traitement**, vous pouvez simplement appeler votre fonction existante qui traite les données du polling :

```dart
// Dans votre WebSocket handler
void _handleStreamStatusChanged(dynamic data) {
  // Les données WebSocket ont la même structure que getAutoPlaylistCurrentUrl()
  // Vous pouvez réutiliser votre logique existante
  final streamData = Map<String, dynamic>.from(data);
  
  // Appeler votre fonction existante qui traite les données du polling
  _processStreamData(streamData); // Votre fonction existante
}
```

**Avantage :** Pas besoin de dupliquer le code ! Le WebSocket remplace simplement le polling, mais utilise la même logique de traitement.

---

## 💻 INTÉGRATION FLUTTER WEB

### Package requis

Pour Flutter Web, vous devez utiliser un package compatible avec Laravel Echo / Pusher :

**Option 1 : `pusher_channels_flutter`** (Recommandé - compatible avec Laravel Reverb)
```yaml
dependencies:
  pusher_channels_flutter: ^2.2.0
  http: ^1.1.0  # Pour le fallback polling
```

**Option 2 : `web_socket_channel`** (Bas niveau - nécessite plus de code)
```yaml
dependencies:
  web_socket_channel: ^2.4.0
  http: ^1.1.0
```

**Note :** L'implémentation JavaScript dans `watch.blade.php` utilise Laravel Echo avec Pusher JS. Le package `pusher_channels_flutter` est l'équivalent Flutter et suit les mêmes principes.

### Exemple d'implémentation avec `pusher_channels_flutter`

**Note importante :** Cette implémentation est basée sur l'implémentation JavaScript réelle utilisée dans `watch.blade.php`. Les mêmes principes s'appliquent à Flutter Web.

```dart
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'dart:async';

class WebSocketService {
  PusherChannelsFlutter pusher;
  Channel? channel;
  
  // Configuration (valeurs confirmées depuis le serveur)
  static const String appKey = 'mn7s2vqxddwgxiyui68x'; // ✅ Confirmé
  static const String appId = '501782'; // ✅ Confirmé (optionnel pour Reverb)
  static const String host = 'tv.embmission.com';
  static const int port = 8444;
  static const String channelName = 'webtv-stream-status';
  static const String eventName = '.stream.status.changed'; // ✅ IMPORTANT: avec le point au début (comme dans watch.blade.php ligne 1106)
  
  Future<void> connect() async {
    try {
      pusher = PusherChannelsFlutter.getInstance();
      
      await pusher.init(
        apiKey: appKey,
        cluster: '', // Vide pour Reverb
        hostEndPoint: host,
        wsPort: port,
        wssPort: port,
        encrypted: true, // Force WSS
        activityTimeout: 30000,
        pongTimeout: 6000,
        maxReconnectionAttempts: 6,
        maxReconnectionGap: 10,
        enableLogging: true,
      );
      
      await pusher.connect();
      
      // S'abonner au channel
      channel = await pusher.subscribe(channelName: channelName);
      
      // Écouter l'événement (IMPORTANT: le nom de l'événement doit inclure le point)
      // Dans watch.blade.php, on utilise: '.stream.status.changed'
      channel?.bind(eventName: eventName, (event) {
        print('📡 Event WebSocket reçu: ${event.eventName}');
        _handleStreamStatusChanged(event.data);
      });
      
      // Gestion des erreurs de connexion (comme dans watch.blade.php)
      pusher.onConnectionStateChange((currentState, previousState) {
        print('WebSocket State: $previousState -> $currentState');
        
        // Gérer les changements d'état comme dans watch.blade.php
        switch (currentState) {
          case ConnectionState.CONNECTED:
            print('✅ WebSocket connecté');
            // Désactiver le fallback polling si actif
            _deactivatePollingFallback();
            break;
          case ConnectionState.DISCONNECTED:
            print('⚠️ WebSocket déconnecté, activer le fallback polling');
            // Activer le fallback polling
            _activatePollingFallback();
            break;
          case ConnectionState.CONNECTING:
            print('🔄 Connexion en cours...');
            break;
          case ConnectionState.RECONNECTING:
            print('🔄 Reconnexion en cours...');
            break;
        }
      });
      
      pusher.onError((error) {
        print('❌ Erreur WebSocket: $error');
        // Activer le fallback polling en cas d'erreur
        _activatePollingFallback();
      });
      
    } catch (e) {
      print('Erreur de connexion WebSocket: $e');
    }
  }
  
  void _handleStreamStatusChanged(dynamic data) {
    try {
      // Les données arrivent déjà comme Map<String, dynamic> depuis pusher_channels_flutter
      // Si c'est une String, la parser en JSON
      Map<String, dynamic> streamData;
      if (data is String) {
        streamData = json.decode(data);
      } else if (data is Map) {
        streamData = Map<String, dynamic>.from(data);
      } else {
        print('Format de données inattendu: ${data.runtimeType}');
        return;
      }
      
      print('📡 Event WebSocket reçu: mode=${streamData['mode']}');
      
      // ✅ IMPORTANT : Réutiliser votre fonction existante qui traite les données du polling
      // Les données WebSocket ont exactement la même structure que getAutoPlaylistCurrentUrl()
      // Donc vous pouvez appeler directement votre fonction de traitement existante
      
      // Option 1 : Si vous avez une fonction qui traite les données du polling
      // _processStreamData(streamData); // Votre fonction existante
      
      // Option 2 : Si vous utilisez setState avec _autoPlaylistCurrentUrl (comme dans webtv_player_page.dart)
      // setState(() {
      //   _autoPlaylistCurrentUrl = streamData;
      //   // Votre logique existante pour mettre à jour l'interface
      // });
      
      // Option 3 : Exemple de traitement direct (si vous n'avez pas de fonction existante)
      _updatePlayerFromStreamData(streamData);
      
    } catch (e) {
      print('Erreur de parsing des données WebSocket: $e');
    }
  }
  
  // Fonction simplifiée - réutilisez votre logique existante si possible
  void _updatePlayerFromStreamData(Map<String, dynamic> streamData) {
    final String mode = streamData['mode'] ?? 'vod';
    final String? streamUrl = streamData['stream_url'] ?? streamData['url'];
    
    // Utiliser la même logique que votre code de polling existant
    // Par exemple, si vous avez déjà une fonction qui gère le mode live/vod
    if (mode == 'live') {
      // Votre logique existante pour le mode live
      print('📺 Mode Live: $streamUrl');
    } else if (mode == 'vod') {
      // Votre logique existante pour le mode VoD
      print('📡 Mode VoD: ${streamData['item_title']}');
    }
  }
  
  void _updatePlayer({
    required String mode,
    String? streamUrl,
    String? streamId,
    String? itemTitle,
    int? itemId,
    required double currentTime,
    double? duration,
    required bool isFinished,
    required int syncTimestamp,
  }) {
    // Implémenter la logique de mise à jour du lecteur
    // Cette fonction doit gérer les mêmes cas que handleStreamData dans watch.blade.php
    
    if (mode == 'paused') {
      // Afficher un message de pause
      print('⏸️ Diffusion en pause');
      return;
    }
    
    if (mode == 'live') {
      // Basculer vers le mode Live
      // Vérifier que ce n'est pas le même stream pour éviter les reconnexions inutiles
      if (streamUrl != null) {
        print('📺 Mode Live: $streamUrl');
        // Charger l'URL du stream live
        // Note: En JavaScript, on vérifie aussi si c'est le même stream_id pour éviter les reconnexions
      }
    } else if (mode == 'vod') {
      // Basculer vers le mode VoD
      if (streamUrl != null) {
        print('📡 Mode VoD: $itemTitle (${itemId})');
        // Charger l'URL du VoD
        // Vérifier si la vidéo est terminée (isFinished ou currentTime >= duration)
        if (isFinished || (duration != null && currentTime >= duration - 0.5)) {
          print('🏁 Vidéo terminée, passer à la suivante');
          // Demander la vidéo suivante
        }
      }
    } else {
      print('❌ Mode inconnu: $mode');
    }
  }
  
  // Fallback vers polling HTTP (comme dans watch.blade.php)
  Timer? _pollingTimer;
  bool _pollingFallbackActive = false;
  
  void _activatePollingFallback() {
    if (_pollingFallbackActive) return;
    
    _pollingFallbackActive = true;
    print('🔄 Activation du fallback polling (comme dans watch.blade.php)');
    
    // Polling de sécurité toutes les 30 secondes (comme dans watch.blade.php ligne 258-263)
    _pollingTimer = Timer.periodic(Duration(seconds: 30), (timer) {
      _fetchStatusFromAPI();
    });
  }
  
  void _deactivatePollingFallback() {
    if (!_pollingFallbackActive) return;
    
    _pollingFallbackActive = false;
    _pollingTimer?.cancel();
    _pollingTimer = null;
    print('✅ Désactivation du fallback polling (WebSocket actif)');
  }
  
  Future<void> _fetchStatusFromAPI() async {
    // Appel API de fallback: GET /api/webtv-auto-playlist/current-url
    // (même endpoint que dans watch.blade.php ligne 130)
    try {
      final response = await http.get(
        Uri.parse('https://tv.embmission.com/api/webtv-auto-playlist/current-url'),
      );
      
      if (response.statusCode == 200) {
        final jsonData = json.decode(response.body);
        if (jsonData['success'] == true && jsonData['data'] != null) {
          final data = jsonData['data']['data'] ?? jsonData['data'];
          _handleStreamStatusChanged(data);
        }
      }
    } catch (e) {
      print('Erreur API de fallback: $e');
    }
  }
  
  void disconnect() {
    channel?.unbind(eventName: eventName);
    pusher.unsubscribe(channelName: channelName);
    pusher.disconnect();
    _pollingTimer?.cancel();
    _pollingTimer = null;
  }
}
```

### ⚠️ PRÉREQUIS : Initialiser Laravel Echo dans le HTML

**IMPORTANT** : Avant d'utiliser le WebSocket dans Flutter, vous devez initialiser Laravel Echo dans votre fichier `web/index.html`.

**Option 1 : Utiliser la fonction existante** (si `initLaravelEcho` existe déjà dans `index.html`) :

Dans votre code Flutter, appeler la fonction JavaScript :

```dart
// Dans initState() ou _initWebSocket()
if (kIsWeb) {
  try {
    // Appeler la fonction initLaravelEcho existante dans index.html
    js_util.callMethod(
      js_util.globalThis,
      'initLaravelEcho',
      ['mn7s2vqxddwgxiyui68x'], // ✅ Clé confirmée
    );
    print('✅ Laravel Echo initialisé via initLaravelEcho()');
  } catch (e) {
    print('⚠️ Erreur initialisation Echo: $e');
  }
}
```

**Option 2 : Initialiser directement dans le HTML** (si la fonction n'existe pas) :

Ajoutez ce code dans `<head>` ou avant la fermeture de `<body>` dans `EMB-Mission_RTV-main/web/index.html` :

```html
<!-- Laravel Echo & Pusher JS pour WebSocket -->
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

<script>
  // Configuration Laravel Echo pour Reverb
  window.Pusher = Pusher;
  
  const isLocalDev = window.location.hostname === 'localhost' || 
                    window.location.hostname === '127.0.0.1' ||
                    window.location.hostname.startsWith('192.168.') ||
                    window.location.hostname.startsWith('10.') ||
                    (window.location.port !== '' && window.location.port !== '443' && window.location.port !== '80');
  
  const wsHost = isLocalDev ? 'tv.embmission.com' : window.location.hostname;
  const wsPort = 8444;
  const wssPort = 8444;
  const useTLS = wsHost === 'tv.embmission.com';
  
  const echoConfig = {
    broadcaster: 'reverb',
    key: 'mn7s2vqxddwgxiyui68x', // ✅ Clé confirmée
    wsHost: wsHost,
    wsPort: wsPort,
    wssPort: wssPort,
    forceTLS: useTLS,
    enabledTransports: useTLS ? ['wss'] : ['ws', 'wss'],
    disableStats: true,
    cluster: '',
    authEndpoint: isLocalDev ? 'https://tv.embmission.com/broadcasting/auth' : '/broadcasting/auth',
    auth: {
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      }
    }
  };
  
  window.Echo = new Echo(echoConfig);
  
  console.log("📡 Laravel Echo initialisé pour Reverb", {
    environment: isLocalDev ? 'local' : 'production',
    wsHost: wsHost,
    wsPort: wsPort,
    useTLS: useTLS
  });
</script>
```

### Déclencher un snapshot WebSocket

Une fois connecté, appelez **une seule fois** l'API `current-url` avec `emit_event=1` pour forcer l'envoi d'un snapshot WebSocket (permet à tous les clients d'obtenir immédiatement l'état courant) :

```dart
Future<void> _requestWebSocketSnapshot() async {
  final uri = Uri.parse(
    '${ApiConfig.webtvAutoPlaylistCurrentUrl}?emit_event=1&snapshot_source=flutter&ts=${DateTime.now().millisecondsSinceEpoch}',
  );
  await http.get(uri);
}
```

> Côté backend, un anti-spam limite ces snapshots (cooldown de 2 secondes par mode).

### Exemple d'intégration dans `webtv_player_page.dart`

**Basé sur votre code existant**, voici comment intégrer le WebSocket :

```dart
// ✅ IMPORTANT : Ajouter cet import en haut du fichier (si pas déjà présent)
import 'dart:js_util' as js_util;

// Dans _WebtvPlayerPageState, ajouter ces variables (après les variables existantes) :
bool _websocketConnected = false;
bool _websocketFallbackActive = false;
dynamic _websocketChannel;
dynamic _websocketEcho;
// Note: _statusTimer existe déjà dans votre code, pas besoin de le redéclarer

@override
void initState() {
  super.initState();
  if (kIsWeb) {
    // Option 1 : Si initLaravelEcho existe dans index.html, l'appeler
    try {
      js_util.callMethod(
        js_util.globalThis,
        'initLaravelEcho',
        ['mn7s2vqxddwgxiyui68x'], // ✅ Clé confirmée
      );
    } catch (e) {
      print('⚠️ initLaravelEcho non disponible, Echo doit être initialisé dans index.html');
    }
    
    // Attendre un peu que Laravel Echo soit initialisé dans le HTML
    Future.delayed(const Duration(milliseconds: 500), () {
      _initWebSocket(); // Initialiser WebSocket sur le web
    });
  }
  _loadData();
  _startPeriodicRefresh(); // Garder le polling comme fallback
}

// Nouvelle fonction pour initialiser le WebSocket
void _initWebSocket() {
  try {
    // Vérifier que les bibliothèques JavaScript sont disponibles
    if (!js_util.hasProperty(js_util.globalThis, 'Echo')) {
      print('⚠️ Laravel Echo non disponible, utilisation du polling uniquement');
      _websocketFallbackActive = true;
      return;
    }
    
    // Récupérer Echo depuis le contexte JavaScript global
    _websocketEcho = js_util.getProperty(js_util.globalThis, 'Echo');
    
    // S'abonner au channel public
    _websocketChannel = js_util.callMethod(
      _websocketEcho,
      'channel',
      ['webtv-stream-status'],
    );
    
    // Écouter les changements de statut
    js_util.callMethod(
      _websocketChannel,
      'listen',
      [
        '.stream.status.changed',
        js_util.allowInterop((data) {
          print('📡 Event WebSocket reçu: $data');
          _websocketConnected = true;
          
          // Convertir les données JavaScript en Map Dart
          final streamData = _jsToDartMap(data);
          
          // ✅ RÉUTILISER VOTRE LOGIQUE EXISTANTE
          // Mettre à jour les données comme si elles venaient du polling
          if (mounted) {
            setState(() {
              // Mettre à jour _autoPlaylistCurrentUrl avec les données WebSocket
              // (même structure que getAutoPlaylistCurrentUrl())
              _autoPlaylistCurrentUrl = streamData;
              
              // Mettre à jour aussi _autoPlaylistStatus si nécessaire
              // (pour que _isLiveMode() fonctionne correctement)
              if (_autoPlaylistStatus == null) {
                _autoPlaylistStatus = {};
              }
              // Mettre à jour le mode dans status si présent dans les données
              if (streamData['mode'] != null) {
                _autoPlaylistStatus!['mode'] = streamData['mode'];
                _autoPlaylistStatus!['is_live'] = streamData['mode'] == 'live';
              }
              
              // Détecter le changement de mode/URL (même logique que _loadData)
              final currentMode = _isLiveMode() ? 'live' : 'vod';
              final currentVideoUrl = _getCurrentVideoUrl();
              final modeChanged = _lastPlayerMode != null && _lastPlayerMode != currentMode;
              final urlChanged = _lastVideoUrl != null && _lastVideoUrl != currentVideoUrl;
              final currentItemTitle = streamData['item_title']?.toString() ?? '';
              final contentChanged = currentItemTitle.isNotEmpty && 
                                     currentItemTitle != (_lastItemTitle ?? '');
              
              if (modeChanged || urlChanged || contentChanged) {
                print('🔄 Changement détecté via WebSocket: mode=$_lastPlayerMode->$currentMode');
                _lastPlayerMode = currentMode;
                _lastVideoUrl = currentVideoUrl;
                _lastItemTitle = currentItemTitle;
                _manualRefreshTs = DateTime.now().millisecondsSinceEpoch;
              } else {
                // Mettre à jour quand même les variables de suivi
                _lastPlayerMode = currentMode;
                _lastVideoUrl = currentVideoUrl;
                _lastItemTitle = currentItemTitle;
              }
            });
          }
        }),
      ],
    );
    
    // Gestion des erreurs de connexion
    final pusher = js_util.getProperty(_websocketEcho, 'connector');
    final connection = js_util.getProperty(pusher, 'pusher');
    final conn = js_util.getProperty(connection, 'connection');
    
    js_util.callMethod(
      conn,
      'bind',
      [
        'error',
        js_util.allowInterop((err) {
          print('❌ Erreur WebSocket: $err');
          _websocketConnected = false;
          if (!_websocketFallbackActive) {
            _websocketFallbackActive = true;
            _startPeriodicRefresh(); // Activer le polling de fallback
          }
        }),
      ],
    );
    
    js_util.callMethod(
      conn,
      'bind',
      [
        'connected',
        js_util.allowInterop(() {
          print('✅ WebSocket connecté');
          _websocketConnected = true;
          _websocketFallbackActive = false;
          // Réduire le polling si WebSocket fonctionne
          _statusTimer?.cancel();
          _statusTimer = null;
        }),
      ],
    );
    
    js_util.callMethod(
      conn,
      'bind',
      [
        'disconnected',
        js_util.allowInterop(() {
          print('⚠️ WebSocket déconnecté, activation du fallback polling');
          _websocketConnected = false;
          _websocketFallbackActive = true;
          _startPeriodicRefresh();
        }),
      ],
    );
    
    print('📡 WebSocket initialisé et en écoute');
  } catch (error) {
    print('❌ Erreur initialisation WebSocket: $error');
    _websocketFallbackActive = true;
    _startPeriodicRefresh();
  }
}

// Fonction utilitaire pour convertir les données JavaScript en Map Dart
Map<String, dynamic> _jsToDartMap(dynamic jsData) {
  final result = <String, dynamic>{};
  try {
    // Si c'est déjà un Map, le retourner
    if (jsData is Map) {
      return Map<String, dynamic>.from(jsData);
    }
    
    // Sinon, essayer de convertir depuis JavaScript
    final keys = js_util.objectKeys(jsData);
    for (var i = 0; i < js_util.getProperty(keys, 'length'); i++) {
      final key = js_util.getProperty(keys, i.toString());
      final value = js_util.getProperty(jsData, key);
      result[key.toString()] = _jsToDartValue(value);
    }
  } catch (e) {
    print('⚠️ Erreur conversion JS->Dart: $e');
  }
  return result;
}

dynamic _jsToDartValue(dynamic value) {
  if (value == null) return null;
  if (value is String || value is num || value is bool) return value;
  if (js_util.hasProperty(value, 'length')) {
    // C'est probablement un tableau
    final list = <dynamic>[];
    final length = js_util.getProperty(value, 'length');
    for (var i = 0; i < length; i++) {
      list.add(_jsToDartValue(js_util.getProperty(value, i.toString())));
    }
    return list;
  }
  if (js_util.hasProperty(value, 'constructor') && 
      js_util.getProperty(value, 'constructor') == js_util.getProperty(js_util.globalThis, 'Object')) {
    // C'est un objet JavaScript
    return _jsToDartMap(value);
  }
  return value.toString();
}

// Modifier _startPeriodicRefresh pour tenir compte du WebSocket
void _startPeriodicRefresh() {
  _statusTimer?.cancel();
  
  // ✅ Désactiver le polling si WebSocket est connecté
  if (_websocketConnected && !_websocketFallbackActive) {
    print('📡 WebSocket actif - Polling désactivé');
    // Garder un polling de sécurité toutes les 30 secondes (comme dans watch.blade.php)
    _statusTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (!mounted) return;
      if (_websocketFallbackActive || !_websocketConnected) {
        _loadData(showLoader: false);
      }
    });
    return;
  }
  
  // ✅ Activer le polling uniquement si WebSocket n'est pas disponible
  print('🔄 Polling activé (WebSocket non disponible ou en fallback)');
  _statusTimer = Timer.periodic(const Duration(seconds: 5), (_) {
    if (!mounted) return;
    _loadData(showLoader: false);
  });
}

@override
void dispose() {
  _statusTimer?.cancel();
  // Nettoyer le WebSocket si nécessaire
  if (_websocketChannel != null) {
    try {
      js_util.callMethod(_websocketChannel, 'stopListening', ['.stream.status.changed']);
      js_util.callMethod(_websocketEcho, 'leave', ['webtv-stream-status']);
    } catch (e) {
      print('⚠️ Erreur nettoyage WebSocket: $e');
    }
  }
  super.dispose();
}
```

**Points importants :**
1. ✅ Réutilise votre logique existante (`_isLiveMode()`, `_getCurrentVideoUrl()`, etc.)
2. ✅ Met à jour `_autoPlaylistCurrentUrl` comme le polling
3. ✅ Déclenche les mêmes détections de changement (mode, URL, contenu)
4. ✅ Garde le polling comme fallback de sécurité
5. ✅ Compatible avec votre structure de code actuelle

---

## 🔐 AUTHENTIFICATION

### Channel Public

Le channel `webtv-stream-status` est **public** et ne nécessite **pas d'authentification**.

Cependant, si vous devez vous connecter à d'autres channels privés à l'avenir, utilisez :

**Endpoint d'authentification :** `https://tv.embmission.com/broadcasting/auth`

**Méthode :** POST

**Headers requis :**
```
Content-Type: application/json
X-CSRF-TOKEN: <token_csrf>
Authorization: Bearer <token_sanctum> (si authentifié)
```

**Body :**
```json
{
  "socket_id": "<socket_id>",
  "channel_name": "webtv-stream-status"
}
```

---

## 🚨 GESTION DES ERREURS

### Reconnexion automatique

Le package `pusher_channels_flutter` gère automatiquement la reconnexion. Configurez :

```dart
maxReconnectionAttempts: 6,
maxReconnectionGap: 10,
```

### Fallback vers Polling

En cas d'échec de connexion WebSocket, implémentez un fallback vers le polling HTTP :

```dart
class StreamStatusService {
  WebSocketService? _webSocket;
  Timer? _pollingTimer;
  bool _useWebSocket = false;
  
  void start() {
    _initWebSocket();
    // Si WebSocket échoue après 5 secondes, utiliser polling
    Timer(Duration(seconds: 5), () {
      if (!_useWebSocket) {
        _startPolling();
      }
    });
  }
  
  Future<void> _initWebSocket() async {
    try {
      _webSocket = WebSocketService();
      await _webSocket!.connect();
      _useWebSocket = true;
    } catch (e) {
      print('WebSocket échoué, utilisation du polling');
      _useWebSocket = false;
    }
  }
  
  void _startPolling() {
    _pollingTimer = Timer.periodic(Duration(seconds: 8), (timer) {
      _fetchStatusFromAPI();
    });
  }
  
  Future<void> _fetchStatusFromAPI() async {
    // Appel API : GET /api/webtv-auto-playlist/current-url
    // ...
  }
}
```

---

## 📊 ÉTAT DE LA CONNEXION

### Événements de connexion

Surveillez l'état de la connexion :

```dart
pusher.onConnectionStateChange((currentState, previousState) {
  switch (currentState) {
    case ConnectionState.CONNECTED:
      print('✅ WebSocket connecté');
      break;
    case ConnectionState.DISCONNECTED:
      print('❌ WebSocket déconnecté');
      break;
    case ConnectionState.CONNECTING:
      print('🔄 Connexion en cours...');
      break;
    case ConnectionState.RECONNECTING:
      print('🔄 Reconnexion en cours...');
      break;
  }
});
```

---

## 🧪 TEST DE CONNEXION

### Test manuel avec JavaScript (pour débogage)

```javascript
// Dans la console du navigateur
const echo = new Echo({
  broadcaster: 'reverb',
  key: 'mn7s2vqxddwgxiyui68x', // ✅ Clé confirmée
  wsHost: 'tv.embmission.com',
  wsPort: 8444,
  wssPort: 8444,
  forceTLS: true,
  enabledTransports: ['wss'],
});

const channel = echo.channel('webtv-stream-status');

channel.listen('.stream.status.changed', (data) => {
  console.log('📡 Event reçu:', data);
});
```

---

## 📝 CHECKLIST D'INTÉGRATION

- [ ] Installer le package `pusher_channels_flutter` ou équivalent
- [ ] Configurer les paramètres de connexion (host, port, key)
- [ ] Implémenter la connexion WebSocket
- [ ] S'abonner au channel `webtv-stream-status`
- [ ] Écouter l'événement `stream.status.changed`
- [ ] Parser les données JSON reçues
- [ ] Mettre à jour l'interface selon le mode (live/vod)
- [ ] Gérer les erreurs de connexion
- [ ] Implémenter un fallback vers polling si nécessaire
- [ ] Tester la connexion en développement
- [ ] Tester la connexion en production

---

## 🔗 RESSOURCES

### Documentation Laravel Reverb
- https://laravel.com/docs/reverb

### Documentation Pusher Channels (compatible)
- https://pusher.com/docs/channels

### Package Flutter recommandé
- https://pub.dev/packages/pusher_channels_flutter

---

## ❓ QUESTIONS FRÉQUENTES

### Q: Le WebSocket fonctionne-t-il en localhost ?
**R:** Oui, mais vous devez vous connecter au serveur de production (`tv.embmission.com`) même en développement local.

### Q: Dois-je gérer l'authentification ?
**R:** Non, le channel `webtv-stream-status` est public et ne nécessite pas d'authentification.

### Q: Que faire si le WebSocket ne se connecte pas ?
**R:** Implémentez un fallback vers le polling HTTP (toutes les 8-10 secondes).

### Q: Les événements sont-ils émis en continu ?
**R:** Non, les événements sont émis uniquement lors d'un changement de statut (Live ↔ VoD).

### Q: Comment obtenir la clé d'application réelle ?
**R:** Les valeurs sont maintenant confirmées dans ce document :
- `REVERB_APP_KEY` : `mn7s2vqxddwgxiyui68x` ✅
- `REVERB_APP_ID` : `501782` ✅
- `REVERB_HOST` : `tv.embmission.com` (pour les clients)
- `REVERB_PORT` : `8444` (pour les clients, avec SSL)

---

## 📞 SUPPORT

Pour toute question ou problème d'intégration, contactez l'équipe backend Laravel.

**Fichiers de référence :**
- `/var/www/emb-mission/app/Events/StreamStatusChanged.php`
- `/var/www/emb-mission/routes/channels.php`
- `/var/www/emb-mission/config/reverb.php`
- `/var/www/emb-mission/config/broadcasting.php`

---

*Document généré le : 2025-01-18*  
*Version : 1.0*

