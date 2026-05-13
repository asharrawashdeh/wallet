<?php

namespace App\Wallet\Payment\Elements;

use App\Wallet\DTOs\PaymentTransfer;
use DOMDocument;
use DOMElement;

class TransferInfoElement extends AbstractXmlElement
{
    public function shouldInclude(PaymentTransfer $transfer): bool
    {
        return true;
    }

    public function render(DOMDocument $doc, DOMElement $root, PaymentTransfer $transfer): void
    {
        $section = $doc->createElement('TransferInfo');
        $this->appendTextChild($doc, $section, 'Reference', $transfer->reference);
        $this->appendTextChild($doc, $section, 'Date', $transfer->date);
        $this->appendTextChild($doc, $section, 'Amount', $transfer->amount);
        $this->appendTextChild($doc, $section, 'Currency', $transfer->currency);
        $root->appendChild($section);
    }
}
