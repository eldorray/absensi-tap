<?php

namespace App\Enums;

enum StatusPerangkat: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Revoked = 'revoked';
}
