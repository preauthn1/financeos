<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\sys\AdminModel;
use app\support\ErrorCode;
use app\support\Security\LoginSecurityService;
use app\support\Security\TokenSecurityService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService
{
    protected LoginSecurityService $loginSecurity;
    protected TokenSecurityService $tokenSecurity;
    protected LogService $logService;
    protected AdminGoogleAuthService $googleAuthService;

    public function __construct(
        ?LoginSecurityService $loginSecurity = null,
        ?TokenSecurityService $tokenSecurity = null,
        ?LogService $logService = null,
        ?AdminGoogleAuthService $googleAuthService = null
    ) {
        $this->loginSecurity = $loginSecurity ?? new LoginSecurityService();
        $this->tokenSecurity = $tokenSecurity ?? new TokenSecurityService();
        $this->logService = $logService ?? new LogService();
        $this->googleAuthService = $googleAuthService ?? new AdminGoogleAuthService();
    }

    /**
     * @param array{ip?: string, user_agent?: string, captcha_key?: string, captcha_answer?: string, google_code?: string} $context
     */
    public function login(string $username, string $password, array $context = []): array
    {
        $ip = (string) ($context['ip'] ?? '');
        $userAgent = (string) ($context['user_agent'] ?? '');
        $captchaKey = isset($context['captcha_key']) ? (string) $context['captcha_key'] : null;
        $captchaAnswer = isset($context['captcha_answer']) ? (string) $context['captcha_answer'] : null;
        $googleCode = isset($context['google_code']) ? (string) $context['google_code'] : null;

        $this->loginSecurity->assertNotLocked($username, $ip);
        $this->loginSecurity->assertCaptcha($username, $ip, $captchaKey, $captchaAnswer);

        $admin = AdminModel::where('username', $username)->where('status', 1)->first();
        if (!$admin || !password_verify($password, $admin->password)) {
            $this->loginSecurity->recordFailure($username, $ip);
            $this->logService->recordLoginAttempt(
                username: $username,
                success: false,
                adminId: $admin?->id,
                ip: $ip,
                userAgent: $userAgent,
                reason: '用户名或密码错误'
            );
            throw new BusinessException(ErrorCode::USERNAME_OR_PASSWORD_ERROR);
        }

        try {
            $this->googleAuthService->assertCode($admin, $googleCode);
        } catch (BusinessException $e) {
            if ($e->getCode() === ErrorCode::GOOGLE_AUTH_INVALID) {
                $this->loginSecurity->recordFailure($username, $ip);
                $this->logService->recordLoginAttempt(
                    username: $username,
                    success: false,
                    adminId: $admin->id,
                    ip: $ip,
                    userAgent: $userAgent,
                    reason: '谷歌验证码错误'
                );
            }
            throw $e;
        }

        $this->loginSecurity->clearFailures($username, $ip);
        $this->tokenSecurity->revokePreviousSessionOnLogin($admin->id);

        $roles = $admin->roles()->pluck('slug')->toArray();
        $tokens = $this->issueTokens($admin, $roles);

        $this->logService->recordLoginAttempt(
            username: $username,
            success: true,
            adminId: $admin->id,
            ip: $ip,
            userAgent: $userAgent
        );

        return [
            'token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_in'],
            'captcha_required' => $this->loginSecurity->shouldRequireCaptcha($username, $ip),
            'google_auth_bound' => $this->googleAuthService->isBound($admin),
            'user' => [
                'id' => $admin->id,
                'username' => $admin->username,
                'nickname' => $admin->nickname,
                'avatar' => $admin->avatar,
                'roles' => $roles,
            ],
        ];
    }

    public function refresh(string $refreshToken): array
    {
        $adminId = $this->tokenSecurity->getAdminIdByRefreshToken($refreshToken);
        if (!$adminId) {
            throw new BusinessException(ErrorCode::REFRESH_TOKEN_INVALID);
        }

        $admin = AdminModel::find($adminId);
        if (!$admin || $admin->status !== 1) {
            throw new BusinessException(ErrorCode::REFRESH_TOKEN_INVALID);
        }

        $session = $this->tokenSecurity->getSession($adminId);
        if ($session !== null && ($session['refresh_token'] ?? '') !== $refreshToken) {
            throw new BusinessException(ErrorCode::REFRESH_TOKEN_INVALID, '会话已失效，请重新登录');
        }

        $roles = $admin->roles()->pluck('slug')->toArray();
        $this->tokenSecurity->deleteRefreshToken($refreshToken);

        if ($session !== null) {
            $this->tokenSecurity->blacklistAccessToken(
                (string) ($session['jti'] ?? ''),
                (int) ($session['exp'] ?? 0)
            );
        }

        $tokens = $this->issueTokens($admin, $roles);

        $result = [
            'token' => $tokens['access_token'],
            'expires_in' => $tokens['expires_in'],
        ];

        if ($this->tokenSecurity->isRefreshRotateEnabled()) {
            $result['refresh_token'] = $tokens['refresh_token'];
        } else {
            $this->tokenSecurity->storeRefreshToken($refreshToken, $adminId);
            if ($session !== null) {
                $this->tokenSecurity->registerSession(
                    $adminId,
                    $refreshToken,
                    (string) ($session['jti'] ?? ''),
                    (int) ($session['exp'] ?? 0)
                );
            }
        }

        return $result;
    }

    public function logout(string $refreshToken, ?string $accessToken = null): void
    {
        if ($refreshToken !== '') {
            $adminId = $this->tokenSecurity->getAdminIdByRefreshToken($refreshToken);
            $this->tokenSecurity->deleteRefreshToken($refreshToken);
            if ($adminId) {
                $session = $this->tokenSecurity->getSession($adminId);
                if ($session !== null && ($session['refresh_token'] ?? '') === $refreshToken) {
                    $this->tokenSecurity->revokeAllSessions($adminId);
                    if ($accessToken) {
                        $this->blacklistAccessTokenString($accessToken);
                    }
                    return;
                }
            }
        }

        if ($accessToken) {
            $this->blacklistAccessTokenString($accessToken);
        }
    }

    public function revokeAdminSessions(int $adminId): void
    {
        $this->tokenSecurity->revokeAllSessions($adminId);
    }

    public function getUserInfo(int $adminId): array
    {
        $admin = AdminModel::with('roles:id,name,slug')->find($adminId);
        if (!$admin) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $permissionService = new PermissionService();

        return [
            'id' => $admin->id,
            'username' => $admin->username,
            'nickname' => $admin->nickname,
            'avatar' => $admin->avatar,
            'roles' => $admin->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'data_scope' => $role->data_scope ?? 'self',
            ])->toArray(),
            'permissions' => $permissionService->getAdminPermissions($adminId),
            'google_auth_bound' => $this->googleAuthService->isBound($admin),
        ];
    }

    public function loginRequiresGoogleAuth(string $username): bool
    {
        return $this->googleAuthService->isBoundByUsername($username);
    }

    public function getMenus(int $adminId, array $roles): array
    {
        $permissionService = new PermissionService();
        return $permissionService->getAdminMenus($adminId, $roles);
    }

    public function loginRequiresCaptcha(string $username, string $ip): bool
    {
        return $this->loginSecurity->shouldRequireCaptcha($username, $ip);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, jti: string, exp: int}
     */
    private function issueTokens(AdminModel $admin, array $roles): array
    {
        $jti = bin2hex(random_bytes(16));
        $expiresIn = (int) env('JWT_ACCESS_TTL', 7200);
        $exp = time() + $expiresIn;

        $accessToken = JWT::encode([
            'iss' => env('APP_NAME', 'webman-admin'),
            'sub' => $admin->id,
            'admin_id' => $admin->id,
            'username' => $admin->username,
            'roles' => $roles,
            'jti' => $jti,
            'iat' => time(),
            'exp' => $exp,
        ], env('JWT_SECRET', 'your-secret-key'), 'HS256');

        $refreshToken = bin2hex(random_bytes(32));
        $this->tokenSecurity->storeRefreshToken($refreshToken, $admin->id);
        $this->tokenSecurity->registerSession($admin->id, $refreshToken, $jti, $exp);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
            'jti' => $jti,
            'exp' => $exp,
        ];
    }

    private function blacklistAccessTokenString(string $accessToken): void
    {
        try {
            $decoded = JWT::decode($accessToken, new Key(env('JWT_SECRET', 'your-secret-key'), 'HS256'));
            $this->tokenSecurity->blacklistAccessToken(
                (string) ($decoded->jti ?? ''),
                (int) ($decoded->exp ?? 0)
            );
        } catch (\Throwable) {
            // ignore invalid token on logout
        }
    }
}
