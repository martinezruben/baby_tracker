<?php

namespace App\Enums;

enum AllergenType: string
{
    case Food = 'food';
    case Medicine = 'medicine';
    case Environment = 'environment';
    case Other = 'other';
}
