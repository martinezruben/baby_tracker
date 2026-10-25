<?php

namespace App\Events;

use App\Models\Caretaker;
use App\Models\NotificationOccurrence;

/** Un cuidador confirmó ("Dada", "Listo"…) un aviso; otros módulos reaccionan (p. ej. registrar la toma). */
final readonly class ReminderAcknowledged
{
    public function __construct(
        public NotificationOccurrence $occurrence,
        public Caretaker $by,
    ) {}
}
