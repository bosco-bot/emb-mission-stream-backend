#!/bin/bash
#
# cleanup-live-segments.sh
# ─────────────────────────────────────────────────────────────────────────────
# Supprime les segments .ts live après un délai de grâce de 5 minutes.
#
# Contexte :
#   deleteHLSFilesOnEnded=false dans Ant Media → les segments ne sont plus
#   supprimés immédiatement à la fin du live (pour éviter les 404 en cours de
#   téléchargement). Ce script assure le nettoyage différé.
#
# Ciblage :
#   - Fichiers .ts UNIQUEMENT à la RACINE de STREAMS_DIR (live segments)
#   - -maxdepth 1 : exclut les sous-dossiers vod_* (segments VoD critiques)
#   - -mmin +5    : uniquement les fichiers de plus de 5 minutes
#
# Usage : lancé par cron toutes les 5 minutes (voir /etc/cron.d/antmedia-cleanup)
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

STREAMS_DIR="/usr/local/antmedia/webapps/LiveApp/streams"
GRACE_MINUTES=5
LOG_TAG="antmedia-cleanup"

if [ ! -d "$STREAMS_DIR" ]; then
    logger -t "$LOG_TAG" "ERREUR : Dossier streams introuvable : $STREAMS_DIR"
    exit 1
fi

# Compter avant suppression
COUNT=$(find "$STREAMS_DIR" -maxdepth 1 -name "*.ts" -mmin "+${GRACE_MINUTES}" 2>/dev/null | wc -l)

if [ "$COUNT" -eq 0 ]; then
    exit 0  # Rien à faire, sortie silencieuse
fi

# Suppression ciblée (racine uniquement, anciens de plus de 5min)
find "$STREAMS_DIR" -maxdepth 1 -name "*.ts" -mmin "+${GRACE_MINUTES}" -delete 2>/dev/null

logger -t "$LOG_TAG" "✅ $COUNT segments .ts live supprimés (grâce: ${GRACE_MINUTES}min)"
