<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Wallet\Banking\BankAdapterResolver;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use App\Wallet\WalletLogger;
use RuntimeException;

class ImportTransactionLineJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * Exponential backoff: 2^attempt * 5 seconds (10s, 20s, 40s, …).
     */
    public function backoff(): int
    {
        return (int) (2 ** $this->attempts() * 5);
    }

    public function __construct(
        public int $webhookReceiptId,
        public int $clientId,
        public string $bank,
        public int $lineIndex,
        public string $rawLine,
    ) {}

    public function handle(BankAdapterResolver $resolver): void
    {
        $context = [
            'receipt_id' => $this->webhookReceiptId,
            'client_id' => $this->clientId,
            'bank' => $this->bank,
            'line_index' => $this->lineIndex,
        ];

        $adapter = $resolver->resolve($this->bank);
        $dto = $adapter->parseLine($this->rawLine);

        if ($dto === null) {
            WalletLogger::error('Unparseable transaction line.', $context + ['raw_line' => $this->rawLine]);

            throw new RuntimeException("Unparseable transaction line at index {$this->lineIndex} for receipt {$this->webhookReceiptId}.");
        }

        $context['reference'] = $dto->reference;
        $occurredAt = Carbon::createFromFormat('Ymd', $dto->occurredAtYmd)->startOfDay();

        try {
            Transaction::query()->create([
                'client_id' => $this->clientId,
                'reference' => $dto->reference,
                'amount' => $dto->amount,
                'currency' => 'SAR',
                'occurred_at' => $occurredAt,
                'bank' => strtolower($this->bank),
                'metadata' => $dto->metadata === [] ? null : $dto->metadata,
            ]);

            WalletLogger::info('Transaction created.', $context + ['amount' => $dto->amount]);
        } catch (UniqueConstraintViolationException) {
            WalletLogger::info('Duplicate transaction skipped.', $context);
        }
    }
}
