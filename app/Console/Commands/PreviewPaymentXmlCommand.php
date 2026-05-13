<?php

namespace App\Console\Commands;

use App\Wallet\Contracts\PaymentRequestXmlBuilder;
use App\Wallet\DTOs\PaymentTransfer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PreviewPaymentXmlCommand extends Command
{
    protected $signature = 'wallet:preview-payment-xml
        {--notes=* : Note texts to include (repeatable)}
        {--payment-type=99 : PaymentType value (omitted from XML when 99)}
        {--charge-details=SHA : ChargeDetails value (omitted from XML when SHA)}';

    protected $description = 'Render a sample PaymentRequestMessage XML using the live builder so you can verify output in production.';

    public function handle(PaymentRequestXmlBuilder $builder): int
    {
        $transfer = new PaymentTransfer(
            reference: (string) Str::uuid(),
            date: now()->format('Y-m-d H:i:sP'),
            amount: '100.00',
            currency: 'SAR',
            senderAccountNumber: 'SA6980000204608016212908',
            receiverBankCode: 'FDCSSARI',
            receiverAccountNumber: 'SA6980000204608016211111',
            receiverBeneficiaryName: 'Jane Doe',
            notes: array_filter($this->option('notes')),
            paymentType: $this->option('payment-type'),
            chargeDetails: $this->option('charge-details'),
        );

        $xml = $builder->build($transfer);

        $this->line($xml);

        return self::SUCCESS;
    }
}
