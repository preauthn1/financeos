<?php

declare(strict_types=1);

namespace app\support;

use app\model\sys\LangModel;
use app\service\SettingService;
use support\Context;

/**
 * 统一语言解析：H5 vue-i18n 代码与 API/DB 语言代码映射。
 */
class Locale
{
    public const CONTEXT_KEY = 'request_locale';

    /** vue-i18n / 前端简码 → API/DB 语言代码 */
    private const FRONT_TO_API = [
        'cn' => 'zh-cn',
        'zh' => 'zh-cn',
        'zh-cn' => 'zh-cn',
        'zh_cn' => 'zh-cn',
        'zh-CN' => 'zh-cn',
        'zh-tw' => 'zh-tw',
        'zh-TW' => 'zh-tw',
        'pt' => 'pt',
        'en' => 'en',
        'en-us' => 'en-us',
        'es' => 'es',
    ];

    /** API/DB 语言代码 → vue-i18n 简码 */
    private const API_TO_FRONT = [
        'zh-cn' => 'cn',
        'zh-tw' => 'cn',
        'pt' => 'pt',
        'en' => 'en',
        'en-us' => 'en',
        'es' => 'pt',
    ];

    public static function normalize(string $code): string
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return self::defaultApiLocale();
        }
        return self::FRONT_TO_API[$code] ?? $code;
    }

    public static function toFrontCode(string $apiCode): string
    {
        $apiCode = self::normalize($apiCode);
        return self::API_TO_FRONT[$apiCode] ?? 'cn';
    }

    public static function defaultApiLocale(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $fromSetting = (new SettingService())->getValue('system_default_lang', '');
        if (is_string($fromSetting) && $fromSetting !== '') {
            $cached = self::normalize($fromSetting);
            return $cached;
        }

        $row = LangModel::where('is_default_lang', 1)->where('status', 1)->first();
        if ($row) {
            $cached = self::normalize((string) $row->lang);
            return $cached;
        }

        $cached = 'zh-cn';
        return $cached;
    }

    public static function resolveFromRequest(?string $queryLang, ?string $cookieLang, ?string $userLang = null): string
    {
        $checkout = (int) (new SettingService())->getValue('checkout_language', 1);
        if ($checkout !== 1) {
            return self::defaultApiLocale();
        }

        foreach ([$queryLang, $cookieLang, $userLang] as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                $normalized = self::normalize($candidate);
                if (self::isEnabled($normalized)) {
                    return $normalized;
                }
            }
        }

        return self::defaultApiLocale();
    }

    public static function isEnabled(string $apiCode): bool
    {
        $apiCode = self::normalize($apiCode);
        return LangModel::where('lang', $apiCode)->where('status', 1)->exists();
    }

    public static function enabledCodes(): array
    {
        return LangModel::where('status', 1)
            ->orderByDesc('is_default')
            ->orderBy('sort')
            ->orderBy('id')
            ->pluck('lang')
            ->map(fn ($code) => self::normalize((string) $code))
            ->values()
            ->all();
    }

    public static function setCurrent(string $apiCode): void
    {
        Context::set(self::CONTEXT_KEY, self::normalize($apiCode));
    }

    public static function current(): string
    {
        $ctx = Context::get(self::CONTEXT_KEY);
        if (is_string($ctx) && $ctx !== '') {
            return $ctx;
        }
        return self::defaultApiLocale();
    }
}
