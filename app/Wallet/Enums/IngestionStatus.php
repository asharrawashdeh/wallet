<?php

namespace App\Wallet\Enums;

enum IngestionStatus: string
{
    case PendingDispatch = 'pending_dispatch';
    case Dispatched = 'dispatched';
    case Completed = 'completed';
    case Failed = 'failed';
}
