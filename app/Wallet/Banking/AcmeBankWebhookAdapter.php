<?php

namespace App\Wallet\Banking;

use App\Wallet\Contracts\WebhookBankAdapter;
use App\Wallet\DTOs\NormalizedIncomingTransaction;

class AcmeBankWebhookAdapter implements WebhookBankAdapter
{
    public function parseLine(string $line): ?NormalizedIncomingTransaction
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        $parts = explode('//', $line);
        if (count($parts) !== 3) {
            return null;
        }

        [$amountRaw, $reference, $dateRaw] = array_map('trim', $parts);

        if ($reference === '' || ! preg_match('/^\d{8}$/', $dateRaw)) {
            return null;
        }

        if (! preg_match('/^\d+,\d{2}$/', $amountRaw)) {
            return null;
        }

        $amount = str_replace(',', '.', $amountRaw);

        return new NormalizedIncomingTransaction(
            reference: $reference,
            amount: $amount,
            occurredAtYmd: $dateRaw,
            metadata: [],
            rawLine: $line,
        );
    }
}
