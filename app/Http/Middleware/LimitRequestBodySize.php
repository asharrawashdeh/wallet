<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Wallet\WalletLogger;
use Symfony\Component\HttpFoundation\Response;

class LimitRequestBodySize
{
    public function handle(Request $request, Closure $next): Response
    {
        $maxBytes = (int) config('wallet.max_body_bytes', 2 * 1024 * 1024);
        $contentLength = $request->header('Content-Length');

        if ($contentLength !== null && (int) $contentLength > $maxBytes) {
            WalletLogger::warning('Webhook rejected: payload too large.', [
                'content_length' => (int) $contentLength,
                'max_bytes' => $maxBytes,
            ]);

            return response('', 413);
        }

        $bodySize = strlen((string) $request->getContent());

        if ($bodySize > $maxBytes) {
            WalletLogger::warning('Webhook rejected: payload too large.', [
                'body_size' => $bodySize,
                'max_bytes' => $maxBytes,
            ]);

            return response('', 413);
        }

        return $next($request);
    }
}
