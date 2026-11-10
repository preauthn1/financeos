<?php

declare(strict_types=1);

namespace app\middleware;

use app\support\ErrorCode;
use app\service\AdminIpWhitelistService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class IpWhitelistMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if (!$this->enabled()) {
            return $next($request);
        }

        $ip = (string) $request->getRealIp();
        if ((new AdminIpWhitelistService())->isAllowed($ip)) {
            return $next($request);
        }

        return fail(ErrorCode::IP_NOT_ALLOWED, null, 403);
    }

    private function enabled(): bool
    {
        $raw = env('ADMIN_IP_WHITELIST_ENABLED', false);

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw) || is_float($raw)) {
            return (int) $raw === 1;
        }

        $value = strtolower(trim((string) $raw));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
