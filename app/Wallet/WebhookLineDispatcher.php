<?php

namespace App\Wallet;

use App\Jobs\ImportTransactionLineJob;
use App\Models\WebhookReceipt;
use App\Wallet\Enums\IngestionStatus;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

final class WebhookLineDispatcher
{
    /**
     * @param  list<string>  $lines
     */
    public function dispatchForReceipt(WebhookReceipt $receipt, array $lines): void
    {
        $jobs = [];

        foreach ($lines as $lineIndex => $rawLine) {
            if (trim($rawLine) === '') {
                continue;
            }

            $jobs[] = new ImportTransactionLineJob(
                $receipt->id,
                $receipt->client_id,
                $receipt->bank,
                $lineIndex,
                $rawLine,
            );
        }

        if ($jobs === []) {
            WalletLogger::info('Webhook receipt has no non-empty lines; skipping batch dispatch.', [
                'receipt_id' => $receipt->id,
            ]);

            return;
        }

        $receiptId = $receipt->id;

        // Mark dispatched before launching the batch so the finally callback
        // (which may fire inline with a sync queue) always runs after this state.
        $receipt->update(['ingestion_status' => IngestionStatus::Dispatched]);

        $batch = Bus::batch($jobs)
            ->finally(function (Batch $batch) use ($receiptId) {
                $status = $batch->failedJobs > 0 ? IngestionStatus::Failed : IngestionStatus::Completed;
                WebhookReceipt::query()
                    ->whereKey($receiptId)
                    ->update(['ingestion_status' => $status]);

                WalletLogger::info('Webhook receipt batch finished.', [
                    'receipt_id' => $receiptId,
                    'batch_id' => $batch->id,
                    'ingestion_status' => $status->value,
                    'total_jobs' => $batch->totalJobs,
                    'failed_jobs' => $batch->failedJobs,
                ]);
            })
            ->allowFailures()
            ->dispatch();

        $receipt->update(['batch_id' => $batch->id]);

        WalletLogger::info('Webhook receipt batch dispatched.', [
            'receipt_id' => $receipt->id,
            'batch_id' => $batch->id,
            'job_count' => count($jobs),
        ]);
    }
}
