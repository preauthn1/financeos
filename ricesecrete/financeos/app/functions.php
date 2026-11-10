<?php

declare(strict_types=1);

use support\Response;
use app\support\ErrorCode;

function json_response(array $data, int $status = 200)
{
    return new Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    );
}

function success(mixed $data = null, string $msg = 'success')
{
    // 无业务数据时统一返回空数组，避免前端收到 data: null
    if ($data === null) {
        $data = [];
    }

    return json_response(['code' => ErrorCode::OK, 'msg' => $msg, 'data' => $data]);
}

function fail(int $code, ?string $msg = null, int $httpStatus = 200)
{
    return json_response(['code' => $code, 'msg' => ErrorCode::message($code, $msg), 'data' => []], $httpStatus);
}

/**
 * 按当前请求语言翻译文案（键通常为中文源文本）。
 */
function app_lang(string $key, ?string $default = null): string
{
    static $cache = [];
    $locale = \app\support\Locale::current();
    if (!isset($cache[$locale])) {
        $cache[$locale] = (new \app\service\LangExportService())->loadForLocale($locale);
    }
    return $cache[$locale][$key] ?? ($default ?? $key);
}

/**
 * IP 转地区（兼容参考项目 ip2location）。
 */
function ip2location(string $ip): string
{
    return \app\support\IpLocationResolver::resolve($ip);
}

/**
 * IP 转地区结构化数据（兼容参考项目 ip2location_arr）。
 *
 * @return array<string, string>
 */
function ip2location_arr(string $ip): array
{
    return \app\support\IpLocationResolver::resolveDetail($ip);
}
