<?php

namespace App\Enums;

enum MeasurementType: string
{
    case Height = 'height';
    case Weight = 'weight';
    case HeadCircumference = 'head_circumference';
    case Temperature = 'temperature';
}
