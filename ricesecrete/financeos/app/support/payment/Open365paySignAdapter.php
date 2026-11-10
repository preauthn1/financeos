<?php

declare(strict_types=1);

namespace app\support\payment;

class Open365paySignAdapter implements PaymentSignAdapterInterface
{
    public function supports(string $signType): bool
    {
        return in_array($signType, ['open365pay', 'open365', '365open'], true);
    }

    public function verify(array $config, array $payload): bool
    {
        $sign = (string) ($payload['sign'] ?? '');
        if ($sign === '') {
            return false;
        }

        $key = (string) ($config['key'] ?? $config['secret'] ?? '');
        $userCode = (string) ($config['userCode'] ?? $config['merchant_id'] ?? '');
        if ($key === '' || $userCode === '') {
            return false;
        }

        $expected = strtoupper(md5(implode('&', [
            $userCode,
            (string) ($payload['orderCode'] ?? $payload['order_no'] ?? ''),
            (string) ($payload['platformOrderCode'] ?? $payload['trade_no'] ?? ''),
            (string) ($payload['amount'] ?? $payload['money'] ?? ''),
            (string) ($payload['status'] ?? ''),
            (string) ($payload['payTime'] ?? $payload['paid_at'] ?? ''),
            $key,
        ])));

        return hash_equals($expected, strtoupper($sign));
    }
}
