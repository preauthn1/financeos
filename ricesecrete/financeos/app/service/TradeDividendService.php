<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\ad\AdProductOrderModel;
use app\model\invest\InvestmentProductOrderModel;
use app\model\member\UserModel;
use app\model\trade\TradeDividendDailyModel;
use app\service\MemberWalletService;
use app\support\ErrorCode;
use app\support\UserWaterDetailType;
use Illuminate\Database\Capsule\Manager as DB;

class TradeDividendService
{
    public function __construct(
        private ?SettingService $settingService = null,
        private ?ActiveUserService $activeUserService = null,
        private ?MemberWalletService $walletService = null,
    ) {
        $this->settingService = $settingService ?? new SettingService();
        $this->activeUserService = $activeUserService ?? new ActiveUserService();
        $this->walletService = $walletService ?? new MemberWalletService();
    }

    public function getConfig(): array
    {
        return $this->settingService->getValue('trade_dividend_config', $this->defaultConfig());
    }

    public function index(int $userId): array
    {
        $config = $this->getConfig();
        $today = date('Ymd');
        $yesterday = date('Ymd', strtotime('-1 day'));

        $todayLevel = $this->calcTodayLevel($userId, $config);
        $pending = TradeDividendDailyModel::where('user_id', $userId)
            ->where('date', $yesterday)
            ->where('status', TradeDividendDailyModel::STATUS_PENDING)
            ->first();

        return [
            'today_level' => $todayLevel,
            'pending_reward' => (string) ($pending?->reward ?? 0),
            'pending_level' => (int) ($pending?->level ?? 0),
            'can_claim' => $pending && $this->isInClaimWindow($config),
            'claim_start' => $config['claim_start'] ?? '20:00',
            'claim_end' => $config['claim_end'] ?? '24:00',
            'levels' => $config['levels'] ?? [],
        ];
    }

    public function claim(int $userId): array
    {
        $config = $this->getConfig();
        if (!$this->isInClaimWindow($config)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '当前时间无法领取');
        }

        $yesterday = date('Ymd', strtotime('-1 day'));

        return DB::transaction(function () use ($userId, $yesterday) {
            $record = TradeDividendDailyModel::where('user_id', $userId)
                ->where('date', $yesterday)
                ->where('status', TradeDividendDailyModel::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if (!$record || bccomp((string) $record->reward, '0', 4) <= 0) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '暂无可领取分红');
            }

            $reward = (string) $record->reward;
            $this->walletService->changeBalance(
                $userId, $reward, MemberWalletService::WALLET_BASE, MemberWalletService::DIRECTION_IN,
                '每日交易分红', UserWaterDetailType::TRADE_DIVIDEND, (int) $record->id
            );
            $record->update(['status' => TradeDividendDailyModel::STATUS_CLAIMED, 'claimed_at' => time()]);

            return ['reward' => $reward, 'level' => $record->level];
        });
    }

    /**
     * 每日结算（默认 00:00）
     */
    public function settle(): int
    {
        $config = $this->getConfig();
        $settleTime = (string) ($config['settle_time'] ?? '00:00');
        if (date('H:i') !== substr($settleTime, 0, 5)) {
            return 0;
        }

        $today = date('Ymd');
        $cacheKey = 'trade_dividend_settled:' . $today;
        if (\support\Redis::get($cacheKey)) {
            return 0;
        }

        $yesterday = date('Ymd', strtotime('-1 day'));
        $userIds = UserModel::where('status', 1)->pluck('id');
        $count = 0;

        foreach ($userIds as $userId) {
            try {
                $level = $this->calcLevelForDate((int) $userId, $yesterday, $config);
                $reward = $this->rewardForLevel($level, $config);
                if (bccomp($reward, '0', 4) <= 0) {
                    continue;
                }

                TradeDividendDailyModel::updateOrCreate(
                    ['user_id' => $userId, 'date' => $yesterday],
                    [
                        'level' => $level,
                        'position_amount' => $this->calcPosition((int) $userId),
                        'direct_new' => $this->countDirectNew((int) $userId, $yesterday),
                        'team_active' => $this->activeUserService->countTeamActiveForDate((int) $userId, $yesterday),
                        'studio_certified' => (int) (UserModel::find($userId)?->studio_certified ?? 0),
                        'reward' => $reward,
                        'status' => TradeDividendDailyModel::STATUS_PENDING,
                    ]
                );
                $count++;
            } catch (\Throwable) {
                // continue
            }
        }

        \support\Redis::setex($cacheKey, 86400, '1');

        return $count;
    }

    private function calcTodayLevel(int $userId, array $config): int
    {
        return $this->calcLevelForDate($userId, date('Ymd'), $config);
    }

    private function calcLevelForDate(int $userId, string $date, array $config): int
    {
        $user = UserModel::find($userId);
        if (!$user) {
            return 0;
        }

        $position = $this->calcPosition($userId);
        $directNew = $this->countDirectNew($userId, $date);
        $teamActive = $this->activeUserService->countTeamActiveForDate($userId, $date);
        $studio = (int) $user->studio_certified;

        $maxLevel = 0;
        foreach ($config['levels'] ?? [] as $lv => $rule) {
            $level = (int) $lv;
            if ($level < 1 || $level > 5) {
                continue;
            }
            if ($position < (float) ($rule['position'] ?? 0)) {
                continue;
            }
            if ($directNew < (int) ($rule['direct_new'] ?? 0)) {
                continue;
            }
            if ($teamActive < (int) ($rule['team_active'] ?? 0)) {
                continue;
            }
            if (!empty($rule['require_studio']) && $studio !== 1) {
                if ($level >= 4) {
                    continue;
                }
            }
            $maxLevel = max($maxLevel, $level);
        }

        return $maxLevel;
    }

    private function rewardForLevel(int $level, array $config): string
    {
        if ($level <= 0) {
            return '0';
        }

        return (string) ($config['levels'][$level]['reward'] ?? 0);
    }

    private function calcPosition(int $userId): string
    {
        $user = UserModel::find($userId);
        $balance = (string) ($user?->balance ?? 0);

        $trading = (string) AdProductOrderModel::where('user_id', $userId)
            ->where('status', AdProductOrderModel::STATUS_BOUGHT)
            ->sum('price');

        $investing = (string) InvestmentProductOrderModel::where('user_id', $userId)
            ->where('status', InvestmentProductOrderModel::STATUS_BOUGHT)
            ->sum('money');

        return bcadd(bcadd($balance, $trading, 4), $investing, 4);
    }

    private function countDirectNew(int $userId, string $date): int
    {
        $dayStart = strtotime(substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2));
        $dayEnd = $dayStart + 86400;

        return UserModel::where('leader1', $userId)
            ->where('registered_at', '>=', $dayStart)
            ->where('registered_at', '<', $dayEnd)
            ->count();
    }

    private function isInClaimWindow(array $config): bool
    {
        $start = (string) ($config['claim_start'] ?? '20:00');
        $end = (string) ($config['claim_end'] ?? '24:00');
        $now = date('H:i');

        return $now >= $start && $now <= $end;
    }

    private function defaultConfig(): array
    {
        return [
            'settle_time' => '00:00',
            'claim_start' => '20:00',
            'claim_end' => '24:00',
            'levels' => [
                1 => ['position' => 1000, 'direct_new' => 1, 'team_active' => 3, 'require_studio' => 0, 'reward' => 10],
                2 => ['position' => 5000, 'direct_new' => 2, 'team_active' => 5, 'require_studio' => 0, 'reward' => 30],
                3 => ['position' => 10000, 'direct_new' => 3, 'team_active' => 10, 'require_studio' => 0, 'reward' => 60],
                4 => ['position' => 50000, 'direct_new' => 5, 'team_active' => 20, 'require_studio' => 1, 'reward' => 100],
                5 => ['position' => 100000, 'direct_new' => 10, 'team_active' => 50, 'require_studio' => 1, 'reward' => 200],
            ],
        ];
    }
}
