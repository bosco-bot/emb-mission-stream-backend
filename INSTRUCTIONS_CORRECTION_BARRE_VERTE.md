# Instructions pour corriger la barre de progression (vert à la fin)

## Problème
La barre de progression reste bleue même après la fin de l'upload, alors qu'elle devrait devenir verte.

## Solution appliquée
Le code source a été modifié dans :
`public/EMB-Mission_RTV-main/lib/features/contents/view/add_content_page.dart`

Lignes 1593-1623 : Logique de couleur de la barre de progression corrigée.

## Ce qui a été changé

**Avant :**
- La logique ne vérifiait pas correctement si le fichier était terminé
- `uploadProgress` prenait toujours la priorité même après la fin

**Après :**
- **PRIORITÉ 1** : Si fichier terminé (`isCompleted == true`) → **TOUJOURS VERT**
- **PRIORITÉ 2** : Si upload en cours (`uploadProgress < 1.0`) → **BLEU**
- **PRIORITÉ 3** : Sinon → Couleur du statut

## Action requise

Le code source Dart a été modifié, mais il faut **recompiler** pour que les changements apparaissent.

### Option 1 : Compiler sur le serveur
```bash
cd /var/www/emb-mission/public/EMB-Mission_RTV-main
flutter build web
# Les fichiers compilés seront dans build/web/
# Copier main.dart.js vers /var/www/emb-mission/public/
```

### Option 2 : Compiler localement
```bash
# Sur votre machine locale avec Flutter installé
cd /path/to/EMB-Mission_RTV-main
flutter build web
# Copier build/web/main.dart.js vers le serveur
```

### Option 3 : Utiliser un script de build existant
Si vous avez un script de déploiement, l'utiliser pour recompiler.

## Fichiers à vérifier après compilation

1. `/var/www/emb-mission/public/main.dart.js` doit être mis à jour
2. La date de modification doit être récente
3. Tester dans le navigateur : la barre doit devenir verte après l'upload

## Résumé
- ✅ Code source modifié
- ❌ Fichier compilé (main.dart.js) pas encore mis à jour
- ⚠️ **Action requise** : Recompiler avec `flutter build web`
