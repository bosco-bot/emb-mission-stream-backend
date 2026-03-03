#!/bin/bash
# Script de vérification des permissions Laravel

STORAGE_PATH="/var/www/emb-mission/storage"
EXPECTED_OWNER="www-data"
EXPECTED_GROUP="www-data"
EXPECTED_PERM="775"

echo "🔍 Vérification des permissions de storage/..."

if [ ! -d "$STORAGE_PATH" ]; then
    echo "❌ Dossier storage/ non trouvé"
    exit 1
fi

# Vérifier le propriétaire
CURRENT_OWNER=$(stat -c "%U" "$STORAGE_PATH")
CURRENT_GROUP=$(stat -c "%G" "$STORAGE_PATH")

if [ "$CURRENT_OWNER" != "$EXPECTED_OWNER" ] || [ "$CURRENT_GROUP" != "$EXPECTED_GROUP" ]; then
    echo "⚠️  Permissions incorrectes détectées!"
    echo "   Propriétaire actuel: $CURRENT_OWNER:$CURRENT_GROUP"
    echo "   Propriétaire attendu: $EXPECTED_OWNER:$EXPECTED_GROUP"
    echo ""
    echo "🔧 Correction automatique..."
    sudo chown -R $EXPECTED_OWNER:$EXPECTED_GROUP "$STORAGE_PATH"
    sudo chmod -R $EXPECTED_PERM "$STORAGE_PATH"
    echo "✅ Permissions corrigées"
else
    echo "✅ Permissions correctes: $CURRENT_OWNER:$CURRENT_GROUP"
fi
