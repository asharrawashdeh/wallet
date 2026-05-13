<?php

namespace Tests\Unit;

use App\Wallet\Banking\AcmeBankWebhookAdapter;
use PHPUnit\Framework\TestCase;

class AcmeBankWebhookAdapterTest extends TestCase
{
    private AcmeBankWebhookAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new AcmeBankWebhookAdapter;
    }

    public function test_parses_sample_line(): void
    {
        $line = '156,50//202506159000001//20250615';
        $dto = $this->adapter->parseLine($line);

        $this->assertNotNull($dto);
        $this->assertSame('202506159000001', $dto->reference);
        $this->assertSame('156.50', $dto->amount);
        $this->assertSame('20250615', $dto->occurredAtYmd);
        $this->assertSame([], $dto->metadata);
    }

    public function test_returns_null_for_wrong_segment_count(): void
    {
        $this->assertNull($this->adapter->parseLine('156,50//ref'));
    }
}
