# 🔍 DIAGNOSTIC : Nouveaux audios non pris en compte dans AzuraCast

## ❌ PROBLÈME IDENTIFIÉ

Lors de la mise à jour d'une playlist, les nouveaux audios sont ajoutés en base Laravel 
mais AzuraCast continue de jouer les anciens audios.

## 🔍 ANALYSE DU CODE

### Processus actuel (syncPlaylist):

1. **Copie des fichiers** (lignes 178-197)
   - Condition : `if (!$item->mediaFile->azuracast_id && !$existsInAzuraCast)`
   - ❌ PROBLÈME 1 : Ne copie que si le fichier N'A PAS d'azuracast_id ET n'existe pas
   - ❌ PROBLÈME 2 : Si le fichier existe déjà dans AzuraCast mais pas dans la playlist,
     il ne sera PAS copié et pourra manquer lors de l'import M3U

2. **Scan des médias** (lignes 200-210)
   - Scanne seulement si des fichiers ont été copiés (`$copiedFilesCount > 0`)
   - ❌ PROBLÈME 3 : Si les nouveaux fichiers existent déjà physiquement dans AzuraCast,
     mais ne sont pas encore scannés, le scan ne sera pas déclenché

3. **Génération M3U** (ligne 468-480)
   - Utilise `original_name` pour référencer les fichiers
   - ✅ Génère le M3U avec TOUS les items de la playlist

4. **Import M3U** (ligne 482-520)
   - Importe le M3U dans AzuraCast
   - ❌ PROBLÈME 4 : Si AzuraCast ne trouve pas les fichiers référencés dans le M3U
     (car pas encore scannés), l'import peut échouer silencieusement ou ignorer ces fichiers

## 🚨 CAUSES PROBABLES

### Cause 1 : Fichiers non copiés
- Les nouveaux fichiers ne sont copiés que s'ils n'existent PAS dans AzuraCast
- Mais ils peuvent exister physiquement sans être dans la base AzuraCast (scan non fait)

### Cause 2 : Scan insuffisant
- Le scan n'est déclenché que si `$copiedFilesCount > 0`
- Si les fichiers existent déjà dans AzuraCast, ils ne seront pas copiés
- Mais s'ils ne sont pas scannés, ils ne seront pas disponibles pour l'import M3U

### Cause 3 : Délai de scan insuffisant
- Attente : `5s + (nombre_fichiers × 3s)`
- Pour un fichier : 8 secondes
- Pour 5 fichiers : 20 secondes
- Le scan AzuraCast peut prendre plus de temps selon la taille des fichiers

### Cause 4 : Import M3U échoue silencieusement
- Si AzuraCast ne trouve pas les fichiers référencés dans le M3U, 
  l'import peut échouer sans erreur visible
- Les fichiers manquants sont ignorés

### Cause 5 : Ordre des opérations
1. Vide la playlist (ligne 220)
2. Génère le M3U avec TOUS les items (ligne 222)
3. Importe le M3U (ligne 223)
4. Mais si les fichiers ne sont pas encore dans AzuraCast, l'import peut échouer

## 🔧 SOLUTION PROPOSÉE

### Option 1 : Forcer la copie et le scan pour les nouveaux items
- Copier les fichiers des nouveaux items même s'ils existent déjà
- Toujours scanner après mise à jour

### Option 2 : Vérifier que les fichiers sont disponibles avant l'import
- Avant d'importer le M3U, vérifier que TOUS les fichiers existent dans AzuraCast
- Si certains manquent, les copier et scanner

### Option 3 : Réessayer l'import après un délai
- Si l'import échoue, attendre et réessayer
- Vérifier les résultats de l'import pour détecter les fichiers manquants

