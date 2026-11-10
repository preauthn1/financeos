<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\model\member\UserLogModel;
use app\support\IpLocationResolver;
use app\model\member\UserModel;
use app\service\MemberService;
use app\service\SettingService;
use app\service\api\ApiVerifyCodeService;
use app\support\ErrorCode;
use app\support\MemberPassword;
use app\support\Security\MemberTokenSecurityService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class MemberAuthService
{
    public function __construct(
        private ?MemberService $memberService = null,
        private ?MemberTokenSecurityService $tokenSecurity = null,
        private ?SettingService $settingService = null,
        private ?ApiVerifyCodeService $verifyCodeService = null,
    ) {
        $this->memberService = $memberService ?? new MemberService();
        $this->tokenSecurity = $tokenSecurity ?? new MemberTokenSecurityService();
        $this->settingService = $settingService ?? new SettingService();
        $this->verifyCodeService = $verifyCodeService ?? new ApiVerifyCodeService();
    }

    public function login(string $loginType, string $account, string $password, string $phoneCode = '', string $ip = ''): array
    {
        $account = trim($account);
        if ($account === '' || $password === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '账号或密码不能为空');
        }

        $query = UserModel::query()->where('status', 1);
        if ($loginType === 'email') {
            $query->where('email', $account);
        } else {
            $this->applyPhoneCodeFilter($query, $phoneCode);
            $query->where(function ($q) use ($account) {
                $q->where('mobile', $account)->orWhere('username', $account);
            });
        }

        $user = $query->first();
        if (!$user || !MemberPassword::verify($password, (string) $user->password)) {
            throw new BusinessException(ErrorCode::USERNAME_OR_PASSWORD_ERROR);
        }

        if (MemberPassword::needsRehash((string) $user->password)) {
            $user->password = MemberPassword::hash($password);
            $user->save();
        }

        return $this->buildAuthResponse($user, $ip);
    }

    public function register(array $data, string $ip = ''): array
    {
        $loginType = (string) ($data['login_type'] ?? 'mobile');
        $account = trim((string) ($data['account'] ?? $data['mobile'] ?? $data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $payPassword = (string) ($data['pay_password'] ?? $data['paypwd'] ?? '');
        $inviteCode = trim((string) ($data['invite_code'] ?? $data['ic'] ?? ''));

        if ($account === '' || $password === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '账号或密码不能为空');
        }
        if ($payPassword === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请设置安全密码');
        }

        if ($loginType === 'mobile' && (string) $this->settingService->getValue('is_verify_phone_code', '0') === '1') {
            $smsCode = trim((string) ($data['sms_code'] ?? $data['code'] ?? ''));
            if ($smsCode === '' || !$this->verifyCodeService->verifySms($account, $smsCode)) {
                throw new BusinessException(ErrorCode::CAPTCHA_INVALID, '短信验证码错误或已过期');
            }
        }

        $payload = [
            'username' => $account,
            'password' => $password,
            'pay_password' => $payPassword,
            'account_type' => $loginType === 'email' ? 'email' : 'mobile',
            'phone_code' => (string) ($data['phone_code'] ?? ''),
            'ip' => $ip,
            'parent_invite_code' => $inviteCode,
        ];

        $user = $this->memberService->create($payload);
        return $this->buildAuthResponse($user, $ip);
    }

    public function refresh(string $refreshToken): array
    {
        $userId = $this->tokenSecurity->getUserIdByRefreshToken($refreshToken);
        if (!$userId) {
            throw new BusinessException(ErrorCode::REFRESH_TOKEN_INVALID);
        }

        $user = UserModel::where('id', $userId)->where('status', 1)->first();
        if (!$user) {
            throw new BusinessException(ErrorCode::REFRESH_TOKEN_INVALID);
        }

        $session = $this->tokenSecurity->getSession($userId);
        if ($session !== null && ($session['refresh_token'] ?? '') !== $refreshToken) {
            throw new BusinessException(ErrorCode::REFRESH_TOKEN_INVALID, '会话已失效，请重新登录');
        }

        $this->tokenSecurity->deleteRefreshToken($refreshToken);
        if ($session !== null) {
            $this->tokenSecurity->blacklistAccessToken((string) ($session['jti'] ?? ''), (int) ($session['exp'] ?? 0));
        }

        $tokens = $this->issueTokens($user);
        return [
            'token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_time' => $tokens['exp'],
            'user_info' => $this->transformUser($user),
        ];
    }

    public function logout(int $userId, string $refreshToken = ''): void
    {
        if ($refreshToken !== '') {
            $this->tokenSecurity->deleteRefreshToken($refreshToken);
        }
        $this->tokenSecurity->revokeSession($userId);
    }

    public function transformUser(UserModel $user): array
    {
        $balance = (string) $user->balance;

        return [
            'id' => $user->id,
            'username' => $user->username,
            'user_name' => $user->username,
            'nickname' => $user->nickname,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'level_id' => (int) $user->level_id,
            'invite_code' => $user->invite_code,
            'user_money' => $balance,
            'user_money_value' => $balance,
            'brokerage_money' => (string) $user->brokerage_balance,
            'recharge_money' => (string) $user->total_recharge,
            'withdrawal_amount' => (string) $user->total_withdraw,
            'is_xuni' => (int) $user->is_virtual,
            'kyc_status' => (int) $user->kyc_status,
            'credit_score' => (int) $user->credit_score,
            'sign_in_days' => (int) $user->sign_in_days,
            'lottery_count' => (int) $user->lottery_count,
        ];
    }

    private function applyPhoneCodeFilter($query, string $phoneCode): void
    {
        if ($phoneCode === '') {
            return;
        }

        $normalized = $this->normalizePhoneCode($phoneCode);
        $aliases = array_values(array_unique([$phoneCode, $normalized, '86', '+86', '']));

        $query->where(function ($q) use ($aliases) {
            $q->whereIn('phone_code', $aliases)->orWhereNull('phone_code');
        });
    }

    private function normalizePhoneCode(string $code): string
    {
        $code = trim($code);
        if ($code === '' || $code === '86' || $code === '+86') {
            return '+86';
        }

        return str_starts_with($code, '+') ? $code : '+' . $code;
    }

    private function buildAuthResponse(UserModel $user, string $ip): array
    {
        $user->update([
            'login_count' => (int) $user->login_count + 1,
            'last_login_ip' => $ip,
            'last_login_at' => time(),
        ]);

        UserLogModel::create([
            'user_id' => $user->id,
            'ip' => $ip,
            'area' => IpLocationResolver::resolveForStorage($ip),
            'type' => 0,
            'device' => '',
        ]);

        $tokens = $this->issueTokens($user->fresh());
        return [
            'token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_time' => $tokens['exp'],
            'user_info' => $this->transformUser($user->fresh()),
        ];
    }

    /** @return array{access_token: string, refresh_token: string, expires_in: int, jti: string, exp: int} */
    private function issueTokens(UserModel $user): array
    {
        $jti = bin2hex(random_bytes(16));
        $expiresIn = (int) env('JWT_ACCESS_TTL', 7200);
        $exp = time() + $expiresIn;

        $accessToken = JWT::encode([
            'iss' => env('APP_NAME', 'webman-admin'),
            'sub' => $user->id,
            'user_id' => $user->id,
            'guard' => 'member',
            'username' => $user->username,
            'jti' => $jti,
            'iat' => time(),
            'exp' => $exp,
        ], env('JWT_SECRET', 'your-secret-key'), 'HS256');

        $refreshToken = bin2hex(random_bytes(32));
        $this->tokenSecurity->storeRefreshToken($refreshToken, (int) $user->id);
        $this->tokenSecurity->registerSession((int) $user->id, $refreshToken, $jti, $exp);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
            'jti' => $jti,
            'exp' => $exp,
        ];
    }
}
