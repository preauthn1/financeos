<?php

declare(strict_types=1);

namespace app\middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use app\support\ErrorCode;
use app\support\Security\MemberTokenSecurityService;

class MemberAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $token = $this->extractToken($request);
        if ($token === '') {
            return fail(ErrorCode::MEMBER_TOKEN_EXPIRED);
        }

        try {
            $decoded = JWT::decode($token, new Key(env('JWT_SECRET', 'your-secret-key'), 'HS256'));
            if (($decoded->guard ?? '') !== 'member') {
                return fail(ErrorCode::MEMBER_TOKEN_EXPIRED);
            }

            $jti = (string) ($decoded->jti ?? '');
            $tokenSecurity = new MemberTokenSecurityService();
            if ($tokenSecurity->isAccessTokenBlacklisted($jti)) {
                return fail(ErrorCode::MEMBER_TOKEN_EXPIRED, 'Token 已失效，请重新登录');
            }

            $request->user_id = (int) ($decoded->user_id ?? $decoded->sub ?? 0);
            $request->token_jti = $jti;
            $request->auth_guard = 'member';

            if ($request->user_id <= 0) {
                return fail(ErrorCode::MEMBER_TOKEN_EXPIRED);
            }

            return $next($request);
        } catch (\Throwable) {
            return fail(ErrorCode::MEMBER_TOKEN_EXPIRED);
        }
    }

    public static function extractToken(Request $request): string
    {
        $auth = (string) $request->header('Authorization', '');
        if ($auth === '') {
            return '';
        }
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return $auth;
    }
}
