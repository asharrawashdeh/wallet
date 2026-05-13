<?php

namespace Tests\Unit;

use App\Wallet\Banking\AcmeBankWebhookAdapter;
use App\Wallet\Banking\BankAdapterResolver;
use App\Wallet\Banking\FoodicsBankWebhookAdapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('slow')]
class WebhookAdapterPerformanceTest extends TestCase
{
    public function test_parses_one_thousand_foodics_lines(): void
    {
        $adapter = new FoodicsBankWebhookAdapter;
        $template = '20250615156,50#REF%010d#note/debt payment march/internal_reference/A462JE81';

        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $dto = $adapter->parseLine(sprintf($template, $i));
            $this->assertNotNull($dto);
        }
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(2.0, $elapsed, "Parsing 1000 Foodics lines took {$elapsed}s.");
    }

    public function test_parses_one_thousand_acme_lines(): void
    {
        $adapter = new AcmeBankWebhookAdapter;
        $template = '156,50//REF%010d//20250615';

        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $dto = $adapter->parseLine(sprintf($template, $i));
            $this->assertNotNull($dto);
        }
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(2.0, $elapsed, "Parsing 1000 Acme lines took {$elapsed}s.");
    }

    public function test_resolver_overhead_is_negligible_at_scale(): void
    {
        $resolver = new BankAdapterResolver([
            'foodics' => new FoodicsBankWebhookAdapter,
            'acme' => new AcmeBankWebhookAdapter,
        ]);

        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $resolver->resolve('foodics');
        }
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(0.1, $elapsed, "1000 resolver lookups took {$elapsed}s.");
    }
}
