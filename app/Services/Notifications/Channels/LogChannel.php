<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\ReminderChannel;
use App\Models\Caretaker;
use Illuminate\Support\Facades\Log;

class LogChannel implements ReminderChannel
{
    public function send(Caretaker $caretaker, array $payload): void
    {
        Log::info('Aviso enviado', ['caretaker_id' => $caretaker->id] + $payload);
    }
}
