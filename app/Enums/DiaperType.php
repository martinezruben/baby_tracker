<?php

namespace App\Enums;

enum DiaperType: string
{
    case Wet = 'wet';
    case Dirty = 'dirty';
    case Both = 'both';
    case Dry = 'dry';
}
