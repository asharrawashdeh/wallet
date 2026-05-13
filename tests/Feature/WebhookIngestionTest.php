<?php

namespace Tests\Feature;

use App\Jobs\ImportTransactionLineJob;
use App\Models\Client;
use App\Wallet\WalletIngestionGate;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WebhookIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
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

    public function test_webhook_imports_transactions_and_receipt_completes(): void
    {
        $client = Client::query()->create(['name' => 'Wallet client']);
        $body = "20250615156,50#202506159000001#note/x\n202506151,00#202506159000002#";

        $response = $this->postWebhook('foodics', $client->webhook_token, $body);

        $response->assertStatus(202);
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', ['reference' => '202506159000001', 'client_id' => $client->id]);
        $this->assertDatabaseHas('webhook_receipts', ['ingestion_status' => 'completed', 'client_id' => $client->id]);
    }

    public function test_receipt_stores_batch_id(): void
    {
        $client = Client::query()->create(['name' => 'Wallet client']);
        $body = '20250615156,50#BATCHID01#';

        $this->postWebhook('foodics', $client->webhook_token, $body)->assertStatus(202);

        $receipt = $client->webhookReceipts()->first();
        $this->assertNotNull($receipt->batch_id);
        $this->assertDatabaseHas('job_batches', ['id' => $receipt->batch_id]);
    }

    public function test_duplicate_reference_across_webhooks_creates_single_row(): void
    {
        $client = Client::query()->create(['name' => 'Wallet client']);
        $body = '20250615156,50#DUPREF001#';

        $this->postWebhook('foodics', $client->webhook_token, $body)->assertStatus(202);
        $this->postWebhook('foodics', $client->webhook_token, $body)->assertStatus(202);

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', ['reference' => 'DUPREF001']);
    }

    public function test_unknown_bank_returns_422(): void
    {
        $client = Client::query()->create(['name' => 'Wallet client']);

        $this->postWebhook('other', $client->webhook_token, 'x')->assertStatus(422);
    }

    public function test_invalid_token_returns_401(): void
    {
        $this->postWebhook('foodics', 'bogus-token-that-does-not-exist', '20250615156,50#R1#')
            ->assertStatus(401);
    }

    public function test_when_ingestion_disabled_receipt_stays_pending_and_no_batch_dispatched(): void
    {
        Bus::fake();
        Cache::put(WalletIngestionGate::CACHE_KEY, false);

        $client = Client::query()->create(['name' => 'Wallet client']);
        $body = '20250615156,50#REFPEND001#';

        $this->postWebhook('foodics', $client->webhook_token, $body)->assertStatus(202);

        Bus::assertNothingBatched();
        $this->assertDatabaseHas('webhook_receipts', [
            'ingestion_status' => 'pending_dispatch',
            'client_id' => $client->id,
        ]);
    }

    public function test_dispatch_pending_command_processes_stored_webhooks(): void
    {
        Cache::put(WalletIngestionGate::CACHE_KEY, false);
        $client = Client::query()->create(['name' => 'Wallet client']);
        $body = '20250615156,50#REFCMD001#';

        $this->postWebhook('foodics', $client->webhook_token, $body)->assertStatus(202);
        $this->assertDatabaseCount('transactions', 0);

        Cache::put(WalletIngestionGate::CACHE_KEY, true);
        Artisan::call('wallet:dispatch-pending-webhooks');

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('webhook_receipts', ['ingestion_status' => 'completed']);
    }

    public function test_batch_dispatched_with_correct_job_count(): void
    {
        Bus::fake();
        $client = Client::query()->create(['name' => 'Wallet client']);
        $body = "20250615156,50#A#\n\n202506151,00#B#\n";

        $this->postWebhook('foodics', $client->webhook_token, $body)->assertStatus(202);

        Bus::assertBatched(function (PendingBatch $batch) {
            return $batch->jobs->count() === 2
                && $batch->jobs->every(fn ($job) => $job instanceof ImportTransactionLineJob);
        });
    }

    public function test_acme_line_on_acme_route(): void
    {
        $client = Client::query()->create(['name' => 'Wallet client']);
        $body = '156,50//202506159000099//20250615';

        $this->postWebhook('acme', $client->webhook_token, $body)->assertStatus(202);

        $this->assertDatabaseHas('transactions', [
            'reference' => '202506159000099',
            'bank' => 'acme',
        ]);
    }
}
