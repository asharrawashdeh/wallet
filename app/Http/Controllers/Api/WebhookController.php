<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\WebhookReceipt;
use App\Wallet\Banking\BankAdapterResolver;
use App\Wallet\WalletIngestionGate;
use App\Wallet\WebhookLineDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WebhookController extends Controller
{
    public function __construct(
        private BankAdapterResolver $adapterResolver,
        private WebhookLineDispatcher $lineDispatcher,
    ) {}

    public function store(Request $request, string $bank, string $token): Response
    {
        try {
            $this->adapterResolver->resolve($bank);
        } catch (InvalidArgumentException) {
            Log::warning('Webhook rejected: unsupported bank.', ['bank' => $bank]);

            return response('', 422);
        }

        $client = Client::query()->where('webhook_token', $token)->first();

        if ($client === null) {
            Log::warning('Webhook rejected: invalid token.', ['bank' => $bank]);

            return response('', 401);
        }

        $rawBody = (string) $request->getContent();
        $allLines = $rawBody === '' ? [] : preg_split('/\r\n|\r|\n/', $rawBody, -1);

        $nonEmptyLineCount = 0;
        foreach ($allLines as $line) {
            if (trim((string) $line) !== '') {
                $nonEmptyLineCount++;
            }
        }

        $receipt = WebhookReceipt::query()->create([
            'client_id' => $client->id,
            'bank' => strtolower($bank),
            'raw_body' => $rawBody,
            'line_count' => $nonEmptyLineCount,
            'ingestion_status' => 'pending_dispatch',
        ]);

        Log::info('Webhook received.', [
            'receipt_id' => $receipt->id,
            'bank' => $receipt->bank,
            'client_id' => $client->id,
            'line_count' => $nonEmptyLineCount,
            'ingestion_enabled' => WalletIngestionGate::enabled(),
        ]);

        if (WalletIngestionGate::enabled()) {
            $this->lineDispatcher->dispatchForReceipt($receipt, $allLines);
        }

        return response('', 202);
    }
}
