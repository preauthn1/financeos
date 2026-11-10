<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\member\UserModel;
use app\model\member\WithdrawLevelDepositModel;
use app\service\MemberWalletService;
use app\support\ErrorCode;
use Illuminate\Database\Capsule\Manager as DB;

class WithdrawLevelService
{
    public function __construct(
        private ?SettingService $settingService = null,
        private ?MemberWalletService $walletService = null,
    ) {
        $this->settingService = $settingService ?? new SettingService();
        $this->walletService = $walletService ?? new MemberWalletService();
    }

    public function getConfig(): array
    {
        $config = $this->settingService->getValue('withdraw_level_config', $this->defaultLevels());
        if (is_string($config)) {
            $decoded = json_decode($config, true);
            $config = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($config) || !isset($config['levels']) || !is_array($config['levels'])) {
            return $this->defaultLevels();
        }

        return array_merge($this->defaultLevels(), $config);
    }

    public function getFeeRate(int $userId, string $amount): float
    {
        $largeConfig = $this->settingService->getValue('withdraw_large_fee', []);
        if (is_string($largeConfig)) {
            $decoded = json_decode($largeConfig, true);
            $largeConfig = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($largeConfig)) {
            $largeConfig = [];
        }
        $threshold = (float) ($largeConfig['threshold'] ?? 0);
        $fixedRate = (float) ($largeConfig['rate'] ?? 0);
        if ($threshold > 0 && $fixedRate > 0 && (float) $amount >= $threshold) {
            return $fixedRate;
        }

        $user = UserModel::find($userId);
        if (!$user) {
            return 0;
        }

        if ((int) $user->withdraw_level_locked >= 0 && (int) $user->withdraw_level_locked !== (int) $user->withdraw_level) {
            $level = (int) $user->withdraw_level_locked;
        } else {
            $level = (int) $user->withdraw_level;
        }

        $config = $this->getConfig();
        foreach ($config['levels'] ?? [] as $item) {
            if ((int) ($item['level'] ?? -1) === $level) {
                return (float) ($item['fee_rate'] ?? 0);
            }
        }

        return (float) ($this->settingService->getValue('withdraw_rate', 0));
    }

    public function depositUpgrade(int $userId, int $targetLevel): array
    {
        $config = $this->getConfig();
        if (empty($config['deposit_upgrade_enabled'])) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '保证金升级未开启');
        }

        $levelConfig = null;
        foreach ($config['levels'] ?? [] as $item) {
            if ((int) ($item['level'] ?? -1) === $targetLevel) {
                $levelConfig = $item;
                break;
            }
        }
        if (!$levelConfig || (float) ($levelConfig['deposit'] ?? 0) <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '目标等级不可保证金升级');
        }

        $amount = (string) $levelConfig['deposit'];
        $days = (int) ($levelConfig['deposit_days'] ?? 7);

        return DB::transaction(function () use ($userId, $targetLevel, $amount, $days) {
            $this->walletService->changeBalance(
                $userId, $amount, MemberWalletService::WALLET_BASE, MemberWalletService::DIRECTION_OUT,
                '提现等级保证金', null, 0, "升级至等级{$targetLevel}"
            );

            WithdrawLevelDepositModel::create([
                'user_id' => $userId,
                'target_level' => $targetLevel,
                'amount' => $amount,
                'return_at' => time() + $days * 86400,
                'status' => WithdrawLevelDepositModel::STATUS_ACTIVE,
            ]);

            return ['amount' => $amount, 'return_at' => time() + $days * 86400];
        });
    }

    /**
     * 每日更新用户提现等级（基于昨日直推/保证金）
     */
    public function updateLevels(): int
    {
        if (date('H:i') !== '00:00') {
            return 0;
        }

        $today = date('Ymd');
        if (\support\Redis::get('withdraw_level_updated:' . $today)) {
            return 0;
        }

        $config = $this->getConfig();
        $yesterdayStart = strtotime('yesterday');
        $yesterdayEnd = strtotime('today') - 1;
        $count = 0;

        $users = UserModel::where('status', 1)->get(['id', 'withdraw_level', 'withdraw_level_locked']);
        foreach ($users as $user) {
            if ((int) $user->withdraw_level_locked >= 0) {
                continue;
            }

            $directCount = UserModel::where('leader1', $user->id)
                ->where('registered_at', '>=', $yesterdayStart)
                ->where('registered_at', '<=', $yesterdayEnd)
                ->count();

            $depositLevel = WithdrawLevelDepositModel::where('user_id', $user->id)
                ->where('status', WithdrawLevelDepositModel::STATUS_ACTIVE)
                ->where('created_at', '>=', date('Y-m-d 00:00:00', $yesterdayStart))
                ->max('target_level');

            $newLevel = $this->resolveLevel($directCount, (int) $depositLevel, $config);
            if ($newLevel !== (int) $user->withdraw_level) {
                $user->withdraw_level = $newLevel;
                $user->save();
                $count++;
            }
        }

        \support\Redis::setex('withdraw_level_updated:' . $today, 86400, '1');

        return $count;
    }

    public function returnDeposits(): int
    {
        $now = time();
        $records = WithdrawLevelDepositModel::where('status', WithdrawLevelDepositModel::STATUS_ACTIVE)
            ->where('return_at', '>', 0)
            ->where('return_at', '<=', $now)
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($records as $record) {
            try {
                DB::transaction(function () use ($record) {
                    $this->walletService->changeBalance(
                        (int) $record->user_id,
                        (string) $record->amount,
                        MemberWalletService::WALLET_BASE,
                        MemberWalletService::DIRECTION_IN,
                        '保证金返还',
                        null,
                        (int) $record->id
                    );
                    $record->update([
                        'status' => WithdrawLevelDepositModel::STATUS_RETURNED,
                        'returned_at' => time(),
                    ]);
                });
                $count++;
            } catch (\Throwable) {
                // continue
            }
        }

        return $count;
    }

    private function resolveLevel(int $directCount, int $depositLevel, array $config): int
    {
        $levelByDirect = 0;
        foreach ($config['levels'] ?? [] as $item) {
            if ($directCount >= (int) ($item['direct_count'] ?? 0)) {
                $levelByDirect = max($levelByDirect, (int) ($item['level'] ?? 0));
            }
        }

        return max($levelByDirect, $depositLevel);
    }

    private function defaultLevels(): array
    {
        return [
            'deposit_upgrade_enabled' => 0,
            'levels' => [
                ['level' => 0, 'name' => '普通', 'direct_count' => 0, 'fee_rate' => 5, 'deposit' => 0, 'deposit_days' => 0],
                ['level' => 1, 'name' => '中级', 'direct_count' => 5, 'fee_rate' => 3, 'deposit' => 1000, 'deposit_days' => 7],
                ['level' => 2, 'name' => '高级', 'direct_count' => 20, 'fee_rate' => 1, 'deposit' => 2000, 'deposit_days' => 10],
            ],
        ];
    }
}
