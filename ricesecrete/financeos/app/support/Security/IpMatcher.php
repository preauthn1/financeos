<?php

declare(strict_types=1);

namespace app\support\Security;

final class IpMatcher
{
    /**
     * @param string[] $rules
     */
    public static function match(string $ip, array $rules): bool
    {
        $ip = self::normalizeIp(trim($ip));
        if ($ip === '' || $rules === []) {
            return false;
        }

        foreach ($rules as $rule) {
            $rule = trim((string) $rule);
            if ($rule === '') {
                continue;
            }
            if ($rule === '*' || $rule === '0.0.0.0/0' || $rule === '::/0') {
                return true;
            }

            if (str_contains($rule, '/')) {
                if (self::matchCidr($ip, $rule)) {
                    return true;
                }
                // 本机 IPv4/IPv6 双向兼容：::1 ↔ 127.0.0.1
                foreach (self::aliases($ip) as $alias) {
                    if (self::matchCidr($alias, $rule)) {
                        return true;
                    }
                }
                continue;
            }

            $normalizedRule = self::normalizeIp($rule);
            if ($ip === $normalizedRule) {
                return true;
            }

            foreach (self::aliases($ip) as $alias) {
                if ($alias === $normalizedRule) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function normalizeIp(string $ip): string
    {
        if ($ip === '::ffff:127.0.0.1') {
            return '127.0.0.1';
        }

        return $ip;
    }

    /**
     * @return string[]
     */
    private static function aliases(string $ip): array
    {
        return match ($ip) {
            '127.0.0.1', '::ffff:127.0.0.1' => ['::1'],
            '::1' => ['127.0.0.1'],
            default => [],
        };
    }

    private static function matchCidr(string $ip, string $cidr): bool
    {
        [$subnet, $maskBits] = array_pad(explode('/', $cidr, 2), 2, '');
        $subnet = trim($subnet);
        $maskBits = (int) trim($maskBits);

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        $len = strlen($ipBin);
        if ($len !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = $len * 8;
        if ($maskBits < 0 || $maskBits > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($maskBits, 8);
        $remainBits = $maskBits % 8;

        if ($fullBytes > 0) {
            if (substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
                return false;
            }
        }

        if ($remainBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainBits)) & 0xFF;
        return ((ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask));
    }
}

