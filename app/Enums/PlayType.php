<?php

namespace App\Enums;

enum PlayType: string
{
    case TummyTime = 'tummy_time';
    case IndoorPlay = 'indoor_play';
    case OutdoorPlay = 'outdoor_play';
    case Walk = 'walk';
    case Custom = 'custom';
}
