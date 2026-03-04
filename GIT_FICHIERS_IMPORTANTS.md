# Fichiers importants à versionner sur GitHub

Ce document liste ce qui **doit** être dans le dépôt Git pour ne rien perdre.

## À committer (toujours)

- **`app/`** – Controllers, Models, Services, tout le code PHP métier
- **`config/`** – Fichiers de configuration (sans secrets)
- **`database/migrations/`** – Migrations
- **`database/seeders/`** – Seeders
- **`routes/`** – web.php, api.php, etc.
- **`resources/views/`** – Vues Blade
- **`resources/css/`**, **`resources/js/`** – Assets sources
- **`public/`** – index.php, .htaccess, build Flutter (index.html, main.dart.js, assets/, etc.) — **sauf** public/hot, public/storage
- **`bootstrap/`** – app.php, cache/
- **`storage/app/`** – Structure (fichiers .gitignore pour garder les dossiers)
- **Fichiers racine** : composer.json, composer.lock, artisan, package.json, etc.
- **`.env.example`** – Exemple sans secrets (jamais `.env`)

## À ne jamais committer (.gitignore)

- **`.env`** – Secrets (clés, mots de passe, DB)
- **`vendor/`** – Reconstruit avec `composer install`
- **`node_modules/**`** – Reconstruit avec `npm install`
- **`storage/logs/*`** – Fichiers de log
- **`storage/framework/views/*`** – Vues compilées (recréées à la volée)
- **`storage/framework/cache/*`**, **sessions/***
- Gros fichiers / builds lourds (zips, dossiers build Flutter type EMB-Mission_RTV-main/build/)

## Vérification rapide avant push

```bash
# Fichiers modifiés ou non suivis dans les dossiers importants
git status -- app/ config/ routes/ database/ resources/ routes/
# Si des fichiers source apparaissent en "untracked", les ajouter :
git add app/ config/ routes/ database/ resources/ routes/
git add public/
git add composer.json composer.lock .env.example
git status
git commit -m "Description des changements"
git push origin main
```

## Après un clone ou une récupération

1. `composer install`
2. `cp .env.example .env` puis éditer `.env` (ou restaurer `.env` depuis une sauvegarde sécurisée)
3. `php artisan key:generate`
4. Créer les dossiers storage manquants :  
   `mkdir -p storage/framework/{views,cache/data,sessions}`  
   `chmod -R 775 storage bootstrap/cache`  
   (et chown www-data si besoin)
5. `php artisan migrate` (si DB utilisée)
