<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\sys\AdminModel;
use app\support\ErrorCode;
use app\support\Security\GoogleAuthService;
use support\Redis;

class AdminGoogleAuthService
{
    private const SETUP_PREFIX = 'admin:google_auth:setup:';
    private const SETUP_TTL = 600;

    protected GoogleAuthService $googleAuth;

    public function __construct(?GoogleAuthService $googleAuth = null)
    {
        $this->googleAuth = $googleAuth ?? new GoogleAuthService();
    }

    public function isBound(AdminModel $admin): bool
    {
        return $this->normalizeSecret((string) ($admin->google_auth_secret ?? '')) !== '';
    }

    public function isBoundByUsername(string $username): bool
    {
        if ($username === '') {
            return false;
        }

        $admin = AdminModel::where('username', $username)->where('status', 1)->first();

        return $admin !== null && $this->isBound($admin);
    }

    public function assertCode(AdminModel $admin, ?string $code): void
    {
        $secret = $this->normalizeSecret((string) ($admin->google_auth_secret ?? ''));
        if ($secret === '') {
            return;
        }

        if ($code === null || trim($code) === '') {
            throw new BusinessException(ErrorCode::GOOGLE_AUTH_REQUIRED);
        }

        if (!$this->googleAuth->verifyCode($secret, trim($code))) {
            throw new BusinessException(ErrorCode::GOOGLE_AUTH_INVALID);
        }
    }

    /**
     * @return array{secret: string, otpauth_url: string}
     */
    public function createSetup(int $adminId): array
    {
        $admin = $this->findAdmin($adminId);
        if ($this->isBound($admin)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '您已绑定谷歌验证器，无需重复绑定');
        }

        $secret = $this->googleAuth->createSecret();
        Redis::setex(self::SETUP_PREFIX . $adminId, self::SETUP_TTL, $secret);

        $issuer = (string) env('APP_NAME', 'Webman Admin');
        $otpAuthUrl = $this->googleAuth->getOtpAuthUrl($admin->username, $secret, $issuer);

        return [
            'secret' => $secret,
            'otpauth_url' => $otpAuthUrl,
        ];
    }

    public function bind(int $adminId, string $code): void
    {
        $admin = $this->findAdmin($adminId);
        if ($this->isBound($admin)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '您已绑定谷歌验证器');
        }

        $pendingSecret = Redis::get(self::SETUP_PREFIX . $adminId);
        if ($pendingSecret === null || $pendingSecret === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '绑定信息已过期，请重新获取二维码');
        }

        if (!$this->googleAuth->verifyCode((string) $pendingSecret, trim($code))) {
            throw new BusinessException(ErrorCode::GOOGLE_AUTH_INVALID);
        }

        $admin->update(['google_auth_secret' => (string) $pendingSecret]);
        Redis::del(self::SETUP_PREFIX . $adminId);
    }

    public function clear(int $adminId): void
    {
        $admin = $this->findAdmin($adminId);
        $admin->update(['google_auth_secret' => '']);
        Redis::del(self::SETUP_PREFIX . $adminId);
    }

    private function findAdmin(int $adminId): AdminModel
    {
        $admin = AdminModel::find($adminId);
        if (!$admin) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '管理员不存在');
        }

        return $admin;
    }

    private function normalizeSecret(string $secret): string
    {
        return trim($secret);
    }
}
