<?php

declare(strict_types=1);

namespace app\service;

use support\Log;
use support\Redis;

class FinanceSettleJobService
{
    private const LOCK_KEY = 'command:finance:settle';

    private const LOCK_TTL = 350;

    public function __construct(private ?OrderSettlementService $settlementService = null)
    {
        $this->settlementService = $settlementService ?? new OrderSettlementService();
    }

    /**
     * 带分布式锁执行结算，锁冲突时跳过
     *
     * @return array<string, int>|null 执行结果，跳过时返回 null
     */
    public function runWithLock(): ?array
    {
        if (!$this->acquireLock()) {
            Log::info('finance:settle skipped, lock held');

            return null;
        }

        try {
            $result = $this->settlementService->runAll();
            Log::info('finance:settle completed', $result);

            return $result;
        } catch (\Throwable $e) {
            Log::error('finance:settle failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            throw $e;
        } finally {
            $this->releaseLock();
        }
    }

    private function acquireLock(): bool
    {
        try {
            return Redis::setNx(self::LOCK_KEY, '1', self::LOCK_TTL);
        } catch (\Throwable) {
            return true;
        }
    }

    private function releaseLock(): void
    {
        try {
            Redis::del(self::LOCK_KEY);
        } catch (\Throwable) {
            // ignore
        }
    }
}
