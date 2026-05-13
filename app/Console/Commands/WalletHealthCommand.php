<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\WebhookReceipt;
use App\Wallet\Enums\IngestionStatus;
use App\Wallet\WalletIngestionGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WalletHealthCommand extends Command
{
    protected $signature = 'wallet:health';

    protected $description = 'Show a quick health overview of the wallet ingestion pipeline.';

    public function handle(): int
    {
        $this->info('Ingestion gate: '.(WalletIngestionGate::enabled() ? 'ENABLED' : 'DISABLED'));
        $this->newLine();

        $this->renderReceiptStatus();
        $this->renderBatchSummary();
        $this->renderTransactionSummary();

        return self::SUCCESS;
    }

    private function renderReceiptStatus(): void
    {
        $rows = WebhookReceipt::query()
            ->select('ingestion_status', DB::raw('COUNT(*) as count'), DB::raw('SUM(line_count) as total_lines'))
            ->groupBy('ingestion_status')
            ->orderBy('ingestion_status')
            ->get()
            ->map(fn ($r) => [$r->ingestion_status->value ?? $r->ingestion_status, $r->count, $r->total_lines])
            ->toArray();

        if ($rows === []) {
            $this->warn('No webhook receipts found.');

            return;
        }

        $this->table(['Receipt Status', 'Count', 'Total Lines'], $rows);
    }

    private function renderBatchSummary(): void
    {
        $stuckDispatched = WebhookReceipt::query()
            ->where('ingestion_status', IngestionStatus::Dispatched)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->count();

        $failedReceipts = WebhookReceipt::query()
            ->where('ingestion_status', IngestionStatus::Failed)
            ->count();

        if ($stuckDispatched > 0) {
            $this->warn("  {$stuckDispatched} receipt(s) stuck in 'dispatched' for over 10 minutes.");
        }

        if ($failedReceipts > 0) {
            $this->warn("  {$failedReceipts} receipt(s) in 'failed' status.");
        }

        if ($stuckDispatched === 0 && $failedReceipts === 0) {
            $this->info('  No stuck or failed receipts.');
        }

        $this->newLine();
    }

    private function renderTransactionSummary(): void
    {
        $total = Transaction::query()->count();
        $lastHour = Transaction::query()->where('created_at', '>=', now()->subHour())->count();
        $lastDay = Transaction::query()->where('created_at', '>=', now()->subDay())->count();

        $this->table(
            ['Transactions', 'Count'],
            [
                ['Total', $total],
                ['Last hour', $lastHour],
                ['Last 24h', $lastDay],
            ]
        );
    }
}
