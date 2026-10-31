<?php

namespace App\Enums;

enum ErpSyncState: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
}
