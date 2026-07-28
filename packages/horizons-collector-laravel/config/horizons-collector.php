<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token de monitoring HorizonsPlus
    |--------------------------------------------------------------------------
    |
    | Authentifie GET /api/monitoring/health (collecte).
    |
    */

    'token' => env('HORIZONS_MONITORING_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Secret webhook digest
    |--------------------------------------------------------------------------
    |
    | Authentifie POST /api/horizons/digests (notification digests).
    | Même valeur que le secret généré dans HorizonsPlus → Clients.
    |
    */

    'digest_webhook_secret' => env('HORIZONS_DIGEST_WEBHOOK_SECRET'),

];
