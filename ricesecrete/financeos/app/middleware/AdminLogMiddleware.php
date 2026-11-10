<?php

declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use app\service\LogService;

class AdminLogMiddleware implements MiddlewareInterface
{
    private const LOG_METHODS = ['POST', 'PUT', 'DELETE'];

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        if (in_array($request->method(), self::LOG_METHODS, true) && isset($request->admin_id)) {
            $this->recordLog($request);
        }

        return $response;
    }

    private function recordLog(Request $request): void
    {
        $path = $request->path();
        $module = $this->extractModule($path);
        $action = $this->extractAction($request->method());

        (new LogService())->record(
            adminId: (int) $request->admin_id,
            module: $module,
            action: $action,
            description: "{$action} {$module}",
            requestData: $request->all(),
            ip: $request->getRealIp(),
            userAgent: $request->header('User-Agent', '')
        );
    }

    private function extractModule(string $path): string
    {
        $parts = array_values(array_filter(explode('/', $path)));
        return $parts[1] ?? 'unknown';
    }

    private function extractAction(string $method): string
    {
        return match ($method) {
            'POST' => 'create',
            'PUT' => 'update',
            'DELETE' => 'delete',
            default => 'unknown',
        };
    }
}
