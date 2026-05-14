<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\WebhookReceipt;
use App\Wallet\Banking\BankAdapterResolver;
use App\Wallet\Enums\IngestionStatus;
use App\Wallet\WalletIngestionGate;
use App\Wallet\WalletLogger;
use App\Wallet\WebhookLineDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            WalletLogger::warning('Webhook rejected: unsupported bank.', ['bank' => $bank]);

            return response('', 422);
        }

        if (($client = Client::byToken($token)->first()) === null) {
            WalletLogger::warning('Webhook rejected: invalid token.', ['bank' => $bank]);

            return response('', 401);
        }

        $allLines = $this->parseLines($request);
        $receipt = $this->storeReceipt($client, $bank, $request, $allLines);

        if (WalletIngestionGate::enabled()) {
            $this->lineDispatcher->dispatchForReceipt($receipt, $allLines);
        }

        return response('', 202);
    }

    /** @return list<string> */
    private function parseLines(Request $request): array
    {
        $rawBody = (string) $request->getContent();

        return $rawBody === '' ? [] : preg_split('/\r\n|\r|\n/', $rawBody, -1);
    }

    private function storeReceipt(Client $client, string $bank, Request $request, array $allLines): WebhookReceipt
    {
        $nonEmptyLineCount = collect($allLines)->filter(fn ($l) => trim((string) $l) !== '')->count();

        $receipt = WebhookReceipt::query()->create([
            'client_id' => $client->id,
            'bank' => strtolower($bank),
            'raw_body' => (string) $request->getContent(),
            'line_count' => $nonEmptyLineCount,
            'ingestion_status' => IngestionStatus::PendingDispatch,
        ]);

        WalletLogger::info('Webhook received.', [
            'receipt_id' => $receipt->id,
            'bank' => $receipt->bank,
            'client_id' => $client->id,
            'line_count' => $nonEmptyLineCount,
            'ingestion_enabled' => WalletIngestionGate::enabled(),
        ]);

        return $receipt;
    }
}
