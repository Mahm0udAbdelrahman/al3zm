<?php

namespace App\Enums;

enum ItemType: string
{
    case Flight = 'flight';
    case Stay = 'stay';
    case Activity = 'activity';
}
