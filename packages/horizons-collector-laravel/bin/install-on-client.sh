#!/usr/bin/env bash
# Installation du collecteur HorizonsPlus sur un projet Laravel client (ex. EMB-MISSION).
# Usage : depuis la racine Laravel du client
#   bash /chemin/vers/horizons-collector-laravel/bin/install-on-client.sh

set -euo pipefail

PACKAGE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PROJECT_DIR="$(pwd)"

if [[ ! -f "${PROJECT_DIR}/artisan" ]]; then
  echo "Erreur : exécutez ce script depuis la racine Laravel (artisan introuvable)." >&2
  exit 1
fi

if [[ ! -f "${PACKAGE_DIR}/composer.json" ]]; then
  echo "Erreur : package collecteur introuvable dans ${PACKAGE_DIR}" >&2
  exit 1
fi

TARGET_DIR="${PROJECT_DIR}/packages/horizons-collector-laravel"
mkdir -p "${PROJECT_DIR}/packages"

if [[ "$(realpath "${PACKAGE_DIR}")" != "$(realpath "${TARGET_DIR}" 2>/dev/null || echo '')" ]]; then
  echo "→ Copie du package vers packages/horizons-collector-laravel"
  rm -rf "${TARGET_DIR}"
  cp -a "${PACKAGE_DIR}" "${TARGET_DIR}"
fi

COMPOSER_JSON="${PROJECT_DIR}/composer.json"
if ! grep -q 'horizons-collector-laravel' "${COMPOSER_JSON}"; then
  echo "→ Ajout du dépôt path dans composer.json (manuel si échec)"
  php -r '
    $path = getenv("PROJECT_DIR");
    $file = $path . "/composer.json";
    $data = json_decode(file_get_contents($file), true);
    $data["repositories"] ??= [];
    $exists = false;
    foreach ($data["repositories"] as $repo) {
      if (($repo["url"] ?? "") === "packages/horizons-collector-laravel") {
        $exists = true;
        break;
      }
    }
    if (! $exists) {
      $data["repositories"][] = [
        "type" => "path",
        "url" => "packages/horizons-collector-laravel",
        "options" => ["symlink" => true],
      ];
      file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
      echo "   dépôt path ajouté\n";
    }
  ' PROJECT_DIR="${PROJECT_DIR}"
fi

echo "→ composer require horizonsplus/collector-laravel:@dev"
composer require horizonsplus/collector-laravel:@dev --no-interaction

echo "→ Vérification de la route"
php artisan route:list --path=monitoring/health || true

echo ""
echo "Étapes restantes :"
echo "  1. Dans .env du client :"
echo "       HORIZONS_MONITORING_TOKEN=<clé générée dans HorizonsPlus>"
echo "  2. php artisan config:clear"
echo "  3. Test :"
echo "       curl -sS -H \"Authorization: Bearer VOTRE_CLE\" https://radio.embmission.com/api/monitoring/health | head"
echo "  4. Dans HorizonsPlus → Projet → Collecteur → Lancer le test"
