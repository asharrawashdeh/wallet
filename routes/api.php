<?php

use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\LimitRequestBodySize;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/{bank}/{token}', [WebhookController::class, 'store'])
    ->middleware([LimitRequestBodySize::class, VerifyWebhookSignature::class, 'throttle:webhooks'])
    ->where('bank', '[a-z]+');
