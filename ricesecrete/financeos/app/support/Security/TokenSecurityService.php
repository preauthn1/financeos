<?php

declare(strict_types=1);

namespace app\support\Security;

use support\Redis;

final class TokenSecurityService
{
    private const REFRESH_PREFIX = 'refresh_token:';
    private const BLACKLIST_PREFIX = 'token:blacklist:';
    private const SESSION_PREFIX = 'admin:session:';

    public function isSingleDeviceEnabled(): bool
    {
        return filter_var(env('AUTH_SINGLE_DEVICE', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function isRefreshRotateEnabled(): bool
    {
        return filter_var(env('AUTH_REFRESH_ROTATE', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function storeRefreshToken(string $refreshToken, int $adminId): void
    {
        $ttl = (int) env('JWT_REFRESH_TTL', 604800);
        Redis::setex(self::REFRESH_PREFIX . $refreshToken, $ttl, (string) $adminId);
    }

    public function getAdminIdByRefreshToken(string $refreshToken): ?int
    {
        $adminId = Redis::get(self::REFRESH_PREFIX . $refreshToken);
        return $adminId === null ? null : (int) $adminId;
    }

    public function deleteRefreshToken(string $refreshToken): void
    {
        if ($refreshToken !== '') {
            Redis::del(self::REFRESH_PREFIX . $refreshToken);
        }
    }

    public function blacklistAccessToken(string $jti, int $expiresAt): void
    {
        if ($jti === '') {
            return;
        }

        $ttl = $expiresAt - time();
        if ($ttl > 0) {
            Redis::setex(self::BLACKLIST_PREFIX . $jti, $ttl, '1');
        }
    }

    public function isAccessTokenBlacklisted(string $jti): bool
    {
        if ($jti === '') {
            return false;
        }

        return Redis::get(self::BLACKLIST_PREFIX . $jti) !== null;
    }

    /**
     * @param array{refresh_token: string, jti: string, exp: int} $session
     */
    public function saveSession(int $adminId, array $session): void
    {
        $ttl = (int) env('JWT_REFRESH_TTL', 604800);
        Redis::setex(self::SESSION_PREFIX . $adminId, $ttl, json_encode($session));
    }

    /**
     * @return array{refresh_token: string, jti: string, exp: int}|null
     */
    public function getSession(int $adminId): ?array
    {
        $raw = Redis::get(self::SESSION_PREFIX . $adminId);
        if ($raw === null) {
            return null;
        }

        $session = json_decode($raw, true);
        return is_array($session) ? $session : null;
    }

    public function revokeAllSessions(int $adminId): void
    {
        $session = $this->getSession($adminId);
        if ($session !== null) {
            $this->deleteRefreshToken((string) ($session['refresh_token'] ?? ''));
            $this->blacklistAccessToken(
                (string) ($session['jti'] ?? ''),
                (int) ($session['exp'] ?? 0)
            );
        }

        Redis::del(self::SESSION_PREFIX . $adminId);
    }

    public function revokePreviousSessionOnLogin(int $adminId): void
    {
        if (!$this->isSingleDeviceEnabled()) {
            return;
        }

        $this->revokeAllSessions($adminId);
    }

    public function registerSession(int $adminId, string $refreshToken, string $jti, int $accessExp): void
    {
        $this->saveSession($adminId, [
            'refresh_token' => $refreshToken,
            'jti' => $jti,
            'exp' => $accessExp,
        ]);
    }
}
