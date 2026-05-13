<?php

namespace App\Wallet\DTOs;

readonly class NormalizedIncomingTransaction
{
    /**
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public string $reference,
        public string $amount,
        public string $occurredAtYmd,
        public array $metadata,
        public string $rawLine,
    ) {}
}
