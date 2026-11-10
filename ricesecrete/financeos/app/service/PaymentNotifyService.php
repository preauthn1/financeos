<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\support\payment\PaymentNotifyVerifier;
use support\Log;
use support\Redis;

class PaymentNotifyService
{
    public function __construct(
        private ?RechargeService $rechargeService = null,
        private ?WithdrawService $withdrawService = null,
        private ?PaymentNotifyVerifier $verifier = null,
    ) {
        $this->rechargeService = $rechargeService ?? new RechargeService();
        $this->withdrawService = $withdrawService ?? new WithdrawService();
        $this->verifier = $verifier ?? new PaymentNotifyVerifier();
    }

    /**
     * 充值回调（兼容 order_no / orderNo / trade_no，status=1|2|3 为成功）
     *
     * @return array{code: int, msg: string}
     */
    public function handleRecharge(array $payload): array
    {
        $orderNo = (string) ($payload['order_no'] ?? $payload['orderNo'] ?? $payload['orderCode'] ?? $payload['trade_no'] ?? '');
        $status = $this->normalizeRechargeStatus($payload);
        $amount = (string) ($payload['amount'] ?? $payload['money'] ?? '');
        $tradeNo = (string) ($payload['trade_no'] ?? $payload['transaction_id'] ?? $payload['platformOrderCode'] ?? '');

        Log::info('payment notify recharge', $payload);

        if ($orderNo === '') {
            return ['code' => 0, 'msg' => '缺少订单号'];
        }

        if (!$this->verifier->verify('recharge', $payload)) {
            return ['code' => 0, 'msg' => '签名验证失败'];
        }

        if (!in_array($status, [1, 2], true)) {
            return ['code' => 0, 'msg' => '状态非成功'];
        }

        $lockKey = 'notify:recharge:' . $orderNo;
        if (!$this->acquireLock($lockKey, 30)) {
            return ['code' => 1, 'msg' => '处理中'];
        }

        try {
            $this->rechargeService->notifySuccess($orderNo, $amount, $tradeNo);

            return ['code' => 1, 'msg' => 'success'];
        } catch (BusinessException $e) {
            Log::warning('payment notify recharge failed: ' . $e->getMessage(), ['order_no' => $orderNo]);

            return ['code' => 0, 'msg' => $e->getMessage()];
        } finally {
            $this->releaseLock($lockKey);
        }
    }

    /**
     * 提现代付回调（status=1|2|4 视为成功）
     *
     * @return array{code: int, msg: string}
     */
    public function handleWithdraw(array $payload): array
    {
        $orderNo = (string) ($payload['order_no'] ?? $payload['orderNo'] ?? $payload['orderCode'] ?? '');
        $status = (int) ($payload['status'] ?? 0);

        Log::info('payment notify withdraw', $payload);

        if ($orderNo === '') {
            return ['code' => 0, 'msg' => '缺少订单号'];
        }

        if (!$this->verifier->verify('withdraw', $payload)) {
            return ['code' => 0, 'msg' => '签名验证失败'];
        }

        if (!in_array($status, [1, 2, 3, 4], true)) {
            return ['code' => 0, 'msg' => '状态非成功'];
        }

        $lockKey = 'notify:withdraw:' . $orderNo;
        if (!$this->acquireLock($lockKey, 30)) {
            return ['code' => 1, 'msg' => '处理中'];
        }

        try {
            $this->withdrawService->notifySuccess($orderNo);

            return ['code' => 1, 'msg' => 'success'];
        } catch (BusinessException $e) {
            Log::warning('payment notify withdraw failed: ' . $e->getMessage(), ['order_no' => $orderNo]);

            return ['code' => 0, 'msg' => $e->getMessage()];
        } finally {
            $this->releaseLock($lockKey);
        }
    }

  /**
     * @param array<string, mixed> $payload
     */
    private function normalizeRechargeStatus(array $payload): int
    {
        $status = $payload['status'] ?? $payload['returnCode'] ?? 0;
        if (is_string($status) && $status === '3') {
            return 2;
        }

        return (int) $status;
    }

    private function acquireLock(string $key, int $ttl): bool
    {
        try {
            return Redis::setNx($key, '1', $ttl);
        } catch (\Throwable) {
            return true;
        }
    }

    private function releaseLock(string $key): void
    {
        try {
            Redis::del($key);
        } catch (\Throwable) {
            // ignore
        }
    }
}
