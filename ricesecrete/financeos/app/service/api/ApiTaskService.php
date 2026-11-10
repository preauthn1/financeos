<?php

declare(strict_types=1);

namespace app\service\api;

use app\model\ad\AdProductOrderModel;
use app\model\member\UserLevelModel;
use app\model\member\UserModel;
use app\model\member\UserWaterModel;
use app\service\MemberWalletService;
use app\service\SettingService;

class ApiTaskService
{
    private const INCOME_DETAIL_TYPE = 16;

    public function __construct(
        private ?FinanceProductLevelConfigService $levelConfigService = null,
        private ?SettingService $settingService = null,
    ) {
        $this->levelConfigService = $levelConfigService ?? new FinanceProductLevelConfigService();
        $this->settingService = $settingService ?? new SettingService();
    }

    public function taskInfo(int $userId): array
    {
        $user = UserModel::find($userId);
        $levels = $this->getVisibleLevels();

        $todayIncome = $this->sumIncome($userId, true);
        $totalIncome = $this->sumIncome($userId, false);
        $totalTaskIncome = $this->sumTaskIncome($userId);

        $todayIncomeText = $this->formatCurrency($todayIncome);
        $totalIncomeText = $this->formatCurrency($totalIncome);
        $totalTaskIncomeText = $this->formatCurrency($totalTaskIncome);

        return [
            'level_list' => array_map(
                fn (array $level, int $index) => $this->transformLevel($level, $index + 1),
                $levels,
                array_keys($levels)
            ),
            'task_count' => [
                'task_reset_time' => $this->getTaskResetCountdown($userId),
                'today_income' => $todayIncomeText,
                'total_task_income' => $totalTaskIncomeText,
            ],
            'user_income' => [
                'today_income' => $todayIncomeText,
                'total_income' => $totalIncomeText,
                'user_money' => $this->formatMoney((string) ($user?->balance ?? '0')),
            ],
            'user_level' => $this->getUserLevels($userId),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function getVisibleLevels(): array
    {
        $levels = array_values(array_filter(
            $this->levelConfigService->getLevels(),
            static fn (array $level): bool => (int) ($level['is_show'] ?? 1) === 1
        ));

        usort($levels, static fn (array $a, array $b): int => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

        return $levels;
    }

    /** @return array<string, mixed> */
    private function transformLevel(array $level, int $fallbackId): array
    {
        $feeRate = (float) ($level['fee_rate'] ?? 0);
        $limitUnlockText = (string) ($level['limit_unlock_num_text'] ?? '');
        if ($limitUnlockText === '' && $feeRate > 0) {
            $limitUnlockText = rtrim(rtrim(number_format($feeRate, 2, '.', ''), '0'), '.') . '%';
        }

        $incomeText = (string) ($level['income_text'] ?? '');
        if ($incomeText === '') {
            $minRate = (float) ($level['invest_rate'] ?? 0);
            $maxRate = (float) ($level['invest_max_rate'] ?? 0);
            if ($minRate > 0 || $maxRate > 0) {
                $incomeText = sprintf(
                    '%s%%-%s%%',
                    rtrim(rtrim(number_format($minRate, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($maxRate > 0 ? $maxRate : $minRate, 2, '.', ''), '0'), '.')
                );
            }
        }

        $investIncomeText = (string) ($level['invest_income_text'] ?? '');
        if ($investIncomeText === '0') {
            $investIncomeText = '';
        }

        $levelTimeText = (string) ($level['level_time_text'] ?? '');
        if ($levelTimeText === '') {
            $levelTimeText = ((int) ($level['level_at'] ?? 0)) === 1 ? '团队代理' : '所有代理';
        }

        $priceText = (string) ($level['price_text'] ?? '');
        if ($priceText === '') {
            $priceText = $this->formatMoney((string) ($level['price'] ?? '0'));
        }

        return [
            'id' => (int) ($level['id'] ?? $fallbackId),
            'level_id' => (int) ($level['level_id'] ?? 0),
            'img' => $this->resolveMediaUrl((string) ($level['img'] ?? '')),
            'income' => $this->formatMoney((string) ($level['income'] ?? '0')),
            'income_text' => $incomeText,
            'invest_income_text' => $investIncomeText,
            'invest_num' => (int) ($level['invest_num'] ?? 0),
            'task_num' => (int) ($level['task_num'] ?? 0),
            'invite_num' => (int) ($level['invite_num'] ?? 0),
            'invite_num_amount' => $this->formatMoney((string) ($level['invite_num_amount'] ?? '0')),
            'level_at' => (int) ($level['level_at'] ?? 0),
            'level_time_text' => $levelTimeText,
            'price' => $this->formatMoney((string) ($level['price'] ?? '0')),
            'price_text' => $priceText,
            'level_name' => (string) ($level['level_name'] ?? $level['name'] ?? ''),
            'is_show' => (int) ($level['is_show'] ?? 1),
            'is_can_buy' => (int) ($level['is_can_buy'] ?? 1),
            'type' => (int) ($level['type'] ?? 0),
            'limit_unlock_num_text' => $limitUnlockText,
        ];
    }

    /** @return array<int, array<string, int>> */
    private function getUserLevels(int $userId): array
    {
        return UserLevelModel::query()
            ->where('user_id', $userId)
            ->where('expired_at', '>', time())
            ->orderByDesc('to_level')
            ->get(['id', 'user_id', 'to_level'])
            ->map(static fn (UserLevelModel $row): array => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'level_id' => (int) $row->to_level,
            ])
            ->values()
            ->all();
    }

    private function sumIncome(int $userId, bool $todayOnly): float
    {
        $query = UserWaterModel::query()
            ->where('user_id', $userId)
            ->where('type', MemberWalletService::WALLET_BASE)
            ->where('direction', MemberWalletService::DIRECTION_IN)
            ->where('detail_type', self::INCOME_DETAIL_TYPE);

        if ($todayOnly) {
            $query->where('created_at', '>=', strtotime(date('Y-m-d 00:00:00')));
        }

        return (float) $query->sum('amount');
    }

    private function sumTaskIncome(int $userId): float
    {
        $fromWater = $this->sumIncome($userId, false);
        if ($fromWater > 0) {
            return $fromWater;
        }

        return (float) AdProductOrderModel::query()
            ->where('user_id', $userId)
            ->where('status', AdProductOrderModel::STATUS_SETTLED)
            ->sum('profit_amount');
    }

    private function getTaskResetCountdown(int $userId): float
    {
        $resetType = (int) $this->settingService->getValue('task_reset_type', 3);

        if ($resetType === 1) {
            $lastOrder = AdProductOrderModel::query()
                ->where('user_id', $userId)
                ->whereIn('status', [
                    AdProductOrderModel::STATUS_BOUGHT,
                    AdProductOrderModel::STATUS_SETTLED,
                ])
                ->orderByDesc('id')
                ->value('created_at');

            if (!$lastOrder) {
                return 0;
            }

            $nextReset = (int) $lastOrder + 86400;
            return max(0, $nextReset - time());
        }

        if ($resetType === 2) {
            $resetTime = (string) $this->settingService->getValue('task_reset_time', '12:00');
            $parts = explode(':', $resetTime);
            $hour = (int) ($parts[0] ?? 12);
            $minute = (int) ($parts[1] ?? 0);
            $target = mktime($hour, $minute, 0);
            if ($target <= time()) {
                $target = mktime($hour, $minute, 0, (int) date('n'), (int) date('j') + 1);
            }

            return max(0, $target - time());
        }

        return $this->getCurrentSessionCountdown();
    }

    private function getCurrentSessionCountdown(): float
    {
        $now = date('H:i:s');
        $sessions = $this->levelConfigService->getTaskSessions();
        foreach ($sessions as $session) {
            $start = (string) ($session['started_at'] ?? '00:00:00');
            $end = (string) ($session['ended_at'] ?? '23:59:59');
            if ($start <= $now && $now <= $end) {
                $endTs = strtotime(date('Y-m-d') . ' ' . $end);
                if ($endTs === false) {
                    return 0;
                }
                return max(0, $endTs - time());
            }
        }

        return max(0, strtotime('tomorrow') - time());
    }

    private function formatCurrency(float $amount): string
    {
        $site = $this->settingService->getValue('site_config', []);
        $symbol = is_array($site) ? (string) ($site['fiat_currency_symbol'] ?? '¥') : '¥';
        if ($symbol === '') {
            $symbol = '¥';
        }

        return $symbol . number_format($amount, 2, '.', ',');
    }

    private function formatMoney(string $amount): string
    {
        if (!is_numeric($amount)) {
            return '0.00';
        }

        return number_format((float) $amount, 2, '.', '');
    }

    private function resolveMediaUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = (string) $this->settingService->getValue('media_base_url', '');
        if ($base === '') {
            $site = $this->settingService->getValue('site_config', []);
            if (is_array($site)) {
                $base = (string) ($site['media_base_url'] ?? '');
            }
        }
        if ($base === '') {
            return $path;
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}
