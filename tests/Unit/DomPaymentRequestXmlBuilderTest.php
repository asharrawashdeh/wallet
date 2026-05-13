<?php

namespace Tests\Unit;

use App\Wallet\DTOs\PaymentTransfer;
use App\Wallet\Payment\DomPaymentRequestXmlBuilder;
use App\Wallet\Payment\Elements\ChargeDetailsElement;
use App\Wallet\Payment\Elements\NotesElement;
use App\Wallet\Payment\Elements\PaymentTypeElement;
use App\Wallet\Payment\Elements\ReceiverInfoElement;
use App\Wallet\Payment\Elements\SenderInfoElement;
use App\Wallet\Payment\Elements\TransferInfoElement;
use PHPUnit\Framework\TestCase;

class DomPaymentRequestXmlBuilderTest extends TestCase
{
    private DomPaymentRequestXmlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new DomPaymentRequestXmlBuilder([
            new TransferInfoElement,
            new SenderInfoElement,
            new ReceiverInfoElement,
            new NotesElement,
            new PaymentTypeElement,
            new ChargeDetailsElement,
        ]);
    }

    public function test_omits_notes_payment_type_and_charge_details_when_defaults(): void
    {
        $xml = $this->builder->build(new PaymentTransfer(
            reference: 'e0f4763d-28ea-42d4-ac1c-c4013c242105',
            date: '2025-02-25 06:33:00+03',
            amount: '177.39',
            currency: 'SAR',
            senderAccountNumber: 'SA6980000204608016212908',
            receiverBankCode: 'FDCSSARI',
            receiverAccountNumber: 'SA6980000204608016211111',
            receiverBeneficiaryName: 'Jane Doe',
        ));

        $this->assertStringContainsString('<Reference>e0f4763d-28ea-42d4-ac1c-c4013c242105</Reference>', $xml);
        $this->assertStringNotContainsString('<Notes>', $xml);
        $this->assertStringNotContainsString('<PaymentType>', $xml);
        $this->assertStringNotContainsString('<ChargeDetails>', $xml);
    }

    public function test_includes_optional_sections_when_rules_allow(): void
    {
        $xml = $this->builder->build(new PaymentTransfer(
            reference: 'r1',
            date: '2025-02-25 06:33:00+03',
            amount: '10.00',
            currency: 'SAR',
            senderAccountNumber: 'SA1',
            receiverBankCode: 'FDC',
            receiverAccountNumber: 'SA2',
            receiverBeneficiaryName: 'Bob',
            notes: ['Lorem Epsum', 'Dolor Sit Amet'],
            paymentType: '421',
            chargeDetails: 'RB',
        ));

        $this->assertStringContainsString('<Notes>', $xml);
        $this->assertStringContainsString('<Note>Lorem Epsum</Note>', $xml);
        $this->assertStringContainsString('<PaymentType>421</PaymentType>', $xml);
        $this->assertStringContainsString('<ChargeDetails>RB</ChargeDetails>', $xml);
    }

    public function test_charge_details_sha_is_case_insensitive(): void
    {
        $xml = $this->builder->build(new PaymentTransfer(
            reference: 'r1',
            date: '2025-02-25 06:33:00+03',
            amount: '10.00',
            currency: 'SAR',
            senderAccountNumber: 'SA1',
            receiverBankCode: 'FDC',
            receiverAccountNumber: 'SA2',
            receiverBeneficiaryName: 'Bob',
            paymentType: '99',
            chargeDetails: 'sha',
        ));

        $this->assertStringNotContainsString('<ChargeDetails>', $xml);
    }
}
