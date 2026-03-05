# Plan d'exécution : routage `/hls/streams/unified.m3u8` vers Laravel

Objectif : faire servir l’URL publique par Laravel (PHP-FPM) pour que la détection live/VoD et les transitions soient correctes.  
Fichier Nginx concerné sur le serveur : **`/etc/nginx/sites-enabled/tv.embmission.com`**.

---

## État actuel (rappel)

- **sites-available** : `location = /hls/streams/unified.m3u8` → rewrite + fastcgi (Laravel).
- **sites-enabled** (actif) : même `location` → `proxy_pass` vers Rust (8081).
- Conséquence : l’URL publique ne renvoie pas l’en-tête Laravel (script KO).

---

## Étapes d’exécution

### Étape 1 — Backup de la config active

Sur le serveur :

```bash
sudo cp /etc/nginx/sites-enabled/tv.embmission.com /etc/nginx/sites-enabled/tv.embmission.com.bak-$(date +%Y%m%d-%H%M%S)
```

Vérifier que le fichier existe :

```bash
ls -la /etc/nginx/sites-enabled/tv.embmission.com*
```

---

### Étape 2 — Remplacer le bloc `location = /hls/streams/unified.m3u8`

Ouvrir le fichier :

```bash
sudo nano /etc/nginx/sites-enabled/tv.embmission.com
```

Rechercher le bloc suivant (actuellement proxy vers Rust) :

```nginx
    location = /hls/streams/unified.m3u8 {
        # ✅ Même moteur que /api/stream : proxy vers Rust (évite latence Laravel → moins de gels)
        proxy_pass http://127.0.0.1:8081/unified.m3u8;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_buffering off;
        proxy_cache off;
        add_header Cache-Control "no-cache, no-store, must-revalidate, private" always;
    }
```

Le **remplacer entièrement** par le bloc ci-dessous (servi par Laravel) :

```nginx
    location = /hls/streams/unified.m3u8 {
        # Servi par Laravel (détection live/VoD et transitions correctes)
        rewrite ^/hls/streams/unified.m3u8$ /api/stream/unified.m3u8 break;
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/emb-mission/public/index.php;
        fastcgi_param REQUEST_URI /api/stream/unified.m3u8;
        fastcgi_param QUERY_STRING $query_string;
    }
```

Enregistrer et quitter (nano : Ctrl+O, Entrée, Ctrl+X).

---

### Étape 3 — Tester la configuration Nginx

```bash
sudo nginx -t
```

Résultat attendu : `syntax is ok` et `test is successful`.  
Si erreur : corriger le fichier (vérifier guillemets, points-virgules, chemins) puis relancer `sudo nginx -t`.

---

### Étape 4 — Recharger Nginx

```bash
sudo systemctl reload nginx
```

Vérifier que Nginx tourne :

```bash
sudo systemctl status nginx
```

---

### Étape 5 — Vérification automatique

Depuis le serveur (ou une machine ayant accès à `https://tv.embmission.com`) :

```bash
cd /var/www/emb-mission
./scripts/verify-unified-stream-routing.sh
```

Résultat attendu pour l’étape 1 du script : **OK - L'URL publique est bien traitee par Laravel.**

Vérification manuelle optionnelle :

```bash
curl -sI "https://tv.embmission.com/hls/streams/unified.m3u8" | grep -i x-served-by
```

Attendu : `X-Served-By: Laravel-UnifiedStream`

---

### Étape 6 — Validation en conditions réelles (recommandé)

- Ouvrir la page watch / lecteur sur `https://tv.embmission.com` et vérifier que le flux démarre (mode VoD ou live selon l’état actuel).
- Si possible : lancer un stream live (OBS/vMix → Ant Media), vérifier le passage en live puis l’arrêt du stream et le retour en VoD (transition sans erreur visible).

---

## Rollback en cas de problème

Si le flux ne fonctionne plus ou si tu veux revenir à Rust :

```bash
# Restaurer le dernier backup (adapter le nom du fichier selon la date créée à l’étape 1)
sudo cp /etc/nginx/sites-enabled/tv.embmission.com.bak-YYYYMMDD-HHMMSS /etc/nginx/sites-enabled/tv.embmission.com
sudo nginx -t && sudo systemctl reload nginx
```

Puis relancer `./scripts/verify-unified-stream-routing.sh` : l’étape 1 doit repasser en KO (Rust).

---

## Résumé des commandes (copier-coller)

```bash
# 1. Backup
sudo cp /etc/nginx/sites-enabled/tv.embmission.com /etc/nginx/sites-enabled/tv.embmission.com.bak-$(date +%Y%m%d-%H%M%S)

# 2. Éditer (remplacer le bloc location unified.m3u8 par le bloc Laravel ci-dessus)
sudo nano /etc/nginx/sites-enabled/tv.embmission.com

# 3. Test + reload
sudo nginx -t && sudo systemctl reload nginx

# 4. Vérification
cd /var/www/emb-mission && ./scripts/verify-unified-stream-routing.sh
```

---

## Fichier de référence du bloc Laravel

Le bloc Nginx exact à utiliser est aussi présent dans le dépôt : **`nginx-snippet-unified-stream-laravel.conf`** (à copier si besoin).
