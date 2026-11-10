<?php

declare(strict_types=1);

namespace app\support\Security;

use support\Redis;

class MemberTokenSecurityService
{
    private const REFRESH_PREFIX = 'member_refresh_token:';
    private const SESSION_PREFIX = 'member_session:';

    public function storeRefreshToken(string $refreshToken, int $userId): void
    {
        $ttl = (int) env('JWT_REFRESH_TTL', 604800);
        Redis::setex(self::REFRESH_PREFIX . $refreshToken, $ttl, (string) $userId);
    }

    public function getUserIdByRefreshToken(string $refreshToken): ?int
    {
        $userId = Redis::get(self::REFRESH_PREFIX . $refreshToken);
        return $userId ? (int) $userId : null;
    }

    public function deleteRefreshToken(string $refreshToken): void
    {
        Redis::del(self::REFRESH_PREFIX . $refreshToken);
    }

    /** @param array{refresh_token: string, jti: string, exp: int} $session */
    public function registerSession(int $userId, string $refreshToken, string $jti, int $exp): void
    {
        $ttl = (int) env('JWT_REFRESH_TTL', 604800);
        Redis::setex(self::SESSION_PREFIX . $userId, $ttl, json_encode([
            'refresh_token' => $refreshToken,
            'jti' => $jti,
            'exp' => $exp,
        ], JSON_UNESCAPED_UNICODE));
    }

    /** @return array{refresh_token: string, jti: string, exp: int}|null */
    public function getSession(int $userId): ?array
    {
        $raw = Redis::get(self::SESSION_PREFIX . $userId);
        if (!$raw) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    public function revokeSession(int $userId): void
    {
        $session = $this->getSession($userId);
        if ($session !== null) {
            $this->deleteRefreshToken((string) ($session['refresh_token'] ?? ''));
        }
        Redis::del(self::SESSION_PREFIX . $userId);
    }

    public function isAccessTokenBlacklisted(string $jti): bool
    {
        if ($jti === '') {
            return false;
        }
        return (bool) Redis::exists('member_access_blacklist:' . $jti);
    }

    public function blacklistAccessToken(string $jti, int $exp): void
    {
        if ($jti === '') {
            return;
        }
        $ttl = max(1, $exp - time());
        Redis::setex('member_access_blacklist:' . $jti, $ttl, '1');
    }
}
