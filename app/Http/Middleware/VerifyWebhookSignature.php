<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $bank = strtolower($request->route('bank', ''));
        $secret = config("wallet.bank_secrets.{$bank}");

        // No secret configured for this bank — skip verification (dev/testing).
        if ($secret === null) {
            return $next($request);
        }

        $signature = $request->header('X-Webhook-Signature');

        if ($signature === null) {
            Log::warning('Webhook rejected: missing signature header.', ['bank' => $bank]);

            return response('', 401);
        }

        $expected = hash_hmac('sha256', (string) $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('Webhook rejected: invalid signature.', ['bank' => $bank]);

            return response('', 401);
        }

        return $next($request);
    }
}
