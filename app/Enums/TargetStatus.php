<?php

namespace App\Enums;

enum TargetStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Exhausted = 'exhausted';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
