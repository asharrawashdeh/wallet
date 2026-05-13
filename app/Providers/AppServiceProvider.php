<?php

namespace App\Providers;

use App\Wallet\Banking\AcmeBankWebhookAdapter;
use App\Wallet\Banking\BankAdapterResolver;
use App\Wallet\Banking\FoodicsBankWebhookAdapter;
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

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
