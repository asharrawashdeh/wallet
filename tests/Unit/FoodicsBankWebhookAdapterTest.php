<?php

namespace Tests\Unit;

use App\Wallet\Banking\FoodicsBankWebhookAdapter;
use PHPUnit\Framework\TestCase;

class FoodicsBankWebhookAdapterTest extends TestCase
{
    private FoodicsBankWebhookAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new FoodicsBankWebhookAdapter;
    }

    public function test_parses_sample_line(): void
    {
        $line = '20250615156,50#202506159000001#note/debt payment march/internal_reference/A462JE81';
        $dto = $this->adapter->parseLine($line);

        $this->assertNotNull($dto);
        $this->assertSame('202506159000001', $dto->reference);
        $this->assertSame('156.50', $dto->amount);
        $this->assertSame('20250615', $dto->occurredAtYmd);
        $this->assertSame([
            'note' => 'debt payment march',
            'internal_reference' => 'A462JE81',
        ], $dto->metadata);
        $this->assertSame($line, $dto->rawLine);
    }

    public function test_returns_null_for_invalid_line(): void
    {
        $this->assertNull($this->adapter->parseLine('not-a-line'));
        $this->assertNull($this->adapter->parseLine(''));
        $this->assertNull($this->adapter->parseLine('   '));
    }
}
