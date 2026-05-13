<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LimitRequestBodySize
{
    public function handle(Request $request, Closure $next): Response
    {
        $maxBytes = (int) config('wallet.max_body_bytes', 2 * 1024 * 1024);
        $contentLength = $request->header('Content-Length');

        if ($contentLength !== null && (int) $contentLength > $maxBytes) {
            Log::warning('Webhook rejected: payload too large.', [
                'content_length' => (int) $contentLength,
                'max_bytes' => $maxBytes,
            ]);

            return response('', 413);
        }

        $bodySize = strlen((string) $request->getContent());

        if ($bodySize > $maxBytes) {
            Log::warning('Webhook rejected: payload too large.', [
                'body_size' => $bodySize,
                'max_bytes' => $maxBytes,
            ]);

            return response('', 413);
        }

        return $next($request);
    }
}
