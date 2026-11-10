<?php

declare(strict_types=1);

namespace app\support\Security;

use app\exception\BusinessException;
use app\support\ErrorCode;
use support\Redis;

final class LoginSecurityService
{
    private const FAIL_PREFIX = 'login:fail:';
    private const LOCK_PREFIX = 'login:lock:';

    public function assertNotLocked(string $username, string $ip): void
    {
        $lockKey = $this->lockKey($username, $ip);
        $lockedUntil = Redis::get($lockKey);
        if ($lockedUntil === null) {
            return;
        }

        $remaining = (int) $lockedUntil - time();
        if ($remaining > 0) {
            throw new BusinessException(
                ErrorCode::LOGIN_LOCKED,
                sprintf('登录失败次数过多，请 %d 分钟后再试', (int) ceil($remaining / 60))
            );
        }

        Redis::del($lockKey);
    }

    public function shouldRequireCaptcha(string $username, string $ip): bool
    {
        $captchaService = new CaptchaService();
        if (!$captchaService->isEnabled()) {
            return false;
        }

        $threshold = max(0, (int) env('LOGIN_CAPTCHA_AFTER_FAILURES', 3));
        if ($threshold === 0) {
            return true;
        }

        return $this->getFailureCount($username, $ip) >= $threshold;
    }

    public function assertCaptcha(string $username, string $ip, ?string $captchaKey, ?string $captchaAnswer): void
    {
        if (!$this->shouldRequireCaptcha($username, $ip)) {
            return;
        }

        if ($captchaKey === null || $captchaKey === '' || $captchaAnswer === null || $captchaAnswer === '') {
            throw new BusinessException(ErrorCode::CAPTCHA_REQUIRED);
        }

        $captchaService = new CaptchaService();
        if (!$captchaService->verify($captchaKey, $captchaAnswer)) {
            throw new BusinessException(ErrorCode::CAPTCHA_INVALID);
        }
    }

    public function recordFailure(string $username, string $ip): void
    {
        $maxAttempts = max(1, (int) env('LOGIN_MAX_ATTEMPTS', 5));
        $lockMinutes = max(1, (int) env('LOGIN_LOCK_MINUTES', 15));
        $windowSeconds = max(60, (int) env('LOGIN_FAIL_WINDOW_SECONDS', 900));

        $failKey = $this->failKey($username, $ip);
        $count = Redis::incr($failKey);
        if ($count === 1) {
            Redis::expire($failKey, $windowSeconds);
        }

        if ($count >= $maxAttempts) {
            Redis::set(
                $this->lockKey($username, $ip),
                (string) (time() + $lockMinutes * 60),
                $lockMinutes * 60
            );
            Redis::del($failKey);
        }
    }

    public function clearFailures(string $username, string $ip): void
    {
        Redis::del($this->failKey($username, $ip), $this->lockKey($username, $ip));
    }

    private function getFailureCount(string $username, string $ip): int
    {
        $value = Redis::get($this->failKey($username, $ip));
        return $value === null ? 0 : (int) $value;
    }

    private function failKey(string $username, string $ip): string
    {
        return self::FAIL_PREFIX . md5(strtolower($username) . '|' . $ip);
    }

    private function lockKey(string $username, string $ip): string
    {
        return self::LOCK_PREFIX . md5(strtolower($username) . '|' . $ip);
    }
}
