#!/usr/bin/env bash
# Verification du routage unified.m3u8 (Laravel vs Rust/fichier)
# Lancer sur le serveur ou en local (curl vers tv.embmission.com).

set -e
BASE_URL="${BASE_URL:-https://tv.embmission.com}"
PUBLIC_URL="${BASE_URL}/hls/streams/unified.m3u8"
API_URL="${BASE_URL}/api/stream/unified.m3u8"

echo "=============================================="
echo "Verification routage flux unifie"
echo "=============================================="
echo "URL publique (clients): $PUBLIC_URL"
echo "URL API Laravel:        $API_URL"
echo ""

echo "1) L'URL publique est-elle servie par Laravel ?"
echo "   (recherche de l'en-tete X-Served-By: Laravel-UnifiedStream)"
if curl -sS -I -L --max-time 10 "$PUBLIC_URL" 2>/dev/null | grep -qi "X-Served-By:.*Laravel-UnifiedStream"; then
    echo "   OK - L'URL publique est bien traitee par Laravel (live + VoD coherents)."
else
    echo "   KO - L'URL publique ne renvoie pas l'en-tete Laravel."
    echo "   Nginx envoie probablement /hls/streams/unified.m3u8 a Rust ou fichier statique."
    echo "   En mode live, le flux peut etre incorrect. Voir docs/VERIFICATION_FLUX_UNIFIE.md"
fi
echo ""

echo "2) L'endpoint API Laravel repond-il ?"
if curl -sS -I -L --max-time 10 "$API_URL" 2>/dev/null | grep -qi "X-Served-By:.*Laravel-UnifiedStream"; then
    echo "   OK - L'API /api/stream/unified.m3u8 est bien servie par Laravel."
else
    echo "   KO - Pas d'en-tete Laravel sur l'API (verifier PHP-FPM et Laravel)."
fi
echo ""

echo "=============================================="
echo "Verification Nginx (sur le serveur)"
echo "=============================================="
echo "Sur le serveur, executer :"
echo "  sudo grep -R 'hls/streams\\|8081' /etc/nginx/"
echo "  sudo nginx -T 2>/dev/null | grep -A5 'unified\\|hls/streams'"
echo ""
echo "Souhaite: /hls/streams/unified.m3u8 doit etre traite par Laravel (PHP-FPM)."
echo "Exemple voir: docs/VERIFICATION_FLUX_UNIFIE.md"
