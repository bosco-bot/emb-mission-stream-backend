# 🎵 Configuration Google Analytics 4 (GA4) pour EMB Mission

## ✅ Ce qui est déjà configuré

### 1. Page WebRadio `/player` - Tracking JavaScript
- **URL**: `https://radio.embmission.com/player`
- **Méthode**: Google Analytics JavaScript standard (gtag.js)
- **ID**: `G-ZN07XKXPCN`
- **Événements trackés**:
  - `radio_play` - Démarrage de la lecture
  - `radio_pause` - Mise en pause
  - `radio_listening_time` - Temps d'écoute (toutes les 60 secondes)

### 2. URL Directe `/listen` - Tracking Serveur
- **URL**: `https://radio.embmission.com/listen`
- **Méthode**: Google Analytics Measurement Protocol
- **Commande**: `php artisan analytics:track-listeners`
- **Événements trackés**:
  - `stream_access` - Accès direct au stream
  - Données: IP, Country, Device Type

---

## 🔧 Configuration nécessaire

### Étape 1: Obtenir le API Secret de Google Analytics

1. Allez sur https://analytics.google.com
2. Sélectionnez votre propriété GA4 "EMB Mission"
3. Allez dans **Admin** (⚙️) → **Data Streams** → Cliquez sur votre stream
4. Dans la section **Measurement Protocol API secrets**, cliquez sur **Create**
5. Donnez un nom (ex: "EMB Mission Server Tracking")
6. **COPIEZ** le secret généré (format: `xxxxxxxxxxxx`)

### Étape 2: Ajouter le secret dans Laravel

Connectez-vous au serveur et ajoutez la variable dans `.env`:

```bash
ssh emb-mission-server
cd /var/www/emb-mission
nano .env
```

Ajoutez cette ligne (remplacez `votre_secret_ici` par le vrai secret):

```
GA4_API_SECRET=votre_secret_ici
```

Sauvegardez (Ctrl+X, puis Y, puis Enter) et rechargez:

```bash
php artisan config:clear
php artisan cache:clear
```

### Étape 3: Tester le tracking

Testez manuellement:

```bash
php artisan analytics:track-listeners --hours=24
```

---

## 📊 Automatiser le tracking (Optionnel mais recommandé)

Pour tracker automatiquement les auditeurs toutes les heures, ajoutez une tâche cron:

```bash
crontab -e
```

Ajoutez cette ligne:

```
0 * * * * cd /var/www/emb-mission && php artisan analytics:track-listeners --hours=1 >> /dev/null 2>&1
```

Cela exécutera le tracking toutes les heures.

---

## 📈 Visualiser les données dans GA4

### Dans Google Analytics:

1. Allez dans **Reports** → **Engagement** → **Events**
2. Vous verrez ces événements:
   - `radio_play`
   - `radio_pause`
   - `radio_listening_time`
   - `stream_access`

### Créer un rapport personnalisé:

1. **Reports** → **Explore** → **Blank**
2. Ajoutez ces dimensions:
   - Event name
   - Country (pour les accès directs)
   - Device category
3. Ajoutez ces métriques:
   - Event count
   - Active users
4. Filtrez par ces événements audio:
   - `radio_play`
   - `radio_pause`
   - `stream_access`

---

## 🎯 Résumé

| Source | Méthode | État | Action requise |
|--------|---------|------|----------------|
| `/player` | JavaScript | ✅ Actif | Aucune |
| `/listen` | Measurement Protocol | ⏳ En attente | Ajouter GA4_API_SECRET |

---

## ❓ Dépannage

### Les données n'apparaissent pas dans GA4?

1. Vérifiez que le `GA4_API_SECRET` est correct dans `.env`
2. Exécutez: `php artisan config:clear`
3. Testez: `php artisan analytics:track-listeners --hours=1`
4. Attendez 5-10 minutes (GA4 a un délai de traitement)

### Erreur "GA4_API_SECRET non configuré"?

Ajoutez le secret dans le fichier `.env` (voir Étape 2)

### Les événements ne sont pas trackés en temps réel?

C'est normal! GA4 traite les données avec un délai de 24-48h pour les rapports standard.

---

## 📝 Notes importantes

- **Délai de traitement**: Les données peuvent prendre jusqu'à 24-48h pour apparaître dans les rapports standards
- **Realtime**: Les données temps réel sont disponibles immédiatement (Realtime → Events)
- **Quotas**: Google Analytics limite à 1 million d'événements par jour
- **Géolocalisation**: Le service ip-api.com est gratuit jusqu'à 45 requêtes/minute

---

## 🎉 Terminé!

Votre système de tracking est maintenant complet:
- ✅ Tracking JavaScript pour la page web
- ✅ Tracking serveur pour les accès directs
- ✅ Géolocalisation des auditeurs
- ✅ Détection du type d'appareil

Les données seront visibles dans Google Analytics dans les prochaines heures!

