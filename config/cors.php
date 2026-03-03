<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*', 'hls/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Production
        'https://radio.embmission.com',
        'https://tv.embmission.com',
        'https://rtv.embmission.com',
    ],

    'allowed_origins_patterns' => [
        // Développement (pour Flutter local et futurs devs)
        '/^http:\/\/localhost(:\d+)?$/',
        '/^http:\/\/127\.0\.0\.1(:\d+)?$/',
        // Permettre toutes les origines HTTPS (pour compatibilité maximale)
        '/^https:\/\/.*$/',
        // Permettre toutes les origines HTTP (pour développement)
        '/^http:\/\/.*$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
