<?php

namespace App\Wallet\Payment\Elements;

use App\Wallet\DTOs\PaymentTransfer;
use DOMDocument;
use DOMElement;

class PaymentTypeElement extends AbstractXmlElement
{
    public function shouldInclude(PaymentTransfer $transfer): bool
    {
        return $transfer->paymentType !== '99';
    }

    public function render(DOMDocument $doc, DOMElement $root, PaymentTransfer $transfer): void
    {
        $this->appendTextChild($doc, $root, 'PaymentType', $transfer->paymentType);
    }
}
