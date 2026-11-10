<?php

declare(strict_types=1);

namespace app\support\payment;

use app\model\pay\PayPlatformModel;
use app\model\pay\RechargeOrderModel;
use app\model\pay\WithdrawOrderModel;
use support\Log;

class PaymentNotifyVerifier
{
    /** @var PaymentSignAdapterInterface[] */
    private array $adapters;

    public function __construct(?array $adapters = null)
    {
        $this->adapters = $adapters ?? [
            new Open365paySignAdapter(),
            new SortedMd5SignAdapter(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function verify(string $notifyType, array $payload): bool
    {
        if (filter_var(env('PAYMENT_NOTIFY_SKIP_VERIFY', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        if (!isset($payload['sign']) && !isset($payload['signature'])) {
            return !filter_var(env('PAYMENT_NOTIFY_REQUIRE_SIGN', false), FILTER_VALIDATE_BOOLEAN);
        }

        $platform = $this->resolvePlatform($notifyType, $payload);
        if (!$platform) {
            Log::warning('payment notify platform not found', ['type' => $notifyType, 'payload' => $payload]);

            return !filter_var(env('PAYMENT_NOTIFY_REQUIRE_SIGN', false), FILTER_VALIDATE_BOOLEAN);
        }

        $config = is_array($platform->config) ? $platform->config : [];
        $signType = (string) ($config['sign_type'] ?? $platform->currency_code ?? 'sorted_md5');

        foreach ($this->adapters as $adapter) {
            if (!$adapter->supports($signType)) {
                continue;
            }
            if ($adapter->verify($config, $payload)) {
                return true;
            }
        }

        Log::warning('payment notify sign invalid', [
            'platform' => $platform->currency_code,
            'sign_type' => $signType,
        ]);

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolvePlatform(string $notifyType, array $payload): ?PayPlatformModel
    {
        $platformId = (int) ($payload['pay_platform_id'] ?? $payload['platform_id'] ?? 0);
        if ($platformId > 0) {
            $platform = PayPlatformModel::find($platformId);
            if ($platform) {
                return $platform;
            }
        }

        $orderNo = (string) ($payload['order_no'] ?? $payload['orderNo'] ?? $payload['orderCode'] ?? '');
        if ($orderNo !== '') {
            if ($notifyType === 'withdraw') {
                $order = WithdrawOrderModel::query()->where('order_no', $orderNo)->first();
                $channel = (string) ($order?->channel ?? '');
                if ($channel !== '') {
                    return PayPlatformModel::query()
                        ->where('currency_code', $channel)
                        ->orWhere('name', $channel)
                        ->first();
                }
            } else {
                $order = RechargeOrderModel::query()->where('order_no', $orderNo)->first();
                if ($order && (int) $order->pay_platform_id > 0) {
                    return PayPlatformModel::find((int) $order->pay_platform_id);
                }
            }
        }

        $channel = (string) ($payload['channel'] ?? $payload['pay_type'] ?? $payload['currency_code'] ?? '');
        if ($channel === '') {
            return null;
        }

        return PayPlatformModel::query()
            ->where('currency_code', $channel)
            ->orWhere('name', $channel)
            ->first();
    }
}
