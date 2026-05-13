<?php

namespace App\Wallet\Contracts;

use App\Wallet\DTOs\NormalizedIncomingTransaction;

interface WebhookBankAdapter
{
    /**
     * Parse a single bank-specific line into our canonical shape, or null if the line is not parseable.
     */
    public function parseLine(string $line): ?NormalizedIncomingTransaction;
}
