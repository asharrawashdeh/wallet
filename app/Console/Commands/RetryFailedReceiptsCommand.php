<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\WebhookReceipt;
use App\Wallet\WalletLogger;
use App\Wallet\WebhookLineDispatcher;
use Illuminate\Console\Command;

class RetryFailedReceiptsCommand extends Command
{
    protected $signature = 'wallet:retry-failed
        {receipt? : Specific receipt ID to retry (omit to retry all failed)}';

    protected $description = 'Re-dispatch jobs for failed webhook receipts, skipping lines that already have a transaction.';

    public function handle(WebhookLineDispatcher $dispatcher): int
    {
        $receiptId = $this->argument('receipt');

        $receipts = WebhookReceipt::failed()
            ->when($receiptId, fn ($q) => $q->whereKey((int) $receiptId))
            ->get();

        if ($receipts->isEmpty()) {
            $this->info($receiptId
                ? "Receipt {$receiptId} is not in failed status."
                : 'No failed receipts found.'
            );

            return self::SUCCESS;
        }

        foreach ($receipts as $receipt) {
            $this->retryReceipt($receipt, $dispatcher);
        }

        return self::SUCCESS;
    }

    private function retryReceipt(WebhookReceipt $receipt, WebhookLineDispatcher $dispatcher): void
    {
        $rawBody = $receipt->raw_body;
        $allLines = $rawBody === '' ? [] : preg_split('/\r\n|\r|\n/', $rawBody, -1);

        $totalLines = count($allLines);
        $alreadyImported = Transaction::byClient($receipt->client_id)
            ->byBank($receipt->bank)
            ->count();

        WalletLogger::info('Retrying failed receipt.', [
            'receipt_id' => $receipt->id,
            'total_lines' => $totalLines,
            'already_imported' => $alreadyImported,
        ]);

        $this->line("Receipt {$receipt->id}: {$totalLines} lines, {$alreadyImported} already imported. Re-dispatching.");

        $dispatcher->dispatchForReceipt($receipt, $allLines);

        $this->info("Receipt {$receipt->id}: batch dispatched.");
    }
}
