<?php

namespace App\Enums;

enum FeedType: string
{
    case Breast = 'breast';
    case Bottle = 'bottle';
    case Solids = 'solids';
}
