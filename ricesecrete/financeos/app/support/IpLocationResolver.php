<?php

declare(strict_types=1);

namespace app\support;

use itbdw\Ip\IpLocation;

/**
 * IP 归属地解析（基于 itbdw/ip-database 离线库）。
 */
final class IpLocationResolver
{
    private const STORAGE_MAX_LENGTH = 100;

    /**
     * 解析为展示用地区字符串。
     */
    public static function resolve(string $ip): string
    {
        $detail = self::resolveDetail($ip);
        if ($detail === []) {
            return '';
        }

        if (!empty($detail['area'])) {
            return (string) $detail['area'];
        }

        $parts = array_filter([
            $detail['country'] ?? '',
            $detail['province'] ?? '',
            $detail['city'] ?? '',
            $detail['county'] ?? '',
            $detail['isp'] ?? '',
        ], static fn ($value) => $value !== '' && $value !== '-');

        return implode(' ', array_unique($parts));
    }

    /**
     * 写入数据库时截断后的地区。
     */
    public static function resolveForStorage(string $ip): string
    {
        $area = self::resolve($ip);
        if ($area === '') {
            return '';
        }

        return mb_substr($area, 0, self::STORAGE_MAX_LENGTH);
    }

    /**
     * 优先使用已存储地区，否则实时解析。
     */
    public static function resolveOrStored(string $ip, ?string $stored = null): string
    {
        $stored = trim((string) $stored);
        if ($stored !== '') {
            return $stored;
        }

        return self::resolve($ip);
    }

    /**
     * @return array{
     *     ip?: string,
     *     country?: string,
     *     province?: string,
     *     city?: string,
     *     county?: string,
     *     isp?: string,
     *     area?: string,
     *     error?: string
     * }
     */
    public static function resolveDetail(string $ip): array
    {
        $ip = trim($ip);
        if ($ip === '' || self::isPrivateOrReserved($ip)) {
            return [];
        }

        try {
            $result = IpLocation::getLocation(
                $ip,
                self::v4DatabasePath(),
                self::v6DatabasePath()
            );
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($result) || isset($result['error'])) {
            return [];
        }

        return array_map(static fn ($value) => is_string($value) ? trim($value) : $value, $result);
    }

    private static function v4DatabasePath(): string
    {
        $path = trim((string) env('IP_DATABASE_V4_PATH', ''));

        return $path !== '' ? $path : IpLocation::src('/libs/qqwry.dat');
    }

    private static function v6DatabasePath(): string
    {
        $path = trim((string) env('IP_DATABASE_V6_PATH', ''));

        return $path !== '' ? $path : IpLocation::src('/libs/ipv6wry.db');
    }

    private static function isPrivateOrReserved(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
