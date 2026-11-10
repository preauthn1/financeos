<?php

declare(strict_types=1);

namespace app\support\payment;

interface PaymentSignAdapterInterface
{
    public function supports(string $signType): bool;

    /**
     * @param array<string, mixed> $config 通道 config 字段
     * @param array<string, mixed> $payload 回调参数
     */
    public function verify(array $config, array $payload): bool;
}
