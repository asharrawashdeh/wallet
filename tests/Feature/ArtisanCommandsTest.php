<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\WebhookReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtisanCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulate_webhook_foodics(): void
    {
        $client = Client::query()->create(['name' => 'Test', 'is_test' => true]);

        $this->artisan('wallet:simulate-webhook', ['bank' => 'foodics', 'client' => $client->id, '--lines' => 3])
            ->assertSuccessful()
            ->expectsOutputToContain('3 line(s)');

        $this->assertDatabaseCount('transactions', 3);
        $this->assertDatabaseHas('webhook_receipts', [
            'client_id' => $client->id,
            'ingestion_status' => 'completed',
        ]);
    }

    public function test_simulate_webhook_acme(): void
    {
        $client = Client::query()->create(['name' => 'Test', 'is_test' => true]);

        $this->artisan('wallet:simulate-webhook', ['bank' => 'acme', 'client' => $client->id])
            ->assertSuccessful();

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_simulate_webhook_succeeds_with_hmac_secret_configured(): void
    {
        config(['wallet.bank_secrets.foodics' => 'sim-test-secret']);
        $client = Client::query()->create(['name' => 'Test', 'is_test' => true]);

        $this->artisan('wallet:simulate-webhook', ['bank' => 'foodics', 'client' => $client->id])
            ->assertSuccessful();

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_simulate_webhook_rejected_for_non_test_client(): void
    {
        $client = Client::query()->create(['name' => 'Production client']);

        $this->artisan('wallet:simulate-webhook', ['bank' => 'foodics', 'client' => $client->id])
            ->assertFailed()
            ->expectsOutputToContain('not a test client');
    }

    public function test_simulate_webhook_unknown_bank_fails(): void
    {
        $client = Client::query()->create(['name' => 'Test', 'is_test' => true]);

        $this->artisan('wallet:simulate-webhook', ['bank' => 'unknown', 'client' => $client->id])
            ->assertFailed();
    }

    public function test_simulate_webhook_missing_client_fails(): void
    {
        $this->artisan('wallet:simulate-webhook', ['bank' => 'foodics', 'client' => 9999])
            ->assertFailed();
    }

    public function test_preview_payment_xml_default(): void
    {
        $this->artisan('wallet:preview-payment-xml')
            ->assertSuccessful()
            ->expectsOutputToContain('<PaymentRequestMessage>');
    }

    public function test_preview_payment_xml_with_notes_and_non_default_options(): void
    {
        $this->artisan('wallet:preview-payment-xml', [
            '--notes' => ['Hello', 'World'],
            '--payment-type' => '421',
            '--charge-details' => 'RB',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('<Notes>');
    }

    public function test_retry_failed_re_dispatches_missing_transactions(): void
    {
        $client = Client::query()->create(['name' => 'Retry client']);
        $body = "20250615100,00#RETRY01#\n20250615200,00#RETRY02#\n20250615300,00#RETRY03#";

        $receipt = WebhookReceipt::query()->create([
            'client_id' => $client->id,
            'bank' => 'foodics',
            'raw_body' => $body,
            'line_count' => 3,
            'ingestion_status' => 'failed',
        ]);

        // Simulate that 1 of 3 transactions already succeeded before the batch failed
        \App\Models\Transaction::query()->create([
            'client_id' => $client->id,
            'reference' => 'RETRY01',
            'amount' => '100.00',
            'currency' => 'SAR',
            'occurred_at' => now(),
            'bank' => 'foodics',
        ]);

        $this->artisan('wallet:retry-failed', ['receipt' => $receipt->id])
            ->assertSuccessful()
            ->expectsOutputToContain('batch dispatched');

        // Idempotency ensures RETRY01 stays as 1 row; RETRY02 and RETRY03 are new
        $this->assertDatabaseCount('transactions', 3);
        $this->assertDatabaseHas('webhook_receipts', [
            'id' => $receipt->id,
            'ingestion_status' => 'completed',
        ]);
    }

    public function test_retry_failed_with_no_failed_receipts(): void
    {
        $this->artisan('wallet:retry-failed')
            ->assertSuccessful()
            ->expectsOutputToContain('No failed receipts');
    }

    public function test_health_command_runs_with_empty_db(): void
    {
        $this->artisan('wallet:health')
            ->assertSuccessful()
            ->expectsOutputToContain('Ingestion gate:');
    }

    public function test_health_command_reports_receipt_and_transaction_counts(): void
    {
        $client = Client::query()->create(['name' => 'Test']);
        WebhookReceipt::query()->create([
            'client_id' => $client->id,
            'bank' => 'foodics',
            'raw_body' => 'x',
            'line_count' => 1,
            'ingestion_status' => 'completed',
        ]);

        $this->artisan('wallet:health')
            ->assertSuccessful()
            ->expectsOutputToContain('completed');
    }
}
