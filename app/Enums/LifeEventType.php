<?php

namespace App\Enums;

enum LifeEventType: string
{
    case Tooth = 'tooth';
    case Food = 'food';
    case FirstSteps = 'first_steps';
    case Crawling = 'crawling';
    case Word = 'word';
    case Trip = 'trip';
    case Illness = 'illness';
    case Other = 'other';
}
