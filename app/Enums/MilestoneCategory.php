<?php

namespace App\Enums;

enum MilestoneCategory: string
{
    case Motor = 'motor';
    case Cognitive = 'cognitive';
    case Social = 'social';
    case Language = 'language';
    case Custom = 'custom';
}
