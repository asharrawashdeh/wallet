<?php

namespace App\Wallet\Payment;

use App\Wallet\Contracts\PaymentRequestXmlBuilder;
use App\Wallet\Contracts\XmlElement;
use App\Wallet\DTOs\PaymentTransfer;
use DOMDocument;

class DomPaymentRequestXmlBuilder implements PaymentRequestXmlBuilder
{
    /**
     * @param  list<XmlElement>  $elements
     */
    public function __construct(
        private array $elements,
    ) {}

    public function build(PaymentTransfer $transfer): string
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('PaymentRequestMessage');
        $doc->appendChild($root);

        foreach ($this->elements as $element) {
            if ($element->shouldInclude($transfer)) {
                $element->render($doc, $root, $transfer);
            }
        }

        $xml = $doc->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Failed to serialize payment XML.');
        }

        return $xml;
    }
}
