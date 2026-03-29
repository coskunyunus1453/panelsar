<?php

namespace App\Enums;

enum DomainStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Pending = 'pending';
    case Disabled = 'disabled';
    case Expired = 'expired';
}
