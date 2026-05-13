<?php

namespace App\Wallet\Payment\Elements;

use App\Wallet\DTOs\PaymentTransfer;
use DOMDocument;
use DOMElement;

class SenderInfoElement extends AbstractXmlElement
{
    public function shouldInclude(PaymentTransfer $transfer): bool
    {
        return true;
    }

    public function render(DOMDocument $doc, DOMElement $root, PaymentTransfer $transfer): void
    {
        $section = $doc->createElement('SenderInfo');
        $this->appendTextChild($doc, $section, 'AccountNumber', $transfer->senderAccountNumber);
        $root->appendChild($section);
    }
}
