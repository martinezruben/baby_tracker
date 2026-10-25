<?php

namespace App\Enums;

enum FoodEnjoyment: string
{
    case Hated = 'hated';
    case Disliked = 'disliked';
    case Neutral = 'neutral';
    case Liked = 'liked';
    case Loved = 'loved';
}
