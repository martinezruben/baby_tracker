<?php

return [
    // Cuántos respaldos automáticos se conservan en el servidor (los más recientes).
    'keep' => (int) env('BACKUP_KEEP', 7),

    // Hora (del servidor) del respaldo diario automático.
    'time' => env('BACKUP_TIME', '03:10'),

    // El automático solo guarda la base de datos: las fotos y los archivos ya están en el mismo disco.
    // El manual puede incluirlos (ZIP con todo) para llevarlo a otro servidor.
    'auto_include_files' => (bool) env('BACKUP_AUTO_FILES', false),
];
