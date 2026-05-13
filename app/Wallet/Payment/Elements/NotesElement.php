<?php

namespace App\Wallet\Payment\Elements;

use App\Wallet\DTOs\PaymentTransfer;
use DOMDocument;
use DOMElement;

class NotesElement extends AbstractXmlElement
{
    public function shouldInclude(PaymentTransfer $transfer): bool
    {
        return $transfer->notes !== [];
    }

    public function render(DOMDocument $doc, DOMElement $root, PaymentTransfer $transfer): void
    {
        $section = $doc->createElement('Notes');
        foreach ($transfer->notes as $noteText) {
            $this->appendTextChild($doc, $section, 'Note', $noteText);
        }
        $root->appendChild($section);
    }
}
