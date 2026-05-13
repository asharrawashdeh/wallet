<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Client extends Model
{
    protected $fillable = [
        'name',
        'webhook_token',
        'is_test',
    ];

    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if (empty($client->webhook_token)) {
                $client->webhook_token = Str::random(48);
            }
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function webhookReceipts(): HasMany
    {
        return $this->hasMany(WebhookReceipt::class);
    }
}
