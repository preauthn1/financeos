<?php

declare(strict_types=1);

namespace app\support;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * 兼容 unix 整数与 Eloquent Carbon/timestamp 字符串的格式化工具。
 */
final class DateTimeFormat
{
    public static function datetime(mixed $value, string $format = 'Y-m-d H:i:s'): string
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }

        if (is_numeric($value)) {
            return date($format, (int) $value);
        }

        $parsed = Carbon::parse((string) $value);

        return $parsed->format($format);
    }

    public static function unix(mixed $value): int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return 0;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return Carbon::parse((string) $value)->getTimestamp();
    }
}
