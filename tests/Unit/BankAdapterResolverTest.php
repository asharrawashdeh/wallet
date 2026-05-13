<?php

namespace Tests\Unit;

use App\Wallet\Banking\AcmeBankWebhookAdapter;
use App\Wallet\Banking\BankAdapterResolver;
use App\Wallet\Banking\FoodicsBankWebhookAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BankAdapterResolverTest extends TestCase
{
    public function test_resolves_known_banks_case_insensitively(): void
    {
        $resolver = new BankAdapterResolver([
            'foodics' => new FoodicsBankWebhookAdapter,
            'acme' => new AcmeBankWebhookAdapter,
        ]);

        $this->assertInstanceOf(FoodicsBankWebhookAdapter::class, $resolver->resolve('FOODICS'));
        $this->assertInstanceOf(AcmeBankWebhookAdapter::class, $resolver->resolve('acme'));
    }

    public function test_throws_for_unknown_bank(): void
    {
        $resolver = new BankAdapterResolver([
            'foodics' => new FoodicsBankWebhookAdapter,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve('unknown');
    }
}
