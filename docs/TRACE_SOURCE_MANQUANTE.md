# Traçage : badge "Source manquante"

## Origine du statut

- **Endpoint** : `GET /api/media/files/conversion-status`
- **Contrôleur** : `MediaController::getConversionStatus()` (l.1428-1559)
- **Règle** : pour chaque vidéo avec `file_path` non null, le backend calcule  
  `$sourcePath = storage_path('app/media/' . $mediaFile->file_path)`  
  et fait `$sourceExists = file_exists($sourcePath)`.  
  Si `!$sourceExists` → `conversion_status = 'source_missing'` (l.1460).

## Chaîne API → affichage

1. Flutter appelle `GET /api/media/files/conversion-status`.
2. Backend renvoie pour chaque fichier `conversion_status` (dont `source_missing`).
3. Le build Flutter (`public/main.dart.js`) affiche "Source manquante" quand le statut est `source_missing`.

## Cause

Des enregistrements dans `media_files` ont un `file_path` tel que le fichier physique n'existe pas à  
`storage_path('app/media/' . file_path)` (fichier supprimé/déplacé, chemin erroné, volume non monté).

## Diagnostic

- Appel API : `GET .../api/media/files/conversion-status` → voir `data[].conversion_status === 'source_missing'`.
- Commande : `php artisan diagnose:source-missing` (voir `app/Console/Commands/DiagnoseSourceMissing.php`).
