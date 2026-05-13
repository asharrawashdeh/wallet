<?php

namespace App\Wallet;

use Illuminate\Support\Facades\Cache;

final class WalletIngestionGate
{
    public const CACHE_KEY = 'wallet:ingestion_enabled';

    public static function enabled(): bool
    {
        if (Cache::has(self::CACHE_KEY)) {
            return (bool) Cache::get(self::CACHE_KEY);
        }

        return (bool) config('wallet.ingestion_enabled');
    }
}
