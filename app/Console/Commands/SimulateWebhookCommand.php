<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Wallet\Banking\BankAdapterResolver;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SimulateWebhookCommand extends Command
{
    protected $signature = 'wallet:simulate-webhook
        {bank : Bank identifier (e.g. foodics, acme)}
        {client : Client ID}
        {--lines=1 : Number of sample transaction lines to generate}';

    protected $description = 'Push a synthetic webhook through the full ingestion pipeline by calling the real webhook endpoint.';

    private const FOODICS_TEMPLATE = '20250615%s#SIM%s#simulation/true';

    private const ACME_TEMPLATE = '%s//SIM%s//20250615';

    public function handle(BankAdapterResolver $resolver): int
    {
        $bank = strtolower($this->argument('bank'));
        $clientId = (int) $this->argument('client');
        $lineCount = max(1, (int) $this->option('lines'));

        try {
            $resolver->resolve($bank);
        } catch (InvalidArgumentException) {
            $this->error("Unknown bank: {$bank}");

            return self::FAILURE;
        }

        $client = Client::query()->find($clientId);
        if ($client === null) {
            $this->error("Client {$clientId} not found.");

            return self::FAILURE;
        }

        if (! $client->is_test) {
            $this->error("Client {$clientId} is not a test client. Set is_test=true to allow simulation.");

            return self::FAILURE;
        }

        $lines = [];
        for ($i = 0; $i < $lineCount; $i++) {
            $ref = str_pad((string) ($i + 1), 10, '0', STR_PAD_LEFT);
            $amount = number_format(mt_rand(100, 99999) / 100, 2, ',', '');

            $lines[] = match ($bank) {
                'foodics' => sprintf(self::FOODICS_TEMPLATE, $amount, $ref),
                'acme' => sprintf(self::ACME_TEMPLATE, $amount, $ref),
                default => '',
            };
        }

        $body = implode("\n", $lines);
        $uri = "/api/webhooks/{$bank}/{$client->webhook_token}";

        $this->info("Posting {$lineCount} line(s) to {$uri}");

        $request = Request::create($uri, 'POST', content: $body);
        $request->headers->set('Content-Type', 'text/plain');

        $secret = config("wallet.bank_secrets.{$bank}");
        if ($secret !== null) {
            $request->headers->set('X-Webhook-Signature', hash_hmac('sha256', $body, $secret));
        }
        $response = app()->handle($request);

        $status = $response->getStatusCode();
        $this->info("Response status: {$status}");

        if ($status >= 200 && $status < 300) {
            $this->info('Webhook accepted. Check logs and wallet:health for results.');
        } else {
            $this->error("Webhook rejected with status {$status}.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
