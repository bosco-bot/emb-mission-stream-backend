# 📋 FICHE DE MAINTENANCE - PLAYLIST VIDÉO EMB MISSION

## 🎯 INFORMATIONS GÉNÉRALES

**Système** : EMB Mission WebTV  
**Composant** : Système de Playlist Vidéo  
**Version** : Laravel 10 + Ant Media Server  
**Environnement** : Production (tv.embmission.com)  
**Date de création** : 13 Novembre 2025  

---

## 🏗️ ARCHITECTURE DU SYSTÈME

### **Composants Principaux**
- **Laravel Backend** : Gestion des playlists et métadonnées
- **Ant Media Server** : Streaming et conversion HLS
- **FFmpeg** : Transcodage et optimisation vidéo
- **Base de données** : MySQL (tables `webtv_playlists`, `webtv_playlist_items`)
- **Stockage** : `/usr/local/antmedia/webapps/LiveApp/streams/`

### **Flux de Données**
```
Fichiers MP4 → Laravel → Ant Media → FFmpeg → HLS Segments → Diffusion
```

---

## 🔧 TÂCHES DE MAINTENANCE QUOTIDIENNES

### **1. Vérification de l'État du Système**
```bash
# Statut des services critiques
sudo systemctl status ffmpeg-live-transcode.service
sudo systemctl status antmedia
sudo systemctl status nginx
sudo systemctl status mysql

# Vérification des processus
ps aux | grep ffmpeg
ps aux | grep java | grep antmedia
```

### **2. Contrôle de la Synchronisation**
```bash
# Commande de vérification automatique
php artisan webtv:check-sync --alert

# Vérification manuelle des items en erreur
php artisan tinker
>>> WebTVPlaylistItem::where('sync_status', 'error')->count()
>>> WebTVPlaylistItem::where('sync_status', 'pending')->whereNotNull('ant_media_item_id')->count()
```

### **3. Surveillance de l'Espace Disque**
```bash
# Espace disque Ant Media
df -h /usr/local/antmedia/webapps/LiveApp/streams/

# Nettoyage automatique des anciens VoD (si nécessaire)
find /usr/local/antmedia/webapps/LiveApp/streams/ -name "vod_*" -mtime +30 -type d
```

---

## 🔍 TÂCHES DE MAINTENANCE HEBDOMADAIRES

### **1. Analyse des Performances**
```bash
# Statistiques des playlists
php artisan tinker
>>> WebTVPlaylist::where('is_active', true)->with('items')->get()
>>> WebTVPlaylistItem::where('sync_status', 'synced')->count()

# Vérification des logs d'erreur
tail -100 /var/log/nginx/tv.embmission.com-error.log
sudo journalctl -u antmedia --since "7 days ago" | grep ERROR
```

### **2. Optimisation de la Base de Données**
```bash
# Nettoyage des items orphelins
php artisan tinker
>>> WebTVPlaylistItem::whereDoesntHave('playlist')->delete()

# Recalcul des totaux des playlists
>>> WebTVPlaylist::all()->each->updateTotals()
```

### **3. Vérification de l'Intégrité des Fichiers**
```bash
# Script de vérification des VoD
for dir in /usr/local/antmedia/webapps/LiveApp/streams/vod_*/; do
    if [ -d "$dir" ]; then
        if [ ! -f "$dir/playlist.m3u8" ]; then
            echo "❌ Playlist manquante: $dir"
        fi
    fi
done
```

---

## 🚨 TÂCHES DE MAINTENANCE MENSUELLES

### **1. Sauvegarde Complète**
```bash
# Sauvegarde base de données
mysqldump -u root -p emb_mission webtv_playlists webtv_playlist_items > backup_playlists_$(date +%Y%m%d).sql

# Sauvegarde configuration Ant Media
tar -czf antmedia_config_$(date +%Y%m%d).tar.gz /usr/local/antmedia/conf/

# Sauvegarde des VoD critiques (optionnel selon l'espace)
# tar -czf vod_backup_$(date +%Y%m%d).tar.gz /usr/local/antmedia/webapps/LiveApp/streams/vod_*
```

### **2. Mise à Jour des Paramètres**
```bash
# Vérification des paramètres FFmpeg
cat /etc/ffmpeg-live-transcode.env

# Test des paramètres de qualité
php artisan tinker
>>> WebTVPlaylist::where('quality', '1080p')->where('bitrate', '<', 3000)->get()
```

### **3. Audit de Sécurité**
```bash
# Vérification des permissions
ls -la /usr/local/antmedia/webapps/LiveApp/streams/
ls -la /var/www/emb-mission/storage/app/public/media/

# Contrôle des accès réseau
sudo netstat -tlnp | grep -E "(1935|5080|1936)"
```

---

## 🛠️ COMMANDES DE DIAGNOSTIC

### **Vérification Rapide du Système**
```bash
# Status global
curl -s "https://tv.embmission.com/api/webtv/stats/all" | jq .

# Test de la playlist unifiée
curl -s "https://tv.embmission.com/hls/streams/unified.m3u8" | head -10

# Vérification Ant Media API
curl -s "http://127.0.0.1:5080/LiveApp/rest/v2/broadcasts/live_transcoded" | jq .
```

### **Diagnostic Approfondi**
```bash
# Analyse des logs Laravel
tail -50 /var/www/emb-mission/storage/logs/laravel.log | grep -E "(WebTV|Playlist|VoD)"

# Vérification des jobs en queue
php artisan queue:work --once --verbose

# Test de création VoD
php artisan tinker
>>> $item = WebTVPlaylistItem::where('sync_status', 'pending')->first()
>>> if($item) { dispatch(new App\Jobs\CreateVodStreamJob($item)); }
```

---

## 🚨 PROCÉDURES D'URGENCE

### **Playlist Non Fonctionnelle**
1. **Vérifier le service Ant Media**
   ```bash
   sudo systemctl restart antmedia
   sudo systemctl status antmedia
   ```

2. **Resynchroniser la playlist**
   ```bash
   php artisan tinker
   >>> $playlist = WebTVPlaylist::where('is_active', true)->first()
   >>> $service = new App\Services\AntMediaPlaylistService()
   >>> $result = $service->syncPlaylist($playlist)
   ```

3. **Forcer la régénération des VoD**
   ```bash
   php artisan webtv:check-sync --fix
   ```

### **Problème de Streaming Live**
1. **Redémarrer FFmpeg**
   ```bash
   sudo systemctl restart ffmpeg-live-transcode.service
   sudo systemctl status ffmpeg-live-transcode.service
   ```

2. **Vérifier la configuration**
   ```bash
   cat /etc/ffmpeg-live-transcode.env
   sudo ss -tlnp | grep 1936
   ```

### **Espace Disque Plein**
1. **Nettoyage d'urgence**
   ```bash
   # Supprimer les VoD anciens (>30 jours)
   find /usr/local/antmedia/webapps/LiveApp/streams/ -name "vod_*" -mtime +30 -exec rm -rf {} \;
   
   # Nettoyer les logs
   sudo journalctl --vacuum-time=7d
   ```

---

## 📊 INDICATEURS DE PERFORMANCE

### **Métriques à Surveiller**
- **Nombre de playlists actives** : `WebTVPlaylist::where('is_active', true)->count()`
- **Items en erreur** : `WebTVPlaylistItem::where('sync_status', 'error')->count()`
- **Utilisation disque** : `df -h /usr/local/antmedia/`
- **Temps de réponse API** : `curl -w "%{time_total}" https://tv.embmission.com/api/webtv/stats/all`

### **Seuils d'Alerte**
- ⚠️ **Items en erreur > 5%** du total
- 🚨 **Espace disque > 85%**
- 🚨 **Temps de réponse API > 2 secondes**
- ⚠️ **Redémarrages FFmpeg > 3/jour**

---

## 🔄 AUTOMATISATION RECOMMANDÉE

### **Cron Jobs à Configurer**
```bash
# Vérification quotidienne (6h du matin)
0 6 * * * cd /var/www/emb-mission && php artisan webtv:check-sync --alert

# Nettoyage hebdomadaire (dimanche 2h)
0 2 * * 0 find /usr/local/antmedia/webapps/LiveApp/streams/ -name "vod_*" -mtime +30 -type d -exec rm -rf {} \;

# Sauvegarde mensuelle (1er du mois 3h)
0 3 1 * * mysqldump -u root -p emb_mission webtv_playlists webtv_playlist_items > /backup/playlists_$(date +\%Y\%m\%d).sql
```

### **Monitoring Automatique**
```bash
# Script de surveillance (à exécuter toutes les 5 minutes)
#!/bin/bash
# /usr/local/bin/webtv_monitor.sh

# Vérifier les services
systemctl is-active --quiet antmedia || echo "ALERTE: Ant Media down" | mail -s "EMB Mission Alert" admin@embmission.com
systemctl is-active --quiet ffmpeg-live-transcode || echo "ALERTE: FFmpeg down" | mail -s "EMB Mission Alert" admin@embmission.com

# Vérifier l'espace disque
USAGE=$(df /usr/local/antmedia | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $USAGE -gt 85 ]; then
    echo "ALERTE: Espace disque à ${USAGE}%" | mail -s "EMB Mission Disk Alert" admin@embmission.com
fi
```

---

## 📞 CONTACTS ET ESCALADE

### **Niveaux d'Intervention**
1. **Niveau 1** : Administrateur système (redémarrages, vérifications)
2. **Niveau 2** : Développeur Laravel (problèmes applicatifs)
3. **Niveau 3** : Expert Ant Media (problèmes de streaming)

### **Documentation de Référence**
- **Laravel** : https://laravel.com/docs
- **Ant Media** : https://antmedia.io/docs
- **FFmpeg** : https://ffmpeg.org/documentation.html

---

## 📝 HISTORIQUE DES MODIFICATIONS

| Date | Version | Modifications | Auteur |
|------|---------|---------------|--------|
| 2025-11-13 | 1.0 | Création initiale de la fiche | Assistant IA |

---

**⚠️ IMPORTANT** : Cette fiche doit être mise à jour à chaque modification majeure du système de playlist vidéo.
