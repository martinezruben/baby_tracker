<?php

namespace App\Enums;

enum AckScope: string
{
    case Any = 'any';
    case Each = 'each';
}
