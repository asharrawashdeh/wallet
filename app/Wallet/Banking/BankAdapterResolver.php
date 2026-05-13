<?php

namespace App\Wallet\Banking;

use App\Wallet\Contracts\WebhookBankAdapter;
use InvalidArgumentException;

class BankAdapterResolver
{
    /**
     * @param  array<string, WebhookBankAdapter>  $adaptersByBank
     */
    public function __construct(
        private array $adaptersByBank,
    ) {}

    public function resolve(string $bank): WebhookBankAdapter
    {
        $bank = strtolower($bank);
        if (! isset($this->adaptersByBank[$bank])) {
            throw new InvalidArgumentException("Unsupported bank adapter: {$bank}");
        }

        return $this->adaptersByBank[$bank];
    }
}
