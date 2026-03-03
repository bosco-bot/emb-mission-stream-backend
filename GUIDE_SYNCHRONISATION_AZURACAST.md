# 📘 GUIDE: Comment constituer une playlist AzuraCast depuis Laravel

## 🎯 PRINCIPE

Pour constituer une playlist dans AzuraCast, il faut:
1. **Uploader le fichier audio** dans Laravel
2. **Copier le fichier** dans le container AzuraCast  
3. **Scanner les médias** dans AzuraCast
4. **Créer la playlist** dans AzuraCast
5. **Ajouter les fichiers** à la playlist via l'interface web AzuraCast

## 📱 DEPUIS FLUTTER WEB

### Étape 1: Upload du fichier audio
```dart
// Upload via l'API Laravel
POST https://radio.embmission.com/api/media/upload
Content-Type: multipart/form-data

file: <fichier_audio.mp3>
metadata: {
  title: Mon audio,
  artist: Artiste
}
```

### Étape 2: Créer une playlist dans Laravel
```dart
// Création de la playlist
POST https://radio.embmission.com/api/playlists
Content-Type: application/json

{
  name: Ma Playlist,
  description: Description,
  type: audio,
  is_loop: false,
  is_shuffle: true
}
```

### Étape 3: Copier le fichier vers AzuraCast
```dart
// Copie du fichier vers AzuraCast
POST https://radio.embmission.com/api/azuracast/copy-file
Content-Type: application/json

{
  media_file_id: 17  // ID du fichier uploadé
}
```

### Étape 4: Scanner les médias dans AzuraCast
```dart
// Déclencher le scan des médias
POST https://radio.embmission.com/api/azuracast/scan
```

### Étape 5: Créer la playlist dans AzuraCast
```dart
// Synchroniser la playlist
POST https://radio.embmission.com/api/playlists/34/sync
```

## 🌐 INTERFACE WEB AZURACAST

Une fois les fichiers copiés, vous devez:
1. **Accéder à AzuraCast**: http://15.235.86.98:8080
2. **Aller dans Media** pour vérifier que les fichiers sont visibles
3. **Aller dans Playlists** pour voir votre playlist
4. **Cliquer sur Edit** sur votre playlist
5. **Ajouter les médias** en les glissant dans la playlist
6. **Sauvegarder**

## ⚡ SOLUTION RAPIDE (RECOMMANDÉE)

Pour tester rapidement avec :

1. **Le fichier est déjà copié** dans AzuraCast ✅
2. **La playlist existe** (ID: 4 - Playlist Matinale) ✅
3. **Il reste à ajouter le fichier** à la playlist via l'interface web

### Commande manuelle:
```bash
# Connectez-vous à AzuraCast: http://15.235.86.98:8080
# Login: admin / Mot de passe fourni
# Allez dans Playlists → Playlist Matinale → Edit
# Ajoutez verification_finale.mp3 à la playlist
# Sauvegardez
```

## 📊 STATUT ACTUEL

- ✅ Fichier  présent dans AzuraCast
- ✅ Playlist Playlist Matinale créée (ID: 4)
- ⚠️ Fichier pas encore ajouté à la playlist (à faire via interface web)

## 🔧 APIs DISPONIBLES

| Endpoint | Méthode | Description |
|----------|---------|-------------|
|  | POST | Upload un fichier audio |
|  | POST | Crée une playlist Laravel |
|  | POST | Copie un fichier vers AzuraCast |
|  | POST | Scanner les médias AzuraCast |
|  | POST | Synchronise une playlist |
|  | GET | Liste les fichiers AzuraCast |

## ✅ WORKFLOW COMPLET

1. Flutter Web → Upload fichier → Laravel (✅ Fonctionne)
2. Laravel → Copie fichier → AzuraCast (✅ Fonctionne)  
3. AzuraCast → Scan médias → Détection fichier (✅ Fonctionne)
4. Laravel → Crée playlist → AzuraCast (✅ Fonctionne)
5. Interface Web → Ajoute fichier → Playlist (⚠️ Manuel)

## 🎯 PROCHAINES ÉTAPES

Pour automatiser complètement l'étape 5, il faudrait:
- Utiliser l'API AzuraCast pour ajouter les médias aux playlists
- Actuellement, l'API a des limitations (erreur 405 - Method Not Allowed)
- Solution temporaire: Interface web AzuraCast

