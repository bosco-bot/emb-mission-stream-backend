#!/bin/bash

# Script pour démarrer le processus FFmpeg qui écoute les connexions RTMP
# et les pousse vers Ant Media Server

RTMP_PORT=1936
RTMP_KEY="R9rzvVzMPvCClU6s1562847982308692"
ANT_MEDIA_URL="rtmp://127.0.0.1:1935/LiveApp/live_transcoded"

# Fonction pour arrêter le processus existant
stop_existing() {
    echo "Arrêt des processus FFmpeg existants..."
    pkill -f "rtmp://.*:$RTMP_PORT" 2>/dev/null || true
    sleep 2
}

# Fonction pour démarrer l'écoute RTMP
start_listener() {
    echo "Démarrage du listener RTMP sur le port $RTMP_PORT..."
    
    # FFmpeg écoute sur le port 1936 et pousse vers Ant Media Server
    ffmpeg -hide_banner -loglevel info \
        -f flv -listen 1 -i rtmp://0.0.0.0:$RTMP_PORT/live_input/$RTMP_KEY \
        -c:v libx264 -preset veryfast -b:v 3500k -g 60 -keyint_min 60 \
        -sc_threshold 0 -force_key_frames "expr:gte(t,n_forced*2)" \
        -c:a aac -b:a 128k -ar 48000 -ac 2 \
        -f flv $ANT_MEDIA_URL \
        > /var/log/rtmp-listener.log 2>&1 &
    
    echo $! > /var/run/rtmp-listener.pid
    echo "Listener RTMP démarré avec PID: $(cat /var/run/rtmp-listener.pid)"
}

# Fonction pour vérifier le statut
check_status() {
    if [ -f /var/run/rtmp-listener.pid ]; then
        PID=$(cat /var/run/rtmp-listener.pid)
        if ps -p $PID > /dev/null 2>&1; then
            echo "Listener RTMP actif (PID: $PID)"
            return 0
        else
            echo "Fichier PID trouvé mais processus inactif"
            rm -f /var/run/rtmp-listener.pid
            return 1
        fi
    else
        echo "Listener RTMP inactif"
        return 1
    fi
}

# Fonction principale
case "${1:-start}" in
    start)
        if check_status; then
            echo "Listener RTMP déjà actif"
        else
            stop_existing
            start_listener
        fi
        ;;
    stop)
        stop_existing
        rm -f /var/run/rtmp-listener.pid
        echo "Listener RTMP arrêté"
        ;;
    restart)
        stop_existing
        rm -f /var/run/rtmp-listener.pid
        start_listener
        ;;
    status)
        check_status
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status}"
        exit 1
        ;;
esac

