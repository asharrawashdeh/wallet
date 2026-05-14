<?php

namespace App\Wallet;

use Illuminate\Support\Facades\Log;

final class WalletLogger
{
    public static function info(string $message, array $context = []): void
    {
        static::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        static::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        static::log('error', $message, $context);
    }

    private static function log(string $level, string $message, array $context): void
    {
        if (! config('wallet.logging_enabled', true)) {
            return;
        }

        Log::channel('wallet')->{$level}($message, $context);
    }
}
