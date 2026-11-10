<?php

declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if ($request->method() === 'OPTIONS') {
            return $this->addCorsHeaders(response('', 204), $request);
        }

        return $this->addCorsHeaders($next($request), $request);
    }

    private function addCorsHeaders(Response $response, Request $request): Response
    {
        $origin = $request->header('origin', '');
        $allowed = env('CORS_ORIGIN', '*');

        if ($allowed === '*') {
            $allowOrigin = $origin !== '' ? $origin : '*';
        } else {
            $allowedOrigins = array_map('trim', explode(',', $allowed));
            $allowOrigin = in_array($origin, $allowedOrigins, true)
                ? $origin
                : ($allowedOrigins[0] ?? '*');
        }

        return $response->withHeaders([
            'Access-Control-Allow-Origin' => $allowOrigin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => env(
                'CORS_ALLOW_HEADERS',
                'Content-Type, Authorization, X-Requested-With, uuid, x-request-cf, Accept, Accept-Language'
            ),
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
        ]);
    }
}
