<?php

declare(strict_types=1);

namespace app\support;

/**
 * 会员密码校验：兼容 bcrypt 与旧站 md5(password . salt)。
 */
final class MemberPassword
{
    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    public static function verify(string $plain, string $hash): bool
    {
        $hash = (string) $hash;
        if ($hash === '' || $plain === '') {
            return false;
        }

        if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2b$')) {
            return password_verify($plain, $hash);
        }

        // 旧站：md5(password . app.salt)
        if (preg_match('/^[a-f0-9]{32}$/i', $hash) === 1) {
            $salt = (string) env('LEGACY_PASSWORD_SALT', 'wZPb~yxvA!ir38&Z');

            return hash_equals(strtolower($hash), md5($plain . $salt));
        }

        return false;
    }

    public static function needsRehash(string $hash): bool
    {
        $hash = (string) $hash;
        if ($hash === '') {
            return true;
        }

        return !(str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2b$'));
    }
}
