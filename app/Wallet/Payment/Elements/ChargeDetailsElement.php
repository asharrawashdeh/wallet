<?php

namespace App\Wallet\Payment\Elements;

use App\Wallet\DTOs\PaymentTransfer;
use DOMDocument;
use DOMElement;

class ChargeDetailsElement extends AbstractXmlElement
{
    public function shouldInclude(PaymentTransfer $transfer): bool
    {
        return strtoupper($transfer->chargeDetails) !== 'SHA';
    }

    public function render(DOMDocument $doc, DOMElement $root, PaymentTransfer $transfer): void
    {
        $this->appendTextChild($doc, $root, 'ChargeDetails', $transfer->chargeDetails);
    }
}
