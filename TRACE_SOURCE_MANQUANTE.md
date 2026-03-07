# Traçage précis : badge « Source manquante »

## 1. Où s'affiche le badge

- **Écran** : Playlist Builder (et éventuellement médiathèque / liste des vidéos).
- **URL** : `https://rtv.embmission.com/contents/playlist-builder`
- **Comportement** : Des badges **« Source manquante »** apparaissent sur certaines cartes vidéo.

Le texte et la logique d'affichage sont dans le **build Flutter** déployé :  
`public/main.dart.js` (ligne ~55167) :

- Un `switch` sur une valeur de statut.
- Pour la valeur **`source_missing`** → affichage du libellé **« Source manquante »**.

Donc côté front, le problème vient **dès qu'une entité (fichier / item) a le statut `source_missing`**.

---

## 2. D'où vient la valeur `source_missing`

Elle ne vient **pas** de la table `webtv_playlist_items.sync_status` (qui contient `pending` / `processing` / `synced` / `error`).

Elle vient du **statut de conversion HLS** des **fichiers vidéo** de la médiathèque, exposé par l'API suivante.

### 2.1 Endpoint concerné

| Élément | Valeur |
|--------|--------|
| **Méthode / route** | `GET /api/media/files/conversion-status` |
| **Contrôleur** | `App\Http\Controllers\Api\MediaController` |
| **Méthode** | `getConversionStatus()` |
| **Fichier** | `app/Http/Controllers/Api/MediaController.php` (lignes 1428–1559) |

### 2.2 Logique côté backend (origine du statut)

Pour **chaque** fichier vidéo ayant un `file_path` non null :

1. **Source sur disque**  
   - Chemin calculé : `storage_path('app/media/' . $mediaFile->file_path)`  
   - Exemple : `.../storage/app/media/<file_path>`.  
   - Variable : `$sourceExists = file_exists($sourcePath);`

2. **Règle qui produit « Source manquante »**  
   - Si **`!$sourceExists`** → le backend met **`conversion_status = 'source_missing'`** (ligne 1460).  
   - Aucune autre condition n'est nécessaire pour ce libellé : **le fichier source physique est absent** au chemin dérivé de `media_files.file_path`.

3. **Autres statuts (pour contexte)**  
   - Si la source existe mais pas de `playlist.m3u8` → `not_started`  
   - Si playlist existe mais conversion pas finie → `incomplete`  
   - Si conversion en cours (verrou) → `in_progress`  
   - Si HLS complet → `completed`

La réponse JSON contient pour chaque fichier un objet avec notamment :

- `media_file_id`
- `conversion_status` (dont `source_missing`)
- `source_exists`

Et un `summary` avec le nombre de `source_missing`.

---

## 3. Chaîne complète « API → affichage »

| Étape | Détail |
|-------|--------|
| 1 | L'app (Flutter) récupère la liste des médias (ex. `GET /api/media/files`) et, pour l'affichage des statuts de conversion, appelle **`GET /api/media/files/conversion-status`**. |
| 2 | Le backend parcourt les `media_files` (vidéo, `file_path` non null), calcule `storage_path('app/media/' . file_path)` et fait `file_exists(...)`. |
| 3 | Si le fichier n'existe pas → `conversion_status = 'source_missing'` dans la réponse. |
| 4 | Le front utilise ce `conversion_status` (fusion avec la liste des fichiers ou affichage direct). |
| 5 | Le widget de badge fait un `switch` sur ce statut ; pour `source_missing` il affiche **« Source manquante »**. |

Donc **l'origine du problème est uniquement** :  
**au moins un enregistrement dans `media_files` a un `file_path` tel que le fichier physique correspondant n'existe pas** à `storage_path('app/media/' . file_path)`.

---

## 4. Données et causes possibles

- **Table** : `media_files`
- **Colonne utilisée** : `file_path` (relatif sous `storage/app/media/`).
- **Cause immédiate** :  
  `file_exists(storage_path('app/media/' . $mediaFile->file_path)) === false`

Causes typiques :

1. Fichier supprimé ou déplacé sur le disque alors que la BDD n'a pas été mise à jour.
2. `file_path` erroné (typo, ancien chemin, mauvais enregistrement).
3. Stockage sur un autre volume/NFS non monté au moment de l'appel.
4. Droits ou chemin différent entre l'environnement d'upload et celui qui sert l'API.

---

## 5. Vérification rapide (diagnostic)

Pour savoir **quels** enregistrements déclenchent « Source manquante » :

- Exécuter le script de diagnostic (voir section 6) **ou**
- Appeler en GET :  
  `https://radio.embmission.com/api/media/files/conversion-status`  
  (ou le domaine réel de l'API) et lire :
  - `data[].conversion_status === 'source_missing'`
  - `data[].media_file_id`, `data[].original_name`, `data[].source_exists`

Les lignes avec `source_missing` sont celles pour lesquelles le fichier source est manquant au chemin calculé.

---

## 6. Script de diagnostic

Un script Artisan liste les vidéos qui auraient le statut `source_missing` et le chemin vérifié :

- Fichier : `app/Console/Commands/DiagnoseSourceMissing.php`  
- Commande : `php artisan diagnose:source-missing`

Il affiche pour chaque cas : `id`, `file_path`, chemin absolu résolu, et si le fichier existe ou non.

---

## 7. Résumé en une phrase

**Le badge « Source manquante » vient de l'API `GET /api/media/files/conversion-status` : le backend renvoie `conversion_status: 'source_missing'` pour tout fichier vidéo dont le chemin physique `storage_path('app/media/' . file_path)` n'existe pas sur le disque.**
