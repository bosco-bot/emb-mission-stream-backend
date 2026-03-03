
echo "🚀 Démarrage de la synchronisation"

SOURCE_DIR="/var/www/emb-mission/storage/app/media/audios"
TARGET_DIR="/var/lib/docker/volumes/azuracast_station_data/_data/radio_emb_mission/media"

if [ ! -d "$SOURCE_DIR" ]; then
    echo "❌ Dossier source introuvable: $SOURCE_DIR"
    exit 1
fi

if [ ! -d "$TARGET_DIR" ]; then
    echo "❌ Dossier cible introuvable: $TARGET_DIR"
    exit 1
fi

echo "📊 Synchronisation des fichiers..."

rsync -av --progress "$SOURCE_DIR/" "$TARGET_DIR/" --exclude="*.tmp" --exclude="*.part"

echo ""
echo "✅ Synchronisation terminée"
echo ""
echo "📊 Nombre de fichiers dans AzuraCast:"
ls -1 "$TARGET_DIR" | wc -l
