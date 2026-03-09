# Redémarrage des services depuis le Monitoring (sudo)

Le tableau de bord **Monitoring système** (https://tv.embmission.com/system-monitoring) permet de redémarrer des services (ex. `laravel-queue-worker`).  
PHP tourne sous l’utilisateur **www-data**, qui n’a pas le droit d’exécuter `systemctl restart` sans élévation de privilèges. Sans configuration, le bouton « Redémarrer » renvoie **Échec de redémarrage**.

## Cause

- La commande exécutée est : `systemctl restart <service>`.
- Elle est lancée par le processus PHP (utilisateur **www-data**).
- Seul **root** (ou un utilisateur autorisé par sudo) peut redémarrer des services systemd.

## Solution : autoriser www-data à redémarrer certains services

Sur le serveur, en **root** ou avec `sudo` :

1. Créer un fichier sudoers dédié (ne pas éditer `/etc/sudoers` à la main) :

```bash
sudo visudo -f /etc/sudoers.d/emb-mission-monitoring
```

2. Y ajouter (une seule ligne, en remplaçant par les services dont vous avez besoin) :

```
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart laravel-queue-worker, /bin/systemctl restart unified-stream, /bin/systemctl restart laravel-reverb, /bin/systemctl restart supervisor, /bin/systemctl restart nginx, /bin/systemctl restart php8.1-fpm, /bin/systemctl restart antmedia, /bin/systemctl restart ffmpeg-live-transcode
```

3. Sauvegarder et quitter. Vérifier les permissions :

```bash
sudo chmod 440 /etc/sudoers.d/emb-mission-monitoring
```

4. Dans l’application, la route de redémarrage doit appeler **sudo** (ex. `sudo systemctl restart laravel-queue-worker`). Si ce n’est pas le cas, il faut adapter le contrôleur pour utiliser `sudo` devant la commande.

Après cela, le redémarrage depuis le Monitoring devrait fonctionner pour les services listés.

## Redémarrage manuel (sans config sudo)

En SSH sur le serveur :

```bash
sudo systemctl restart laravel-queue-worker
sudo systemctl status laravel-queue-worker
```
