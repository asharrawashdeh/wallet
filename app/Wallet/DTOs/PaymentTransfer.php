<?php

namespace App\Wallet\DTOs;

readonly class PaymentTransfer
{
    /**
     * @param  list<string>  $notes
     */
    public function __construct(
        public string $reference,
        public string $date,
        public string $amount,
        public string $currency,
        public string $senderAccountNumber,
        public string $receiverBankCode,
        public string $receiverAccountNumber,
        public string $receiverBeneficiaryName,
        public array $notes = [],
        public string $paymentType = '99',
        public string $chargeDetails = 'SHA',
    ) {}
}
