# horizons-collector-laravel

Package Composer interne HorizonsPlus — collecte les métadonnées techniques d'un projet **Laravel** via `GET /api/monitoring/health`.

Même contrat JSON que `horizons-collector-node`.

## Installation (projet client Laravel, ex. LUXÎLES)

Dans le `composer.json` du projet client :

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../horizons/packages/horizons-collector-laravel",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "horizonsplus/collector-laravel": "@dev"
  }
}
```

```bash
composer require horizonsplus/collector-laravel:@dev
```

## Configuration

`.env` du projet client :

```env
HORIZONS_MONITORING_TOKEN=la-cle-generee-dans-horizonsplus
HORIZONS_DIGEST_WEBHOOK_SECRET=le-secret-webhook-genere-dans-horizonsplus
```

Le package s'enregistre automatiquement via Laravel Package Discovery.

## Endpoints

### Collecte — `GET /api/monitoring/health`

Header : `Authorization: Bearer <HORIZONS_MONITORING_TOKEN>`

Réponse (extrait) :

```json
{
  "status": "ok",
  "collected_at": "2026-04-01T10:00:00.000Z",
  "runtime": { "php": "8.2.12" },
  "framework": { "name": "laravel", "version": "12.63.0" },
  "dependencies": { "laravel/framework": "^12.0" },
  "audit": {
    "tool": "composer",
    "vulnerabilities": [
      {
        "name": "vendor/package",
        "severity": "high",
        "title": "..."
      }
    ]
  },
  "updates": {
    "tool": "composer",
    "packages": [
      {
        "name": "laravel/framework",
        "current": "12.0.0",
        "latest": "12.5.0",
        "severity": "low"
      }
    ]
  }
}
```

Le collecteur exécute :
- `composer audit` → failles de sécurité
- `composer outdated --direct` → mises à jour de dépendances directes

### Notification digest — `POST /api/horizons/digests`

Reçoit le digest reformulé poussé par HorizonsPlus à l'envoi.

Header : `X-Horizons-Signature: <hmac-sha256 du body avec HORIZONS_DIGEST_WEBHOOK_SECRET>`

Payload (extrait) :

```json
{
  "event": "digest.sent",
  "sent_at": "2026-07-01T10:00:00+00:00",
  "digest": {
    "id": 12,
    "period_start": "2026-06-01",
    "period_end": "2026-06-30",
    "has_findings": true,
    "headline": "Une mise à jour est recommandée",
    "tone": "warn",
    "items": [
      {
        "project": "LUXÎLES — Plateforme web",
        "title": "Mise à jour de sécurité recommandée",
        "body": "Nous vous conseillons d'appliquer une mise à jour…"
      }
    ]
  }
}
```

Le package émet l'événement `HorizonsPlus\CollectorLaravel\Events\DigestReceived`.
Dans le backoffice client (ex. LUXÎLES), écoutez-le pour créer une notification :

```php
use HorizonsPlus\CollectorLaravel\Events\DigestReceived;

Event::listen(DigestReceived::class, function (DigestReceived $event) {
    // Créer une notification backoffice à partir de $event->payload
});
```

Dans HorizonsPlus → Clients → renseigner :
`https://admin.luxiles.com/api/horizons/digests` + générer le secret.

## Sécurité

- HTTPS obligatoire en production
- Token / secret en variables d'environnement uniquement
- Collecte : aucune donnée métier — métadonnées techniques seulement
- Digest : contenu déjà reformulé en langage client (validé par l'agence)

## Publier la config (optionnel)

```bash
php artisan vendor:publish --tag=horizons-collector-config
```
