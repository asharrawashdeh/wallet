<?php

return [
    /*
    | When false, webhook payloads are stored but line jobs are not dispatched until
    | replayed (e.g. php artisan wallet:dispatch-pending-webhooks). Cache key
    | wallet:ingestion_enabled overrides this when set (boolean).
    */
    'ingestion_enabled' => env('WALLET_INGESTION_ENABLED', true),

    /*
    | Per-bank HMAC shared secrets for webhook signature verification.
    | The bank signs the request body with HMAC-SHA256 and sends the hex digest
    | in the X-Webhook-Signature header. Set to null to skip verification for
    | a bank (useful in development).
    */
    'bank_secrets' => [
        'foodics' => env('WEBHOOK_SECRET_FOODICS'),
        'acme' => env('WEBHOOK_SECRET_ACME'),
    ],
];
