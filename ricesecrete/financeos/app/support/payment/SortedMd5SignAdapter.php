<?php

declare(strict_types=1);

namespace app\support\payment;

class SortedMd5SignAdapter implements PaymentSignAdapterInterface
{
    public function supports(string $signType): bool
    {
        return in_array($signType, ['sorted_md5', 'md5', 'generic_md5', ''], true);
    }

    public function verify(array $config, array $payload): bool
    {
        $sign = (string) ($payload['sign'] ?? $payload['signature'] ?? '');
        if ($sign === '') {
            return false;
        }

        $key = (string) ($config['key'] ?? $config['secret'] ?? $config['api_key'] ?? '');
        if ($key === '') {
            return false;
        }

        $params = $payload;
        unset($params['sign'], $params['signature']);
        ksort($params);

        $parts = [];
        foreach ($params as $name => $value) {
            if ($value === '' || $value === null || is_array($value)) {
                continue;
            }
            $parts[] = $name . '=' . $value;
        }

        $expected = strtoupper(md5(implode('&', $parts) . '&key=' . $key));

        return hash_equals($expected, strtoupper($sign));
    }
}
