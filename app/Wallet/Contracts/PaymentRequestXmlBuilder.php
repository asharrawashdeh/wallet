<?php

namespace App\Wallet\Contracts;

use App\Wallet\DTOs\PaymentTransfer;

interface PaymentRequestXmlBuilder
{
    public function build(PaymentTransfer $transfer): string;
}
