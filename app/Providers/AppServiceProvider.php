<?php

namespace App\Providers;

use App\Wallet\Banking\AcmeBankWebhookAdapter;
use App\Wallet\Banking\BankAdapterResolver;
use App\Wallet\Banking\FoodicsBankWebhookAdapter;
use App\Wallet\Contracts\PaymentRequestXmlBuilder;
use App\Wallet\Payment\DomPaymentRequestXmlBuilder;
use App\Wallet\Payment\Elements\ChargeDetailsElement;
use App\Wallet\Payment\Elements\NotesElement;
use App\Wallet\Payment\Elements\PaymentTypeElement;
use App\Wallet\Payment\Elements\ReceiverInfoElement;
use App\Wallet\Payment\Elements\SenderInfoElement;
use App\Wallet\Payment\Elements\TransferInfoElement;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BankAdapterResolver::class, function ($app) {
            return new BankAdapterResolver([
                'foodics' => $app->make(FoodicsBankWebhookAdapter::class),
                'acme' => $app->make(AcmeBankWebhookAdapter::class),
            ]);
        });

        $this->app->singleton(PaymentRequestXmlBuilder::class, function () {
            return new DomPaymentRequestXmlBuilder([
                new TransferInfoElement,
                new SenderInfoElement,
                new ReceiverInfoElement,
                new NotesElement,
                new PaymentTypeElement,
                new ChargeDetailsElement,
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
