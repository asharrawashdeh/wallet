<?php

namespace App\Models;

use App\Wallet\Enums\IngestionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookReceipt extends Model
{
    protected $fillable = [
        'client_id',
        'bank',
        'raw_body',
        'line_count',
        'ingestion_status',
        'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'ingestion_status' => IngestionStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('ingestion_status', IngestionStatus::Failed);
    }
}
