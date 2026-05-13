<?php

namespace App\Wallet\Contracts;

use App\Wallet\DTOs\PaymentTransfer;
use DOMDocument;
use DOMElement;

interface XmlElement
{
    public function shouldInclude(PaymentTransfer $transfer): bool;

    public function render(DOMDocument $doc, DOMElement $root, PaymentTransfer $transfer): void;
}
