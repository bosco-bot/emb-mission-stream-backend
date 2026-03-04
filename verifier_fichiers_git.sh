#!/bin/bash
# Vérifie que les fichiers importants du projet sont bien suivis par Git.
# Usage: ./verifier_fichiers_git.sh

set -e
cd "$(dirname "$0")"

echo "=== Fichiers critiques (doivent être suivis) ==="
CRITICAL=(
  "app/Services/AntMediaService.php"
  "app/Http/Controllers/Api/WebTVController.php"
  "routes/web.php"
  "routes/api.php"
  "config/app.php"
  "composer.json"
  ".env.example"
)
MISSING=0
for f in "${CRITICAL[@]}"; do
  if git ls-files --error-unmatch "$f" &>/dev/null; then
    echo "  OK   $f"
  else
    echo "  MANQUE $f"
    MISSING=1
  fi
done

if [ "$MISSING" -eq 1 ]; then
  echo ""
  echo "Pour ajouter les fichiers manquants :"
  echo "  git add app/ config/ routes/ database/ resources/ public/*.html public/*.json public/manifest.json public/assets/ public/icons/ public/canvaskit/ public/flutter*.js public/main.dart.js"
  echo "  git add composer.json composer.lock .env.example .gitignore bootstrap/ artisan"
  echo "  git status"
  exit 1
fi
echo ""
echo "Tous les fichiers critiques sont versionnés."
