<?php

use App\Services\Notifications\Channels\LogChannel;

return [
    // Canal => clase que lo envía. 'push' usará Web Push cuando se conecte el paquete.
    'channels' => [
        'push' => LogChannel::class,
        'log' => LogChannel::class,
    ],

    // Tope de repeticiones cuando una regla repite sin pedir confirmación ni límite propio.
    'default_max_repeats' => 3,

    // Tope absoluto para reglas que insisten hasta confirmar (evita avisos infinitos).
    'hard_cap_repeats' => 288,

    // Reintentos cuando fallan todos los canales de un envío.
    'retry_after_minutes' => 5,
    'max_failed_attempts' => 3,

    'default_snooze_options' => [5, 10, 15, 30],
];
