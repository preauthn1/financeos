<?php

declare(strict_types=1);

namespace app\support;

class FinanceProductMatchResult
{
    public static function wrap(array $payload): array
    {
        return ['code' => ErrorCode::OK, 'msg' => 'success', 'data' => $payload];
    }

    public static function successOrder(array $order): array
    {
        return self::wrap([
            'code' => 0,
            'msg' => '匹配成功',
            'data' => $order,
        ]);
    }

    public static function error(int $code, string $msg, array $extra = []): array
    {
        return self::wrap(array_merge(['code' => $code, 'msg' => $msg], $extra));
    }
}
