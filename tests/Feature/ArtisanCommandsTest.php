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
        $client = Client::query()->create(['name' => 'Test']);

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
        $client = Client::query()->create(['name' => 'Test']);

        $this->artisan('wallet:simulate-webhook', ['bank' => 'acme', 'client' => $client->id])
            ->assertSuccessful();

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_simulate_webhook_succeeds_with_hmac_secret_configured(): void
    {
        config(['wallet.bank_secrets.foodics' => 'sim-test-secret']);
        $client = Client::query()->create(['name' => 'Test']);

        $this->artisan('wallet:simulate-webhook', ['bank' => 'foodics', 'client' => $client->id])
            ->assertSuccessful();

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_simulate_webhook_unknown_bank_fails(): void
    {
        $client = Client::query()->create(['name' => 'Test']);

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
