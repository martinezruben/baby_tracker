<?php

namespace App\Contracts;

use App\Models\Caretaker;

interface ReminderChannel
{
    /**
     * Envía el aviso. Lanza una excepción si no se pudo entregar.
     *
     * @param  array{title: string, body: ?string, target_id: int, occurrence_id: int, event_type: string, snooze_options: array<int, int>, requires_ack: bool}  $payload
     */
    public function send(Caretaker $caretaker, array $payload): void;
}
