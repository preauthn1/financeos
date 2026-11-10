<?php

declare(strict_types=1);

namespace app\support;

use support\Redis;

class ContentCache
{
    public static function bannerKey(string $region, string $lang): string
    {
        return "content:banner:{$region}:{$lang}";
    }

    public static function noticeKey(string $lang): string
    {
        return "content:notice:{$lang}";
    }

    public static function customerServiceKey(): string
    {
        return 'content:customer_service';
    }

    public static function indexVideoKey(string $lang): string
    {
        return "content:index_video:{$lang}";
    }

    public static function clearBanner(?string $region = null): void
    {
        $regions = $region ? [$region] : ['index', 'introduce'];
        foreach ($regions as $item) {
            self::forgetPrefix("content:banner:{$item}:");
        }
    }

    public static function clearNotice(?string $lang = null): void
    {
        if ($lang) {
            Redis::del(self::noticeKey($lang));
            return;
        }
        self::forgetPrefix('content:notice:');
    }

    public static function clearCustomerService(): void
    {
        Redis::del(self::customerServiceKey());
    }

    public static function clearVideo(?string $lang = null): void
    {
        if ($lang) {
            Redis::del(self::indexVideoKey($lang));
            return;
        }
        self::forgetPrefix('content:index_video:');
    }

    private static function forgetPrefix(string $prefix): void
    {
        $pattern = str_ends_with($prefix, ':') ? $prefix . '*' : $prefix . '*';
        $keys = Redis::keys($pattern) ?: [];
        foreach ($keys as $key) {
            Redis::del($key);
        }
    }
}
