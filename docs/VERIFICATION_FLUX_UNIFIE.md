# Vérifications du flux unifié (live / VoD et routage Nginx)

Ce document décrit comment vérifier que la détection live/VoD et les transitions fonctionnent correctement, et que Nginx envoie bien les requêtes vers Laravel.

---

## 1. Script automatique : qui sert `unified.m3u8` ?

Laravel envoie l’en-tête **`X-Served-By: Laravel-UnifiedStream`** sur toutes les réponses du flux unifié. On peut donc savoir si une URL est servie par Laravel ou par un autre service (Rust, fichier statique).

**Lancer le script (depuis la racine du projet ou le serveur) :**

```bash
chmod +x scripts/verify-unified-stream-routing.sh
./scripts/verify-unified-stream-routing.sh
```

Ou en ciblant un autre domaine :

```bash
BASE_URL=https://tv.embmission.com ./scripts/verify-unified-stream-routing.sh
```

**Interprétation :**

- Si le script affiche **OK** pour l'URL publique : les clients passent bien par Laravel, détection live/VoD et transitions correctes.
- Si le script affiche **KO** pour l'URL publique : Nginx envoie cette URL à Rust ou à un fichier. Il faut alors ajouter/modifier une règle Nginx pour que cette URL soit traitée par Laravel (voir section 3).

---

## 2. Vérifications manuelles

### 2.1 Vérifier que l’URL publique passe par Laravel

```bash
curl -sI "https://tv.embmission.com/hls/streams/unified.m3u8" | grep -i x-served-by
```

Résultat attendu : `X-Served-By: Laravel-UnifiedStream`

### 2.2 Vérifier l’endpoint API Laravel

```bash
curl -sI "https://tv.embmission.com/api/stream/unified.m3u8" | grep -i x-served-by
```

Même en-tête attendu. Si l’API le renvoie mais pas l’URL publique, le problème vient du routage Nginx de `/hls/streams/unified.m3u8`.

### 2.3 Vérifier le mode (live vs VoD) côté Laravel

Depuis le serveur (contexte Laravel) :

```bash
cd /var/www/emb-mission
php artisan tinker --execute="
\$s = app(\App\Services\WebTVAutoPlaylistService::class);
\$ctx = \$s->getCurrentPlaybackContext();
echo 'Mode: ' . (\$ctx['mode'] ?? '?') . PHP_EOL;
echo 'Success: ' . (\$ctx['success'] ? 'yes' : 'no') . PHP_EOL;
"
```

Cela confirme que la détection live/VoD fonctionne côté application.

---

## 3. Vérifier la configuration Nginx (sur le serveur)

Sur la machine qui héberge Nginx :

```bash
# Chercher les blocs qui concernent le flux unifié ou le port Rust
sudo grep -R 'unified.m3u8\|hls/streams\|8081' /etc/nginx/

# Voir la config complète et filtrer
sudo nginx -T 2>/dev/null | grep -B2 -A8 'unified\|hls/streams'
```

**Souhaité :** une `location` pour `/hls/streams/unified.m3u8` qui envoie la requête à **PHP-FPM** (Laravel), et non uniquement au service Rust (port 8081). Exemple de bloc à intégrer dans le vhost `tv.embmission.com` (à adapter selon votre config) :

```nginx
location = /hls/streams/unified.m3u8 {
    try_files $uri @laravel_unified;
}
location @laravel_unified {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME /var/www/emb-mission/public/index.php;
    fastcgi_param REQUEST_URI /api/stream/unified.m3u8;
    fastcgi_param QUERY_STRING $query_string;
}
```

Après modification :

```bash
sudo nginx -t && sudo systemctl reload nginx
```

Puis relancer le script de la section 1 pour confirmer que l’URL publique est bien servie par Laravel.

---

## 4. Résumé

| Vérification | Commande / action |
|-------------|-------------------|
| Qui sert l’URL publique ? | `./scripts/verify-unified-stream-routing.sh` ou `curl -sI ... \| grep -i x-served-by` |
| L’API Laravel répond ? | `curl -sI https://tv.embmission.com/api/stream/unified.m3u8` |
| Mode courant (live/VoD) ? | `php artisan tinker --execute="..."` (voir 2.3) |
| Config Nginx | `sudo grep -R 'hls/streams\|8081' /etc/nginx/` puis lecture du vhost |

Si l’URL publique renvoie `X-Served-By: Laravel-UnifiedStream`, la détection live/VoD et les transitions sont bien appliquées pour les clients utilisant `https://tv.embmission.com/hls/streams/unified.m3u8`.
quées pour les clients utilisant `https://tv.embmission.com/hls/streams/unified.m3u8`.
