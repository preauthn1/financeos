<?php

declare(strict_types=1);

namespace app\middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use app\support\Security\MemberTokenSecurityService;

class OptionalMemberAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $request->user_id = 0;
        $request->auth_guard = '';

        $token = MemberAuthMiddleware::extractToken($request);
        if ($token !== '') {
            try {
                $decoded = JWT::decode($token, new Key(env('JWT_SECRET', 'your-secret-key'), 'HS256'));
                if (($decoded->guard ?? '') === 'member') {
                    $jti = (string) ($decoded->jti ?? '');
                    $tokenSecurity = new MemberTokenSecurityService();
                    if (!$tokenSecurity->isAccessTokenBlacklisted($jti)) {
                        $request->user_id = (int) ($decoded->user_id ?? $decoded->sub ?? 0);
                        $request->token_jti = $jti;
                        $request->auth_guard = 'member';
                    }
                }
            } catch (\Throwable) {
                // optional auth: ignore invalid token
            }
        }

        return $next($request);
    }
}
