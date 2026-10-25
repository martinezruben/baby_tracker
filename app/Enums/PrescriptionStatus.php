<?php

namespace App\Enums;

enum PrescriptionStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
