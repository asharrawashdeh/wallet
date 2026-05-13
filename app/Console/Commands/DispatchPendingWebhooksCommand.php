<?php

namespace App\Console\Commands;

use App\Models\WebhookReceipt;
use App\Wallet\Enums\IngestionStatus;
use App\Wallet\WalletIngestionGate;
use App\Wallet\WebhookLineDispatcher;
use Illuminate\Console\Command;

class DispatchPendingWebhooksCommand extends Command
{
    protected $signature = 'wallet:dispatch-pending-webhooks';

    protected $description = 'Dispatch queued import jobs for webhook receipts that were stored while ingestion was paused.';

    public function handle(WebhookLineDispatcher $dispatcher): int
    {
        if (! WalletIngestionGate::enabled()) {
            $this->warn('Ingestion is disabled (config or cache). Enable it before dispatching pending webhooks.');

            return self::FAILURE;
        }

        $receipts = WebhookReceipt::query()
            ->where('ingestion_status', IngestionStatus::PendingDispatch)
            ->orderBy('id')
            ->get();

        foreach ($receipts as $receipt) {
            $rawBody = $receipt->raw_body;
            $allLines = $rawBody === '' ? [] : preg_split('/\r\n|\r|\n/', $rawBody, -1);
            $dispatcher->dispatchForReceipt($receipt, $allLines);
            $this->line("Dispatched lines for webhook receipt {$receipt->id}.");
        }

        if ($receipts->isEmpty()) {
            $this->info('No pending webhook receipts.');
        }

        return self::SUCCESS;
    }
}
