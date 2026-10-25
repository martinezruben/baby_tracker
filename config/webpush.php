<?php

return [
    // Claves VAPID: se generan con `php artisan webpush:vapid` y van SOLO en el .env (nunca en el repo).
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:'.env('MAIL_FROM_ADDRESS', 'hello@example.com')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    // Cuánto tiempo conserva el servicio push un aviso si el teléfono está apagado.
    'ttl_seconds' => 3600,

    // Validez de los botones Confirmar/Posponer incluidos en cada aviso.
    'action_ttl_hours' => 48,

    // Tras tantos fallos seguidos de entrega (que no sean "suscripción caducada") se borra la suscripción.
    'max_failures' => 5,
];
