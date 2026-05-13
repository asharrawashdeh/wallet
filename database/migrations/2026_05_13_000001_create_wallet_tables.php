<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('webhook_token', 64)->unique();
            $table->boolean('is_test')->default(false);
            $table->timestamps();
        });

        Schema::create('webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('bank', 32);
            $table->longText('raw_body');
            $table->unsignedInteger('line_count')->default(0);
            $table->string('ingestion_status', 32)->default('pending_dispatch');
            $table->string('batch_id')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('SAR');
            $table->timestamp('occurred_at');
            $table->string('bank', 32);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'bank', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('webhook_receipts');
        Schema::dropIfExists('clients');
    }
};
