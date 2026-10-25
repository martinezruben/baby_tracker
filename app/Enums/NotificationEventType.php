<?php

namespace App\Enums;

enum NotificationEventType: string
{
    case Feed = 'feed';
    case Diaper = 'diaper';
    case Sleep = 'sleep';
    case Medicine = 'medicine';
    case Vaccine = 'vaccine';
    case Appointment = 'appointment';
    case Custom = 'custom';
}
