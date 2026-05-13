<?php

namespace Tests\Feature;

use App\Jobs\ImportTransactionLineJob;
use App\Models\Client;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('slow')]
class WebhookStressTest extends TestCase
{
    use RefreshDatabase;

    private function buildFoodicsBody(int $lineCount): string
    {
        $lines = [];
        for ($i = 0; $i < $lineCount; $i++) {
            $amount = number_format(mt_rand(100, 99999) / 100, 2, ',', '');
            $lines[] = sprintf('20250615%s#STRESS%010d#note/load test', $amount, $i);
        }

        return implode("\n", $lines);
    }

    private function buildAcmeBody(int $lineCount): string
    {
        $lines = [];
        for ($i = 0; $i < $lineCount; $i++) {
            $amount = number_format(mt_rand(100, 99999) / 100, 2, ',', '');
            $lines[] = sprintf('%s//STRESSACME%010d//20250615', $amount, $i);
        }

        return implode("\n", $lines);
    }

    private function postWebhook(string $bank, string $token, string $body)
    {
        return $this->call(
            'POST',
            "/api/webhooks/{$bank}/{$token}",
            server: ['CONTENT_TYPE' => 'text/plain'],
            content: $body
        );
    }

    public function test_foodics_webhook_with_1000_lines_end_to_end(): void
    {
        $client = Client::query()->create(['name' => 'Stress client']);
        $body = $this->buildFoodicsBody(1000);

        $start = microtime(true);
        $this->postWebhook('foodics', $client->webhook_token, $body)->assertStatus(202);
        $elapsed = microtime(true) - $start;

        $this->assertDatabaseCount('transactions', 1000);
        $this->assertDatabaseHas('webhook_receipts', [
            'ingestion_status' => 'completed',
            'line_count' => 1000,
        ]);

        $this->assertLessThan(10.0, $elapsed, "Full 1000-line webhook round-trip took {$elapsed}s.");
    }

    public function test_acme_webhook_with_1000_lines_end_to_end(): void
    {
        $client = Client::query()->create(['name' => 'Stress client']);
        $body = $this->buildAcmeBody(1000);

        $start = microtime(true);
        $this->postWebhook('acme', $client->webhook_token, $body)->assertStatus(202);
        $elapsed = microtime(true) - $start;

        $this->assertDatabaseCount('transactions', 1000);
        $this->assertLessThan(10.0, $elapsed, "Full 1000-line Acme webhook round-trip took {$elapsed}s.");
    }

    public function test_1000_line_webhook_creates_single_batch(): void
    {
        Bus::fake();
        $client = Client::query()->create(['name' => 'Stress client']);
        $body = $this->buildFoodicsBody(1000);

        $this->postWebhook('foodics', $client->webhook_token, $body)->assertStatus(202);

        Bus::assertBatchCount(1);
        Bus::assertBatched(function (PendingBatch $batch) {
            return $batch->jobs->count() === 1000
                && $batch->jobs->every(fn ($j) => $j instanceof ImportTransactionLineJob);
        });
    }

    public function test_duplicate_references_across_two_1000_line_webhooks(): void
    {
        $client = Client::query()->create(['name' => 'Stress client']);
        $body = $this->buildFoodicsBody(1000);

        $this->postWebhook('foodics', $client->webhook_token, $body)->assertStatus(202);
        $this->assertDatabaseCount('transactions', 1000);

        $this->postWebhook('foodics', $client->webhook_token, $body)->assertStatus(202);
        $this->assertDatabaseCount('transactions', 1000);
    }

    public function test_same_reference_from_different_banks_creates_separate_transactions(): void
    {
        $client = Client::query()->create(['name' => 'Multi-bank client']);

        $foodicsLines = [];
        $acmeLines = [];
        for ($i = 0; $i < 100; $i++) {
            $ref = sprintf('SHARED%010d', $i);
            $amount = number_format(mt_rand(100, 9999) / 100, 2, ',', '');
            $foodicsLines[] = sprintf('20250615%s#%s#', $amount, $ref);
            $acmeLines[] = sprintf('%s//%s//20250615', $amount, $ref);
        }

        $this->postWebhook('foodics', $client->webhook_token, implode("\n", $foodicsLines))->assertStatus(202);
        $this->postWebhook('acme', $client->webhook_token, implode("\n", $acmeLines))->assertStatus(202);

        // Unique on (client_id, bank, reference) — same reference from different banks are distinct
        $this->assertDatabaseCount('transactions', 200);
    }
}
