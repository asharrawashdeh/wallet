<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function postWebhook(string $bank, string $token, string $body, array $headers = [])
    {
        $server = ['CONTENT_TYPE' => 'text/plain'];
        foreach ($headers as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }

        return $this->call(
            'POST',
            "/api/webhooks/{$bank}/{$token}",
            server: $server,
            content: $body
        );
    }

    public function test_invalid_token_returns_401(): void
    {
        $this->postWebhook('foodics', 'nonexistent-token', '20250615156,50#R1#')
            ->assertStatus(401);
    }

    public function test_valid_token_is_accepted(): void
    {
        $client = Client::query()->create(['name' => 'Secure client']);

        $this->postWebhook('foodics', $client->webhook_token, '20250615156,50#SEC001#')
            ->assertStatus(202);
    }

    public function test_hmac_signature_required_when_secret_configured(): void
    {
        config(['wallet.bank_secrets.foodics' => 'test-secret-foodics']);
        $client = Client::query()->create(['name' => 'HMAC client']);

        $this->postWebhook('foodics', $client->webhook_token, '20250615156,50#HMAC01#')
            ->assertStatus(401);
    }

    public function test_invalid_hmac_signature_rejected(): void
    {
        config(['wallet.bank_secrets.foodics' => 'test-secret-foodics']);
        $client = Client::query()->create(['name' => 'HMAC client']);

        $this->postWebhook('foodics', $client->webhook_token, '20250615156,50#HMAC02#', [
            'X-Webhook-Signature' => 'deadbeef',
        ])->assertStatus(401);
    }

    public function test_valid_hmac_signature_accepted(): void
    {
        $secret = 'test-secret-foodics';
        config(['wallet.bank_secrets.foodics' => $secret]);
        $client = Client::query()->create(['name' => 'HMAC client']);

        $body = '20250615156,50#HMAC03#';
        $signature = hash_hmac('sha256', $body, $secret);

        $this->postWebhook('foodics', $client->webhook_token, $body, [
            'X-Webhook-Signature' => $signature,
        ])->assertStatus(202);

        $this->assertDatabaseHas('transactions', ['reference' => 'HMAC03']);
    }

    public function test_hmac_not_required_when_no_secret_configured(): void
    {
        config(['wallet.bank_secrets.foodics' => null]);
        $client = Client::query()->create(['name' => 'No HMAC client']);

        $this->postWebhook('foodics', $client->webhook_token, '20250615156,50#NOHMAC01#')
            ->assertStatus(202);
    }

    public function test_each_bank_uses_its_own_secret(): void
    {
        $foodicsSecret = 'secret-foodics';
        $acmeSecret = 'secret-acme';
        config([
            'wallet.bank_secrets.foodics' => $foodicsSecret,
            'wallet.bank_secrets.acme' => $acmeSecret,
        ]);
        $client = Client::query()->create(['name' => 'Multi-bank HMAC']);

        $foodicsBody = '20250615100,00#BANKISO01#';
        $acmeBody = '100,00//BANKISO01//20250615';

        // Foodics secret on Acme endpoint should fail
        $this->postWebhook('acme', $client->webhook_token, $acmeBody, [
            'X-Webhook-Signature' => hash_hmac('sha256', $acmeBody, $foodicsSecret),
        ])->assertStatus(401);

        // Correct secret per bank should succeed
        $this->postWebhook('foodics', $client->webhook_token, $foodicsBody, [
            'X-Webhook-Signature' => hash_hmac('sha256', $foodicsBody, $foodicsSecret),
        ])->assertStatus(202);

        $this->postWebhook('acme', $client->webhook_token, $acmeBody, [
            'X-Webhook-Signature' => hash_hmac('sha256', $acmeBody, $acmeSecret),
        ])->assertStatus(202);
    }
}
