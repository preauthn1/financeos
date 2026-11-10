<?php

declare(strict_types=1);

namespace app\middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use app\service\PermissionService;
use app\support\ErrorCode;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $token = $request->header('Authorization');
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return fail(ErrorCode::UNAUTHORIZED);
        }

        try {
            $jwt = substr($token, 7);
            $decoded = JWT::decode($jwt, new Key(env('JWT_SECRET', 'your-secret-key'), 'HS256'));

            $request->admin_id = (int) $decoded->admin_id;
            $request->admin_roles = (array) ($decoded->roles ?? []);
            $request->token_jti = (string) ($decoded->jti ?? '');

            $tokenSecurity = new \app\support\Security\TokenSecurityService();
            if ($tokenSecurity->isAccessTokenBlacklisted($request->token_jti)) {
                return fail(ErrorCode::TOKEN_INVALID, 'Token 已失效，请重新登录');
            }

            // 白名单路由：登录后即可访问（不校验 API 权限）
            if ($this->isAuthWhitelisted($request->path(), $request->method())) {
                return $next($request);
            }

            if (in_array('super_admin', $request->admin_roles, true)) {
                return $next($request);
            }

            $permissionService = new PermissionService();
            if (!$permissionService->checkApiPermission(
                $request->admin_id,
                $request->path(),
                $request->method()
            )) {
                return fail(ErrorCode::FORBIDDEN);
            }

            return $next($request);
        } catch (\Exception $e) {
            return fail(ErrorCode::TOKEN_INVALID);
        }
    }

    private function isAuthWhitelisted(string $path, string $method = 'GET'): bool
    {
        static $whitelist = [
            '/admin/me',
            '/admin/menus',
            '/admin/server-time',
            '/admin/logout',
            '/admin/dashboard/total',
            '/admin/dashboard/userAccessSource',
            '/admin/dashboard/weeklyUserActivity',
            '/admin/dashboard/monthlySales',
            '/admin/upload/presign',
            '/api/upload',
            '/admin/articles/meta',
            '/admin/ad-configs/meta',
        ];

        if (in_array($path, $whitelist, true)) {
            return true;
        }

        if (
            strtoupper($method) === 'GET'
            && preg_match('#^/admin/dicts/code/[a-z0-9_\-]+$#i', $path) === 1
        ) {
            return true;
        }

        return str_starts_with($path, '/admin/upload/');
    }
}
