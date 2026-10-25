<?php

namespace App\Enums;

enum Mood: string
{
    case Happy = 'happy';
    case Calm = 'calm';
    case Fussy = 'fussy';
    case Crying = 'crying';
}
