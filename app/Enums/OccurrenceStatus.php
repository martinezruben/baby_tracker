<?php

namespace App\Enums;

enum OccurrenceStatus: string
{
    case Active = 'active';
    case Acknowledged = 'acknowledged';
    case Finished = 'finished';
    case Cancelled = 'cancelled';
}
