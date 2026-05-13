<?php

namespace App\Wallet\Payment\Elements;

use App\Wallet\DTOs\PaymentTransfer;
use DOMDocument;
use DOMElement;

class ReceiverInfoElement extends AbstractXmlElement
{
    public function shouldInclude(PaymentTransfer $transfer): bool
    {
        return true;
    }

    public function render(DOMDocument $doc, DOMElement $root, PaymentTransfer $transfer): void
    {
        $section = $doc->createElement('ReceiverInfo');
        $this->appendTextChild($doc, $section, 'BankCode', $transfer->receiverBankCode);
        $this->appendTextChild($doc, $section, 'AccountNumber', $transfer->receiverAccountNumber);
        $this->appendTextChild($doc, $section, 'BeneficiaryName', $transfer->receiverBeneficiaryName);
        $root->appendChild($section);
    }
}
