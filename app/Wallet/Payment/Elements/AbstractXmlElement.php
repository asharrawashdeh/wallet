<?php

namespace App\Wallet\Payment\Elements;

use App\Wallet\Contracts\XmlElement;
use DOMDocument;
use DOMElement;

abstract class AbstractXmlElement implements XmlElement
{
    protected function appendTextChild(DOMDocument $doc, DOMElement $parent, string $name, string $value): void
    {
        $el = $doc->createElement($name);
        $el->appendChild($doc->createTextNode($value));
        $parent->appendChild($el);
    }
}
