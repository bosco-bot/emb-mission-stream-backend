# Configuration HLS Ant Media Server - Résolution Problème Segments Manquants

**Date de modification :** 2025-11-21 00:38  
**Fichier modifié :** `/usr/local/antmedia/conf/red5.properties`  
**Backup créé :** `/usr/local/antmedia/conf/red5.properties.backup.20251121_003804`

## 📋 Problème identifié

### Symptômes
- Ant Media Server référence parfois des segments `.ts` qui n'existent plus sur le disque
- Erreurs `DEMUXER_ERROR_COULD_NOT_PARSE` dans le navigateur
- Segments référencés dans la playlist mais déjà supprimés (404 lors du chargement)

### Cause racine
- **Décalage entre génération et suppression** des segments HLS
- Paramètres HLS par défaut d'Ant Media trop agressifs
- Suppression trop rapide des segments avant mise à jour de la playlist

## ✅ Solution appliquée

### Paramètres HLS ajoutés dans `red5.properties`

```properties
# Durée de chaque segment HLS (en secondes)
settings.hlsTime=2

# Nombre de segments à conserver dans la playlist (sliding window)
# 3 segments × 2 secondes = 6 secondes de buffer
settings.hlsListSize=3

# Délai avant suppression des segments (en secondes)
# Les segments restent au moins 60 secondes sur le disque
settings.hlsFragmentDeleteAge=60

# Délai avant suppression de la playlist (en secondes)
settings.hlsPlayListDeleteAge=60

# Type de playlist HLS: live (sliding) ou event
# Mode "live" permet à la playlist de glisser sans redémarrer
settings.hlsPlayListType=live

# Empêcher la suppression brutale des segments à la fin du stream
settings.deleteHLSFilesOnEnded=false
```

## 🎯 Pourquoi ces paramètres ?

### 1. `hlsTime=2` et `hlsListSize=3`
- **Buffer de 6 secondes** (3 × 2s)
- Ant Media supprime les segments **après** avoir mis à jour la playlist
- Évite les références à des segments déjà supprimés

### 2. `hlsFragmentDeleteAge=60` et `hlsPlayListDeleteAge=60`
- Les segments restent **au moins 60 secondes** sur le disque
- Ant Media a le temps de mettre la playlist à jour correctement
- Protection contre les suppressions trop rapides

### 3. `hlsPlayListType=live`
- Mode **"sliding window"** : la playlist glisse proprement
- Mode "event" casse le fonctionnement dynamique du HLS
- Compatible avec les flux live en continu

### 4. `deleteHLSFilesOnEnded=false`
- Empêche la suppression **brutale et non contrôlée** des segments
- Permet une gestion propre selon les paramètres ci-dessus

## 🔄 Architecture complète

```
vMix/OBS 
    ↓ RTMP port 1936
FFmpeg Listener (transcode RTMP → RTMP)
    ↓ RTMP port 1935
Ant Media Server
    ↓ Génère HLS segments (paramètres ci-dessus)
    ↓ live_transcoded.m3u8 + segments .ts
Laravel (UnifiedStreamController)
    ↓ Valide et filtre les segments
Client (hls.js)
```

## 📊 Résultats attendus

### Avant (valeurs par défaut)
- ❌ Segments référencés mais déjà supprimés
- ❌ Erreurs DEMUXER_ERROR_COULD_NOT_PARSE
- ❌ Flux instable, coupures fréquentes

### Après (configuration optimisée)
- ✅ Les segments restent disponibles assez longtemps
- ✅ Le navigateur ne charge plus de segments manquants
- ✅ La playlist reflète exactement les segments présents
- ✅ Fin des erreurs DEMUXER_ERROR_COULD_NOT_PARSE
- ✅ Flux HLS très stable, sans trous de séquence

## 🚀 Activation

**⚠️ IMPORTANT :** Les modifications nécessitent un redémarrage d'Ant Media Server pour être prises en compte.

```bash
# Vérifier la configuration
sudo grep -E 'settings\.hls|settings\.deleteHLS' /usr/local/antmedia/conf/red5.properties

# Redémarrer Ant Media Server
sudo systemctl restart antmedia

# Vérifier le statut
sudo systemctl status antmedia

# Vérifier les logs
sudo tail -f /var/log/antmedia/antmedia-error.log
```

## 🔙 Rollback

En cas de problème, restaurer le backup :

```bash
sudo cp /usr/local/antmedia/conf/red5.properties.backup.20251121_003804 /usr/local/antmedia/conf/red5.properties
sudo systemctl restart antmedia
```

## 📝 Notes importantes

1. **FFmpeg n'intervient pas** dans la génération des segments HLS pour le live
   - FFmpeg ne fait que transcoder RTMP → RTMP
   - Ant Media génère les segments HLS avec ces paramètres

2. **Solution Laravel complémentaire**
   - `UnifiedStreamController` valide déjà les segments avant de les servir
   - Cette configuration Ant Media résout le problème à la source
   - Les deux solutions se complètent

3. **Impact sur l'espace disque**
   - Conservation de 60 secondes de segments peut utiliser plus d'espace
   - Nécessaire pour la stabilité du flux live
   - Nettoyage automatique après 60 secondes

4. **Compatibilité**
   - Compatible avec le système actuel (pas de breaking changes)
   - Améliore la stabilité sans modifier le comportement général

## 🔍 Monitoring

Surveiller les logs Ant Media après redémarrage :

```bash
# Logs en temps réel
sudo tail -f /var/log/antmedia/antmedia-error.log | grep -i hls

# Vérifier la génération des segments
ls -lth /usr/local/antmedia/webapps/LiveApp/streams/live_transcoded*.ts | head -10

# Vérifier la playlist
cat /usr/local/antmedia/webapps/LiveApp/streams/live_transcoded.m3u8
```

## ✅ Validation

Après redémarrage, vérifier :
- ✅ Les segments restent plus de 60 secondes
- ✅ La playlist ne référence plus de segments manquants
- ✅ Pas d'erreurs DEMUXER_ERROR_COULD_NOT_PARSE côté client
- ✅ Flux stable sans coupures

---

**Référence :** Solution basée sur l'analyse du problème de segments manquants dans Ant Media Server HLS streaming.





