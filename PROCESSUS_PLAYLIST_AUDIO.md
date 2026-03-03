# 📋 PROCESSUS PLAYLIST AUDIO - ÉTAT ACTUEL

## 🎯 WORKFLOW COMPLET

### 1️⃣ CRÉATION/MISE À JOUR DE PLAYLIST (Flutter → Laravel)

**Route:** `POST /api/playlists`

**Paramètres:**
- `name` (requis) : Nom de la playlist
- `description` (optionnel) : Description
- `type` (optionnel) : Type de playlist (défaut: 'default')
- `is_loop` (boolean) : Lecture en boucle
- `is_shuffle` (boolean) : Lecture aléatoire
- `items` (array) : Liste des IDs de MediaFile
- `auto_sync` (boolean, défaut: true) : Synchronisation automatique avec AzuraCast

**Processus:**
1. Vérifie si playlist existe (par nom, case-insensitive)
2. Si existe → Mise à jour (ajoute nouveaux items, ignore doublons)
3. Si n'existe pas → Création nouvelle playlist
4. Création des PlaylistItem en batch (optimisé)
5. Recalcul des statistiques (durée totale, nombre d'items)
6. Si `auto_sync = true` → Appelle `AzuraCastSyncService::syncPlaylist()`

---

### 2️⃣ SYNCHRONISATION AVEC AZURACAST

**Service:** `AzuraCastSyncService::syncPlaylist()`

**Étapes:**

#### A. Création de la playlist dans AzuraCast (si nécessaire)
- Si `playlist->azuracast_id` est null
- Crée la playlist via API AzuraCast
- Met à jour `playlist->azuracast_id`

#### B. Copie des fichiers audio
- Pour chaque `PlaylistItem` avec `file_type = 'audio'`
- Vérifie si fichier existe déjà dans AzuraCast
- Si n'existe pas:
  - Copie via script shell `copy-to-azuracast.sh`
  - Utilise Docker pour copier dans le container AzuraCast
  - **OPTIMISATION:** Ne scanne PAS après chaque fichier

#### C. Scan des médias (une seule fois)
- Après avoir copié TOUS les fichiers
- Déclenche le scan via API AzuraCast
- Attend: `5s + (nombre_fichiers × 3s)` pour le scan complet
- Met à jour les `azuracast_id` des MediaFile

#### D. Mise à jour des paramètres de playlist
- Nom, description
- `order`: 'random' si shuffle, 'sequential' sinon
- `loop`: selon `is_loop`
- `is_enabled`: true

#### E. Vidage de la playlist AzuraCast
- Supprime tous les médias actuels

#### F. Génération et import du fichier M3U
- Génère un fichier M3U avec tous les fichiers audio
- Format: `#EXTINF:-1,nom_fichier\nnom_fichier\n`
- Importe le M3U dans AzuraCast via API

#### G. Gestion du shuffle et redémarrage
- Si `is_shuffle = true`:
  - Appelle `AzuraCastService::reshufflePlaylist()`
- Redémarre le backend AzuraCast

#### H. Job de finalisation (différé)
- Si des fichiers ont été copiés
- Dispatch `FinalizeAzuraCastSync` avec délai de 2 minutes
- Met à jour les `azuracast_song_id` des PlaylistItem

---

### 3️⃣ JOB DE FINALISATION

**Job:** `FinalizeAzuraCastSync`

**Délai:** 2 minutes après la synchronisation

**Processus:**
1. Attend 10 secondes supplémentaires
2. Pour chaque PlaylistItem:
   - Récupère le `azuracast_song_id` via `AzuraCastService::getMediaIdByFilename()`
   - Met à jour `PlaylistItem->azuracast_song_id`
   - Met à jour `sync_status = 'synced'`

**Note:** Le M3U import a déjà ajouté les médias à la playlist, ce job met juste à jour les IDs.

---

## 🔄 SYNCHRONISATION RAPIDE

**Méthode:** `AzuraCastSyncService::syncPlaylistQuick()`

**Utilisation:** Pour modifications simples (ajout/suppression d'items)

**Différences avec syncPlaylist():**
- ❌ Ne copie PAS les fichiers
- ❌ Ne scanne PAS les médias
- ✅ Met à jour les paramètres
- ✅ Vide la playlist
- ✅ Régénère et importe le M3U
- ✅ Redémarre si shuffle

---

## 📊 MODÈLES ET RELATIONS

### Playlist
- `id`: ID local
- `name`: Nom
- `azuracast_id`: ID dans AzuraCast
- `sync_status`: 'synced', 'pending', 'error'
- `is_shuffle`: boolean
- `is_loop`: boolean

### PlaylistItem
- `playlist_id`: Référence Playlist
- `media_file_id`: Référence MediaFile
- `order`: Ordre dans la playlist
- `azuracast_song_id`: ID du média dans AzuraCast
- `sync_status`: 'synced', 'pending', 'error'

### MediaFile
- `id`: ID local
- `azuracast_id`: ID du fichier dans AzuraCast
- `file_type`: 'audio', 'video', 'image'
- `original_name`: Nom original du fichier

---

## ⚙️ OPTIMISATIONS ACTUELLES

1. **Batch Insert:** Création de tous les PlaylistItem en une seule requête
2. **Scan unique:** Scan des médias une seule fois après copie de tous les fichiers
3. **Vérification d'existence:** Vérifie si fichier existe avant copie
4. **Recherche optimisée:** Utilise `isset()` au lieu de `in_array()` pour vérifier items existants

---

## 🚨 POINTS D'ATTENTION

1. **Délai de finalisation:** 2 minutes peut être insuffisant si scan long
2. **Gestion d'erreurs:** Erreurs silencieuses dans certains cas
3. **Shuffle:** Redémarrage forcé à chaque changement de shuffle
4. **Playlist "default":** AzuraCast peut recréer automatiquement, suppression prévue

---

## 📝 ROUTES API DISPONIBLES

| Route | Méthode | Description |
|-------|---------|-------------|
| `/api/playlists` | GET | Liste toutes les playlists |
| `/api/playlists` | POST | Crée/met à jour une playlist |
| `/api/playlists/{id}` | GET | Détails d'une playlist |
| `/api/playlists/{id}` | PUT | Met à jour une playlist |
| `/api/playlists/{id}` | DELETE | Supprime une playlist |
| `/api/playlists/{id}/sync-azuracast` | POST | Synchronise manuellement avec AzuraCast |

---

## 🔍 LOGS IMPORTANTS

Les logs suivants sont générés:
- `"Début de la synchronisation de la playlist: {name}"`
- `"Fichier copié ({count}): {filename}"`
- `"Tous les fichiers copiés ({count}), déclenchement du scan..."`
- `"Playlist importée dans AzuraCast"`
- `"Synchronisation terminée avec succès"`

