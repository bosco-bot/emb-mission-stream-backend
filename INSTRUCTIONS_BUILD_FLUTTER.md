# Instructions pour build Flutter Web

## Structure correcte

### Sources (ne pas servir)
```
EMB-Mission_RTV-main/
  ├── lib/ (sources Dart)
  ├── web/ (template)
  └── build/web/ (généré par flutter build web)
```

### Fichiers à servir (build web release)
```
public/
  ├── main.dart.js
  ├── index.html
  ├── flutter.js
  └── canvaskit/
```

## Processus de build

1. **Modifier les sources** dans `EMB-Mission_RTV-main/lib/`

2. **Compiler** :
   ```bash
   cd EMB-Mission_RTV-main
   flutter build web
   ```

3. **Copier le build** vers `public/` :
   ```bash
   cp -r build/web/* /var/www/emb-mission/public/
   ```

## Fichiers modifiés (à recompiler)

- `lib/features/contents/view/add_content_page.dart`
- `lib/core/services/media_service.dart`

## Note

Les sources (`EMB-Mission_RTV-main/lib/`) ne devraient pas être dans `public/` car elles ne sont pas nécessaires pour l'exécution. Seul le build compilé (`build/web/`) doit être servi.
