<?php

namespace App\Wallet\Banking;

use App\Wallet\Contracts\WebhookBankAdapter;
use App\Wallet\DTOs\NormalizedIncomingTransaction;

class FoodicsBankWebhookAdapter implements WebhookBankAdapter
{
    public function parseLine(string $line): ?NormalizedIncomingTransaction
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        $parts = explode('#', $line);
        if (count($parts) < 2) {
            return null;
        }

        $head = trim($parts[0]);
        $reference = trim($parts[1]);
        if ($reference === '') {
            return null;
        }

        if (! preg_match('/^(\d{8})(\d+,\d{2})$/', $head, $m)) {
            return null;
        }

        [, $dateYmd, $amountRaw] = $m;
        $amount = str_replace(',', '.', $amountRaw);

        $metadata = [];
        if (isset($parts[2])) {
            $metadata = $this->parseKeyValueTail(trim($parts[2]));
        }

        return new NormalizedIncomingTransaction(
            reference: $reference,
            amount: $amount,
            occurredAtYmd: $dateYmd,
            metadata: $metadata,
            rawLine: $line,
        );
    }

    /**
     * Tail segments use "key/value" chunks separated by "/" between pair boundaries.
     * Example: "note/debt payment march/internal_reference/A462JE81"
     *
     * @return array<string, string>
     */
    private function parseKeyValueTail(string $tail): array
    {
        if ($tail === '') {
            return [];
        }

        $segments = explode('/', $tail);
        if (count($segments) < 2) {
            return [];
        }

        $out = [];
        for ($i = 0; $i + 1 < count($segments); $i += 2) {
            $key = trim($segments[$i]);
            $value = trim($segments[$i + 1]);
            if ($key !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
