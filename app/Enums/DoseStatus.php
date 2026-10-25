<?php

namespace App\Enums;

enum DoseStatus: string
{
    case Pending = 'pending';
    case Given = 'given';
    case Skipped = 'skipped';
}
